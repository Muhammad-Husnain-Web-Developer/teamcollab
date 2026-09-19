<template>
  <div class="flex h-full min-h-0">
    <!-- Main conversation column -->
    <div class="flex flex-col h-full min-w-0 flex-1">
    <!-- Chat header -->
    <ChatHeader
      :channel="headerChannel"
      :is-dm="isDm"
      :other-user="otherUser"
      @toggle-mute="onToggleMute"
      @toggle-members="membersOpen = true"
    />

    <!-- Message list -->
    <div
      ref="scrollContainer"
      class="flex-1 overflow-y-auto px-0 scrollbar-custom"
      @scroll="handleScroll"
    >
      <!-- Load more trigger at top -->
      <div ref="loadMoreTrigger" class="h-1" />

      <!-- Loading older messages -->
      <div v-if="loadingMore" class="flex items-center justify-center py-3">
        <div class="flex items-center gap-2 text-dark-400 text-xs">
          <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          Loading older messages…
        </div>
      </div>

      <!-- Message list component -->
      <MessageList
        :messages="messages"
        :loading="messageStore.loading && !loadingMore"
        :is-dm="isDm"
      />

      <!-- Scroll anchor -->
      <div ref="bottomAnchor" class="h-2" />
    </div>

    <!-- Typing indicator -->
    <TypingIndicator :channel-id="channel.id" />

    <!-- Message input -->
    <MessageInput
      :channel="channel"
      :is-dm="isDm"
      :send-url="sendUrl"
      :typing-url="typingUrl"
      :schedule-url="scheduleUrl"
      @message-sent="onMessageSent"
    />
    </div>

    <!-- Thread side panel (full-screen overlay on small viewports) -->
    <ThreadPanel :is-dm="isDm" />

    <!-- Channel membership (channels only — DMs have a fixed pair) -->
    <ChannelMembersModal
      v-if="!isDm"
      :show="membersOpen"
      :channel="channel"
      @close="closeMembersModal"
    />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { gsap } from 'gsap';
import { usePage } from '@inertiajs/vue3';
import ChatHeader from './ChatHeader.vue';
import MessageList from './MessageList.vue';
import MessageInput from './MessageInput.vue';
import TypingIndicator from './TypingIndicator.vue';
import ThreadPanel from './ThreadPanel.vue';
import ChannelMembersModal from './ChannelMembersModal.vue';
import { useMessageStore } from '../../Stores/useMessageStore';
import { useAuthStore } from '../../Stores/useAuthStore';
import { useChannelStore } from '../../Stores/useChannelStore';
import { useUIStore } from '../../Stores/useUIStore';
import { useThreadStore } from '../../Stores/useThreadStore';

const props = defineProps({
  channel: { type: Object, required: true },
  isDm: { type: Boolean, default: false },
  otherUser: { type: Object, default: null },
  sendUrl: { type: String, default: null },
  fetchUrl: { type: String, default: null },
  typingUrl: { type: String, default: null },
  scheduleUrl: { type: String, default: null },
});

const page = usePage();
const messageStore = useMessageStore();
const authStore = useAuthStore();
const channelStore = useChannelStore();
const uiStore = useUIStore();
const threadStore = useThreadStore();
const scrollContainer = ref(null);
const membersOpen = ref(false);
let echoChannel = null;

// RightPanel (a sibling under AppLayout, not a descendant) opens this modal
// via the shared uiStore rather than a prop, since it has no direct way to
// reach ChatArea's local state.
watch(
  () => uiStore.activeModal,
  (modal) => {
    if (modal === 'channelMembers' && !props.isDm) {
      membersOpen.value = true;
      uiStore.closeModal();
    }
  },
);

function closeMembersModal() {
  membersOpen.value = false;
}

// Mute lives in the store (kept fresh by the toggle + Inertia payloads), so
// overlay it onto the page's channel object for the header.
const headerChannel = computed(() => {
  if (props.isDm) return props.channel;
  const fromStore = channelStore.channels.find(c => c.id === props.channel.id);
  return { ...props.channel, is_muted: Boolean(fromStore?.is_muted) };
});
const bottomAnchor = ref(null);
const loadMoreTrigger = ref(null);
const loadingMore = ref(false);
const isAtBottom = ref(true);

const messages = computed(() =>
  messageStore.getMessages(props.channel.id),
);

// ── Scroll helpers ────────────────────────────────────────────────────
function scrollToBottom(behavior = 'smooth') {
  nextTick(() => {
    bottomAnchor.value?.scrollIntoView({ behavior, block: 'end' });
  });
}

function handleScroll() {
  if (!scrollContainer.value) return;
  const { scrollTop, scrollHeight, clientHeight } = scrollContainer.value;
  isAtBottom.value = scrollHeight - scrollTop - clientHeight < 80;

  // Load more when near top
  if (scrollTop < 200 && !loadingMore.value && messageStore.hasMore(props.channel.id)) {
    loadMoreMessages();
  }
}

async function loadMoreMessages() {
  if (loadingMore.value) return;
  loadingMore.value = true;

  const { scrollHeight: prevHeight } = scrollContainer.value;

  const paginationData = messageStore.pagination.get(props.channel.id);
  const nextPage = (paginationData?.currentPage ?? 1) + 1;

  await messageStore.fetchMessages(props.channel.id, nextPage, props.fetchUrl);

  // Maintain scroll position after prepend
  nextTick(() => {
    if (scrollContainer.value) {
      const newHeight = scrollContainer.value.scrollHeight;
      scrollContainer.value.scrollTop = newHeight - prevHeight;
    }
    loadingMore.value = false;
  });
}

// ── Auto-scroll on new messages ───────────────────────────────────────
watch(messages, (newMsgs, oldMsgs) => {
  if (!oldMsgs || newMsgs.length > oldMsgs.length) {
    if (isAtBottom.value) {
      scrollToBottom('smooth');
    }
  }
});

function onMessageSent() {
  scrollToBottom('smooth');
}

async function onToggleMute() {
  // DMs own their mute state in Show.vue; this handles channels only.
  if (props.isDm) return;

  try {
    await channelStore.toggleMute(props.channel.id);
  } catch {
    uiStore.toastError('Could not change mute setting.');
  }
}

// ── Intersection observer for infinite scroll ─────────────────────────
let observer = null;

function setupEcho() {
  if (props.isDm) return; // DM Show.vue manages its own Echo subscription
  const tenantId = page.props.tenant?.id;
  if (!tenantId || !window.Echo) return;

  const channelName = `tenant.${tenantId}.channel.${props.channel.id}`;
  echoChannel = window.Echo.private(channelName);

  echoChannel.listen('.message.sent', (e) => {
    const msg = e.message;
    // Skip sender's own messages — already added via HTTP response
    if (msg.user?.id === authStore.user?.id) return;
    messageStore.appendMessage(props.channel.id, msg);

    // A reply also belongs to its thread and bumps the parent's counter
    if (msg.parent_id) {
      threadStore.applyIncomingReply(msg);
      messageStore.bumpReplyCount(msg.parent_id);
    }
  });

  echoChannel.listen('.message.updated', (e) => {
    messageStore.updateMessage(e.message);
  });

  echoChannel.listen('.message.preview.ready', (e) => {
    messageStore.updateMessage(e.message);
  });

  echoChannel.listen('.message.deleted', (e) => {
    messageStore.removeMessage(e.messageId);
  });

  echoChannel.listen('.message.reacted', (e) => {
    // Own reactions are already applied from the HTTP response
    if (e.user_id === authStore.user?.id) return;
    messageStore.applyReactionEvent(e, authStore.user?.id);
  });

  echoChannel.listen('.user.typing', (e) => {
    if (e.user_id === authStore.user?.id) return;
    messageStore.setTyping(props.channel.id, e.user_id, e.user_name);
  });
}

function teardownEcho() {
  if (props.isDm) return;
  const tenantId = page.props.tenant?.id;
  if (echoChannel && tenantId) {
    echoChannel.stopListening('.message.sent');
    echoChannel.stopListening('.message.updated');
    echoChannel.stopListening('.message.preview.ready');
    echoChannel.stopListening('.message.deleted');
    echoChannel.stopListening('.message.reacted');
    echoChannel.stopListening('.user.typing');
    window.Echo?.leave(`tenant.${tenantId}.channel.${props.channel.id}`);
    echoChannel = null;
  }
}

onMounted(() => {
  scrollToBottom('instant');
  setupEcho();

  // Observe load-more trigger
  observer = new IntersectionObserver(
    ([entry]) => {
      if (entry.isIntersecting && messageStore.hasMore(props.channel.id)) {
        loadMoreMessages();
      }
    },
    { root: scrollContainer.value, threshold: 0.1 },
  );

  if (loadMoreTrigger.value) {
    observer.observe(loadMoreTrigger.value);
  }
});

onUnmounted(() => {
  teardownEcho();
  if (observer) observer.disconnect();
});
</script>

<style scoped>
.scrollbar-custom::-webkit-scrollbar {
  width: 6px;
}
.scrollbar-custom::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-custom::-webkit-scrollbar-thumb {
  background: #2a2a2e;
  border-radius: 3px;
}
.scrollbar-custom::-webkit-scrollbar-thumb:hover {
  background: #3a3a3e;
}
</style>
