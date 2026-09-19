<template>
  <div class="flex flex-col h-full select-none">
    <!-- Workspace switcher -->
    <WorkspaceSwitcher />

    <!-- Notifications bell -->
    <NotificationBell />

    <!-- Scheduled messages -->
    <ScheduledMessagesTray />

    <!-- Navigation body -->
    <div class="flex-1 overflow-y-auto py-2 px-2 space-y-1 scrollbar-thin">
      <!-- Channels section -->
      <div class="mb-1">
        <div class="flex items-center justify-between px-2 py-1 group">
          <button
            @click="channelsOpen = !channelsOpen"
            class="flex items-center gap-1.5 text-dark-50/70 hover:text-white transition-colors"
          >
            <svg
              class="w-3 h-3 transition-transform duration-200"
              :class="channelsOpen ? 'rotate-90' : ''"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-xs font-semibold uppercase tracking-wider">Channels</span>
          </button>
          <button
            @click="openCreateChannel"
            class="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-white/[0.08] text-dark-50/70 hover:text-white"
            title="Add channel"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
          </button>
        </div>

        <transition name="collapse">
          <div v-show="channelsOpen">
            <ChannelList />
          </div>
        </transition>
      </div>

      <!-- Direct Messages section -->
      <div>
        <div class="flex items-center justify-between px-2 py-1 group">
          <button
            @click="dmsOpen = !dmsOpen"
            class="flex items-center gap-1.5 text-dark-50/70 hover:text-white transition-colors"
          >
            <svg
              class="w-3 h-3 transition-transform duration-200"
              :class="dmsOpen ? 'rotate-90' : ''"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
            </svg>
            <span class="text-xs font-semibold uppercase tracking-wider">Direct Messages</span>
          </button>
          <button
            @click="openNewDM"
            class="opacity-0 group-hover:opacity-100 transition-opacity p-0.5 rounded hover:bg-white/[0.08] text-dark-50/70 hover:text-white"
            title="New direct message"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
          </button>
        </div>

        <transition name="collapse">
          <div v-show="dmsOpen">
            <DirectMessageList />
          </div>
        </transition>
      </div>
    </div>

    <!-- Bottom user bar -->
    <div class="relative border-t border-white/[0.06] px-3 py-3" ref="userBarRef">

      <!-- User settings flyout (above the bar) -->
      <Transition name="flyout">
        <div
          v-if="userMenuOpen"
          class="glass-elevated absolute bottom-full left-0 right-0 mb-1.5 overflow-hidden z-50"
        >
          <!-- User card -->
          <div class="flex items-center gap-3 px-4 py-4 bg-gradient-to-b from-white/[0.04] to-transparent">
            <div class="relative flex-shrink-0">
              <Avatar :src="authStore.userAvatar" :name="authStore.userName" size="sm" />
              <span
                class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-dark-750"
                :class="statusDot(currentStatus)"
              ></span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-bold text-white truncate leading-tight">{{ authStore.userName }}</p>
              <p class="text-xs mt-0.5 capitalize font-medium" :class="statusTextClass(currentStatus)">
                {{ statusOptions.find(s => s.value === currentStatus)?.label ?? currentStatus }}
              </p>
            </div>
          </div>

          <!-- Status section -->
          <div class="px-3 py-2.5">
            <p class="section-title px-1 mb-2">Set a status</p>
            <div class="space-y-0.5">
              <button
                v-for="s in statusOptions"
                :key="s.value"
                class="flex items-center gap-3 w-full px-3 py-2 rounded-xl text-sm transition-all duration-150 text-left group"
                :class="currentStatus === s.value
                  ? 'bg-white/[0.07] text-white'
                  : 'text-dark-50 hover:bg-white/[0.05] hover:text-white'"
                @click="setStatus(s.value)"
              >
                <span class="w-2 h-2 rounded-full flex-shrink-0 ring-2 ring-offset-1 ring-offset-transparent"
                  :class="[s.dot, currentStatus === s.value ? 'ring-white/20' : 'ring-transparent']"
                ></span>
                <span class="flex-1 font-medium">{{ s.label }}</span>
                <span class="text-xs text-dark-100">{{ s.hint }}</span>
                <svg v-if="currentStatus === s.value" class="w-3.5 h-3.5 text-brand-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
              </button>
            </div>
          </div>

          <div class="border-t border-white/[0.06] mx-3"></div>

          <!-- Account actions -->
          <div class="px-3 py-2.5 space-y-0.5">
            <button class="flyout-item" @click="goToProfile">
              <div class="icon-wrap">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
              </div>
              <span>Profile &amp; Settings</span>
              <svg class="w-3.5 h-3.5 text-dark-100 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>

          <div class="border-t border-white/[0.06] mx-3"></div>

          <!-- Sign out -->
          <div class="px-3 py-2.5">
            <button class="flyout-item text-red-400 hover:text-red-300" @click="signOut">
              <div class="icon-wrap bg-red-500/10 text-red-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
              </div>
              <span>Sign Out</span>
            </button>
          </div>
        </div>
      </Transition>

      <!-- User row -->
      <div class="flex items-center gap-2.5">
        <div class="relative flex-shrink-0">
          <Avatar :src="authStore.userAvatar" :name="authStore.userName" size="sm" />
          <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-dark-750" :class="statusDot(currentStatus)"></span>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-white truncate leading-tight">{{ authStore.userName }}</p>
          <p class="text-xs truncate capitalize" :class="statusTextClass(currentStatus)">{{ currentStatus }}</p>
        </div>
        <button
          @click="userMenuOpen = !userMenuOpen"
          class="flex-shrink-0 p-1.5 rounded-lg transition-colors"
          :class="userMenuOpen ? 'bg-white/[0.08] text-white' : 'text-dark-100 hover:bg-white/[0.08] hover:text-white'"
          title="Settings"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Create Channel Modal -->
    <Modal :show="showCreateChannel" title="Create a channel" size="md" @close="showCreateChannel = false">
      <form @submit.prevent="createChannel" class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Channel name</label>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-dark-100 text-sm">#</span>
            <input
              v-model="channelForm.name"
              type="text"
              placeholder="e.g. marketing"
              class="input-field pl-7"
              required
            />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Topic <span class="text-dark-100">(optional)</span></label>
          <input v-model="channelForm.description" type="text" placeholder="What's this channel about?" class="input-field" />
        </div>
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-2">Visibility</label>
          <div class="grid grid-cols-2 gap-2">
            <label
              class="flex items-center gap-3 px-3 py-2.5 rounded-xl border cursor-pointer transition-all"
              :class="channelForm.type === 'public' ? 'bg-brand-500/10 border-brand-500/50' : 'bg-white/[0.03] border-white/[0.08]'"
            >
              <input type="radio" v-model="channelForm.type" value="public" class="sr-only" />
              <svg class="w-4 h-4 text-dark-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
              </svg>
              <div>
                <p class="text-sm font-medium text-white">Public</p>
                <p class="text-xs text-dark-100">Anyone can join</p>
              </div>
            </label>
            <label
              class="flex items-center gap-3 px-3 py-2.5 rounded-xl border cursor-pointer transition-all"
              :class="channelForm.type === 'private' ? 'bg-brand-500/10 border-brand-500/50' : 'bg-white/[0.03] border-white/[0.08]'"
            >
              <input type="radio" v-model="channelForm.type" value="private" class="sr-only" />
              <svg class="w-4 h-4 text-dark-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              <div>
                <p class="text-sm font-medium text-white">Private</p>
                <p class="text-xs text-dark-100">Invite-only</p>
              </div>
            </label>
          </div>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="showCreateChannel = false" class="btn-ghost">Cancel</button>
          <button type="submit" :disabled="channelForm.processing" class="btn-primary">Create channel</button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import axios from 'axios';
import WorkspaceSwitcher from './WorkspaceSwitcher.vue';
import NotificationBell from '../Notifications/NotificationBell.vue';
import ScheduledMessagesTray from './ScheduledMessagesTray.vue';
import ChannelList from './ChannelList.vue';
import DirectMessageList from './DirectMessageList.vue';
import Avatar from '../Common/Avatar.vue';
import Modal from '../Common/Modal.vue';
import { useAuthStore } from '../../Stores/useAuthStore';
import { usePresenceStore } from '../../Stores/usePresenceStore';
import { useUIStore } from '../../Stores/useUIStore';

const authStore    = useAuthStore();
const presenceStore = usePresenceStore();
const uiStore      = useUIStore();

const channelsOpen      = ref(true);
const dmsOpen           = ref(true);
const showCreateChannel = ref(false);
const userMenuOpen      = ref(false);
const userBarRef        = ref(null);
const currentStatus     = ref('online');

const statusOptions = [
  { value: 'online',  label: 'Online',           hint: 'Active',           dot: 'bg-emerald-400' },
  { value: 'away',    label: 'Away',              hint: 'Be right back',    dot: 'bg-amber-400' },
  { value: 'dnd',     label: 'Do Not Disturb',   hint: 'No notifications', dot: 'bg-red-400' },
  { value: 'offline', label: 'Appear Offline',   hint: 'Hidden',           dot: 'bg-dark-400' },
];

function statusDot(s) {
  if (s === 'online')  return 'bg-emerald-400';
  if (s === 'away')    return 'bg-amber-400';
  if (s === 'dnd')     return 'bg-red-400';
  return 'bg-dark-400';
}

function statusTextClass(s) {
  if (s === 'online')  return 'text-emerald-400';
  if (s === 'away')    return 'text-amber-400';
  if (s === 'dnd')     return 'text-red-400';
  return 'text-dark-100';
}

async function setStatus(status) {
  currentStatus.value = status;
  userMenuOpen.value  = false;
  try {
    await axios.post(`/presence/${status === 'dnd' ? 'away' : status}`);
  } catch {}
}

function goToProfile() {
  userMenuOpen.value = false;
  router.visit('/profile');
}

function signOut() {
  userMenuOpen.value = false;
  router.post('/logout');
}

// Close flyout on outside click
function onClickOutside(e) {
  if (userBarRef.value && !userBarRef.value.contains(e.target)) {
    userMenuOpen.value = false;
  }
}

onMounted(() => document.addEventListener('click', onClickOutside, true));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside, true));

const channelForm = useForm({
  name: '',
  description: '',
  type: 'public',
});

function openCreateChannel() {
  showCreateChannel.value = true;
}

function openNewDM() {
  uiStore.openModal('newDM');
}

function createChannel() {
  channelForm.post(route('channels.store'), {
    preserveScroll: true,
    onSuccess: () => {
      showCreateChannel.value = false;
      channelForm.reset();
    },
  });
}
</script>

<style scoped>
.collapse-enter-active,
.collapse-leave-active {
  transition: all 0.2s ease;
  overflow: hidden;
}
.collapse-enter-from,
.collapse-leave-to {
  max-height: 0;
  opacity: 0;
}
.collapse-enter-to,
.collapse-leave-from {
  max-height: 1000px;
  opacity: 1;
}

/* User settings flyout */
.flyout-enter-active,
.flyout-leave-active {
  transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.flyout-enter-from,
.flyout-leave-to {
  opacity: 0;
  transform: translateY(6px) scale(0.96);
}

.flyout-item {
  @apply flex items-center gap-3 w-full px-2 py-2 rounded-xl text-sm font-medium text-dark-50 hover:bg-white/[0.06] hover:text-white transition-all duration-150 text-left;
}

.icon-wrap {
  @apply w-7 h-7 rounded-lg bg-white/[0.06] flex items-center justify-center flex-shrink-0 text-dark-50;
}
.scrollbar-thin::-webkit-scrollbar {
  width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgba(255,255,255,0.1);
  border-radius: 2px;
}
</style>
