<template>
  <div class="relative flex items-center gap-3 px-4 py-3 border-b border-white/[0.06] bg-dark-800/60 backdrop-blur-xl flex-shrink-0">

    <!-- Sidebar toggle — the only way back to navigation on mobile -->
    <button
      v-if="uiStore.isMobile"
      class="flex-shrink-0 -ml-1 p-1.5 rounded-lg text-dark-50 hover:text-white hover:bg-white/[0.08] transition-colors"
      title="Show channels"
      @click="uiStore.toggleSidebar"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
    </button>

    <!-- ── DM header ─────────────────────────────────────────────── -->
    <template v-if="isDm && otherUser">
      <!-- Avatar + status -->
      <div class="relative flex-shrink-0">
        <Avatar :src="otherUser.avatar_url" :name="otherUser.display_name || otherUser.name" size="sm" />
        <span
          class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-dark-800"
          :class="{
            'bg-emerald-400':  dmStatus === 'online',
            'bg-amber-400': dmStatus === 'away',
            'bg-dark-400':   !dmStatus || dmStatus === 'offline',
          }"
        ></span>
      </div>

      <!-- Name + status text -->
      <div class="flex-1 min-w-0">
        <h2 class="text-sm font-bold text-white truncate leading-tight">
          {{ otherUser.display_name || otherUser.name }}
        </h2>
        <p class="text-xs truncate leading-tight" :class="{
          'text-emerald-400':  dmStatus === 'online',
          'text-amber-400': dmStatus === 'away',
          'text-dark-50/60':   !dmStatus || dmStatus === 'offline',
        }">
          {{ dmStatus === 'online' ? 'Online' : dmStatus === 'away' ? 'Away' : 'Offline' }}
        </p>
      </div>

      <!-- DM action buttons -->
      <div class="flex items-center gap-0.5 flex-shrink-0">
        <!-- Voice call -->
        <button
          class="header-btn group relative"
          :class="callStore.callState !== 'idle' ? 'opacity-40 cursor-not-allowed' : ''"
          title="Voice call"
          @click="startAudioCall"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
          </svg>
          <span class="tooltip">Voice call</span>
        </button>

        <!-- Video call -->
        <button
          class="header-btn group relative"
          :class="callStore.callState !== 'idle' ? 'opacity-40 cursor-not-allowed' : ''"
          title="Video call"
          @click="startVideoCall"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <span class="tooltip">Video call</span>
        </button>

        <!-- Search in conversation -->
        <button class="header-btn group relative" title="Search" @click="toggleSearch">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <span class="tooltip">Search</span>
        </button>

        <!-- More options (three dots) -->
        <div class="relative" ref="moreMenuRef">
          <button
            class="header-btn group relative"
            :class="moreOpen ? 'bg-dark-600 text-white' : ''"
            @click="moreOpen = !moreOpen"
            title="More options"
          >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
              <circle cx="5" cy="12" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="19" cy="12" r="1.5" />
            </svg>
          </button>

          <!-- Dropdown menu -->
          <Transition name="dropdown">
            <div
              v-if="moreOpen"
              class="glass-elevated absolute right-0 top-full mt-1.5 w-52 overflow-hidden z-50 py-1"
            >
              <!-- View Profile -->
              <button class="menu-item" @click="onViewProfile">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                View Profile
              </button>

              <!-- Mute notifications -->
              <button class="menu-item" @click="emit('toggle-mute')">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15zM17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                </svg>
                {{ channel?.is_muted ? 'Unmute Notifications' : 'Mute Notifications' }}
              </button>

              <!-- Search in chat -->
              <button class="menu-item" @click="toggleSearch">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                Search in Chat
              </button>

              <div class="h-px bg-dark-600/60 my-1 mx-2"></div>

              <!-- Close / go back -->
              <button class="menu-item text-red-400 hover:text-red-300 hover:bg-red-500/10" @click="onCloseDm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Close Conversation
              </button>
            </div>
          </Transition>
        </div>
      </div>

      <!-- Search bar (slide-down when open) -->
      <Transition name="search-bar">
        <div v-if="searchOpen" class="absolute left-0 right-0 top-full bg-dark-800/95 backdrop-blur-xl border-b border-white/[0.06] px-4 py-2.5 z-40 flex items-center gap-2">
          <svg class="w-4 h-4 text-dark-100 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input
            ref="searchInputRef"
            v-model="searchQuery"
            type="text"
            placeholder="Search in conversation…"
            class="flex-1 bg-transparent text-sm text-white placeholder-dark-100 outline-none"
          />
          <button @click="toggleSearch" class="text-dark-100 hover:text-white p-1 rounded transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </Transition>
    </template>

    <!-- ── Channel header ─────────────────────────────────────────── -->
    <template v-else>
      <div class="flex-shrink-0">
        <svg v-if="channel?.type === 'private'" class="w-5 h-5 text-dark-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span v-else class="text-dark-50 text-lg font-bold leading-none">#</span>
      </div>

      <div class="flex-1 min-w-0">
        <h2 class="text-sm font-bold text-white truncate">{{ channel?.name }}</h2>
        <p v-if="channel?.topic" class="text-xs text-dark-50/60 truncate">{{ channel.topic }}</p>
      </div>

      <div class="flex items-center gap-0.5 flex-shrink-0">
        <!-- Start a group call with people from this channel -->
        <button
          class="header-btn group relative"
          :class="callStore.callState !== 'idle' ? 'opacity-40 cursor-not-allowed' : ''"
          title="Start call"
          @click="startGroupCall"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <span class="tooltip">Start call</span>
        </button>

        <button @click="emit('toggle-members')" class="header-btn group relative flex items-center gap-1.5" title="Members">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span class="text-xs font-medium">{{ channel?.members_count ?? 0 }}</span>
          <span class="tooltip">Members</span>
        </button>

        <button class="header-btn group relative" title="Search">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <span class="tooltip">Search</span>
        </button>

        <button @click="uiStore.toggleRightPanel()" class="header-btn group relative" title="Pinned messages">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
          <span class="tooltip">Pinned</span>
        </button>

        <button @click="uiStore.toggleRightPanel()" class="header-btn group relative" title="Channel info">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="tooltip">Info</span>
        </button>

        <!-- Channel overflow menu -->
        <div class="relative" ref="channelMenuEl">
          <button
            class="header-btn group relative"
            title="More"
            @click.stop="channelMenuOpen = !channelMenuOpen"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01" />
            </svg>
            <span class="tooltip">More</span>
          </button>

          <Transition name="dropdown">
            <div
              v-if="channelMenuOpen"
              class="glass-elevated absolute right-0 mt-2 w-56 py-1 z-50"
            >
              <button class="menu-item" @click="openMembers">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Members
              </button>

              <button class="menu-item" @click="onToggleChannelMute">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15zM17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2" />
                </svg>
                {{ channel?.is_muted ? 'Unmute Notifications' : 'Mute Notifications' }}
              </button>

              <div class="h-px bg-dark-600/60 my-1 mx-2"></div>

              <button
                class="menu-item text-red-400 hover:text-red-300 hover:bg-red-500/10"
                @click="onLeaveChannel"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                Leave Channel
              </button>
            </div>
          </Transition>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import Avatar from '../Common/Avatar.vue';
import { useUIStore } from '../../Stores/useUIStore';
import { usePresenceStore } from '../../Stores/usePresenceStore';
import { useCallStore } from '../../Stores/useCallStore';

const props = defineProps({
  channel:   { type: Object, default: null },
  isDm:      { type: Boolean, default: false },
  otherUser: { type: Object, default: null },
});

const emit = defineEmits(['toggle-members', 'toggle-mute']);

const uiStore       = useUIStore();
const presenceStore = usePresenceStore();
const callStore     = useCallStore();

const moreOpen     = ref(false);
const searchOpen   = ref(false);
const searchQuery  = ref('');
const moreMenuRef  = ref(null);
const searchInputRef = ref(null);

const dmStatus = computed(() => {
  if (!props.otherUser) return '';
  return presenceStore.getStatus(props.otherUser.id) || props.otherUser.status || 'offline';
});

function startAudioCall() {
  if (callStore.callState !== 'idle' || !props.otherUser) return;
  callStore.initiateCall(props.otherUser, 'audio');
}

function startVideoCall() {
  if (callStore.callState !== 'idle' || !props.otherUser) return;
  callStore.initiateCall(props.otherUser, 'video');
}

function startGroupCall() {
  if (callStore.callState !== 'idle') return;
  uiStore.openModal('groupCall', { channel: props.channel });
}

function onViewProfile() {
  moreOpen.value = false;
  if (props.otherUser?.id) {
    router.visit(`/members/${props.otherUser.id}`);
  }
}

function onCloseDm() {
  moreOpen.value = false;
  router.visit('/');
}

// ── Channel overflow menu ─────────────────────────────────────────────
const channelMenuOpen = ref(false);
const channelMenuEl   = ref(null);

function openMembers() {
  channelMenuOpen.value = false;
  emit('toggle-members');
}

function onToggleChannelMute() {
  channelMenuOpen.value = false;
  emit('toggle-mute');
}

function onLeaveChannel() {
  channelMenuOpen.value = false;
  if (!props.channel?.id) return;

  const name = props.channel.name;
  if (!window.confirm(`Leave #${name}? You'll stop receiving its messages.`)) return;

  // Inertia POST so the redirect and flash message are handled for us; the
  // server refuses if this would leave the channel without an admin.
  router.post(`/channels/${props.channel.id}/leave`, {}, {
    preserveScroll: true,
    onError: () => uiStore.toastError(`Could not leave #${name}.`),
  });
}


async function toggleSearch() {
  moreOpen.value = false;
  searchOpen.value = !searchOpen.value;
  if (searchOpen.value) {
    await nextTick();
    searchInputRef.value?.focus();
  } else {
    searchQuery.value = '';
  }
}

// Close whichever dropdown is open when clicking outside it
function onClickOutside(e) {
  if (moreMenuRef.value && !moreMenuRef.value.contains(e.target)) {
    moreOpen.value = false;
  }
  if (channelMenuEl.value && !channelMenuEl.value.contains(e.target)) {
    channelMenuOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside, true));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside, true));
</script>

<style scoped>
.header-btn {
  @apply relative p-2 rounded-lg text-dark-50 hover:text-white hover:bg-white/[0.08] transition-all duration-150;
}

.tooltip {
  @apply absolute -bottom-8 left-1/2 -translate-x-1/2 px-2 py-1 text-xs text-white bg-dark-600 rounded-md whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-50;
}

.menu-item {
  @apply flex items-center gap-3 w-full px-3 py-2.5 text-sm text-dark-50 hover:bg-white/[0.06] hover:text-white transition-colors text-left;
}

/* Dropdown animation */
.dropdown-enter-active,
.dropdown-leave-active {
  transition: all 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
}

/* Search bar slide */
.search-bar-enter-active,
.search-bar-leave-active {
  transition: all 0.2s ease;
}
.search-bar-enter-from,
.search-bar-leave-to {
  opacity: 0;
  transform: translateY(-8px);
}
</style>
