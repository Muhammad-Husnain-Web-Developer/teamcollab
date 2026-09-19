import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('../Utils/sounds', () => ({
  startIncomingRing: vi.fn(),
  startOutgoingRingback: vi.fn(),
  stopRing: vi.fn(),
  playNotificationDing: vi.fn(),
}));

// Every signaling/roster HTTP call is captured here so tests can assert on
// exactly what the store sent to whom.
const posts = [];
vi.mock('axios', () => ({
  default: {
    post: vi.fn(async (url, body) => {
      posts.push({ url, body });
      if (url === '/calls') return { data: { callId: 'call-1', participants: [] } };
      if (url.endsWith('/accept')) return { data: { participants: acceptRoster } };
      return { data: {} };
    }),
  },
}));

let acceptRoster = [];

// ── WebRTC / media stubs ───────────────────────────────────────────────
function fakeTrack(kind, label = kind) {
  return { kind, label, enabled: true, stop: vi.fn(), onended: null };
}
function fakeStream(tracks) {
  return {
    getTracks: () => tracks,
    getVideoTracks: () => tracks.filter(t => t.kind === 'video'),
    getAudioTracks: () => tracks.filter(t => t.kind === 'audio'),
  };
}

const pcs = []; // every RTCPeerConnection created, in order

class FakeRTCPeerConnection {
  constructor() {
    this.senders = [];
    this.remoteDescription = null;
    this.connectionState = 'new';
    this.iceConnectionState = 'new';
    this.closed = false;
    pcs.push(this);
  }
  addTrack(track) {
    const sender = { track, replaceTrack: vi.fn(async (t) => { sender.track = t; }) };
    this.senders.push(sender);
    return sender;
  }
  getSenders() { return this.senders; }
  async createOffer() { return { type: 'offer', sdp: 'offer-sdp' }; }
  async createAnswer() { return { type: 'answer', sdp: 'answer-sdp' }; }
  async setLocalDescription() {}
  async setRemoteDescription(desc) { this.remoteDescription = desc; }
  async addIceCandidate() {}
  close() { this.closed = true; }
  // Test helper: simulate ICE/DTLS completing.
  connect() { this.connectionState = 'connected'; this.onconnectionstatechange?.(); }
}

const cameraTrack = fakeTrack('video', 'camera');
const micTrack = fakeTrack('audio', 'mic');

const alice = { id: 2, name: 'Alice', display_name: 'Alice', avatar_url: null };
const bob   = { id: 3, name: 'Bob',   display_name: 'Bob',   avatar_url: null };
const carol = { id: 4, name: 'Carol', display_name: 'Carol', avatar_url: null };

beforeEach(() => {
  setActivePinia(createPinia());
  posts.length = 0;
  pcs.length = 0;
  acceptRoster = [];

  globalThis.RTCPeerConnection = FakeRTCPeerConnection;
  globalThis.RTCSessionDescription = class { constructor(d) { Object.assign(this, d); } };
  globalThis.RTCIceCandidate = class { constructor(d) { Object.assign(this, d); } };

  Object.defineProperty(globalThis.navigator, 'mediaDevices', {
    configurable: true,
    value: {
      getUserMedia: vi.fn(async () => fakeStream([micTrack, cameraTrack])),
      getDisplayMedia: vi.fn(),
    },
  });
});

async function loadStore() {
  const { useCallStore } = await import('./useCallStore');
  const { useAuthStore } = await import('./useAuthStore');
  useAuthStore().setUser({ id: 1, name: 'Me' });
  return useCallStore();
}

function signalsSentTo(userId, type) {
  return posts.filter(p => p.url.endsWith('/signal')
    && p.body.target_user_id === userId
    && p.body.signal.type === type);
}

describe('useCallStore — mesh signaling', () => {
  it('a joiner offers to EVERY participant already in the call (never the other way round)', async () => {
    const store = await loadStore();
    acceptRoster = [alice, bob]; // both already joined when I accept

    store.handleIncoming({
      callId: 'call-1', callType: 'video', caller: alice,
      participants: [{ ...alice, status: 'joined' }, { ...bob, status: 'joined' }, { id: 1, status: 'invited' }],
    });
    await store.acceptCall();

    expect(pcs).toHaveLength(2);
    expect(signalsSentTo(alice.id, 'offer')).toHaveLength(1);
    expect(signalsSentTo(bob.id, 'offer')).toHaveLength(1);
    expect(store.remoteCount).toBe(2);
    expect(store.participants.get(alice.id).status).toBe('connecting');
    expect(store.callState).toBe('connecting');
  });

  it('an existing participant only answers an incoming offer and does not offer back', async () => {
    const store = await loadStore();

    // I am the caller; carol was invited and now joins.
    await store.initiateCall([carol], 'video');
    expect(posts[0].url).toBe('/calls');
    expect(posts[0].body.callee_ids).toEqual([carol.id]);

    store.handleParticipantJoined({ callId: 'call-1', participant: carol });
    expect(pcs).toHaveLength(0); // no peer yet — we wait for carol's offer

    await store.handleSignal({
      callId: 'call-1', fromUserId: carol.id,
      signal: { type: 'offer', data: { type: 'offer', sdp: 'carol-offer' } },
    });

    expect(pcs).toHaveLength(1);
    expect(signalsSentTo(carol.id, 'answer')).toHaveLength(1);
    expect(signalsSentTo(carol.id, 'offer')).toHaveLength(0);
  });

  it('keeps one peer connection per remote user and routes ICE to the right one', async () => {
    const store = await loadStore();
    acceptRoster = [alice, bob];

    store.handleIncoming({ callId: 'call-1', callType: 'audio', caller: alice, participants: [alice, bob] });
    await store.acceptCall();

    // Answers come back — each must land on its own pc.
    await store.handleSignal({ callId: 'call-1', fromUserId: alice.id, signal: { type: 'answer', data: { type: 'answer', sdp: 'a' } } });
    await store.handleSignal({ callId: 'call-1', fromUserId: bob.id,   signal: { type: 'answer', data: { type: 'answer', sdp: 'b' } } });

    expect(pcs[0].remoteDescription.sdp).toBe('a');
    expect(pcs[1].remoteDescription.sdp).toBe('b');
  });

  it('becomes active when the first peer connects and tracks per-participant status', async () => {
    const store = await loadStore();
    acceptRoster = [alice, bob];

    store.handleIncoming({ callId: 'call-1', callType: 'audio', caller: alice, participants: [alice, bob] });
    await store.acceptCall();
    expect(store.callState).toBe('connecting');

    pcs[0].connect();
    expect(store.callState).toBe('active');
    expect(store.participants.get(alice.id).status).toBe('connected');
    expect(store.participants.get(bob.id).status).toBe('connecting');
  });

  it('drops only the leaver and keeps the call going while others remain', async () => {
    const store = await loadStore();
    acceptRoster = [alice, bob];

    store.handleIncoming({ callId: 'call-1', callType: 'audio', caller: alice, participants: [alice, bob] });
    await store.acceptCall();
    pcs[0].connect();
    pcs[1].connect();

    store.handleParticipantLeft({ callId: 'call-1', userId: bob.id, callEnded: false });

    expect(pcs[1].closed).toBe(true);
    expect(pcs[0].closed).toBe(false);
    expect(store.remoteCount).toBe(1);
    expect(store.callState).toBe('active');
    expect(posts.some(p => p.url.endsWith('/leave'))).toBe(false);
  });

  it('leaves automatically once everyone else has gone', async () => {
    const store = await loadStore();
    acceptRoster = [alice];

    store.handleIncoming({ callId: 'call-1', callType: 'audio', caller: alice, participants: [alice] });
    await store.acceptCall();
    pcs[0].connect();

    store.handleParticipantLeft({ callId: 'call-1', userId: alice.id, callEnded: false });
    await Promise.resolve();

    expect(posts.some(p => p.url === '/calls/call-1/leave')).toBe(true);
    expect(store.callState).toBe('idle');
  });

  it('tears down immediately when the server says the call ended', async () => {
    const store = await loadStore();

    store.handleIncoming({ callId: 'call-1', callType: 'audio', caller: alice, participants: [alice] });
    expect(store.callState).toBe('incoming');

    // Caller cancelled before we answered.
    store.handleParticipantLeft({ callId: 'call-1', userId: alice.id, callEnded: true });

    expect(store.callState).toBe('idle');
    expect(store.remoteCount).toBe(0);
  });

  it('ignores signals for a different call', async () => {
    const store = await loadStore();
    acceptRoster = [alice];

    store.handleIncoming({ callId: 'call-1', callType: 'audio', caller: alice, participants: [alice] });
    await store.acceptCall();

    await store.handleSignal({ callId: 'other-call', fromUserId: bob.id, signal: { type: 'offer', data: {} } });
    expect(pcs).toHaveLength(1);
  });

  it('1-on-1 is just a two-node mesh: caller waits, callee offers, caller answers', async () => {
    const store = await loadStore();

    await store.initiateCall(alice, 'video'); // single user object, not an array
    expect(store.callState).toBe('calling');
    expect(store.remoteCount).toBe(1);
    expect(store.remoteUser.id).toBe(alice.id);

    store.handleParticipantJoined({ callId: 'call-1', participant: alice });
    expect(store.callState).toBe('connecting');

    await store.handleSignal({ callId: 'call-1', fromUserId: alice.id, signal: { type: 'offer', data: { type: 'offer', sdp: 'x' } } });
    expect(signalsSentTo(alice.id, 'answer')).toHaveLength(1);

    pcs[0].connect();
    expect(store.callState).toBe('active');
  });

  it('screen share swaps the track on every peer in the mesh and restores all on stop', async () => {
    const store = await loadStore();
    acceptRoster = [alice, bob];

    store.handleIncoming({ callId: 'call-1', callType: 'video', caller: alice, participants: [alice, bob] });
    await store.acceptCall();
    pcs[0].connect();

    const screenTrack = fakeTrack('video', 'screen');
    navigator.mediaDevices.getDisplayMedia.mockResolvedValue(fakeStream([screenTrack]));

    await store.startScreenShare();
    for (const pc of pcs) {
      expect(pc.getSenders().find(s => s.track?.kind === 'video').track).toBe(screenTrack);
    }

    await store.stopScreenShare();
    for (const pc of pcs) {
      expect(pc.getSenders().find(s => s.track?.kind === 'video').track).toBe(cameraTrack);
    }
    expect(screenTrack.stop).toHaveBeenCalled();
  });
});
