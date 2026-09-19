import { ref } from 'vue';

// MediaRecorder takes ONE MediaStream, so a multi-party call has to be
// composited first: every participant's video is drawn onto an offscreen
// canvas (captureStream → one video track) and every participant's audio is
// mixed through an AudioContext (→ one audio track). Nothing leaves the
// browser — the result is a Blob the user downloads.

const VIDEO_MIME_TYPES = [
  'video/webm;codecs=vp9,opus',
  'video/webm;codecs=vp8,opus',
  'video/webm',
  'video/mp4',
];

const AUDIO_MIME_TYPES = [
  'audio/webm;codecs=opus',
  'audio/webm',
  'audio/ogg;codecs=opus',
  'audio/mp4',
];

function pickSupportedMimeType(candidates) {
  if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') return '';
  return candidates.find(type => MediaRecorder.isTypeSupported(type)) ?? '';
}

export function extensionForMimeType(mimeType) {
  if (mimeType.includes('mp4')) return mimeType.startsWith('audio') ? 'm4a' : 'mp4';
  if (mimeType.includes('ogg')) return 'ogg';
  return 'webm';
}

/** canvas.captureStream() and multi-track MediaRecorder are what Safari lags on. */
export function isCallRecordingSupported() {
  if (typeof window === 'undefined') return false;
  return !!window.MediaRecorder
    && !!(window.AudioContext || window.webkitAudioContext)
    && typeof HTMLCanvasElement !== 'undefined'
    && typeof HTMLCanvasElement.prototype.captureStream === 'function';
}

export function formatBytes(bytes) {
  if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.style.display = 'none';
  document.body.appendChild(a);
  a.click();
  a.remove();
  // Give the browser time to start the download before the URL goes away.
  setTimeout(() => URL.revokeObjectURL(url), 10_000);
}

function initialsOf(label) {
  return (label || '').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) || '?';
}

/**
 * A "source" is one tile in the recording:
 *   { id, label, stream, audioStream?, mirror? }
 *   - stream:      MediaStream drawn as the tile's video (null → avatar tile)
 *   - audioStream: MediaStream whose audio is mixed in (defaults to `stream`;
 *                  the local tile passes the mic stream here even while it
 *                  draws the shared screen, which has no audio)
 *   - mirror:      draw flipped, like the selfie preview (camera only)
 */
export function useCallRecorder({ width = 1280, height = 720, fps = 24 } = {}) {
  const isRecording   = ref(false);
  const durationSec   = ref(0);
  const bytesRecorded = ref(0);
  const error         = ref(null);
  const isSupported   = isCallRecordingSupported();

  // id → { label, mirror, stream, video, audioStream, audioNode }
  const tiles = new Map();
  let order = [];

  let host = null;          // hidden container for the feeder <video>s
  let canvas = null;
  let ctx = null;
  let canvasStream = null;
  let audioCtx = null;
  let audioDest = null;
  let recorder = null;
  let chunks = [];
  let mimeType = '';
  let withVideo = true;
  let drawTimer = null;
  let clockTimer = null;

  /**
   * @param {object[]} sources   see the source shape above
   * @param {{video?: boolean}} opts  video:false records mixed audio only
   *                                  (audio calls — no point drawing avatars)
   */
  async function start(sources, { video = true } = {}) {
    if (!isSupported || isRecording.value) return false;

    withVideo = video;
    error.value = null;
    chunks = [];
    bytesRecorded.value = 0;
    durationSec.value = 0;

    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    audioCtx  = new AudioCtx();
    audioDest = audioCtx.createMediaStreamDestination();
    // Contexts start suspended until a user gesture — we are inside one (the
    // Record click), so this resolves immediately.
    try { await audioCtx.resume(); } catch {}

    const tracks = [];
    if (withVideo) {
      canvas = document.createElement('canvas');
      canvas.width  = width;
      canvas.height = height;
      ctx = canvas.getContext('2d');
      canvasStream = canvas.captureStream(fps);
      tracks.push(...canvasStream.getVideoTracks());
    }
    tracks.push(...audioDest.stream.getAudioTracks());

    syncSources(sources);

    if (withVideo) {
      draw();
      // setInterval rather than requestAnimationFrame: rAF stops completely
      // in a background tab, and people do switch tabs mid-call.
      drawTimer = setInterval(draw, 1000 / fps);
    }

    mimeType = pickSupportedMimeType(withVideo ? VIDEO_MIME_TYPES : AUDIO_MIME_TYPES);

    try {
      recorder = new MediaRecorder(new MediaStream(tracks), {
        ...(mimeType ? { mimeType } : {}),
        videoBitsPerSecond: 2_500_000,
        audioBitsPerSecond: 128_000,
      });
    } catch (e) {
      teardown();
      throw e;
    }

    recorder.ondataavailable = (e) => {
      if (e.data && e.data.size > 0) {
        chunks.push(e.data);
        bytesRecorded.value += e.data.size;
      }
    };
    recorder.onerror = (e) => {
      error.value = e?.error?.message || 'Recording failed.';
    };

    // A timeslice so chunks accumulate as we go — a crash mid-call still
    // leaves everything up to the last second in memory.
    recorder.start(1000);
    isRecording.value = true;
    clockTimer = setInterval(() => { durationSec.value++; }, 1000);

    return true;
  }

  /** Re-sync the tile list mid-recording (someone joined/left, screen share toggled). */
  function updateSources(sources) {
    if (!isRecording.value) return;
    syncSources(sources);
  }

  /** Stops and resolves {blob, durationSec, mimeType, bytes}, or null if nothing was recording. */
  function stop() {
    return new Promise((resolve) => {
      if (!recorder || !isRecording.value) {
        resolve(null);
        return;
      }

      const finalType = mimeType || (withVideo ? 'video/webm' : 'audio/webm');
      const finish = () => {
        const blob = new Blob(chunks, { type: finalType });
        const result = { blob, durationSec: durationSec.value, mimeType: finalType, bytes: blob.size };
        teardown();
        resolve(result);
      };

      if (recorder.state === 'inactive') {
        // Already dead (onerror) — nothing more will arrive.
        finish();
        return;
      }

      recorder.onstop = finish;
      try {
        recorder.stop();
      } catch {
        finish();
      }
    });
  }

  /** Discards the in-progress recording without resolving a blob. */
  function cancel() {
    if (recorder && isRecording.value) {
      recorder.onstop = null;
      try { recorder.stop(); } catch {}
    }
    teardown();
  }

  // ── Sources ──────────────────────────────────────────────────────────
  function syncSources(sources) {
    const seen = new Set();
    for (const src of sources) {
      seen.add(src.id);
      applySource(src);
    }
    for (const id of Array.from(tiles.keys())) {
      if (!seen.has(id)) removeTile(id);
    }
    order = sources.map(s => s.id);
  }

  function applySource(src) {
    let tile = tiles.get(src.id);
    if (!tile) {
      tile = { label: '', mirror: false, stream: null, video: null, audioStream: null, audioNode: null };
      tiles.set(src.id, tile);
    }
    tile.label  = src.label ?? '';
    tile.mirror = !!src.mirror;

    const stream = withVideo ? (src.stream ?? null) : null;
    if (tile.stream !== stream) {
      tile.stream = stream;
      if (stream) {
        if (!tile.video) {
          tile.video = document.createElement('video');
          tile.video.muted = true;
          tile.video.playsInline = true;
          tile.video.autoplay = true;
          ensureHost().appendChild(tile.video);
        }
        tile.video.srcObject = stream;
        tile.video.play?.()?.catch?.(() => {});
      } else if (tile.video) {
        tile.video.srcObject = null;
        tile.video.remove();
        tile.video = null;
      }
    }

    const audioStream = src.audioStream ?? src.stream ?? null;
    if (tile.audioStream !== audioStream) {
      tile.audioNode?.disconnect?.();
      tile.audioNode = null;
      tile.audioStream = audioStream;
    }
    // A MediaStreamAudioSourceNode binds to the tracks present at creation,
    // and a remote stream's audio track can land a beat after its video one —
    // so keep trying until there is something to wire.
    if (!tile.audioNode && audioCtx && audioStream && audioStream.getAudioTracks().length > 0) {
      try {
        tile.audioNode = audioCtx.createMediaStreamSource(audioStream);
        tile.audioNode.connect(audioDest);
      } catch {
        tile.audioNode = null;
      }
    }
  }

  function removeTile(id) {
    const tile = tiles.get(id);
    if (!tile) return;
    tile.audioNode?.disconnect?.();
    if (tile.video) {
      tile.video.srcObject = null;
      tile.video.remove();
    }
    tiles.delete(id);
  }

  function ensureHost() {
    if (host) return host;
    host = document.createElement('div');
    host.setAttribute('aria-hidden', 'true');
    host.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;overflow:hidden;opacity:0;pointer-events:none';
    document.body.appendChild(host);
    return host;
  }

  // ── Drawing ──────────────────────────────────────────────────────────
  // Same grid rule as CallScreen.vue so the file looks like what was on screen.
  function draw() {
    if (!ctx) return;

    ctx.fillStyle = '#0b0f1a';
    ctx.fillRect(0, 0, width, height);

    const n = order.length;
    if (n === 0) return;

    const cols = n <= 1 ? 1 : n <= 4 ? 2 : 3;
    const rows = Math.ceil(n / cols);
    const gap  = 8;
    const tw   = (width  - gap * (cols + 1)) / cols;
    const th   = (height - gap * (rows + 1)) / rows;

    order.forEach((id, i) => {
      const tile = tiles.get(id);
      if (!tile) return;
      const x = gap + (i % cols) * (tw + gap);
      const y = gap + Math.floor(i / cols) * (th + gap);
      drawTile(tile, x, y, tw, th);
    });
  }

  function drawTile(tile, x, y, w, h) {
    const v = tile.video;
    const hasFrame = !!v && v.readyState >= 2 && v.videoWidth > 0 && v.videoHeight > 0;

    ctx.fillStyle = '#151a2b';
    ctx.fillRect(x, y, w, h);

    if (hasFrame) {
      // object-fit: cover
      const scale = Math.max(w / v.videoWidth, h / v.videoHeight);
      const sw = w / scale;
      const sh = h / scale;
      const sx = (v.videoWidth  - sw) / 2;
      const sy = (v.videoHeight - sh) / 2;

      ctx.save();
      ctx.beginPath();
      ctx.rect(x, y, w, h);
      ctx.clip();
      if (tile.mirror) {
        ctx.translate(x + w, y);
        ctx.scale(-1, 1);
        ctx.drawImage(v, sx, sy, sw, sh, 0, 0, w, h);
      } else {
        ctx.drawImage(v, sx, sy, sw, sh, x, y, w, h);
      }
      ctx.restore();
    } else {
      const r = Math.min(w, h) * 0.18;
      ctx.fillStyle = '#4f46e5';
      ctx.beginPath();
      ctx.arc(x + w / 2, y + h / 2, r, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = '#ffffff';
      ctx.font = `bold ${Math.round(r)}px sans-serif`;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(initialsOf(tile.label), x + w / 2, y + h / 2);
    }

    // Name badge, bottom-left, like CallParticipantTile.vue
    const fontPx = Math.max(12, Math.round(h * 0.05));
    const pad = 6;
    ctx.font = `${fontPx}px sans-serif`;
    ctx.textAlign = 'left';
    ctx.textBaseline = 'alphabetic';
    const label = tile.label || '';
    const labelWidth = ctx.measureText(label).width;
    ctx.fillStyle = 'rgba(0,0,0,0.55)';
    ctx.fillRect(x + 8, y + h - fontPx - pad * 2 - 8, labelWidth + pad * 2, fontPx + pad * 2);
    ctx.fillStyle = '#ffffff';
    ctx.fillText(label, x + 8 + pad, y + h - pad - 10);
  }

  // ── Teardown ─────────────────────────────────────────────────────────
  function teardown() {
    if (drawTimer)  { clearInterval(drawTimer);  drawTimer = null; }
    if (clockTimer) { clearInterval(clockTimer); clockTimer = null; }

    for (const id of Array.from(tiles.keys())) removeTile(id);
    order = [];

    canvasStream?.getTracks().forEach(t => t.stop());
    canvasStream = null;
    canvas = null;
    ctx = null;

    audioCtx?.close?.()?.catch?.(() => {});
    audioCtx = null;
    audioDest = null;

    recorder = null;
    chunks = [];

    host?.remove();
    host = null;

    isRecording.value = false;
  }

  return {
    isRecording, durationSec, bytesRecorded, error, isSupported,
    start, stop, cancel, updateSources,
  };
}
