<template>
  <div class="relative flex h-screen bg-dark-800 overflow-hidden isolate">
    <Atmosphere />

    <!-- Sidebar -->
    <transition name="sidebar">
      <aside
        v-show="uiStore.sidebarOpen || !uiStore.isMobile"
        ref="sidebarEl"
        class="relative flex-shrink-0 flex flex-col bg-dark-750/70 backdrop-blur-2xl border-r border-white/[0.06] z-30"
        :class="uiStore.isMobile
          ? 'fixed inset-y-0 left-0 w-[82vw] max-w-[300px] shadow-2xl'
          : 'relative w-60'"
      >
        <AppSidebar />
      </aside>
    </transition>

    <!-- Mobile overlay -->
    <div
      v-if="uiStore.isMobile && uiStore.sidebarOpen"
      class="fixed inset-0 bg-black/50 z-20 backdrop-blur-sm"
      @click="uiStore.toggleSidebar"
    />

    <!-- Main content area -->
    <main class="relative z-10 flex-1 flex flex-col min-w-0 overflow-hidden">
      <slot />
    </main>

    <!-- Right panel (toggleable) -->
    <transition name="right-panel">
      <aside
        v-show="uiStore.rightPanelOpen && !uiStore.isMobile"
        class="relative z-10 flex-shrink-0 w-72 border-l border-white/[0.06] bg-dark-750/70 backdrop-blur-2xl overflow-y-auto"
      >
        <RightPanel />
      </aside>
    </transition>

    <!-- Toast container (fixed overlay) -->
    <ToastContainer />

    <!-- Global call UI (Teleport to body internally) -->
    <IncomingCallToast />
    <CallScreen />

    <!-- New direct message (opened from the sidebar's "+" next to Direct Messages) -->
    <NewDmModal
      :show="uiStore.activeModal === 'newDM'"
      @close="uiStore.closeModal()"
    />

    <!-- Forward message (opened from a message's hover-action bar) -->
    <ForwardMessageModal
      :show="uiStore.activeModal === 'forwardMessage'"
      :message="uiStore.activeModalProps.message"
      @close="uiStore.closeModal()"
    />

    <!-- Group call picker (opened from a channel header) -->
    <GroupCallInviteModal
      :show="uiStore.activeModal === 'groupCall'"
      :channel="uiStore.activeModalProps.channel"
      @close="uiStore.closeModal()"
    />
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { notificationText, notificationRoute, isViewingSource } from '../Utils/notifications';
import { playNotificationDing } from '../Utils/sounds';
import { useUIStore } from '../Stores/useUIStore';
import { useAuthStore } from '../Stores/useAuthStore';
import { useWorkspaceStore } from '../Stores/useWorkspaceStore';
import { useChannelStore } from '../Stores/useChannelStore';
import { useMessageStore } from '../Stores/useMessageStore';
import { usePresenceStore } from '../Stores/usePresenceStore';
import { useNotificationStore } from '../Stores/useNotificationStore';
import { useCallStore } from '../Stores/useCallStore';
import { useEmojiStore } from '../Stores/useEmojiStore';
import AppSidebar from '../Components/Sidebar/AppSidebar.vue';
import RightPanel from '../Components/RightPanel.vue';
import ToastContainer from '../Components/Common/ToastContainer.vue';
import IncomingCallToast from '../Components/Call/IncomingCallToast.vue';
import CallScreen from '../Components/Call/CallScreen.vue';
import NewDmModal from '../Components/Chat/NewDmModal.vue';
import ForwardMessageModal from '../Components/Chat/ForwardMessageModal.vue';
import GroupCallInviteModal from '../Components/Call/GroupCallInviteModal.vue';

const page = usePage();
const uiStore = useUIStore();
const authStore = useAuthStore();
const workspaceStore = useWorkspaceStore();
const channelStore = useChannelStore();
const messageStore = useMessageStore();
const presenceStore = usePresenceStore();
const notificationStore = useNotificationStore();
const callStore = useCallStore();
const emojiStore = useEmojiStore();

const sidebarEl = ref(null);
let echoChannels = [];

// Declared here, not next to subscribeToChannelBadges below: the channels
// watcher runs immediately during setup, so this must already be initialised.
const badgeSubscriptions = new Map(); // channelId -> Echo channel

// ── Bootstrap stores from Inertia shared data ─────────────────────────
function bootstrapFromPageProps() {
  const props = page.props;
  if (props.auth?.user) {
    authStore.setUser(props.auth.user);
  }
  if (props.currentWorkspace) {
    workspaceStore.setCurrentWorkspace(props.currentWorkspace);
  }
}

// Keep channelStore in sync with Inertia's shared channels prop (works across SPA navigations)
watch(
  () => page.props.channels,
  (channels) => {
    if (Array.isArray(channels)) {
      channelStore.channels = channels;
      channelStore.hydrateUnread(channels);
      subscribeToChannelBadges(channels);
    }
  },
  { immediate: true },
);

// Surface server flash messages as toasts. Without this, any redirect carrying
// ->with('error') lands silently and the action that triggered it looks dead.
watch(
  () => page.props.flash,
  (flash) => {
    if (!flash) return;
    if (flash.success) uiStore.toastSuccess(flash.success);
    if (flash.error)   uiStore.toastError(flash.error);
    if (flash.warning) uiStore.toastWarning(flash.warning);
    if (flash.info)    uiStore.toastInfo(flash.info);
  },
  { immediate: true, deep: true },
);

// ── Echo subscriptions ────────────────────────────────────────────────
function setupEchoSubscriptions() {
  const tenantId = page.props.tenant?.id;
  if (!tenantId || !window.Echo) return;

  // Workspace-level private channel (channel created/updated/deleted)
  const workspaceChannel = window.Echo.private(`tenant.${tenantId}.workspace`);
  workspaceChannel
    .listen('.channel.created', e => channelStore.addChannel(e.channel))
    .listen('.channel.updated', e => channelStore.updateChannel(e.channel))
    .listen('.channel.deleted', e => channelStore.removeChannel(e.channelId));
  echoChannels.push(workspaceChannel);

  // Presence channel for online/away tracking
  const presenceChannel = window.Echo.join(`presence-tenant.${tenantId}`);
  presenceChannel
    .here(users => presenceStore.setOnlineUsers(users))
    .joining(user => presenceStore.setUserStatus(user.id, 'online'))
    .leaving(user => presenceStore.setUserStatus(user.id, 'offline'))
    .listen('.user.status', e => presenceStore.setUserStatus(e.userId, e.status, e.lastSeen));
  echoChannels.push(presenceChannel);

  // Personal notification + call signaling channel
  const user = authStore.user;
  if (user) {
    const notifChannel = window.Echo.private(`App.Models.User.${user.id}`);
    notifChannel
      .listen('.notification.sent', e => handleIncomingNotification(e.notification))
      .listen('.call.initiated',          e => callStore.handleIncoming(e))
      .listen('.call.participant.joined', e => callStore.handleParticipantJoined(e))
      .listen('.call.participant.left',   e => callStore.handleParticipantLeft(e))
      .listen('.call.rejected',           e => callStore.handleRejected(e))
      .listen('.call.recording',          e => callStore.handleRecordingToggled(e))
      .listen('.webrtc.signal',           e => callStore.handleSignal(e));
    echoChannels.push(notifChannel);
  }
}

// ── Notification handling ─────────────────────────────────────────────
function handleIncomingNotification(notification) {
  if (!notification) return;

  notificationStore.addNotification(notification);

  // Bump the DM sidebar badge. The server count only refreshes on navigation,
  // so this keeps it live while the user sits on one page.
  const conversationId = notification.data?.conversation_id;
  if (notification.type === 'dm_message' && conversationId && !isViewingSource(notification)) {
    channelStore.incrementDmUnread(conversationId);
  }

  // No toast if the user is already looking at that channel/conversation
  if (isViewingSource(notification)) return;

  const text = notificationText(notification);
  uiStore.toastInfo(text);
  playNotificationDing();

  // Browser notification when the tab is hidden
  if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
    try {
      const browserNotif = new Notification('TeamCollab', {
        body: notification.data?.preview ? `${text}: ${notification.data.preview}` : text,
        icon: notification.data?.sender_avatar ?? undefined,
        tag: `notif-${notification.id}`,
      });
      browserNotif.onclick = () => {
        window.focus();
        const route = notificationRoute(notification);
        if (route) router.visit(route);
        browserNotif.close();
      };
    } catch {
      // Browser notification is best-effort only
    }
  }
}

function requestBrowserNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().catch(() => {});
  }
}

// ── Sidebar unread badges ─────────────────────────────────────────────
// ChatArea only subscribes to the channel you're looking at, so badges for
// every OTHER channel need their own lightweight subscription. We only listen
// for message.sent here and bump a counter — no message bodies are stored.
function subscribeToChannelBadges(channels) {
  const tenantId = page.props.tenant?.id;
  if (!tenantId || !window.Echo) return;

  const wanted = new Set(channels.map(c => c.id));

  // Drop subscriptions for channels the user no longer sees
  badgeSubscriptions.forEach((_, id) => {
    if (!wanted.has(id)) {
      window.Echo.leave(`tenant.${tenantId}.channel.${id}`);
      badgeSubscriptions.delete(id);
    }
  });

  channels.forEach(ch => {
    if (badgeSubscriptions.has(ch.id)) return;

    const echoChannel = window.Echo.private(`tenant.${tenantId}.channel.${ch.id}`);
    echoChannel.listen('.message.sent', e => {
      // Not our own message, not the channel on screen, not a muted channel
      if (e.message?.user_id === authStore.user?.id) return;
      if (channelStore.activeChannel?.id === ch.id) return;
      if (channelStore.isMuted(ch.id)) return;
      channelStore.incrementUnread(ch.id);
    });

    // The person who performed the change already updated their own count
    // optimistically, so skip their echo to avoid counting it twice.
    echoChannel.listen('.member.left', e => {
      if (e.actor_id === authStore.user?.id) return;
      channelStore.adjustMemberCount(ch.id, -1);
    });

    echoChannel.listen('.member.added', e => {
      if (e.actor_id === authStore.user?.id) return;
      channelStore.adjustMemberCount(ch.id, 1);
    });

    badgeSubscriptions.set(ch.id, echoChannel);
  });
}

function teardownChannelBadges() {
  const tenantId = page.props.tenant?.id;
  badgeSubscriptions.forEach((_, id) => {
    if (tenantId) window.Echo?.leave(`tenant.${tenantId}.channel.${id}`);
  });
  badgeSubscriptions.clear();
}

function teardownEcho() {
  echoChannels.forEach(ch => {
    if (ch?.unsubscribe) ch.unsubscribe();
  });
  echoChannels = [];
  teardownChannelBadges();
}

// ── Sidebar animation on mount ────────────────────────────────────────
let removeNavListener = null;

onMounted(() => {
  bootstrapFromPageProps();
  setupEchoSubscriptions();
  requestBrowserNotificationPermission();

  // Picking a channel or DM on mobile should reveal it, not leave the sidebar
  // overlaying it. One listener beats a handler on every sidebar link.
  removeNavListener = router.on('navigate', () => uiStore.closeSidebarOnMobile());

  // Load the unread badge count without waiting for the bell to be opened
  if (!notificationStore.loaded) {
    notificationStore.fetchNotifications();
  }

  // Custom emoji are needed to render :shortcode: tokens in already-loaded
  // messages, not just when a picker is opened.
  emojiStore.ensureLoaded();

  if (sidebarEl.value) {
    gsap.fromTo(
      sidebarEl.value,
      { x: -30, opacity: 0 },
      { x: 0, opacity: 1, duration: 0.5, ease: 'power3.out' },
    );
  }
});

onUnmounted(() => {
  teardownEcho();
  removeNavListener?.();
});
</script>

<style scoped>
.sidebar-enter-active,
.sidebar-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
    opacity 0.3s ease;
}
.sidebar-enter-from,
.sidebar-leave-to {
  transform: translateX(-100%);
  opacity: 0;
}

.right-panel-enter-active,
.right-panel-leave-active {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1),
    opacity 0.3s ease;
}
.right-panel-enter-from,
.right-panel-leave-to {
  transform: translateX(100%);
  opacity: 0;
}
</style>
