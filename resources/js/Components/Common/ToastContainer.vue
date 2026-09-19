<template>
  <Teleport to="body">
    <div
      class="fixed bottom-6 right-6 z-[300] flex flex-col gap-3 pointer-events-none"
      aria-live="polite"
      aria-atomic="false"
    >
      <TransitionGroup name="toast-list" tag="div" class="flex flex-col gap-3">
        <Toast
          v-for="toast in uiStore.toasts"
          :key="toast.id"
          :id="toast.id"
          :message="toast.message"
          :type="toast.type"
          :duration="toast.duration"
          @close="uiStore.removeToast(toast.id)"
        />
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useUIStore } from '../../Stores/useUIStore';
import Toast from './Toast.vue';

const uiStore = useUIStore();
</script>

<style scoped>
.toast-list-enter-active {
  transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-list-leave-active {
  transition: all 0.25s ease;
}
.toast-list-enter-from {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
.toast-list-leave-to {
  opacity: 0;
  transform: translateX(40px) scale(0.95);
}
.toast-list-move {
  transition: transform 0.3s ease;
}
</style>
