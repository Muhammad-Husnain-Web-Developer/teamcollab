<template>
  <button
    v-if="!isRecording"
    @click="handleStart"
    type="button"
    class="toolbar-btn"
    :class="isSupported ? '' : 'opacity-30 cursor-not-allowed'"
    :title="isSupported ? 'Record voice message' : 'Voice recording is not supported in this browser'"
  >
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 003-3V6a3 3 0 10-6 0v6a3 3 0 003 3z" />
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-14 0M12 18v3" />
    </svg>
  </button>

  <div v-else class="flex items-center gap-2 pl-2.5 pr-1.5 py-1 rounded-lg bg-red-500/10 border border-red-500/20">
    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse flex-shrink-0" />
    <span class="text-xs font-mono text-red-300 tabular-nums select-none">{{ formattedDuration }}</span>
    <button @click="handleCancel" type="button" class="p-1 rounded text-dark-100 hover:text-white hover:bg-white/[0.08] transition-colors" title="Discard recording">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
    <button @click="handleStop" type="button" class="p-1 rounded text-brand-400 hover:text-brand-300 hover:bg-white/[0.08] transition-colors" title="Stop and preview">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAudioRecorder, micErrorMessage } from '../../Composables/useAudioRecorder';
import { useUIStore } from '../../Stores/useUIStore';

const emit = defineEmits(['recorded']);

const uiStore = useUIStore();
const { isRecording, durationSec, isSupported, start, stop, cancel } = useAudioRecorder();

const formattedDuration = computed(() => {
  const s = durationSec.value;
  const m = Math.floor(s / 60);
  const sec = String(s % 60).padStart(2, '0');
  return `${m}:${sec}`;
});

async function handleStart() {
  if (!isSupported) {
    uiStore.toastError('Voice recording is not supported in this browser.');
    return;
  }
  try {
    await start();
  } catch (e) {
    uiStore.toastError(micErrorMessage(e));
  }
}

async function handleStop() {
  const result = await stop();
  if (result && result.durationSec > 0) {
    emit('recorded', result);
  }
}

function handleCancel() {
  cancel();
}
</script>
