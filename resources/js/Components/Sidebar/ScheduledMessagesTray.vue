<template>
  <div ref="rootRef" class="relative px-2 py-1">
    <!-- Trigger row -->
    <button
      class="flex items-center gap-2 w-full px-2 py-1.5 rounded-lg text-dark-50/80 hover:text-white hover:bg-white/[0.06] transition-all duration-150"
      :class="open ? 'bg-white/[0.06] text-white' : ''"
      @click="toggle"
    >
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span class="text-sm font-medium flex-1 text-left">Scheduled</span>
      <span v-if="store.count > 0" class="badge flex-shrink-0 bg-white/[0.1] text-dark-50">
        {{ store.count }}
      </span>
    </button>

    <!-- Flyout panel -->
    <Transition name="flyout">
      <div
        v-if="open"
        class="glass-elevated absolute left-full top-0 ml-2 w-80 z-[100] overflow-hidden"
      >
        <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.07] bg-gradient-to-b from-white/[0.03] to-transparent">
          <h3 class="text-sm font-bold text-white">Scheduled Messages</h3>
        </div>

        <div class="max-h-96 overflow-y-auto scrollbar-thin">
          <div v-if="store.loading" class="flex items-center justify-center py-10">
            <svg class="animate-spin w-5 h-5 text-dark-50/60" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
          </div>

          <div v-else-if="store.items.length === 0" class="flex flex-col items-center gap-2 py-10">
            <svg class="w-8 h-8 text-dark-50/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <p class="text-xs text-dark-50/60">Nothing scheduled</p>
          </div>

          <div
            v-for="item in store.items"
            :key="item.id"
            class="flex items-start gap-3 w-full px-4 py-3 text-left hover:bg-white/[0.05] transition-colors border-b border-white/[0.05] last:border-0"
          >
            <div class="flex-1 min-w-0">
              <p class="text-xs text-dark-50 leading-snug line-clamp-2">
                {{ item.body || (item.type === 'voice' ? '🎤 Voice message' : item.type === 'gif' ? '🖼️ GIF' : '📎 Attachment') }}
              </p>
              <p class="text-[10px] text-dark-50/50 mt-1">
                to {{ item.channel_name ? '#' + item.channel_name : 'DM' }} · {{ formatWhen(item.scheduled_for) }}
              </p>
            </div>
            <button
              @click="cancelItem(item.id)"
              class="flex-shrink-0 p-1 rounded text-dark-100 hover:text-red-400 hover:bg-white/[0.06] transition-colors"
              title="Cancel"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useScheduledMessageStore } from '../../Stores/useScheduledMessageStore';
import { useUIStore } from '../../Stores/useUIStore';

const store = useScheduledMessageStore();
const uiStore = useUIStore();

const open = ref(false);
const rootRef = ref(null);

function toggle() {
  open.value = !open.value;
  if (open.value) {
    store.fetchPending();
  }
}

async function cancelItem(id) {
  try {
    await store.cancel(id);
    uiStore.toastSuccess('Scheduled message cancelled.');
  } catch {
    uiStore.toastError('Failed to cancel scheduled message.');
  }
}

function formatWhen(iso) {
  if (!iso) return '';
  const date = new Date(iso);
  const now = new Date();
  const sameDay = date.toDateString() === now.toDateString();
  const time = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
  if (sameDay) return `today ${time}`;
  return `${date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} ${time}`;
}

function onClickOutside(e) {
  if (rootRef.value && !rootRef.value.contains(e.target)) {
    open.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside, true));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside, true));
</script>

<style scoped>
.flyout-enter-active,
.flyout-leave-active { transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1); }
.flyout-enter-from,
.flyout-leave-to     { opacity: 0; transform: translateX(-6px) scale(0.98); }

.scrollbar-thin::-webkit-scrollbar { width: 5px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
.scrollbar-thin::-webkit-scrollbar-thumb { background: #2a2a2e; border-radius: 3px; }
</style>
