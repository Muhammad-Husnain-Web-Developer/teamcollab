<template>
  <Teleport to="body">
    <Transition name="call-fade">
      <div
        v-if="visible"
        class="isolate fixed inset-0 z-[250] flex flex-col bg-dark-900 overflow-hidden"
      >
        <Atmosphere />

        <!-- Someone else is recording — say so, always, in both layouts -->
        <div
          v-if="callStore.recordingParticipants.length"
          class="absolute top-4 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 px-3 py-1.5 rounded-full bg-red-500/20 backdrop-blur-xl border border-red-400/40 shadow-glass"
        >
          <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse" />
          <span class="text-red-100 text-xs font-medium">{{ recordingBannerText }}</span>
        </div>

        <!-- ── Group layout: a grid of tiles (2+ remote participants) ── -->
        <div v-if="isGroup" class="relative z-10 flex-1 min-h-0 p-4 pb-2">
          <div class="h-full grid gap-3" :class="gridClass">
            <CallParticipantTile
              v-for="p in callStore.remoteParticipants"
              :key="p.id"
              :participant="p"
              :is-video-call="callStore.callType === 'video'"
              :recording="callStore.recordingBy.has(p.id)"
            />
            <CallParticipantTile
              :participant="localParticipant"
              :is-video-call="callStore.callType === 'video'"
              is-local
              muted
              :mirror="!callStore.isScreenSharing"
              :hide-video="callStore.isCamOff && !callStore.isScreenSharing"
              :badge="callStore.isScreenSharing ? 'Sharing' : ''"
              :recording="isRecordingLocally"
            />
          </div>

          <!-- Duration badge -->
          <div
            v-if="callStore.callState === 'active'"
            class="absolute top-6 left-6 flex items-center gap-1.5 bg-black/40 backdrop-blur-xl border border-white/[0.08] shadow-glass px-3 py-1.5 rounded-full"
          >
            <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.7)] animate-pulse" />
            <span class="text-white text-xs font-mono tabular-nums">{{ formattedDuration }}</span>
            <span class="text-dark-50/60 text-xs">· {{ callStore.remoteCount + 1 }} in call</span>
          </div>

          <div v-if="isRecordingLocally" class="absolute top-16 left-6 rec-badge">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" />
            <span class="text-red-100 text-xs font-semibold">REC</span>
            <span class="text-white text-xs font-mono tabular-nums">{{ formattedRecDuration }}</span>
            <span class="text-red-100/70 text-xs">· {{ formatBytes(recBytes) }}</span>
          </div>
        </div>

        <!-- ── 1-on-1 layout: big remote + local picture-in-picture ── -->
        <!-- min-h-0: without it the <video>'s intrinsic height becomes the flex
             item's minimum and pushes the controls bar off-screen whenever the
             viewport is taller than the camera's aspect ratio (4:3 cam, narrow window). -->
        <div v-else class="relative z-10 flex-1 min-h-0 overflow-hidden">

          <!-- Remote video -->
          <video
            v-if="callStore.callType === 'video'"
            ref="remoteVideoEl"
            autoplay
            playsinline
            class="w-full h-full object-cover"
          />

          <!-- Audio-only or no remote stream yet: avatar placeholder -->
          <div
            v-if="callStore.callType === 'audio' || !callStore.remoteStream"
            class="absolute inset-0 flex flex-col items-center justify-center gap-4"
          >
            <!-- Pulsing rings (connecting) or static (active) -->
            <div class="relative">
              <div
                v-if="callStore.callState === 'connecting' || callStore.callState === 'calling'"
                class="absolute inset-0 rounded-full bg-brand-500/20 animate-ping scale-150"
              />
              <div
                v-if="callStore.callState === 'connecting' || callStore.callState === 'calling'"
                class="absolute inset-0 rounded-full bg-brand-500/10 animate-ping scale-125"
                style="animation-delay: .3s"
              />
              <div
                class="relative w-28 h-28 rounded-full overflow-hidden bg-gradient-to-br from-brand-500 to-aurora-violet ring-4 transition-all duration-500"
                :class="callStore.callState === 'active' ? 'ring-white/10' : 'ring-brand-500/40 shadow-glow-brand-lg'"
              >
                <img
                  v-if="callStore.remoteUser?.avatar_url"
                  :src="callStore.remoteUser.avatar_url"
                  class="w-full h-full object-cover"
                  alt=""
                />
                <span v-else class="flex items-center justify-center w-full h-full text-4xl font-bold text-white">
                  {{ remoteInitials }}
                </span>
              </div>
            </div>

            <div class="text-center">
              <p class="text-white text-xl font-bold tracking-tight">
                {{ callStore.remoteUser?.display_name || callStore.remoteUser?.name }}
              </p>
              <p class="text-dark-50/70 text-sm mt-1">{{ statusLabel }}</p>
            </div>

            <!-- Connecting dots animation -->
            <div v-if="callStore.callState === 'connecting' || callStore.callState === 'calling'" class="flex gap-1.5">
              <span v-for="i in 3" :key="i" class="w-1.5 h-1.5 rounded-full bg-brand-400 animate-bounce"
                :style="`animation-delay: ${(i - 1) * .15}s`" />
            </div>
          </div>

          <!-- Duration badge (top-left, only when active) -->
          <div
            v-if="callStore.callState === 'active'"
            class="absolute top-4 left-4 flex items-center gap-1.5 bg-black/40 backdrop-blur-xl border border-white/[0.08] shadow-glass px-3 py-1.5 rounded-full"
          >
            <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.7)] animate-pulse" />
            <span class="text-white text-xs font-mono tabular-nums">{{ formattedDuration }}</span>
          </div>

          <div v-if="isRecordingLocally" class="absolute top-14 left-4 rec-badge">
            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse" />
            <span class="text-red-100 text-xs font-semibold">REC</span>
            <span class="text-white text-xs font-mono tabular-nums">{{ formattedRecDuration }}</span>
            <span class="text-red-100/70 text-xs">· {{ formatBytes(recBytes) }}</span>
          </div>

          <!-- Local video (picture-in-picture, bottom-right) -->
          <div
            v-if="callStore.callType === 'video'"
            class="absolute bottom-28 right-4 w-36 h-24 rounded-xl overflow-hidden bg-dark-800 ring-1 shadow-glass-lg"
            :class="[
              callStore.isCamOff && !callStore.isScreenSharing ? 'hidden' : '',
              callStore.isScreenSharing ? 'ring-brand-400' : 'ring-white/[0.12]',
            ]"
          >
            <!-- A camera preview is mirrored like a selfie; a shared screen must not be. -->
            <video
              ref="localVideoEl"
              autoplay
              playsinline
              muted
              class="w-full h-full object-cover"
              :class="callStore.isScreenSharing ? '' : 'scale-x-[-1]'"
            />
            <div
              v-if="callStore.isScreenSharing"
              class="absolute bottom-1 left-1 px-1.5 py-0.5 rounded bg-brand-500/90 text-white text-[9px] font-semibold"
            >
              Sharing
            </div>
          </div>
        </div>

        <!-- ── Controls bar ── -->
        <div class="relative z-10 flex-shrink-0 flex items-center justify-center py-8 bg-gradient-to-t from-black/50 via-black/10 to-transparent">
          <div class="glass-elevated !rounded-full flex items-center gap-4 px-6 py-4">

            <!-- Mute -->
            <button
              @click="callStore.toggleMute()"
              class="call-btn"
              :class="callStore.isMuted ? 'call-btn-active' : ''"
              :title="callStore.isMuted ? 'Unmute' : 'Mute'"
            >
              <svg v-if="!callStore.isMuted" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 15c1.66 0 3-1.34 3-3V6c0-1.66-1.34-3-3-3S9 4.34 9 6v6c0 1.66 1.34 3 3 3zm-1-9c0-.55.45-1 1-1s1 .45 1 1v6c0 .55-.45 1-1 1s-1-.45-1-1V6zm6 6c0 2.76-2.24 5-5 5s-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-2.08C16.39 18.43 19 15.53 19 12h-2z"/>
              </svg>
              <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M19 11h-1.7c0 .74-.16 1.43-.43 2.05l1.23 1.23c.56-.98.9-2.09.9-3.28zm-4.02.17c0-.06.02-.11.02-.17V6c0-1.66-1.34-3-3-3S9 4.34 9 6v.18l5.98 5.99zM4.27 3L3 4.27l6.01 6.01V12c0 1.66 1.33 3 2.99 3 .22 0 .44-.03.65-.08l1.66 1.66c-.71.33-1.5.52-2.31.52-2.76 0-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-2.08c.57-.08 1.12-.24 1.64-.46L19.73 21 21 19.73 4.27 3z"/>
              </svg>
            </button>

            <!-- Camera toggle (video only) -->
            <button
              v-if="callStore.callType === 'video'"
              @click="callStore.toggleCamera()"
              class="call-btn"
              :class="callStore.isCamOff ? 'call-btn-active' : ''"
              :title="callStore.isCamOff ? 'Turn on camera' : 'Turn off camera'"
            >
              <svg v-if="!callStore.isCamOff" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
              </svg>
              <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M21 6.5l-4 4V7c0-.55-.45-1-1-1H9.82L21 17.18V6.5zM3.27 2L2 3.27 4.73 6H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.21 0 .39-.08.54-.18L19.73 21 21 19.73 3.27 2z"/>
              </svg>
            </button>

            <!-- Share screen (video calls, once connected) -->
            <button
              v-if="callStore.callType === 'video' && callStore.callState === 'active'"
              @click="callStore.toggleScreenShare()"
              class="call-btn"
              :class="callStore.isScreenSharing ? 'call-btn-active' : ''"
              :title="callStore.isScreenSharing ? 'Stop sharing screen' : 'Share screen'"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                <path v-if="callStore.isScreenSharing" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 7v4m0 0l-2-2m2 2l2-2" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11V7m0 0l-2 2m2-2l2 2" />
              </svg>
            </button>

            <!-- Record (local, once connected) -->
            <button
              v-if="callStore.callState === 'active' && recSupported"
              @click="toggleRecording()"
              class="call-btn"
              :class="isRecordingLocally ? 'call-btn-rec' : ''"
              :title="isRecordingLocally ? 'Stop recording (saves to your downloads)' : 'Record this call (everyone will be notified)'"
            >
              <svg v-if="!isRecordingLocally" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="8" stroke-width="2" />
                <circle cx="12" cy="12" r="3.5" fill="currentColor" stroke="none" />
              </svg>
              <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <rect x="7" y="7" width="10" height="10" rx="1.5" />
              </svg>
            </button>

            <!-- End call -->
            <button
              @click="callStore.endCall()"
              class="w-16 h-16 rounded-full bg-gradient-to-b from-red-400 to-red-500 hover:from-red-400 hover:to-red-600 text-white flex items-center justify-center shadow-lg shadow-red-500/30 hover:shadow-glow-danger transition-all duration-200 hover:scale-105 active:scale-95"
              title="End call"
            >
              <svg class="w-7 h-7 rotate-[135deg]" fill="currentColor" viewBox="0 0 24 24">
                <path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/>
              </svg>
            </button>

            <!-- Speaker (placeholder for future) -->
            <button class="call-btn opacity-40 cursor-not-allowed" title="Speaker (coming soon)">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { useCallStore } from '../../Stores/useCallStore';
import { useAuthStore } from '../../Stores/useAuthStore';
import { useUIStore } from '../../Stores/useUIStore';
import { useCallRecorder, downloadBlob, extensionForMimeType, formatBytes } from '../../Composables/useCallRecorder';
import CallParticipantTile from './CallParticipantTile.vue';

const callStore = useCallStore();
const authStore = useAuthStore();
const uiStore   = useUIStore();

const localVideoEl  = ref(null);
const remoteVideoEl = ref(null);

const visible = computed(() =>
  ['calling', 'connecting', 'active'].includes(callStore.callState),
);

// Two or more remote participants → tile grid; otherwise the original
// big-remote + PiP layout, so 1-on-1 calls look exactly as they did.
const isGroup = computed(() => callStore.remoteCount >= 2);

const gridClass = computed(() => {
  const n = callStore.remoteCount + 1; // + local
  if (n <= 2) return 'grid-cols-2';
  if (n <= 4) return 'grid-cols-2 grid-rows-2';
  return 'grid-cols-3 grid-rows-2';
});

const localParticipant = computed(() => ({
  id: authStore.user?.id,
  name: authStore.user?.name,
  display_name: authStore.user?.display_name,
  avatar_url: authStore.user?.avatar_url,
  status: 'connected',
  stream: callStore.screenStream ?? callStore.localStream,
}));

const remoteInitials = computed(() => {
  const n = callStore.remoteUser?.display_name || callStore.remoteUser?.name || '?';
  return n.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
});

const statusLabel = computed(() => {
  if (callStore.callState === 'calling')    return 'Calling…';
  if (callStore.callState === 'connecting') {
    return callStore.localStream
      ? 'Connecting…'
      : 'Waiting for microphone access — click Allow in the browser';
  }
  if (callStore.callState === 'active')     return callStore.callType === 'audio' ? 'Audio call in progress' : 'Connected';
  return '';
});

const formattedDuration = computed(() => {
  const s = callStore.duration;
  const m = Math.floor(s / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  return `${m}:${sec}`;
});

// The local PiP shows the shared screen while sharing, the camera otherwise.
const localPreviewStream = computed(() => callStore.screenStream ?? callStore.localStream);

// Attach streams to video elements as soon as they appear
watch(localPreviewStream, (stream) => {
  if (localVideoEl.value && stream) localVideoEl.value.srcObject = stream;
}, { immediate: true });

watch(() => callStore.remoteStream, (stream) => {
  if (remoteVideoEl.value && stream) remoteVideoEl.value.srcObject = stream;
}, { immediate: true });

watch(localVideoEl, (el) => {
  if (el && localPreviewStream.value) el.srcObject = localPreviewStream.value;
});

watch(remoteVideoEl, (el) => {
  if (el && callStore.remoteStream) el.srcObject = callStore.remoteStream;
});

// ── Recording (client-side: canvas + AudioContext → MediaRecorder → download) ──
const recorder = useCallRecorder();
// Destructured so the template sees unwrapped refs, not Ref objects.
const {
  isRecording: isRecordingLocally,
  durationSec: recDuration,
  bytesRecorded: recBytes,
  error: recError,
  isSupported: recSupported,
} = recorder;

const formattedRecDuration = computed(() => {
  const s = recDuration.value;
  const m = Math.floor(s / 60).toString().padStart(2, '0');
  const sec = (s % 60).toString().padStart(2, '0');
  return `${m}:${sec}`;
});

const recordingBannerText = computed(() => {
  const names = callStore.recordingParticipants.map(p => p.display_name || p.name);
  if (names.length === 0) return '';
  if (names.length === 1) return `${names[0]} is recording this call`;
  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]} are recording this call`;
});

// What the recorder composites: every connected remote tile, then me — the
// same tiles CallScreen shows, so the file matches what was on screen.
const recordingSources = computed(() => {
  const isVideo = callStore.callType === 'video';
  const remote = callStore.remoteParticipants
    .filter(p => p.status !== 'invited')
    .map(p => ({
      id: p.id,
      label: p.display_name || p.name,
      stream: isVideo ? p.stream : null,
      audioStream: p.stream,
      mirror: false,
    }));

  const camHidden = callStore.isCamOff && !callStore.isScreenSharing;
  const local = {
    id: 'local',
    label: `${authStore.user?.display_name || authStore.user?.name || 'You'} (you)`,
    stream: isVideo && !camHidden ? (callStore.screenStream ?? callStore.localStream) : null,
    audioStream: callStore.localStream, // mic, even while the tile shows the (silent) shared screen
    mirror: !callStore.isScreenSharing,
  };

  return [...remote, local];
});

watch(recordingSources, (sources) => {
  if (isRecordingLocally.value) recorder.updateSources(sources);
});

async function toggleRecording() {
  if (isRecordingLocally.value) return stopRecording();

  if (!recSupported) {
    uiStore.toastError('Call recording is not supported in this browser.');
    return;
  }

  // Consent notice first — if it cannot be delivered, do not record.
  const announced = await callStore.setRecording(true);
  if (!announced) return;

  try {
    await recorder.start(recordingSources.value, { video: callStore.callType === 'video' });
  } catch {
    await callStore.setRecording(false);
    uiStore.toastError('Could not start recording.');
    return;
  }

  uiStore.toastInfo('Recording started — everyone in the call has been notified.');
}

async function stopRecording() {
  const result = await recorder.stop();
  await callStore.setRecording(false);
  if (!result) return;

  if (result.bytes === 0) {
    uiStore.toastWarning('The recording was empty — nothing to save.');
    return;
  }

  const stamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
  downloadBlob(result.blob, `call-recording-${stamp}.${extensionForMimeType(result.mimeType)}`);
  uiStore.toastSuccess(`Recording saved (${formatBytes(result.bytes)}, ${formattedRecDuration.value}).`);
}

// The call ended under us (hang-up, dropped, everyone left): save what we have.
watch(visible, (isVisible) => {
  if (!isVisible && isRecordingLocally.value) stopRecording();
});

watch(recError, (msg) => {
  if (!msg) return;
  uiStore.toastError(msg);
  stopRecording();
});

// Closing the tab mid-recording would silently lose the file.
function warnBeforeUnload(e) {
  if (!isRecordingLocally.value) return;
  e.preventDefault();
  e.returnValue = '';
}
onMounted(() => window.addEventListener('beforeunload', warnBeforeUnload));
onUnmounted(() => {
  window.removeEventListener('beforeunload', warnBeforeUnload);
  recorder.cancel();
});
</script>

<style scoped>
.call-fade-enter-active, .call-fade-leave-active { transition: opacity .25s ease; }
.call-fade-enter-from,  .call-fade-leave-to      { opacity: 0; }

/* Base control button — glass circle matching the app's icon-btn language */
.call-btn {
  @apply w-14 h-14 rounded-full bg-white/[0.06] backdrop-blur-2xl border border-white/[0.10]
         text-dark-50 hover:text-white hover:bg-white/[0.12] hover:border-white/[0.18]
         flex items-center justify-center shadow-glass
         transition-all duration-200 hover:scale-105 active:scale-95;
}

/* Active state (muted / cam off) */
.call-btn-active {
  @apply bg-white/[0.18] text-white border-white/[0.24];
}

/* Recording in progress */
.call-btn-rec {
  @apply bg-red-500/80 text-white border-red-400/60 hover:bg-red-500 hover:border-red-300;
}

.rec-badge {
  @apply flex items-center gap-1.5 bg-red-500/20 backdrop-blur-xl border border-red-400/40 shadow-glass px-3 py-1.5 rounded-full;
}
</style>
