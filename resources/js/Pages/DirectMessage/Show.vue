<template>
  <AppLayout>
    <div class="flex h-full min-h-0">

      <!-- ── Center: chat area ───────────────────────────────────────── -->
      <div class="flex-1 flex flex-col min-w-0">
        <ChatArea
          v-if="conversation && dmChannel"
          :channel="dmChannel"
          :is-dm="true"
          :other-user="otherUser"
          :send-url="`/dm/${conversation.id}/messages`"
          :fetch-url="`/dm/${conversation.id}/messages`"
          :typing-url="`/dm/${conversation.id}/typing`"
          :schedule-url="`/dm/${conversation.id}/messages/schedule`"
          :key="conversation.id"
        />
        <div v-else class="flex-1 flex flex-col items-center justify-center gap-3 text-dark-400">
          <svg class="w-12 h-12 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <p class="text-sm">Select a conversation from the sidebar</p>
        </div>
      </div>

      <!-- ── Right: user profile panel ──────────────────────────────── -->
      <div
        v-if="otherUser"
        class="w-64 flex-shrink-0 border-l border-dark-600/50 bg-dark-750 flex flex-col overflow-y-auto"
      >
        <!-- Avatar + name -->
        <div class="p-6 text-center">
          <div class="relative inline-block mb-3">
            <div class="w-20 h-20 rounded-full bg-gradient-to-br from-brand-500 to-purple-600 flex items-center justify-center overflow-hidden ring-2 ring-dark-600 mx-auto">
              <img v-if="otherUser.avatar_url" :src="otherUser.avatar_url" class="w-full h-full object-cover" alt="" />
              <span v-else class="text-2xl font-bold text-white">{{ initials(otherUser) }}</span>
            </div>
            <!-- Status indicator -->
            <span
              class="absolute bottom-1 right-1 w-3.5 h-3.5 rounded-full border-2 border-dark-750"
              :class="{
                'bg-green-500': liveStatus === 'online',
                'bg-yellow-500': liveStatus === 'away',
                'bg-dark-400':  liveStatus === 'offline',
              }"
            ></span>
          </div>

          <h3 class="text-base font-semibold text-white leading-tight">
            {{ otherUser.display_name || otherUser.name }}
          </h3>
          <p v-if="otherUser.display_name && otherUser.display_name !== otherUser.name" class="text-xs text-dark-400 mt-0.5">
            @{{ otherUser.name }}
          </p>
          <div class="flex items-center justify-center gap-1.5 mt-2">
            <span
              class="w-2 h-2 rounded-full"
              :class="{
                'bg-green-500': liveStatus === 'online',
                'bg-yellow-500': liveStatus === 'away',
                'bg-dark-400':  liveStatus === 'offline',
              }"
            ></span>
            <span class="text-xs font-medium capitalize" :class="{
              'text-green-400': liveStatus === 'online',
              'text-yellow-400': liveStatus === 'away',
              'text-dark-400': liveStatus === 'offline',
            }">{{ liveStatus }}</span>
          </div>
        </div>

        <div class="border-t border-dark-600/50 mx-4"></div>

        <!-- Action buttons -->
        <div class="px-4 py-4 flex gap-2">
          <button
            class="flex-1 py-2 text-xs font-semibold rounded-lg border border-dark-500/60 text-dark-200 hover:bg-dark-600/60 hover:text-white hover:border-dark-400 active:scale-95 transition-all duration-150"
            @click="goToProfile"
          >
            Profile
          </button>
          <button
            class="flex-1 py-2 text-xs font-semibold rounded-lg border transition-all duration-150 active:scale-95"
            :class="isMuted
              ? 'border-brand-500/60 bg-brand-500/10 text-brand-400 hover:bg-brand-500/20'
              : 'border-dark-500/60 text-dark-200 hover:bg-dark-600/60 hover:text-white hover:border-dark-400'"
            @click="toggleMute"
          >
            {{ isMuted ? 'Unmute' : 'Mute' }}
          </button>
        </div>

        <div class="border-t border-dark-600/50 mx-4"></div>

        <!-- Information section -->
        <div class="px-4 py-3">
          <button
            class="flex items-center justify-between w-full py-1 group"
            @click="infoOpen = !infoOpen"
          >
            <span class="text-xs font-semibold text-dark-300 group-hover:text-dark-100 transition-colors tracking-wider uppercase">
              Information
            </span>
            <svg
              class="w-3.5 h-3.5 text-dark-400 group-hover:text-dark-200 transition-all duration-200"
              :class="infoOpen ? 'rotate-180' : ''"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>

          <div v-if="infoOpen" class="mt-2 space-y-2">
            <div class="flex items-center gap-2.5 text-xs text-dark-300">
              <div class="w-6 h-6 rounded-md bg-dark-600/70 flex items-center justify-center flex-shrink-0">
                <svg class="w-3.5 h-3.5 text-dark-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5" />
                </svg>
              </div>
              <span>Workspace member</span>
            </div>
          </div>
        </div>

        <div class="border-t border-dark-600/50 mx-4"></div>

        <!-- Shared Files section -->
        <div class="px-4 py-3">
          <button
            class="flex items-center justify-between w-full py-1 group"
            @click="filesOpen = !filesOpen"
          >
            <span class="text-xs font-semibold text-dark-300 group-hover:text-dark-100 transition-colors tracking-wider uppercase">
              Shared Files
            </span>
            <svg
              class="w-3.5 h-3.5 text-dark-400 group-hover:text-dark-200 transition-all duration-200"
              :class="filesOpen ? 'rotate-180' : ''"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-if="filesOpen" class="mt-2">
            <p class="text-xs text-dark-500 py-1">No shared files yet.</p>
          </div>
        </div>

        <div class="border-t border-dark-600/50 mx-4"></div>

        <!-- Pinned Items section -->
        <div class="px-4 py-3">
          <button
            class="flex items-center justify-between w-full py-1 group"
            @click="pinnedOpen = !pinnedOpen"
          >
            <span class="text-xs font-semibold text-dark-300 group-hover:text-dark-100 transition-colors tracking-wider uppercase">
              Pinned Items
            </span>
            <svg
              class="w-3.5 h-3.5 text-dark-400 group-hover:text-dark-200 transition-all duration-200"
              :class="pinnedOpen ? 'rotate-180' : ''"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          <div v-if="pinnedOpen" class="mt-2">
            <p class="text-xs text-dark-500 py-1">No pinned items.</p>
          </div>
        </div>

      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ChatArea from '../../Components/Chat/ChatArea.vue';
import { useMessageStore } from '../../Stores/useMessageStore';
import { usePresenceStore } from '../../Stores/usePresenceStore';
import { useAuthStore } from '../../Stores/useAuthStore';
import { useUIStore } from '../../Stores/useUIStore';
import { useChannelStore } from '../../Stores/useChannelStore';
import { useThreadStore } from '../../Stores/useThreadStore';

const page          = usePage();
const messageStore  = useMessageStore();
const presenceStore = usePresenceStore();
const authStore     = useAuthStore();
const uiStore       = useUIStore();
const channelStore  = useChannelStore();
const threadStore   = useThreadStore();

const props = defineProps({
  conversation: { type: Object, default: null },
});

const infoOpen   = ref(true);
const filesOpen  = ref(false);
const pinnedOpen = ref(false);
const isMuted    = ref(Boolean(props.conversation?.is_muted));

// Close AppLayout right panel so it doesn't overlap
onMounted(() => { uiStore.rightPanelOpen = false; });

// ── Derived data ──────────────────────────────────────────────────────
const otherUser = computed(() => props.conversation?.participant ?? null);

const liveStatus = computed(() => {
  if (!otherUser.value) return 'offline';
  return presenceStore.getStatus(otherUser.value.id) || otherUser.value.status || 'offline';
});

// 'dm_N' prefix avoids collision between conversation IDs and channel IDs in the store
const storeKey = computed(() => props.conversation ? `dm_${props.conversation.id}` : null);

const dmChannel = computed(() => {
  if (!props.conversation) return null;
  return {
    id:    storeKey.value,
    name:  otherUser.value?.display_name || otherUser.value?.name || 'Direct Message',
    type:  'dm',
    is_dm: true,
  };
});

// ── Helpers ───────────────────────────────────────────────────────────
function initials(person) {
  const n = person?.display_name || person?.name || '?';
  return n.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function goToProfile() {
  if (otherUser.value?.id) {
    router.visit(`/members/${otherUser.value.id}`);
  }
}

async function toggleMute() {
  if (!props.conversation) return;

  const previous = isMuted.value;
  isMuted.value = !previous; // optimistic — the toggle should feel instant

  try {
    const { data } = await axios.post(`/dm/${props.conversation.id}/mute`);
    isMuted.value = data.is_muted;
  } catch {
    isMuted.value = previous;
    uiStore.toastError('Could not change mute setting.');
  }
}

// ── Echo subscription ─────────────────────────────────────────────────
let echoChannel = null;

function subscribe() {
  const conv     = props.conversation;
  const tenantId = page.props.tenant?.id;
  if (!conv || !tenantId || !window.Echo) return;

  echoChannel = window.Echo.private(`tenant.${tenantId}.dm.${conv.id}`);
  echoChannel
    .listen('.message.sent', e => {
      if (e.message?.user_id === authStore.user?.id) return;
      messageStore.appendMessage(storeKey.value, e.message);

      if (e.message?.parent_id) {
        threadStore.applyIncomingReply(e.message);
        messageStore.bumpReplyCount(e.message.parent_id);
      }
    })
    .listen('.message.updated', e => { messageStore.updateMessage(e.message); })
    .listen('.message.preview.ready', e => { messageStore.updateMessage(e.message); })
    .listen('.message.deleted', e => {
      messageStore.removeMessage(e.messageId);
      if (e.parent_id) messageStore.bumpReplyCount(e.parent_id, -1);
    })
    .listen('.message.reacted', e => {
      // Own reactions are already applied from the HTTP response
      if (e.user_id === authStore.user?.id) return;
      messageStore.applyReactionEvent(e, authStore.user?.id);
    })
    .listen('.user.typing', e => {
      if (e.user_id === authStore.user?.id) return;
      // Keyed by storeKey ("dm_<id>") so TypingIndicator picks it up
      messageStore.setTyping(storeKey.value, e.user_id, e.user_name);
    });
}

function unsubscribe() {
  const tenantId = page.props.tenant?.id;
  if (echoChannel && props.conversation && tenantId) {
    window.Echo?.leave(`tenant.${tenantId}.dm.${props.conversation.id}`);
    echoChannel = null;
  }
}

// ── Lifecycle ─────────────────────────────────────────────────────────
onMounted(() => {
  if (props.conversation && storeKey.value) {
    messageStore.fetchMessages(storeKey.value, 1, `/dm/${props.conversation.id}/messages`);
    subscribe();
    channelStore.markDmReadRemote(props.conversation.id);
  }
});

onUnmounted(() => { unsubscribe(); });

watch(
  () => props.conversation?.id,
  (newId, oldId) => {
    if (newId !== oldId) {
      unsubscribe();
      if (props.conversation && storeKey.value) {
        messageStore.fetchMessages(storeKey.value, 1, `/dm/${props.conversation.id}/messages`);
        subscribe();
        isMuted.value = Boolean(props.conversation.is_muted);
        channelStore.markDmReadRemote(props.conversation.id);
      }
    }
  },
);
</script>
