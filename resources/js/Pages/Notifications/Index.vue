<template>
  <AppLayout>
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-2xl mx-auto py-10 px-6">

        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
          <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Notifications</h1>
            <p class="text-sm text-dark-50/70 mt-1">
              {{ unreadTotal > 0 ? `${unreadTotal} unread` : 'All caught up' }}
            </p>
          </div>
          <button
            v-if="unreadTotal > 0"
            @click="markAllRead"
            class="text-xs font-medium text-brand-400 hover:text-brand-300 bg-brand-500/10 hover:bg-brand-500/15 border border-brand-500/25 px-4 py-2 rounded-xl transition-all"
          >
            Mark all as read
          </button>
        </div>

        <!-- Empty state -->
        <div v-if="items.length === 0" class="empty-state">
          <div class="empty-state-icon">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
          </div>
          <p class="text-white font-medium">No notifications</p>
          <p class="text-dark-50/50 text-sm mt-1">Mentions, messages and missed calls will appear here</p>
        </div>

        <!-- Notification list -->
        <div v-else class="space-y-2">
          <div
            v-for="n in items"
            :key="n.id"
            class="group flex items-start gap-3 p-4 rounded-2xl border backdrop-blur-xl transition-all cursor-pointer"
            :class="!n.read_at
              ? 'bg-brand-500/[0.06] border-brand-500/25 hover:border-brand-500/40'
              : 'bg-white/[0.03] border-white/[0.07] hover:border-white/[0.14]'"
            @click="onClick(n)"
          >
            <!-- Icon -->
            <div class="flex-shrink-0 w-9 h-9 rounded-full flex items-center justify-center" :class="iconBg(n.type)">
              <svg v-if="n.type === 'mention'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
              </svg>
              <svg v-else-if="n.type === 'dm_message'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
              </svg>
              <svg v-else-if="n.type === 'missed_call'" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M6.5 5.5L12 11l7-7-1-1-6 6-4.5-4.5H11V3H5v6h1.5V5.5zm17.21 11.17C20.66 13.78 16.54 12 12 12S3.34 13.78.29 16.67c-.18.18-.29.43-.29.71s.11.53.29.71l2.48 2.48c.18.18.43.29.71.29.27 0 .52-.11.7-.28.79-.74 1.69-1.36 2.66-1.85.33-.16.56-.5.56-.9v-3.1c1.45-.48 3-.73 4.6-.73s3.15.25 4.6.72v3.1c0 .39.23.74.56.9.98.49 1.87 1.12 2.67 1.85.18.18.43.28.7.28.28 0 .53-.11.71-.29l2.48-2.48c.18-.18.29-.43.29-.71s-.12-.52-.3-.7z"/>
              </svg>
              <span v-else class="text-sm font-bold leading-none">#</span>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0">
              <p class="text-sm text-dark-50">
                <span class="font-semibold text-white">{{ n.data?.sender_name ?? 'Someone' }}</span>
                {{ actionText(n) }}
              </p>
              <p v-if="n.data?.preview" class="text-sm text-dark-50/60 truncate mt-0.5">{{ n.data.preview }}</p>
              <p class="text-xs text-dark-50/50 mt-1.5">{{ formatDate(n.created_at) }}</p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-1 flex-shrink-0">
              <span v-if="!n.read_at" class="w-2 h-2 rounded-full bg-brand-400 mr-1" />
              <button
                class="opacity-0 group-hover:opacity-100 p-1.5 rounded-lg text-dark-50/50 hover:text-red-400 hover:bg-red-500/10 transition-all"
                title="Delete"
                @click.stop="remove(n)"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="notifications.last_page > 1" class="flex items-center justify-center gap-2 pt-6">
            <button
              v-for="link in notifications.links"
              :key="link.label"
              :disabled="!link.url"
              class="px-3 py-1.5 text-xs rounded-lg transition-all"
              :class="link.active
                ? 'bg-gradient-to-b from-brand-400 to-brand-600 text-white font-bold shadow-glow-brand'
                : link.url
                  ? 'text-dark-50/70 hover:text-white hover:bg-white/[0.06]'
                  : 'text-dark-50/30 cursor-not-allowed'"
              v-html="link.label"
              @click="link.url && router.visit(link.url, { preserveScroll: true })"
            />
          </div>
        </div>

      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useNotificationStore } from '../../Stores/useNotificationStore';
import { notificationAction, notificationRoute } from '../../Utils/notifications';

const props = defineProps({
  notifications: { type: Object, required: true },
  unread_count:  { type: Number, default: 0 },
});

const store = useNotificationStore();

// Local reactive copy of the current page's items
const items       = ref([...(props.notifications.data ?? [])]);
const unreadTotal = ref(props.unread_count);

const notifications = computed(() => props.notifications);

function onClick(n) {
  if (!n.read_at) {
    n.read_at = new Date().toISOString();
    unreadTotal.value = Math.max(0, unreadTotal.value - 1);
    axios.post(`/notifications/${n.id}/read`).catch(() => {});
    store.markRead(n.id);
  }

  const route = notificationRoute(n);
  if (route) router.visit(route);
}

async function markAllRead() {
  const now = new Date().toISOString();
  items.value.forEach(n => { if (!n.read_at) n.read_at = now; });
  unreadTotal.value = 0;
  store.markAllRead();
}

async function remove(n) {
  items.value = items.value.filter(i => i.id !== n.id);
  if (!n.read_at) unreadTotal.value = Math.max(0, unreadTotal.value - 1);
  store.removeNotification(n.id);
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

function formatDate(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  const seconds = Math.floor((Date.now() - d.getTime()) / 1000);
  if (seconds < 60)    return 'Just now';
  if (seconds < 3600)  return `${Math.floor(seconds / 60)} minutes ago`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
