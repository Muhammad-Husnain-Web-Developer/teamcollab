import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  useCallRecorder,
  isCallRecordingSupported,
  extensionForMimeType,
  formatBytes,
  downloadBlob,
} from './useCallRecorder';

// ── Browser API stubs (jsdom has none of MediaStream/MediaRecorder/WebAudio) ──
function fakeTrack(kind) {
  return { kind, stop: vi.fn(), enabled: true };
}

class FakeMediaStream {
  constructor(tracks = []) { this.tracks = tracks; this.id = Math.random().toString(36).slice(2); }
  getTracks()      { return this.tracks; }
  getVideoTracks() { return this.tracks.filter(t => t.kind === 'video'); }
  getAudioTracks() { return this.tracks.filter(t => t.kind === 'audio'); }
}

const recorders = [];
class FakeMediaRecorder {
  static isTypeSupported(type) { return type === 'video/webm;codecs=vp8,opus' || type === 'audio/webm;codecs=opus'; }
  constructor(stream, options) {
    this.stream = stream;
    this.options = options;
    this.state = 'inactive';
    this.start = vi.fn((timeslice) => { this.timeslice = timeslice; this.state = 'recording'; });
    this.stop = vi.fn(() => { this.state = 'inactive'; this.onstop?.(); });
    recorders.push(this);
  }
  // Test helper: deliver a chunk as the browser would on each timeslice.
  emit(size) {
    this.ondataavailable?.({ data: { size } });
  }
}

const audioContexts = [];
class FakeAudioContext {
  constructor() {
    this.sources = [];
    this.destination = { stream: new FakeMediaStream([fakeTrack('audio')]) };
    this.resume = vi.fn(async () => {});
    this.close = vi.fn(async () => {});
    audioContexts.push(this);
  }
  createMediaStreamDestination() { return this.destination; }
  createMediaStreamSource(stream) {
    const node = { stream, connect: vi.fn(), disconnect: vi.fn() };
    this.sources.push(node);
    return node;
  }
}

const canvasStreams = [];
let ctx;

beforeEach(() => {
  vi.useFakeTimers();
  recorders.length = 0;
  audioContexts.length = 0;
  canvasStreams.length = 0;

  globalThis.MediaStream = FakeMediaStream;
  globalThis.MediaRecorder = FakeMediaRecorder;
  window.MediaRecorder = FakeMediaRecorder;
  window.AudioContext = FakeAudioContext;

  ctx = {
    fillRect: vi.fn(), drawImage: vi.fn(), fillText: vi.fn(), measureText: vi.fn(() => ({ width: 40 })),
    save: vi.fn(), restore: vi.fn(), beginPath: vi.fn(), rect: vi.fn(), clip: vi.fn(),
    translate: vi.fn(), scale: vi.fn(), arc: vi.fn(), fill: vi.fn(),
  };
  HTMLCanvasElement.prototype.captureStream = vi.fn(function () {
    const s = new FakeMediaStream([fakeTrack('video')]);
    canvasStreams.push(s);
    return s;
  });
  vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockImplementation(() => ctx);

  // jsdom's <video> never has frames; pretend every attached one does.
  vi.spyOn(HTMLMediaElement.prototype, 'play').mockImplementation(() => Promise.resolve());
  Object.defineProperty(HTMLMediaElement.prototype, 'readyState', { configurable: true, get: () => 4 });
  Object.defineProperty(HTMLVideoElement.prototype, 'videoWidth',  { configurable: true, get: () => 640 });
  Object.defineProperty(HTMLVideoElement.prototype, 'videoHeight', { configurable: true, get: () => 480 });
});

afterEach(() => {
  vi.useRealTimers();
  vi.restoreAllMocks();
  delete HTMLCanvasElement.prototype.captureStream;
});

const camStream = () => new FakeMediaStream([fakeTrack('audio'), fakeTrack('video')]);

function sources(n = 2) {
  const list = [];
  for (let i = 1; i <= n; i++) list.push({ id: i, label: `User ${i}`, stream: camStream() });
  list.push({ id: 'local', label: 'Me (you)', stream: camStream(), mirror: true });
  return list;
}

function feederVideos() {
  return Array.from(document.querySelectorAll('div[aria-hidden="true"] video'));
}

describe('useCallRecorder — support detection & helpers', () => {
  it('is unsupported without MediaRecorder or canvas.captureStream', () => {
    expect(isCallRecordingSupported()).toBe(true);
    delete HTMLCanvasElement.prototype.captureStream;
    expect(isCallRecordingSupported()).toBe(false);
    HTMLCanvasElement.prototype.captureStream = () => {};
    window.MediaRecorder = undefined;
    expect(isCallRecordingSupported()).toBe(false);
  });

  it('maps mime types to sensible file extensions', () => {
    expect(extensionForMimeType('video/webm;codecs=vp9,opus')).toBe('webm');
    expect(extensionForMimeType('video/mp4')).toBe('mp4');
    expect(extensionForMimeType('audio/mp4')).toBe('m4a');
    expect(extensionForMimeType('audio/ogg;codecs=opus')).toBe('ogg');
  });

  it('formats sizes in KB below a megabyte and MB above', () => {
    expect(formatBytes(512)).toBe('1 KB');
    expect(formatBytes(300 * 1024)).toBe('300 KB');
    expect(formatBytes(3.45 * 1024 * 1024)).toBe('3.5 MB');
  });

  it('downloadBlob clicks a temporary anchor and revokes the URL afterwards', () => {
    URL.createObjectURL = vi.fn(() => 'blob:fake');
    URL.revokeObjectURL = vi.fn();
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});

    downloadBlob(new Blob(['x']), 'call-recording-1.webm');

    expect(click).toHaveBeenCalledTimes(1);
    expect(document.querySelector('a[download]')).toBeNull(); // removed again
    vi.advanceTimersByTime(10_000);
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:fake');
  });
});

describe('useCallRecorder — compositing', () => {
  it('records ONE stream made of the canvas video track plus the mixed audio track', async () => {
    const rec = useCallRecorder({ fps: 10 });
    const ok = await rec.start(sources(2));

    expect(ok).toBe(true);
    expect(rec.isRecording.value).toBe(true);
    expect(recorders).toHaveLength(1);

    const [r] = recorders;
    expect(r.options.mimeType).toBe('video/webm;codecs=vp8,opus');
    expect(r.start).toHaveBeenCalledWith(1000); // timeslice → chunks accumulate as we go
    expect(r.stream.getVideoTracks()).toEqual(canvasStreams[0].getVideoTracks());
    expect(r.stream.getAudioTracks()).toEqual(audioContexts[0].destination.stream.getAudioTracks());

    // Every participant's audio (2 remote + me) is routed into the mixer.
    expect(audioContexts[0].sources).toHaveLength(3);
    audioContexts[0].sources.forEach(n => expect(n.connect).toHaveBeenCalledWith(audioContexts[0].destination));

    // One hidden feeder <video> per tile with a stream.
    expect(feederVideos()).toHaveLength(3);

    rec.cancel();
  });

  it('draws every tile on each tick, mirroring only the tiles that ask for it', async () => {
    const rec = useCallRecorder({ fps: 10 });
    await rec.start(sources(2));

    ctx.drawImage.mockClear();
    ctx.scale.mockClear();
    vi.advanceTimersByTime(100); // one frame at 10fps

    expect(ctx.drawImage).toHaveBeenCalledTimes(3);
    expect(ctx.scale).toHaveBeenCalledTimes(1);          // only the local tile is mirrored
    expect(ctx.scale).toHaveBeenCalledWith(-1, 1);

    rec.cancel();
  });

  it('adds and removes tiles mid-recording when the roster changes', async () => {
    const rec = useCallRecorder();
    const initial = sources(1); // 1 remote + me
    await rec.start(initial);
    expect(feederVideos()).toHaveLength(2);

    // Someone joins.
    const joined = { id: 9, label: 'Newcomer', stream: camStream() };
    rec.updateSources([...initial, joined]);
    expect(feederVideos()).toHaveLength(3);
    expect(audioContexts[0].sources).toHaveLength(3);

    // The first remote leaves.
    rec.updateSources([joined, initial[1]]);
    expect(feederVideos()).toHaveLength(2);
    const leftNode = audioContexts[0].sources.find(n => n.stream === initial[0].stream);
    expect(leftNode.disconnect).toHaveBeenCalled();

    rec.cancel();
  });

  it('swaps the local tile to the shared screen without creating a second feeder video', async () => {
    const rec = useCallRecorder();
    const list = sources(1);
    await rec.start(list);

    const localVideo = feederVideos()[1];
    const mic = list[1].stream;
    const screen = new FakeMediaStream([fakeTrack('video')]); // no audio, like getDisplayMedia({audio:false})

    rec.updateSources([list[0], { ...list[1], stream: screen, audioStream: mic, mirror: false }]);

    expect(feederVideos()).toHaveLength(2);
    expect(feederVideos()[1]).toBe(localVideo);
    expect(localVideo.srcObject).toBe(screen);
    // Mic audio stays wired — nothing was torn down for the swap.
    const micNode = audioContexts[0].sources.find(n => n.stream === mic);
    expect(micNode.disconnect).not.toHaveBeenCalled();

    rec.cancel();
  });

  it('wires a remote audio track that shows up after the tile was first added', async () => {
    const rec = useCallRecorder();
    const lateAudio = new FakeMediaStream([fakeTrack('video')]); // video arrived first
    const list = [{ id: 1, label: 'Late', stream: lateAudio }, sources(0)[0]];
    await rec.start(list);
    expect(audioContexts[0].sources.map(n => n.stream)).not.toContain(lateAudio);

    lateAudio.tracks.push(fakeTrack('audio'));
    rec.updateSources(list);
    expect(audioContexts[0].sources.map(n => n.stream)).toContain(lateAudio);

    rec.cancel();
  });

  it('stop() resolves the assembled blob with duration and size, then tears everything down', async () => {
    const rec = useCallRecorder();
    await rec.start(sources(1));
    const [r] = recorders;

    vi.advanceTimersByTime(3000);
    r.emit(1000);
    r.emit(1500);
    expect(rec.bytesRecorded.value).toBe(2500);
    expect(rec.durationSec.value).toBe(3);

    const result = await rec.stop();

    expect(r.stop).toHaveBeenCalled();
    expect(result.mimeType).toBe('video/webm;codecs=vp8,opus');
    expect(result.durationSec).toBe(3);
    expect(result.blob).toBeInstanceOf(Blob);
    expect(result.blob.type).toBe('video/webm;codecs=vp8,opus');

    expect(rec.isRecording.value).toBe(false);
    expect(audioContexts[0].close).toHaveBeenCalled();
    canvasStreams[0].getTracks().forEach(t => expect(t.stop).toHaveBeenCalled());
    expect(feederVideos()).toHaveLength(0);
    expect(document.querySelector('div[aria-hidden="true"]')).toBeNull();
  });

  it('stop() resolves null when nothing is recording', async () => {
    const rec = useCallRecorder();
    expect(await rec.stop()).toBeNull();
  });

  it('records mixed audio only (no canvas) for audio calls', async () => {
    const rec = useCallRecorder();
    await rec.start(sources(1), { video: false });

    expect(HTMLCanvasElement.prototype.captureStream).not.toHaveBeenCalled();
    expect(recorders[0].options.mimeType).toBe('audio/webm;codecs=opus');
    expect(recorders[0].stream.getVideoTracks()).toHaveLength(0);
    expect(recorders[0].stream.getAudioTracks()).toHaveLength(1);
    expect(feederVideos()).toHaveLength(0); // no video elements needed
    expect(audioContexts[0].sources).toHaveLength(2);

    rec.cancel();
  });

  it('surfaces a MediaRecorder error through the error ref', async () => {
    const rec = useCallRecorder();
    await rec.start(sources(1));

    recorders[0].onerror({ error: { message: 'Disk full' } });
    expect(rec.error.value).toBe('Disk full');

    rec.cancel();
  });
});
