<template>
  <Teleport to="body">
    <Transition name="toast-slide">
      <div
        v-if="callStore.callState === 'incoming'"
        class="glass-elevated fixed top-5 right-5 z-[300] w-80 overflow-hidden"
      >
        <!-- Animated top bar -->
        <div class="h-0.5 bg-gradient-to-r from-brand-500 via-aurora-violet to-brand-500 animate-gradient-x" />

        <div class="p-4">
          <!-- Header -->
          <div class="flex items-center gap-1.5 mb-3">
            <div class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.7)] animate-pulse" />
            <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">
              Incoming {{ callStore.callType === 'video' ? 'Video' : 'Audio' }} Call
            </span>
          </div>

          <!-- Caller info -->
          <div class="flex items-center gap-3 mb-5">
            <!-- Pulsing avatar ring -->
            <div class="relative flex-shrink-0">
              <div class="absolute inset-0 rounded-full bg-brand-500/20 animate-ping" />
              <div class="relative w-12 h-12 rounded-full overflow-hidden bg-gradient-to-br from-brand-500 to-aurora-violet ring-2 ring-brand-500/40">
                <img
                  v-if="callStore.caller?.avatar_url"
                  :src="callStore.caller.avatar_url"
                  class="w-full h-full object-cover"
                  alt=""
                />
                <span v-else class="flex items-center justify-center w-full h-full text-lg font-bold text-white">
                  {{ initials }}
                </span>
              </div>
            </div>

            <div class="min-w-0">
              <p class="text-white font-bold text-sm truncate">
                {{ callStore.caller?.display_name || callStore.caller?.name }}
              </p>
              <p class="text-dark-50/70 text-xs">
                {{ callStore.callType === 'video' ? 'Video call' : 'Audio call' }}
                <template v-if="othersCount > 0"> · {{ othersCount }} other{{ othersCount === 1 ? '' : 's' }} invited</template>
              </p>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="flex items-center justify-center gap-10">
            <!-- Reject -->
            <div class="flex flex-col items-center gap-1.5">
              <button
                @click="callStore.rejectCall()"
                class="w-[52px] h-[52px] rounded-full bg-gradient-to-b from-red-400 to-red-500 hover:from-red-400 hover:to-red-600 text-white flex items-center justify-center shadow-lg shadow-red-500/30 hover:shadow-glow-danger transition-all duration-200 hover:scale-105 active:scale-95"
                title="Decline"
              >
                <svg class="w-5 h-5 rotate-[135deg]" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/>
                </svg>
              </button>
              <span class="text-[11px] font-medium text-dark-50/60">Decline</span>
            </div>

            <!-- Accept -->
            <div class="flex flex-col items-center gap-1.5">
              <button
                @click="callStore.acceptCall()"
                class="w-[52px] h-[52px] rounded-full bg-gradient-to-b from-emerald-400 to-emerald-500 hover:from-emerald-400 hover:to-emerald-600 text-white flex items-center justify-center shadow-lg shadow-emerald-500/30 hover:shadow-glow-success transition-all duration-200 hover:scale-105 active:scale-95"
                title="Accept"
              >
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/>
                </svg>
              </button>
              <span class="text-[11px] font-medium text-dark-50/60">Accept</span>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import { useCallStore } from '../../Stores/useCallStore';

const callStore = useCallStore();

const initials = computed(() => {
  const n = callStore.caller?.display_name || callStore.caller?.name || '?';
  return n.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
});

// Everyone on the roster besides the caller and me.
const othersCount = computed(() =>
  callStore.remoteParticipants.filter(p => p.id !== callStore.caller?.id).length,
);
</script>

<style scoped>
.toast-slide-enter-active { animation: slideIn .3s cubic-bezier(.16,1,.3,1); }
.toast-slide-leave-active { animation: slideOut .25s ease-in forwards; }

@keyframes slideIn {
  from { opacity: 0; transform: translateX(100%) scale(.95); }
  to   { opacity: 1; transform: translateX(0) scale(1); }
}
@keyframes slideOut {
  from { opacity: 1; transform: translateX(0); }
  to   { opacity: 0; transform: translateX(100%); }
}

@keyframes gradient-x {
  0%, 100% { background-position: 0% 50%; }
  50%       { background-position: 100% 50%; }
}
.animate-gradient-x {
  background-size: 200% 200%;
  animation: gradient-x 2s ease infinite;
}
</style>
