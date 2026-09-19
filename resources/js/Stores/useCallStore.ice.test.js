import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

vi.mock('../Utils/sounds', () => ({
  startIncomingRing: vi.fn(),
  startOutgoingRingback: vi.fn(),
  stopRing: vi.fn(),
  playNotificationDing: vi.fn(),
}));

const gets = [];
let iceResponse = null; // null → the request fails
vi.mock('axios', () => ({
  default: {
    get: vi.fn(async (url) => {
      gets.push(url);
      if (iceResponse === null) throw new Error('network down');
      return { data: iceResponse };
    }),
    post: vi.fn(async (url) => {
      if (url === '/calls') return { data: { callId: 'call-1', participants: [] } };
      if (url.endsWith('/accept')) return { data: { participants: [] } };
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

const pcConfigs = [];
class FakeRTCPeerConnection {
  constructor(config) { pcConfigs.push(config); this.senders = []; this.connectionState = 'new'; }
  addTrack(track) { const s = { track, replaceTrack: vi.fn(async () => {}) }; this.senders.push(s); return s; }
  getSenders() { return this.senders; }
  async createOffer() { return { type: 'offer', sdp: 'o' }; }
  async createAnswer() { return { type: 'answer', sdp: 'a' }; }
  async setLocalDescription() {}
  async setRemoteDescription(d) { this.remoteDescription = d; }
  async addIceCandidate() {}
  close() {}
}

const alice = { id: 2, name: 'Alice', display_name: 'Alice', avatar_url: null };
const TURN = [
  { urls: ['stun:stun.example.test:3478'] },
  { urls: ['turn:turn.example.test:3478?transport=udp'], username: '1790000000:1', credential: 'abc=' },
];

beforeEach(() => {
  setActivePinia(createPinia());
  gets.length = 0;
  pcConfigs.length = 0;
  iceResponse = null;

  globalThis.RTCPeerConnection = FakeRTCPeerConnection;
  globalThis.RTCSessionDescription = class { constructor(d) { Object.assign(this, d); } };
  globalThis.RTCIceCandidate = class { constructor(d) { Object.assign(this, d); } };
  Object.defineProperty(globalThis.navigator, 'mediaDevices', {
    configurable: true,
    value: { getUserMedia: vi.fn(async () => fakeStream([fakeTrack('audio'), fakeTrack('video')])), getDisplayMedia: vi.fn() },
  });
});

async function loadStore() {
  const { useCallStore } = await import('./useCallStore');
  const { useAuthStore } = await import('./useAuthStore');
  useAuthStore().setUser({ id: 1, name: 'Me' });
  return useCallStore();
}

async function answerOfferFrom(store, user) {
  store.handleParticipantJoined({ callId: 'call-1', participant: user });
  await store.handleSignal({
    callId: 'call-1', fromUserId: user.id,
    signal: { type: 'offer', data: { type: 'offer', sdp: 'o' } },
  });
}

describe('useCallStore — ICE servers', () => {
  it('opens peer connections with the server-provided STUN + TURN list', async () => {
    iceResponse = { iceServers: TURN, ttl: 600 };
    const store = await loadStore();

    await store.initiateCall([alice], 'video');
    expect(gets).toEqual(['/calls/ice-servers']);

    await answerOfferFrom(store, alice);

    expect(pcConfigs).toHaveLength(1);
    expect(pcConfigs[0].iceServers).toEqual(TURN);
  });

  it('falls back to public STUN when the ICE endpoint is unreachable, and still places the call', async () => {
    iceResponse = null;
    const store = await loadStore();

    await store.initiateCall([alice], 'video');
    expect(store.callState).toBe('calling');

    await answerOfferFrom(store, alice);

    expect(pcConfigs[0].iceServers.map(s => s.urls)).toEqual([
      'stun:stun.l.google.com:19302',
      'stun:stun1.l.google.com:19302',
    ]);
    expect(pcConfigs[0].iceServers.some(s => s.credential)).toBe(false);
  });

  it('reuses the list within its TTL instead of refetching on every call', async () => {
    iceResponse = { iceServers: TURN, ttl: 600 };
    const store = await loadStore();

    await store.initiateCall([alice], 'video');
    await store.cancelCall();
    await store.initiateCall([alice], 'audio');

    expect(gets).toHaveLength(1);
  });

  it('refetches once the minted credentials are about to expire', async () => {
    vi.useFakeTimers();
    try {
      iceResponse = { iceServers: TURN, ttl: 120 };
      const store = await loadStore();

      await store.initiateCall([alice], 'video');
      await store.cancelCall();
      vi.setSystemTime(Date.now() + 61_000); // past the (ttl - 60s) refresh point
      await store.initiateCall([alice], 'video');

      expect(gets).toHaveLength(2);
    } finally {
      vi.useRealTimers();
    }
  });
});
