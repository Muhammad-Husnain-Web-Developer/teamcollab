import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('../Utils/sounds', () => ({
  startIncomingRing: vi.fn(),
  startOutgoingRingback: vi.fn(),
  stopRing: vi.fn(),
  playNotificationDing: vi.fn(),
}));

const posts = [];
let failRecordingPost = false;
vi.mock('axios', () => ({
  default: {
    post: vi.fn(async (url, body) => {
      posts.push({ url, body });
      if (url === '/calls') return { data: { callId: 'call-1', participants: [] } };
      if (url.endsWith('/recording') && failRecordingPost) {
        throw { response: { data: { message: 'Join the call before recording it.' } } };
      }
      return { data: {} };
    }),
  },
}));

function fakeTrack(kind) { return { kind, enabled: true, stop: vi.fn(), onended: null }; }
function fakeStream(tracks) {
  return {
    getTracks: () => tracks,
    getVideoTracks: () => tracks.filter(t => t.kind === 'video'),
    getAudioTracks: () => tracks.filter(t => t.kind === 'audio'),
  };
}

const pcs = [];
class FakeRTCPeerConnection {
  constructor() { this.senders = []; this.remoteDescription = null; this.connectionState = 'new'; this.iceConnectionState = 'new'; pcs.push(this); }
  addTrack(track) { const s = { track, replaceTrack: vi.fn(async () => {}) }; this.senders.push(s); return s; }
  getSenders() { return this.senders; }
  async createOffer() { return { type: 'offer', sdp: 'o' }; }
  async createAnswer() { return { type: 'answer', sdp: 'a' }; }
  async setLocalDescription() {}
  async setRemoteDescription(d) { this.remoteDescription = d; }
  async addIceCandidate() {}
  close() {}
  connect() { this.connectionState = 'connected'; this.onconnectionstatechange?.(); }
}

const alice = { id: 2, name: 'Alice', display_name: 'Alice', avatar_url: null };
const bob   = { id: 3, name: 'Bob',   display_name: 'Bob',   avatar_url: null };

beforeEach(() => {
  setActivePinia(createPinia());
  posts.length = 0;
  pcs.length = 0;
  failRecordingPost = false;

  globalThis.RTCPeerConnection = FakeRTCPeerConnection;
  globalThis.RTCSessionDescription = class { constructor(d) { Object.assign(this, d); } };
  globalThis.RTCIceCandidate = class { constructor(d) { Object.assign(this, d); } };
  Object.defineProperty(globalThis.navigator, 'mediaDevices', {
    configurable: true,
    value: { getUserMedia: vi.fn(async () => fakeStream([fakeTrack('audio'), fakeTrack('video')])), getDisplayMedia: vi.fn() },
  });
});

async function loadStores() {
  const { useCallStore } = await import('./useCallStore');
  const { useAuthStore } = await import('./useAuthStore');
  const { useUIStore } = await import('./useUIStore');
  useAuthStore().setUser({ id: 1, name: 'Me' });
  return { store: useCallStore(), ui: useUIStore() };
}

/** I call alice + bob; alice joins and the pair connects → call is 'active'. */
async function activeCallWith(store, ...joiners) {
  await store.initiateCall([alice, bob], 'video');
  for (const p of joiners) {
    store.handleParticipantJoined({ callId: 'call-1', participant: p });
    await store.handleSignal({ callId: 'call-1', fromUserId: p.id, signal: { type: 'offer', data: { type: 'offer', sdp: 'o' } } });
    pcs[pcs.length - 1].connect();
  }
  expect(store.callState).toBe('active');
}

const recordingPosts = () => posts.filter(p => p.url === '/calls/call-1/recording');

describe('useCallStore — recording notices', () => {
  it('tracks who is recording and toasts on start and stop', async () => {
    const { store, ui } = await loadStores();
    await activeCallWith(store, alice);
    const warn = vi.spyOn(ui, 'toastWarning');
    const info = vi.spyOn(ui, 'toastInfo');

    store.handleRecordingToggled({ callId: 'call-1', userId: alice.id, recording: true });
    expect(store.recordingBy.has(alice.id)).toBe(true);
    expect(store.recordingParticipants.map(p => p.id)).toEqual([alice.id]);
    expect(warn).toHaveBeenCalledWith('Alice started recording this call.', 6000);

    store.handleRecordingToggled({ callId: 'call-1', userId: alice.id, recording: false });
    expect(store.recordingBy.has(alice.id)).toBe(false);
    expect(info).toHaveBeenCalledWith('Alice stopped recording.');
  });

  it('ignores notices for another call, for myself, and duplicates', async () => {
    const { store, ui } = await loadStores();
    await activeCallWith(store, alice);
    const warn = vi.spyOn(ui, 'toastWarning');

    store.handleRecordingToggled({ callId: 'other', userId: alice.id, recording: true });
    store.handleRecordingToggled({ callId: 'call-1', userId: 1, recording: true });
    expect(store.recordingBy.size).toBe(0);

    store.handleRecordingToggled({ callId: 'call-1', userId: alice.id, recording: true });
    store.handleRecordingToggled({ callId: 'call-1', userId: alice.id, recording: true }); // re-announce for a late joiner
    expect(warn).toHaveBeenCalledTimes(1);
  });

  it('setRecording(true) announces first and only then flags a local recording', async () => {
    const { store } = await loadStores();
    await activeCallWith(store, alice);

    expect(await store.setRecording(true)).toBe(true);
    expect(recordingPosts()).toEqual([{ url: '/calls/call-1/recording', body: { recording: true } }]);
    expect(store.isRecordingLocally).toBe(true);

    expect(await store.setRecording(false)).toBe(true);
    expect(recordingPosts()[1].body).toEqual({ recording: false });
    expect(store.isRecordingLocally).toBe(false);
  });

  it('refuses to flag a recording when the consent notice cannot be delivered', async () => {
    const { store, ui } = await loadStores();
    await activeCallWith(store, alice);
    const err = vi.spyOn(ui, 'toastError');
    failRecordingPost = true;

    expect(await store.setRecording(true)).toBe(false);
    expect(store.isRecordingLocally).toBe(false);
    expect(err).toHaveBeenCalledWith('Join the call before recording it.');
  });

  it('refuses to record before the call is active', async () => {
    const { store } = await loadStores();
    await store.initiateCall([alice], 'video'); // still ringing

    expect(await store.setRecording(true)).toBe(false);
    expect(recordingPosts()).toHaveLength(0);
  });

  it('re-announces an ongoing recording to whoever joins later', async () => {
    const { store } = await loadStores();
    await activeCallWith(store, alice);
    await store.setRecording(true);
    posts.length = 0;

    store.handleParticipantJoined({ callId: 'call-1', participant: bob });
    await Promise.resolve();

    expect(recordingPosts()).toEqual([{ url: '/calls/call-1/recording', body: { recording: true } }]);
  });

  it('clears a participant\'s recording flag when they leave, and everything on reset', async () => {
    const { store } = await loadStores();
    await activeCallWith(store, alice, bob);
    store.handleRecordingToggled({ callId: 'call-1', userId: alice.id, recording: true });
    store.handleRecordingToggled({ callId: 'call-1', userId: bob.id, recording: true });
    await store.setRecording(true);

    store.handleParticipantLeft({ callId: 'call-1', userId: alice.id, callEnded: false });
    expect(store.recordingBy.has(alice.id)).toBe(false);
    expect(store.recordingBy.has(bob.id)).toBe(true);

    store.handleParticipantLeft({ callId: 'call-1', userId: bob.id, callEnded: true });
    expect(store.callState).toBe('idle');
    expect(store.recordingBy.size).toBe(0);
    expect(store.isRecordingLocally).toBe(false);
  });
});
