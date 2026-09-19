<template>
  <div
    ref="toastEl"
    class="flex items-start gap-3 px-4 py-3.5 rounded-xl border backdrop-blur-2xl shadow-glass-lg max-w-sm pointer-events-auto"
    :class="containerClass"
  >
    <!-- Icon -->
    <div class="flex-shrink-0 mt-0.5">
      <svg v-if="type === 'success'" class="w-4.5 h-4.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <svg v-else-if="type === 'error'" class="w-4.5 h-4.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <svg v-else-if="type === 'warning'" class="w-4.5 h-4.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z" />
      </svg>
      <svg v-else class="w-4.5 h-4.5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
    </div>

    <!-- Message -->
    <p class="flex-1 text-sm font-medium" :class="textClass">{{ message }}</p>

    <!-- Close button -->
    <button
      @click="emit('close')"
      class="flex-shrink-0 -mt-0.5 -mr-1 p-1 rounded-lg hover:bg-white/10 text-current opacity-60 hover:opacity-100 transition-opacity"
    >
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { gsap } from 'gsap';

const props = defineProps({
  id: { type: Number, required: true },
  message: { type: String, required: true },
  type: {
    type: String,
    default: 'info',
    validator: v => ['success', 'error', 'warning', 'info'].includes(v),
  },
  duration: { type: Number, default: 4000 },
});

const emit = defineEmits(['close']);
const toastEl = ref(null);

const containerClass = computed(() => {
  const map = {
    success: 'bg-dark-750/90 border-emerald-500/25',
    error: 'bg-dark-750/90 border-red-500/25',
    warning: 'bg-dark-750/90 border-amber-500/25',
    info: 'bg-dark-750/90 border-brand-500/25',
  };
  return map[props.type] ?? map.info;
});

const textClass = computed(() => {
  const map = {
    success: 'text-dark-50',
    error: 'text-dark-50',
    warning: 'text-dark-50',
    info: 'text-dark-50',
  };
  return map[props.type] ?? 'text-white';
});

onMounted(() => {
  if (toastEl.value) {
    gsap.fromTo(
      toastEl.value,
      { opacity: 0, x: 40, scale: 0.95 },
      { opacity: 1, x: 0, scale: 1, duration: 0.35, ease: 'back.out(1.2)' },
    );
  }
});
</script>
