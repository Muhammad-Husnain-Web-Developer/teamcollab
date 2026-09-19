<template>
  <AppLayout>
    <div class="flex-1 flex flex-col min-h-0">
      <ChatArea
        v-if="channel"
        :channel="channel"
        :key="channel.id"
      />
      <div v-else class="flex-1 flex items-center justify-center">
        <p class="text-dark-400">Channel not found.</p>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { onMounted, onUnmounted, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ChatArea from '../../Components/Chat/ChatArea.vue';
import { useChannelStore } from '../../Stores/useChannelStore';
import { useMessageStore } from '../../Stores/useMessageStore';

const channelStore = useChannelStore();
const messageStore = useMessageStore();

const props = defineProps({
  channel: { type: Object, default: null },
});

// Echo for the open channel lives in ChatArea; the sidebar badge subscriptions
// live in AppLayout. This page only owns activation and read state.
function openChannel(channel) {
  if (!channel) return;
  channelStore.setActiveChannel(channel);
  messageStore.fetchMessages(channel.id);
  channelStore.markChannelReadRemote(channel.id);
}

onMounted(() => {
  openChannel(props.channel);
});

onUnmounted(() => {
  channelStore.activeChannel = null;
});

watch(
  () => props.channel?.id,
  (newId, oldId) => {
    if (newId !== oldId) openChannel(props.channel);
  },
);
</script>
