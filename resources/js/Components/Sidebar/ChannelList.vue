<template>
  <div class="space-y-0.5 px-1 pb-1">
    <SkeletonLoader v-if="channelStore.loading" type="channel" />

    <template v-else>
      <Link
        v-for="channel in channelStore.channels"
        :key="channel.id"
        :href="`/channels/${channel.id}`"
        class="channel-item group"
        :class="isActive(channel.id)
          ? 'bg-gradient-to-r from-brand-500/25 to-brand-500/5 text-white relative before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-[3px] before:h-4 before:rounded-full before:bg-brand-400'
          : 'text-dark-50 hover:bg-white/[0.06] hover:text-white'"
        @click="channelStore.setActiveChannel(channel)"
      >
        <!-- Icon -->
        <span class="flex-shrink-0 w-4 text-center leading-none">
          <svg v-if="channel.type === 'private'" class="w-3.5 h-3.5 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          <span v-else class="text-sm font-semibold leading-none">#</span>
        </span>

        <!-- Name -->
        <span class="flex-1 min-w-0 text-sm truncate font-medium">
          {{ channel.name }}
        </span>

        <!-- Unread badge -->
        <span
          v-if="unreadCount(channel.id) > 0 && !isActive(channel.id)"
          class="flex-shrink-0 min-w-[18px] h-[18px] px-1.5 rounded-full bg-brand-500 text-white text-[10px] font-bold flex items-center justify-center"
        >
          {{ unreadCount(channel.id) > 99 ? '99+' : unreadCount(channel.id) }}
        </span>
      </Link>

      <!-- Empty state -->
      <p v-if="channelStore.channels.length === 0" class="text-dark-100 text-xs px-3 py-2">
        No channels yet.
      </p>
    </template>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { useChannelStore } from '../../Stores/useChannelStore';
import SkeletonLoader from '../Common/SkeletonLoader.vue';

const channelStore = useChannelStore();

function isActive(channelId) {
  return channelStore.activeChannel?.id === channelId;
}

function unreadCount(channelId) {
  return channelStore.getUnreadCount(channelId);
}
</script>

<style scoped>
.channel-item {
  @apply flex items-center gap-2 px-2 py-1.5 rounded-lg cursor-pointer transition-all duration-150;
}
</style>
