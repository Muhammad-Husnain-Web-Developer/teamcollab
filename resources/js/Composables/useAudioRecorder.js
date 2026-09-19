import { ref } from 'vue';

// Chrome/Firefox default to opus-in-webm or -ogg; Safari only supports mp4/aac.
const CANDIDATE_MIME_TYPES = [
  'audio/webm;codecs=opus',
  'audio/webm',
  'audio/ogg;codecs=opus',
  'audio/ogg',
  'audio/mp4',
];

function pickSupportedMimeType() {
  if (typeof MediaRecorder === 'undefined') return '';
  return CANDIDATE_MIME_TYPES.find(type => MediaRecorder.isTypeSupported(type)) ?? '';
}

/**
 * Laravel's `mimes:` validation rule checks the uploaded file's extension,
 * not its actual content-type, so the Blob filename we upload must carry an
 * extension that matches one FileService/UploadFileRequest allow.
 */
export function extensionForMimeType(mimeType) {
  if (mimeType.includes('ogg')) return 'ogg';
  if (mimeType.includes('mp4')) return 'm4a';
  return 'webm';
}

/** Mirrors useCallStore.js's mediaErrorMessage() for consistent mic-permission UX. */
export function micErrorMessage(e) {
  switch (e?.name) {
    case 'NotAllowedError':
    case 'PermissionDeniedError':
      return 'Microphone permission denied — allow it in the browser and try again.';
    case 'NotFoundError':
      return 'No microphone found on this device.';
    case 'NotReadableError':
      return 'Microphone is in use by another app.';
    default:
      return 'Could not start recording — microphone or browser error.';
  }
}

export function useAudioRecorder() {
  const isRecording = ref(false);
  const durationSec = ref(0);

  const isSupported = typeof window !== 'undefined'
    && !!window.MediaRecorder
    && !!navigator.mediaDevices?.getUserMedia;

  let recorder = null;
  let stream = null;
  let chunks = [];
  let timer = null;
  let mimeType = '';

  async function start() {
    if (!isSupported || isRecording.value) return;

    stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    mimeType = pickSupportedMimeType();
    recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
    chunks = [];

    recorder.ondataavailable = (e) => {
      if (e.data.size > 0) chunks.push(e.data);
    };

    recorder.start();
    isRecording.value = true;
    durationSec.value = 0;
    timer = setInterval(() => { durationSec.value++; }, 1000);
  }

  /** Stops and resolves {blob, durationSec, mimeType}, or null if nothing was recording. */
  function stop() {
    return new Promise((resolve) => {
      if (!recorder || !isRecording.value) {
        resolve(null);
        return;
      }

      recorder.onstop = () => {
        const finalType = mimeType || 'audio/webm';
        const blob = new Blob(chunks, { type: finalType });
        const finishedDuration = durationSec.value;
        teardown();
        resolve({ blob, durationSec: finishedDuration, mimeType: finalType });
      };

      recorder.stop();
    });
  }

  /** Discards the in-progress recording without resolving a blob. */
  function cancel() {
    if (recorder && isRecording.value) {
      recorder.onstop = null;
      recorder.stop();
    }
    teardown();
  }

  function teardown() {
    stream?.getTracks().forEach(t => t.stop());
    stream = null;
    recorder = null;
    chunks = [];
    isRecording.value = false;
    if (timer) { clearInterval(timer); timer = null; }
  }

  return { isRecording, durationSec, isSupported, start, stop, cancel };
}
