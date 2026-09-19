<template>
  <AppLayout>
    <div class="flex-1 overflow-y-auto">
      <!-- Header -->
      <div class="px-6 py-6 border-b border-white/[0.06]">
        <h1 class="text-2xl font-bold text-white tracking-tight">Search results</h1>
        <p class="text-dark-50/70 text-sm mt-1">
          {{ totalCount }} result{{ totalCount === 1 ? '' : 's' }} for "<span class="text-white font-medium">{{ query }}</span>"
        </p>

        <!-- Type filter tabs -->
        <div class="flex items-center gap-1 mt-4">
          <button
            v-for="tab in tabs"
            :key="tab.key"
            class="px-3.5 py-1.5 rounded-lg text-sm font-medium transition-all"
            :class="activeTab === tab.key ? 'bg-brand-500/15 text-brand-400' : 'text-dark-50/70 hover:bg-white/[0.05] hover:text-white'"
            @click="activeTab = tab.key"
          >
            {{ tab.label }} <span class="opacity-60">({{ tab.count }})</span>
          </button>
        </div>
      </div>

      <div class="p-6">
        <div v-if="totalCount === 0" class="empty-state !py-20">
          <div class="empty-state-icon !w-16 !h-16 !rounded-2xl">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
          </div>
          <p class="text-white font-semibold mb-1">No results found</p>
          <p class="text-dark-50/60 text-sm">Try a different search term.</p>
        </div>

        <div v-else class="space-y-6 max-w-3xl">
          <!-- Messages -->
          <section v-if="(activeTab === 'all' || activeTab === 'messages') && results.messages?.length">
            <p class="section-title mb-3">Messages</p>
            <div class="space-y-2">
              <Link
                v-for="msg in results.messages"
                :key="`m-${msg.id}`"
                :href="`/channels/${msg.channel_id}?message=${msg.id}`"
                class="surface-card-hover !p-4 flex items-start gap-3 block"
              >
                <div class="w-8 h-8 rounded-lg bg-brand-500/15 text-brand-400 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm text-white">{{ msg.body }}</p>
                  <p class="text-xs text-dark-50/50 mt-1">in #{{ msg.channel_name }} · {{ msg.user_name }}</p>
                </div>
              </Link>
            </div>
          </section>

          <!-- Channels -->
          <section v-if="(activeTab === 'all' || activeTab === 'channels') && results.channels?.length">
            <p class="section-title mb-3">Channels</p>
            <div class="space-y-2">
              <Link
                v-for="ch in results.channels"
                :key="`c-${ch.id}`"
                :href="`/channels/${ch.id}`"
                class="surface-card-hover !p-4 flex items-center gap-3 block"
              >
                <div class="w-8 h-8 rounded-lg bg-white/[0.06] text-dark-50 flex items-center justify-center flex-shrink-0 font-bold">#</div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-white">{{ ch.name }}</p>
                  <p v-if="ch.description" class="text-xs text-dark-50/50 truncate">{{ ch.description }}</p>
                </div>
              </Link>
            </div>
          </section>

          <!-- Members -->
          <section v-if="(activeTab === 'all' || activeTab === 'members') && results.members?.length">
            <p class="section-title mb-3">People</p>
            <div class="space-y-2">
              <Link
                v-for="m in results.members"
                :key="`p-${m.id}`"
                :href="`/members/${m.id}`"
                class="surface-card-hover !p-4 flex items-center gap-3 block"
              >
                <Avatar :src="m.avatar" :name="m.name" size="sm" />
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-white">{{ m.name }}</p>
                  <p class="text-xs text-dark-50/50 truncate">{{ m.email }}</p>
                </div>
              </Link>
            </div>
          </section>

          <!-- Files -->
          <section v-if="(activeTab === 'all' || activeTab === 'files') && results.files?.length">
            <p class="section-title mb-3">Files</p>
            <div class="space-y-2">
              <a
                v-for="f in results.files"
                :key="`f-${f.id}`"
                :href="f.url"
                class="surface-card-hover !p-4 flex items-center gap-3 block"
              >
                <div class="w-8 h-8 rounded-lg bg-white/[0.06] text-dark-50 flex items-center justify-center flex-shrink-0">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-medium text-white truncate">{{ f.original_name }}</p>
                  <p class="text-xs text-dark-50/50">{{ f.size_human }}</p>
                </div>
              </a>
            </div>
          </section>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Avatar from '../../Components/Common/Avatar.vue';

const props = defineProps({
  query: { type: String, default: '' },
  results: {
    type: Object,
    default: () => ({ messages: [], channels: [], members: [], files: [] }),
  },
});

const activeTab = ref('all');

const totalCount = computed(() =>
  (props.results.messages?.length ?? 0)
  + (props.results.channels?.length ?? 0)
  + (props.results.members?.length ?? 0)
  + (props.results.files?.length ?? 0),
);

const tabs = computed(() => [
  { key: 'all', label: 'All', count: totalCount.value },
  { key: 'messages', label: 'Messages', count: props.results.messages?.length ?? 0 },
  { key: 'channels', label: 'Channels', count: props.results.channels?.length ?? 0 },
  { key: 'members', label: 'People', count: props.results.members?.length ?? 0 },
  { key: 'files', label: 'Files', count: props.results.files?.length ?? 0 },
]);
</script>
