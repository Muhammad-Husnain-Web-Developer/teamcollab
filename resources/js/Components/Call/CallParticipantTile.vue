<template>
  <div class="relative rounded-2xl overflow-hidden bg-dark-800 ring-1 ring-white/[0.08] shadow-glass-lg min-h-0">
    <!-- Video (when there is a stream and this is a video call) -->
    <video
      v-if="showVideo"
      ref="videoEl"
      autoplay
      playsinline
      :muted="muted"
      class="w-full h-full object-cover"
      :class="mirror ? 'scale-x-[-1]' : ''"
    />

    <!-- Avatar fallback (audio call, no stream yet, or camera off) -->
    <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-2">
      <div class="relative">
        <div
          v-if="participant.status === 'connecting'"
          class="absolute inset-0 rounded-full bg-brand-500/20 animate-ping scale-125"
        />
        <div class="relative w-16 h-16 rounded-full overflow-hidden bg-gradient-to-br from-brand-500 to-aurora-violet ring-2 ring-white/10">
          <img v-if="participant.avatar_url" :src="participant.avatar_url" class="w-full h-full object-cover" alt="" />
          <span v-else class="flex items-center justify-center w-full h-full text-xl font-bold text-white">
            {{ initials }}
          </span>
        </div>
      </div>
    </div>

    <!-- Name badge -->
    <div class="absolute bottom-2 left-2 flex items-center gap-1.5 max-w-[calc(100%-1rem)] px-2 py-1 rounded-lg bg-black/50 backdrop-blur-md">
      <span
        class="w-1.5 h-1.5 rounded-full flex-shrink-0"
        :class="{
          'bg-emerald-400': participant.status === 'connected' || isLocal,
          'bg-amber-400 animate-pulse': participant.status === 'connecting',
          'bg-dark-400': participant.status === 'invited',
        }"
      />
      <span class="text-white text-xs font-medium truncate">
        {{ isLocal ? 'You' : (participant.display_name || participant.name) }}
      </span>
      <span v-if="participant.status === 'invited'" class="text-[10px] text-dark-50/60">ringing…</span>
      <svg v-if="isLocal && muted" class="w-3 h-3 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
        <path d="M19 11h-1.7c0 .74-.16 1.43-.43 2.05l1.23 1.23c.56-.98.9-2.09.9-3.28zm-4.02.17c0-.06.02-.11.02-.17V6c0-1.66-1.34-3-3-3S9 4.34 9 6v.18l5.98 5.99zM4.27 3L3 4.27l6.01 6.01V12c0 1.66 1.33 3 2.99 3 .22 0 .44-.03.65-.08l1.66 1.66c-.71.33-1.5.52-2.31.52-2.76 0-5-2.24-5-5H5c0 3.53 2.61 6.43 6 6.92V21h2v-2.08c.57-.08 1.12-.24 1.64-.46L19.73 21 21 19.73 4.27 3z"/>
      </svg>
    </div>

    <div
      v-if="badge"
      class="absolute top-2 left-2 px-1.5 py-0.5 rounded bg-brand-500/90 text-white text-[10px] font-semibold"
    >
      {{ badge }}
    </div>

    <!-- This participant is recording the call (consent notice) -->
    <div
      v-if="recording"
      class="absolute top-2 right-2 flex items-center gap-1 px-1.5 py-0.5 rounded bg-red-500/90 text-white text-[10px] font-semibold"
      title="Recording this call"
    >
      <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse" />
      REC
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  participant: { type: Object, required: true }, // {id, name, display_name, avatar_url, status, stream}
  isVideoCall: { type: Boolean, default: false },
  isLocal:     { type: Boolean, default: false },
  muted:       { type: Boolean, default: false }, // local tile is always muted to avoid echo
  mirror:      { type: Boolean, default: false },
  hideVideo:   { type: Boolean, default: false }, // local camera off
  badge:       { type: String, default: '' },
  recording:   { type: Boolean, default: false }, // shows a red REC pill
});

const videoEl = ref(null);

const showVideo = computed(() =>
  props.isVideoCall && Boolean(props.participant.stream) && !props.hideVideo,
);

const initials = computed(() => {
  const n = props.participant.display_name || props.participant.name || '?';
  return n.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
});

function attach() {
  if (videoEl.value && props.participant.stream) {
    videoEl.value.srcObject = props.participant.stream;
  }
}

watch(() => props.participant.stream, attach, { immediate: true });
watch(videoEl, attach);
</script>
