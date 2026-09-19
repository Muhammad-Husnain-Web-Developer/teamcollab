import { describe, it, expect, beforeEach, vi } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';

// Ring tones use Audio APIs jsdom doesn't have.
vi.mock('../Utils/sounds', () => ({
  startIncomingRing: vi.fn(),
  startOutgoingRingback: vi.fn(),
  stopRing: vi.fn(),
  playNotificationDing: vi.fn(),
}));

// Signaling goes over HTTP — never hit the network from a unit test. Accept
// returns the roster of people already in the call (the caller), which is
// what makes the store open a peer connection to them.
vi.mock('axios', () => ({
  default: {
    post: vi.fn(async (url) => {
      if (url.endsWith('/accept')) {
        return { data: { participants: [{ id: 2, name: 'Alice', display_name: 'Alice', avatar_url: null }] } };
      }
      return { data: {} };
    }),
  },
}));

// ── Minimal WebRTC / media stubs ──────────────────────────────────────────
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

let lastPc = null;

class FakeRTCPeerConnection {
  constructor() {
    this.senders = [];
    this.remoteDescription = null;
    this.connectionState = 'new';
    this.iceConnectionState = 'new';
    lastPc = this;
  }
  addTrack(track) {
    const sender = { track, replaceTrack: vi.fn(async (t) => { sender.track = t; }) };
    this.senders.push(sender);
    return sender;
  }
  getSenders() { return this.senders; }
  async setRemoteDescription(desc) { this.remoteDescription = desc; }
  async createAnswer() { return { type: 'answer', sdp: 'v=0' }; }
  async setLocalDescription() {}
  async addIceCandidate() {}
  close() {}
}

const cameraTrack = fakeTrack('video', 'camera');
const micTrack = fakeTrack('audio', 'mic');

beforeEach(() => {
  setActivePinia(createPinia());
  lastPc = null;

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

/**
 * Drive the store to an ACTIVE video call with a built peer connection —
 * the state screen sharing requires. Mirrors the real callee flow:
 * incoming → accept (gets local media, offers to the caller) → connected.
 */
async function establishVideoCall(store, type = 'video') {
  const alice = { id: 2, name: 'Alice', display_name: 'Alice', avatar_url: null };
  store.handleIncoming({ callId: 'call-1', callType: type, caller: alice, participants: [{ ...alice, status: 'joined' }] });
  await store.acceptCall();
  store.callState = 'active';
}

describe('useCallStore — screen sharing', () => {
  it('swaps the camera track for the screen track on the existing video sender', async () => {
    const { useCallStore } = await import('./useCallStore');
    const store = useCallStore();
    await establishVideoCall(store);

    const screenTrack = fakeTrack('video', 'screen');
    navigator.mediaDevices.getDisplayMedia.mockResolvedValue(fakeStream([screenTrack]));

    await store.startScreenShare();

    const videoSender = lastPc.getSenders().find(s => s.track?.kind === 'video');
    expect(navigator.mediaDevices.getDisplayMedia).toHaveBeenCalledWith({ video: true, audio: false });
    expect(videoSender.replaceTrack).toHaveBeenCalledWith(screenTrack);
    expect(videoSender.track).toBe(screenTrack);
    expect(store.isScreenSharing).toBe(true);
    expect(store.screenStream).not.toBeNull();
  });

  it('restores the original camera track and stops the screen stream on stop', async () => {
    const { useCallStore } = await import('./useCallStore');
    const store = useCallStore();
    await establishVideoCall(store);

    const screenTrack = fakeTrack('video', 'screen');
    navigator.mediaDevices.getDisplayMedia.mockResolvedValue(fakeStream([screenTrack]));

    await store.startScreenShare();
    await store.stopScreenShare();

    const videoSender = lastPc.getSenders().find(s => s.track?.kind === 'video');
    expect(videoSender.replaceTrack).toHaveBeenLastCalledWith(cameraTrack);
    expect(videoSender.track).toBe(cameraTrack);
    expect(screenTrack.stop).toHaveBeenCalled();
    expect(store.isScreenSharing).toBe(false);
    expect(store.screenStream).toBeNull();
  });

  it("reverts automatically when the browser's own Stop-sharing bar ends the track", async () => {
    const { useCallStore } = await import('./useCallStore');
    const store = useCallStore();
    await establishVideoCall(store);

    const screenTrack = fakeTrack('video', 'screen');
    navigator.mediaDevices.getDisplayMedia.mockResolvedValue(fakeStream([screenTrack]));

    await store.startScreenShare();
    expect(typeof screenTrack.onended).toBe('function');

    await screenTrack.onended();

    expect(store.isScreenSharing).toBe(false);
    const videoSender = lastPc.getSenders().find(s => s.track?.kind === 'video');
    expect(videoSender.track).toBe(cameraTrack);
  });

  it('refuses to share on an audio-only call (no video m-line to replace)', async () => {
    const { useCallStore } = await import('./useCallStore');
    const store = useCallStore();
    await establishVideoCall(store, 'audio');

    await store.startScreenShare();

    expect(navigator.mediaDevices.getDisplayMedia).not.toHaveBeenCalled();
    expect(store.isScreenSharing).toBe(false);
  });

  it('does nothing when the user cancels the screen picker', async () => {
    const { useCallStore } = await import('./useCallStore');
    const store = useCallStore();
    await establishVideoCall(store);

    const cancel = new Error('cancelled');
    cancel.name = 'NotAllowedError';
    navigator.mediaDevices.getDisplayMedia.mockRejectedValue(cancel);

    await store.startScreenShare();

    const videoSender = lastPc.getSenders().find(s => s.track?.kind === 'video');
    expect(videoSender.replaceTrack).not.toHaveBeenCalled();
    expect(store.isScreenSharing).toBe(false);
  });

  it('stops the screen stream when the call ends mid-share', async () => {
    const { useCallStore } = await import('./useCallStore');
    const store = useCallStore();
    await establishVideoCall(store);

    const screenTrack = fakeTrack('video', 'screen');
    navigator.mediaDevices.getDisplayMedia.mockResolvedValue(fakeStream([screenTrack]));

    await store.startScreenShare();
    await store.endCall();

    expect(screenTrack.stop).toHaveBeenCalled();
    expect(store.isScreenSharing).toBe(false);
    expect(store.screenStream).toBeNull();
    expect(store.callState).toBe('idle');
  });
});
