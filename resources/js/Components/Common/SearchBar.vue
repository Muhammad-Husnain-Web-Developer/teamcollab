<template>
  <div class="relative" ref="containerEl">
    <!-- Search trigger / input -->
    <div class="relative">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-dark-100 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <input
        ref="inputEl"
        v-model="query"
        type="text"
        placeholder="Search messages, channels, people…"
        class="input-field !pl-9 !py-2"
        @focus="open = true"
        @input="debouncedSearch"
        @keydown.escape="close"
        @keydown.down.prevent="navigateDown"
        @keydown.up.prevent="navigateUp"
        @keydown.enter.prevent="selectActive"
      />
      <kbd class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] text-dark-50/60 border border-white/10 rounded px-1 py-0.5 hidden sm:block">
        ⌘K
      </kbd>
    </div>

    <!-- Dropdown results -->
    <transition name="search-dropdown">
      <div
        v-if="open && (results.length > 0 || recentSearches.length > 0 || query.length === 0)"
        class="glass-elevated absolute top-full left-0 right-0 z-50 mt-2 overflow-hidden"
      >
        <!-- Recent searches (when no query) -->
        <div v-if="query.length === 0 && recentSearches.length > 0" class="p-3">
          <p class="section-title px-2 mb-2">Recent</p>
          <div class="space-y-0.5">
            <button
              v-for="(recent, i) in recentSearches"
              :key="i"
              @click="setQuery(recent)"
              class="flex items-center gap-2.5 w-full px-2 py-2 rounded-lg hover:bg-white/[0.06] transition-colors text-left"
            >
              <svg class="w-4 h-4 text-dark-100 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span class="text-sm text-dark-50">{{ recent }}</span>
            </button>
          </div>
        </div>

        <!-- Search results -->
        <div v-if="results.length > 0" class="p-2 max-h-[70vh] overflow-y-auto">
          <!-- Messages section -->
          <div v-if="groupedResults.messages?.length" class="mb-2">
            <p class="section-title px-2 py-1">Messages</p>
            <button
              v-for="(msg, i) in groupedResults.messages"
              :key="`msg-${i}`"
              @click="navigateTo(msg)"
              class="flex items-start gap-3 w-full px-2 py-2 rounded-lg hover:bg-white/[0.06] transition-colors text-left"
              :class="activeIndex === globalIndex('messages', i) ? 'bg-white/[0.06]' : ''"
            >
              <svg class="w-4 h-4 text-dark-100 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
              <div class="min-w-0">
                <p class="text-sm text-white truncate">{{ msg.body }}</p>
                <p class="text-xs text-dark-100">in #{{ msg.channel_name }} · {{ msg.user_name }}</p>
              </div>
            </button>
          </div>

          <!-- Channels section -->
          <div v-if="groupedResults.channels?.length" class="mb-2">
            <p class="section-title px-2 py-1">Channels</p>
            <button
              v-for="(ch, i) in groupedResults.channels"
              :key="`ch-${i}`"
              @click="navigateTo(ch)"
              class="flex items-center gap-3 w-full px-2 py-2 rounded-lg hover:bg-white/[0.06] transition-colors text-left"
              :class="activeIndex === globalIndex('channels', i) ? 'bg-white/[0.06]' : ''"
            >
              <span class="text-dark-100 text-sm font-bold flex-shrink-0">#</span>
              <span class="text-sm text-white">{{ ch.name }}</span>
            </button>
          </div>

          <!-- Members section -->
          <div v-if="groupedResults.members?.length">
            <p class="section-title px-2 py-1">People</p>
            <button
              v-for="(member, i) in groupedResults.members"
              :key="`m-${i}`"
              @click="navigateTo(member)"
              class="flex items-center gap-3 w-full px-2 py-2 rounded-lg hover:bg-white/[0.06] transition-colors text-left"
              :class="activeIndex === globalIndex('members', i) ? 'bg-white/[0.06]' : ''"
            >
              <Avatar :src="member.avatar_url ?? member.avatar" :name="member.name" size="xs" />
              <div class="min-w-0">
                <p class="text-sm text-white truncate">{{ member.name }}</p>
                <p class="text-xs text-dark-100 truncate">{{ member.email }}</p>
              </div>
            </button>
          </div>
        </div>

        <!-- Loading -->
        <div v-if="searching" class="p-6 text-center">
          <svg class="animate-spin w-5 h-5 text-dark-100 mx-auto" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
        </div>

        <!-- No results -->
        <div v-if="!searching && query.length > 0 && results.length === 0" class="p-6 text-center">
          <p class="text-dark-100 text-sm">No results for "<span class="text-white">{{ query }}</span>"</p>
        </div>

        <!-- View full results -->
        <div v-if="!searching && query.length > 1" class="border-t border-white/[0.06] p-2">
          <button
            @click="viewAllResults"
            class="flex items-center justify-center gap-1.5 w-full px-2 py-2 rounded-lg text-xs font-medium text-brand-400 hover:bg-white/[0.06] transition-colors"
          >
            View all results for "{{ query }}"
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" /></svg>
          </button>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { onClickOutside } from '@vueuse/core';
import axios from 'axios';
import Avatar from './Avatar.vue';

const query = ref('');
const open = ref(false);
const searching = ref(false);
const results = ref([]);
const activeIndex = ref(0);
const recentSearches = ref(
  JSON.parse(localStorage.getItem('tc_recent_searches') ?? '[]'),
);

const containerEl = ref(null);
const inputEl = ref(null);

const groupedResults = computed(() => {
  return {
    messages: results.value.filter(r => r.type === 'message'),
    channels: results.value.filter(r => r.type === 'channel'),
    members: results.value.filter(r => r.type === 'member'),
  };
});

function globalIndex(type, localIdx) {
  const msgs = groupedResults.value.messages?.length ?? 0;
  const chs = groupedResults.value.channels?.length ?? 0;
  if (type === 'messages') return localIdx;
  if (type === 'channels') return msgs + localIdx;
  return msgs + chs + localIdx;
}

const debouncedSearch = useDebounceFn(async () => {
  if (query.value.trim().length < 2) {
    results.value = [];
    return;
  }
  searching.value = true;
  try {
    const { data } = await axios.get('/search', { params: { q: query.value } });
    results.value = [
      ...(data.messages ?? []).map(m => ({ ...m, type: 'message' })),
      ...(data.channels ?? []).map(c => ({ ...c, type: 'channel' })),
      ...(data.members ?? []).map(m => ({ ...m, type: 'member' })),
    ];
  } catch {
    results.value = [];
  } finally {
    searching.value = false;
  }
}, 300);

function setQuery(q) {
  query.value = q;
  debouncedSearch();
}

function close() {
  open.value = false;
  query.value = '';
  results.value = [];
}

function navigateDown() {
  activeIndex.value = Math.min(activeIndex.value + 1, results.value.length - 1);
}

function navigateUp() {
  activeIndex.value = Math.max(activeIndex.value - 1, 0);
}

function selectActive() {
  const r = results.value[activeIndex.value];
  if (r) navigateTo(r);
}

function navigateTo(result) {
  // Save to recent
  const recents = [query.value, ...recentSearches.value.filter(r => r !== query.value)].slice(0, 5);
  recentSearches.value = recents;
  localStorage.setItem('tc_recent_searches', JSON.stringify(recents));

  close();

  if (result.type === 'message') {
    router.visit(`/channels/${result.channel_id}?message=${result.id}`);
  } else if (result.type === 'channel') {
    router.visit(`/channels/${result.id}`);
  } else if (result.type === 'member') {
    router.visit(`/members/${result.id}`);
  }
}

function viewAllResults() {
  const q = query.value;
  const recents = [q, ...recentSearches.value.filter(r => r !== q)].slice(0, 5);
  recentSearches.value = recents;
  localStorage.setItem('tc_recent_searches', JSON.stringify(recents));
  close();
  router.visit(`/search?q=${encodeURIComponent(q)}`);
}

onClickOutside(containerEl, close);
</script>

<style scoped>
.search-dropdown-enter-active,
.search-dropdown-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.search-dropdown-enter-from,
.search-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.98);
}
</style>
