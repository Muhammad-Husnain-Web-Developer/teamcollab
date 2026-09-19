<template>
  <div
    class="flex items-center gap-2.5 rounded-xl px-3 py-2.5 max-w-[260px] border transition-all"
    :class="isDm && isOwnMessage
      ? 'bg-gradient-to-b from-brand-400 to-brand-600 border-white/10 shadow-lg shadow-brand-500/20'
      : 'bg-white/[0.05] border-white/[0.07] hover:bg-white/[0.07]'"
  >
    <button
      @click="togglePlay"
      type="button"
      class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center transition-colors"
      :class="isDm && isOwnMessage ? 'bg-white/20 text-white hover:bg-white/30' : 'bg-white/[0.08] text-dark-50 hover:bg-white/[0.14]'"
      :title="isPlaying ? 'Pause' : 'Play'"
    >
      <svg v-if="!isPlaying" class="w-3.5 h-3.5 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg>
      <svg v-else class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M6 5h4v14H6zM14 5h4v14h-4z" /></svg>
    </button>

    <div class="flex-1 min-w-0 flex items-center gap-2">
      <input
        type="range"
        min="0"
        :max="duration || 0"
        step="0.1"
        :value="currentTime"
        @input="seek"
        class="voice-scrubber flex-1"
        :class="isDm && isOwnMessage ? 'voice-scrubber--light' : ''"
      />
      <span
        class="text-[10px] tabular-nums flex-shrink-0 select-none"
        :class="isDm && isOwnMessage ? 'text-white/70' : 'text-dark-50/60'"
      >
        {{ formattedTime }}
      </span>
    </div>

    <audio
      ref="audioEl"
      :src="src"
      preload="metadata"
      @loadedmetadata="onLoadedMetadata"
      @timeupdate="onTimeUpdate"
      @ended="onEnded"
      class="hidden"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  src: { type: String, required: true },
  initialDuration: { type: Number, default: 0 },
  isOwnMessage: { type: Boolean, default: false },
  isDm: { type: Boolean, default: false },
});

const audioEl = ref(null);
const isPlaying = ref(false);
const currentTime = ref(0);
const duration = ref(props.initialDuration ?? 0);

const formattedTime = computed(() => {
  const seconds = isPlaying.value || currentTime.value > 0
    ? Math.max(0, duration.value - currentTime.value)
    : duration.value;
  const m = Math.floor(seconds / 60);
  const s = String(Math.floor(seconds % 60)).padStart(2, '0');
  return `${m}:${s}`;
});

function togglePlay() {
  if (!audioEl.value) return;
  if (isPlaying.value) {
    audioEl.value.pause();
  } else {
    audioEl.value.play().catch(() => {});
  }
  isPlaying.value = !audioEl.value.paused;
}

function onLoadedMetadata() {
  // A finite duration overrides the metadata.voice_duration_seconds fallback
  // once the browser has actually parsed the audio headers.
  if (Number.isFinite(audioEl.value?.duration) && audioEl.value.duration > 0) {
    duration.value = audioEl.value.duration;
  }
}

function onTimeUpdate() {
  currentTime.value = audioEl.value?.currentTime ?? 0;
}

function onEnded() {
  isPlaying.value = false;
  currentTime.value = 0;
}

function seek(e) {
  const value = Number(e.target.value);
  if (audioEl.value) audioEl.value.currentTime = value;
  currentTime.value = value;
}
</script>

<style scoped>
.voice-scrubber {
  -webkit-appearance: none;
  appearance: none;
  height: 3px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.12);
  outline: none;
}
.voice-scrubber::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: currentColor;
  color: #8b5cf6;
  cursor: pointer;
}
.voice-scrubber--light::-webkit-slider-thumb {
  color: #ffffff;
}
.voice-scrubber::-moz-range-thumb {
  width: 10px;
  height: 10px;
  border: none;
  border-radius: 999px;
  background: currentColor;
  color: #8b5cf6;
  cursor: pointer;
}
.voice-scrubber--light::-moz-range-thumb {
  color: #ffffff;
}
</style>
