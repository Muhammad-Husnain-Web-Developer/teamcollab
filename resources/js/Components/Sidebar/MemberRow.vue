<template>
  <div class="relative flex-shrink-0">
    <Avatar :src="member.avatar_url" :name="member.name" size="xs" />
    <div
      class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 border-dark-750"
      :class="statusDotClass"
    />
  </div>

  <span class="flex-1 min-w-0 text-sm truncate font-medium">
    {{ member.display_name || member.name }}
  </span>

  <span
    v-if="showUnread && unreadCount > 0 && !active"
    class="flex-shrink-0 min-w-[18px] h-[18px] px-1.5 rounded-full bg-brand-500 text-white text-[10px] font-bold flex items-center justify-center"
  >
    {{ unreadCount > 99 ? '99+' : unreadCount }}
  </span>
</template>

<script setup>
import { computed } from 'vue';
import Avatar from '../Common/Avatar.vue';
import { usePresenceStore } from '../../Stores/usePresenceStore';
import { useChannelStore } from '../../Stores/useChannelStore';

const props = defineProps({
  member: { type: Object, required: true },
  showUnread: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
});

const presenceStore = usePresenceStore();
const channelStore = useChannelStore();

// Server count (refreshed on navigation) plus any live arrivals since then.
const unreadCount = computed(() => {
  const fromServer = props.member.unread_count ?? 0;
  const live = props.member.conversation_id
    ? channelStore.getDmUnread(props.member.conversation_id)
    : 0;
  return fromServer + live;
});

const statusDotClass = computed(() => {
  const status = presenceStore.getStatus(props.member.id) ?? props.member.status;
  const map = { online: 'bg-green-500', away: 'bg-yellow-500', offline: 'bg-dark-500' };
  return map[status] ?? map.offline;
});
</script>
