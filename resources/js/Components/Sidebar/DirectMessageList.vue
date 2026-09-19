<template>
  <div class="space-y-0.5 px-1 pb-1">
    <template v-for="member in sortedMembers" :key="member.id">
      <!-- Has existing conversation → Link to it -->
      <Link
        v-if="member.conversation_id"
        :href="`/dm/${member.conversation_id}`"
        class="dm-item group"
        :class="isActiveDM(member.conversation_id)
          ? 'bg-gradient-to-r from-brand-500/25 to-brand-500/5 text-white relative before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-[3px] before:h-4 before:rounded-full before:bg-brand-400'
          : 'text-dark-50 hover:bg-white/[0.06] hover:text-white'"
      >
        <MemberRow :member="member" :show-unread="true" :active="isActiveDM(member.conversation_id)" />
      </Link>

      <!-- No conversation yet → clicking starts one -->
      <button
        v-else
        class="dm-item group w-full text-left text-dark-50 hover:bg-white/[0.06] hover:text-white"
        @click="startDM(member.id)"
      >
        <MemberRow :member="member" :show-unread="false" :active="false" />
      </button>
    </template>

    <div v-if="sortedMembers.length === 0" class="px-3 py-2">
      <p class="text-dark-100 text-xs">No other members yet.</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { usePresenceStore } from '../../Stores/usePresenceStore';
import { useAuthStore } from '../../Stores/useAuthStore';
import MemberRow from './MemberRow.vue';

const page = usePage();
const presenceStore = usePresenceStore();
const authStore = useAuthStore();

const members = computed(() => page.props.workspaceMembers ?? []);

const activeDMId = computed(() => {
  const match = window.location.pathname.match(/\/dm\/(\d+)/);
  return match ? parseInt(match[1]) : null;
});

function isActiveDM(conversationId) {
  return activeDMId.value === conversationId;
}

function startDM(userId) {
  router.post('/dm', { user_id: userId }, {
    preserveScroll: true,
  });
}

// Sort: recently chatted → online → alphabetical
const sortedMembers = computed(() => {
  return [...members.value].sort((a, b) => {
    const aChat = a.last_message_at ? new Date(a.last_message_at).getTime() : 0;
    const bChat = b.last_message_at ? new Date(b.last_message_at).getTime() : 0;

    // 1. Both have chats → most recent first
    if (aChat && bChat) return bChat - aChat;
    if (aChat && !bChat) return -1;
    if (!aChat && bChat) return 1;

    // 2. Online status
    const aOnline = presenceStore.getStatus(a.id) === 'online';
    const bOnline = presenceStore.getStatus(b.id) === 'online';
    if (aOnline && !bOnline) return -1;
    if (!aOnline && bOnline) return 1;

    // 3. Alphabetical
    return (a.display_name || a.name).localeCompare(b.display_name || b.name);
  });
});
</script>

<style scoped>
.dm-item {
  @apply flex items-center gap-2.5 px-2 py-1.5 rounded-lg cursor-pointer transition-all duration-150;
}
</style>
