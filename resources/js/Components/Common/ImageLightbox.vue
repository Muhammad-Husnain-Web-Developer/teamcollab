<template>
  <Teleport to="body">
    <Transition name="lightbox">
      <div
        v-if="file"
        class="fixed inset-0 z-[300] flex flex-col bg-black/90 backdrop-blur-sm"
        @click.self="$emit('close')"
        @keydown.esc="$emit('close')"
      >
        <!-- Top bar -->
        <div class="flex items-center justify-between px-5 py-3.5 flex-shrink-0">
          <div class="min-w-0">
            <p class="text-white text-sm font-semibold truncate">{{ file.original_name }}</p>
            <p class="text-white/50 text-xs">{{ file.size_human }}</p>
          </div>
          <div class="flex items-center gap-2">
            <a
              :href="file.download_url"
              class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-medium transition-all"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              Download
            </a>
            <button
              @click="$emit('close')"
              class="w-9 h-9 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white transition-all"
              title="Close (Esc)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Image -->
        <div class="flex-1 min-h-0 flex items-center justify-center p-6" @click.self="$emit('close')">
          <img
            :src="file.view_url"
            :alt="file.original_name"
            class="max-w-full max-h-full object-contain rounded-lg shadow-2xl select-none"
            draggable="false"
          />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue';

defineProps({
  // { original_name, size_human, view_url, download_url }
  file: { type: Object, default: null },
});

const emit = defineEmits(['close']);

function onKeydown(e) {
  if (e.key === 'Escape') emit('close');
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<style scoped>
.lightbox-enter-active, .lightbox-leave-active { transition: opacity 0.2s ease; }
.lightbox-enter-from,  .lightbox-leave-to      { opacity: 0; }
.lightbox-enter-active img { animation: zoomIn 0.25s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes zoomIn {
  from { transform: scale(0.92); opacity: 0; }
  to   { transform: scale(1);    opacity: 1; }
}
</style>
