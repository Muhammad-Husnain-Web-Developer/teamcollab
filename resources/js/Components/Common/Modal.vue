<template>
  <Teleport to="body">
    <transition name="modal-backdrop">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
      >
        <!-- Overlay -->
        <div
          class="absolute inset-0 bg-dark-900/70 backdrop-blur-md"
          @click="emit('close')"
        />

        <!-- Dialog -->
        <transition name="modal-dialog" appear>
          <div
            v-if="show"
            class="glass-elevated relative z-10 w-full overflow-hidden"
            :class="sizeClasses"
            @keydown.escape="emit('close')"
          >
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-white/[0.07] bg-gradient-to-b from-white/[0.03] to-transparent">
              <h3 class="text-base font-semibold text-white tracking-tight">{{ title }}</h3>
              <button
                @click="emit('close')"
                class="icon-btn"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="max-h-[80vh] overflow-y-auto">
              <slot />
            </div>

            <!-- Footer slot -->
            <slot name="footer" />
          </div>
        </transition>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { computed, watch, onUnmounted } from 'vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  size: {
    type: String,
    default: 'md',
    validator: v => ['sm', 'md', 'lg', 'xl', 'full'].includes(v),
  },
});

const emit = defineEmits(['close']);

const sizeClasses = computed(() => {
  const map = {
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
    full: 'max-w-full mx-4',
  };
  return map[props.size] ?? map.md;
});

// Lock body scroll when modal is open
watch(
  () => props.show,
  open => {
    document.body.style.overflow = open ? 'hidden' : '';
  },
);

// Keyboard escape
function handleEscape(e) {
  if (e.key === 'Escape' && props.show) {
    emit('close');
  }
}

document.addEventListener('keydown', handleEscape);
onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape);
  document.body.style.overflow = '';
});
</script>

<style scoped>
.modal-backdrop-enter-active,
.modal-backdrop-leave-active {
  transition: opacity 0.2s ease;
}
.modal-backdrop-enter-from,
.modal-backdrop-leave-to {
  opacity: 0;
}

.modal-dialog-enter-active {
  transition: opacity 0.25s ease, transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-dialog-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.modal-dialog-enter-from {
  opacity: 0;
  transform: scale(0.92) translateY(-8px);
}
.modal-dialog-leave-to {
  opacity: 0;
  transform: scale(0.95) translateY(-4px);
}
</style>
