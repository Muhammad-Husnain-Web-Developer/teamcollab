<template>
  <div class="px-4 pb-2">
    <!-- Skeleton loading -->
    <div v-if="loading" class="space-y-4 py-4">
      <SkeletonLoader v-for="i in 6" :key="i" type="message" />
    </div>

    <!-- Messages grouped by date -->
    <template v-else>
      <div v-if="groupedMessages.length === 0" class="empty-state !py-20">
        <div class="empty-state-icon !w-16 !h-16 !rounded-2xl bg-brand-500/10 !border-brand-500/20">
          <svg class="w-8 h-8 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
        </div>
        <p class="text-white font-semibold mb-1">No messages yet</p>
        <p class="text-dark-50/60 text-sm">Be the first to say something!</p>
      </div>

      <div
        v-for="group in groupedMessages"
        :key="group.date"
      >
        <!-- Date divider -->
        <div class="date-divider">
          <span class="text-xs text-dark-50/70 font-medium px-2.5 py-1 rounded-full bg-white/[0.05] border border-white/[0.07] flex-shrink-0">
            {{ group.dateLabel }}
          </span>
        </div>

        <!-- Messages in group -->
        <div class="space-y-0.5">
          <MessageItem
            v-for="(message, idx) in group.messages"
            :key="message.id"
            :message="message"
            :show-header="shouldShowHeader(message, group.messages[idx - 1])"
            :is-dm="isDm"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import MessageItem from './MessageItem.vue';
import SkeletonLoader from '../Common/SkeletonLoader.vue';

const props = defineProps({
  messages: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  isDm: { type: Boolean, default: false },
});

// Group messages by date
const groupedMessages = computed(() => {
  const groups = [];
  const groupMap = new Map();

  props.messages.forEach(msg => {
    if (!msg.created_at) return;
    const date = new Date(msg.created_at);
    const key = date.toDateString();

    if (!groupMap.has(key)) {
      groupMap.set(key, {
        date: key,
        dateLabel: formatDateLabel(date),
        messages: [],
      });
      groups.push(groupMap.get(key));
    }

    groupMap.get(key).messages.push(msg);
  });

  return groups;
});

function formatDateLabel(date) {
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  if (date >= today) return 'Today';
  if (date >= yesterday) return 'Yesterday';

  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
  });
}

// Collapse consecutive messages from same user within 5 minutes
function shouldShowHeader(message, prevMessage) {
  if (!prevMessage) return true;
  if (message.user_id !== prevMessage.user_id) return true;
  if (message.deleted_at || prevMessage.deleted_at) return true;

  const timeDiff =
    new Date(message.created_at) - new Date(prevMessage.created_at);
  return timeDiff > 5 * 60 * 1000; // 5 minutes
}
</script>
