<template>
  <div ref="rootRef" class="relative px-2 py-1">
    <!-- Bell row -->
    <button
      class="flex items-center gap-2 w-full px-2 py-1.5 rounded-lg text-dark-50/80 hover:text-white hover:bg-white/[0.06] transition-all duration-150"
      :class="open ? 'bg-white/[0.06] text-white' : ''"
      @click="toggle"
    >
      <div class="relative flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <!-- Unread dot on bell -->
        <span
          v-if="store.unreadCount > 0"
          class="absolute -top-1 -right-1 w-2 h-2 rounded-full bg-red-400 ring-2 ring-dark-750"
        />
      </div>
      <span class="text-sm font-medium flex-1 text-left">Notifications</span>
      <!-- Unread count badge -->
      <span
        v-if="store.unreadCount > 0"
        class="badge flex-shrink-0 bg-red-500/90 text-white"
      >
        {{ store.unreadCount > 99 ? '99+' : store.unreadCount }}
      </span>
    </button>

    <!-- Flyout panel -->
    <Transition name="flyout">
      <div
        v-if="open"
        class="glass-elevated absolute left-full top-0 ml-2 w-80 z-[100] overflow-hidden"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-white/[0.07] bg-gradient-to-b from-white/[0.03] to-transparent">
          <h3 class="text-sm font-bold text-white">Notifications</h3>
          <button
            v-if="store.unreadCount > 0"
            @click="store.markAllRead()"
            class="text-[11px] text-brand-400 hover:text-brand-300 font-medium transition-colors"
          >
            Mark all read
          </button>
        </div>

        <!-- List -->
        <div class="max-h-96 overflow-y-auto scrollbar-thin">
          <!-- Loading -->
          <div v-if="store.loading" class="flex items-center justify-center py-10">
            <svg class="animate-spin w-5 h-5 text-dark-50/60" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
          </div>

          <!-- Empty -->
          <div v-else-if="store.notifications.length === 0" class="flex flex-col items-center gap-2 py-10">
            <svg class="w-8 h-8 text-dark-50/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <p class="text-xs text-dark-50/60">No notifications yet</p>
          </div>

          <!-- Items -->
          <button
            v-for="n in store.notifications"
            :key="n.id"
            class="flex items-start gap-3 w-full px-4 py-3 text-left hover:bg-white/[0.05] transition-colors border-b border-white/[0.05] last:border-0"
            :class="!n.read_at ? 'bg-brand-500/[0.06]' : ''"
            @click="onNotificationClick(n)"
          >
            <!-- Type icon -->
            <div
              class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center mt-0.5"
              :class="iconBg(n.type)"
            >
              <!-- mention -->
              <svg v-if="n.type === 'mention'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
              </svg>
              <!-- dm -->
              <svg v-else-if="n.type === 'dm_message'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
              </svg>
              <!-- missed call -->
              <svg v-else-if="n.type === 'missed_call'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M6.5 5.5L12 11l7-7-1-1-6 6-4.5-4.5H11V3H5v6h1.5V5.5zm17.21 11.17C20.66 13.78 16.54 12 12 12S3.34 13.78.29 16.67c-.18.18-.29.43-.29.71s.11.53.29.71l2.48 2.48c.18.18.43.29.71.29.27 0 .52-.11.7-.28.79-.74 1.69-1.36 2.66-1.85.33-.16.56-.5.56-.9v-3.1c1.45-.48 3-.73 4.6-.73s3.15.25 4.6.72v3.1c0 .39.23.74.56.9.98.49 1.87 1.12 2.67 1.85.18.18.43.28.7.28.28 0 .53-.11.71-.29l2.48-2.48c.18-.18.29-.43.29-.71s-.12-.52-.3-.7z"/>
              </svg>
              <!-- channel message -->
              <span v-else class="text-sm font-bold leading-none">#</span>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <p class="text-xs text-dark-50 leading-snug">
                <span class="font-semibold text-white">{{ n.data?.sender_name ?? 'Someone' }}</span>
                {{ actionText(n) }}
              </p>
              <p v-if="n.data?.preview" class="text-xs text-dark-50/60 truncate mt-0.5">
                {{ n.data.preview }}
              </p>
              <p class="text-[10px] text-dark-50/50 mt-1">{{ timeAgo(n.created_at) }}</p>
            </div>

            <!-- Unread dot -->
            <span v-if="!n.read_at" class="flex-shrink-0 w-2 h-2 rounded-full bg-brand-400 mt-2" />
          </button>
        </div>

        <!-- Footer -->
        <div class="border-t border-white/[0.07]">
          <button
            @click="viewAll"
            class="w-full py-2.5 text-xs font-medium text-dark-50/70 hover:text-white hover:bg-white/[0.05] transition-colors"
          >
            View all notifications
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import { useNotificationStore } from '../../Stores/useNotificationStore';
import { notificationAction, notificationRoute } from '../../Utils/notifications';

const store = useNotificationStore();

const open    = ref(false);
const rootRef = ref(null);

function toggle() {
  open.value = !open.value;
  if (open.value && !store.loaded) {
    store.fetchNotifications();
  }
}

function onNotificationClick(n) {
  store.markRead(n.id);
  open.value = false;

  const route = notificationRoute(n);
  if (route) router.visit(route);
}

function viewAll() {
  open.value = false;
  router.visit('/notifications');
}

const actionText = notificationAction;

function iconBg(type) {
  switch (type) {
    case 'mention':     return 'bg-amber-500/15 text-amber-400';
    case 'dm_message':  return 'bg-brand-500/15 text-brand-400';
    case 'missed_call': return 'bg-red-500/15 text-red-400';
    default:            return 'bg-white/[0.06] text-dark-50';
  }
}

function timeAgo(iso) {
  if (!iso) return '';
  const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (seconds < 60)    return 'just now';
  if (seconds < 3600)  return `${Math.floor(seconds / 60)}m ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
  return `${Math.floor(seconds / 86400)}d ago`;
}

// Close on click outside
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
