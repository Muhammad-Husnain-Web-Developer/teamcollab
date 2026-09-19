<template>
  <div class="flex flex-col h-full">
    <div class="px-4 py-3.5 border-b border-white/[0.06] flex items-center gap-2">
      <svg class="w-4 h-4 text-dark-50/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
      <h3 class="section-title">Channel Details</h3>
    </div>

    <div class="flex-1 overflow-y-auto">
      <template v-if="channel">
        <!-- About -->
        <section class="px-4 py-4 border-b border-white/[0.06]">
          <div class="flex items-center gap-2 mb-1.5">
            <span v-if="channel.type === 'private'" class="text-dark-50">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
            </span>
            <span v-else class="text-dark-50 text-lg font-bold leading-none">#</span>
            <h2 class="text-base font-bold text-white truncate">{{ channel.name }}</h2>
          </div>
          <p v-if="channel.topic" class="text-sm text-dark-50/70 leading-relaxed">{{ channel.topic }}</p>
          <p v-else class="text-sm text-dark-50/40 italic">No topic set.</p>
        </section>

        <!-- Members -->
        <section class="px-4 py-4 border-b border-white/[0.06]">
          <div class="flex items-center justify-between mb-3">
            <p class="section-title">Members — {{ channel.members_count ?? members.length }}</p>
          </div>
          <div v-if="loadingMembers" class="space-y-2">
            <SkeletonLoader v-for="n in 3" :key="n" />
          </div>
          <div v-else class="space-y-1">
            <div
              v-for="member in members.slice(0, 6)"
              :key="member.id"
              class="flex items-center gap-2.5 px-1.5 py-1.5 rounded-lg hover:bg-white/[0.05] transition-colors"
            >
              <Avatar :src="member.avatar_url" :name="member.display_name || member.name" size="xs" :status="presenceStore.getStatus(member.id) || member.status" />
              <span class="flex-1 min-w-0 text-sm text-dark-50 truncate">{{ member.display_name || member.name }}</span>
              <span v-if="member.role === 'admin'" class="role-badge role-admin">Admin</span>
            </div>
            <p v-if="members.length === 0" class="text-xs text-dark-50/50 px-1.5 py-1">No members loaded.</p>
          </div>
          <button
            v-if="members.length > 0"
            class="mt-2 text-xs font-medium text-brand-400 hover:text-brand-300 transition-colors"
            @click="openMembersModal"
          >
            View all members
          </button>
        </section>

        <!-- Pinned messages -->
        <section class="px-4 py-4 border-b border-white/[0.06]">
          <p class="section-title mb-3">Pinned Messages</p>
          <div v-if="loadingPinned" class="space-y-2">
            <SkeletonLoader v-for="n in 2" :key="n" />
          </div>
          <div v-else-if="pinned.length" class="space-y-2">
            <div
              v-for="msg in pinned"
              :key="msg.id"
              class="glass-panel p-2.5 cursor-pointer hover:border-white/[0.14] transition-colors"
              @click="threadStore.open(msg)"
            >
              <div class="flex items-center gap-1.5 mb-1">
                <Avatar :src="msg.user?.avatar_url" :name="msg.user?.name" size="xs" />
                <span class="text-xs font-medium text-white truncate">{{ msg.user?.display_name || msg.user?.name }}</span>
              </div>
              <p class="text-xs text-dark-50/70 line-clamp-2">{{ msg.body || '📎 Attachment' }}</p>
            </div>
          </div>
          <div v-else class="empty-state !py-6">
            <div class="empty-state-icon !w-10 !h-10 !mb-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg>
            </div>
            <p class="text-xs text-dark-50/50">No pinned messages yet.</p>
          </div>
        </section>

        <!-- Files -->
        <section class="px-4 py-4">
          <p class="section-title mb-3">Shared Files</p>
          <div v-if="loadingFiles" class="space-y-2">
            <SkeletonLoader v-for="n in 2" :key="n" />
          </div>
          <div v-else-if="files.length" class="space-y-1.5">
            <a
              v-for="file in files"
              :key="file.id"
              :href="file.download_url"
              class="flex items-center gap-2.5 px-1.5 py-1.5 rounded-lg hover:bg-white/[0.05] transition-colors group"
            >
              <div class="w-7 h-7 rounded-md bg-white/[0.06] flex items-center justify-center flex-shrink-0 text-dark-50">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
              </div>
              <span class="flex-1 min-w-0 text-xs text-dark-50 truncate group-hover:text-white">{{ file.original_name }}</span>
            </a>
          </div>
          <p v-else class="text-xs text-dark-50/50">No files shared yet.</p>
          <Link href="/files" class="mt-2 inline-block text-xs font-medium text-brand-400 hover:text-brand-300 transition-colors">
            Browse all files
          </Link>
        </section>
      </template>

      <div v-else class="empty-state !py-16">
        <div class="empty-state-icon">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
        </div>
        <p class="text-dark-50/50 text-sm">Select a channel to see its details.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import axios from 'axios';
import Avatar from './Common/Avatar.vue';
import SkeletonLoader from './Common/SkeletonLoader.vue';
import { useChannelStore } from '../Stores/useChannelStore';
import { usePresenceStore } from '../Stores/usePresenceStore';
import { useThreadStore } from '../Stores/useThreadStore';
import { useUIStore } from '../Stores/useUIStore';

const channelStore = useChannelStore();
const presenceStore = usePresenceStore();
const threadStore = useThreadStore();
const uiStore = useUIStore();

const channel = computed(() => channelStore.activeChannel);

const members = ref([]);
const pinned = ref([]);
const files = ref([]);
const loadingMembers = ref(false);
const loadingPinned = ref(false);
const loadingFiles = ref(false);

async function loadPanelData(ch) {
  members.value = [];
  pinned.value = [];
  files.value = [];
  if (!ch?.id) return;

  loadingMembers.value = true;
  loadingPinned.value = true;
  loadingFiles.value = true;

  try {
    const { data } = await axios.get(`/channels/${ch.id}/members`);
    members.value = data.members ?? [];
  } catch {
    // Panel stays empty on failure — non-critical, no toast needed.
  } finally {
    loadingMembers.value = false;
  }

  try {
    const { data } = await axios.get(`/channels/${ch.id}/pinned`);
    pinned.value = data.messages ?? [];
  } catch {
    // Non-critical.
  } finally {
    loadingPinned.value = false;
  }

  try {
    const { data } = await axios.get('/files', { params: { channel_id: ch.id } });
    files.value = data.files ?? [];
  } catch {
    // Non-critical.
  } finally {
    loadingFiles.value = false;
  }
}

watch(channel, (ch) => loadPanelData(ch), { immediate: true });

function openMembersModal() {
  uiStore.openModal('channelMembers', { channel: channel.value });
}
</script>
