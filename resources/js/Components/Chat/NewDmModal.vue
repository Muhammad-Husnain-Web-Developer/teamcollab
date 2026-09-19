<template>
  <Modal :show="show" title="New Direct Message" size="md" @close="close">
    <div class="px-6 py-4 space-y-4">
      <div class="relative">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-dark-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="search"
          type="text"
          placeholder="Search people…"
          class="input-field pl-10"
          autofocus
        />
      </div>

      <div v-if="filteredMembers.length" class="max-h-80 overflow-y-auto rounded-xl border border-white/[0.07] divide-y divide-white/[0.06]">
        <button
          v-for="member in filteredMembers"
          :key="member.id"
          type="button"
          class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-white/[0.05] transition-colors
                 disabled:opacity-50 disabled:cursor-not-allowed"
          :disabled="pendingId === member.id"
          @click="startDM(member)"
        >
          <Avatar
            :src="member.avatar_url"
            :name="member.display_name || member.name"
            :status="presenceStore.getStatus(member.id)"
            size="sm"
          />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-dark-50 truncate">{{ member.display_name || member.name }}</p>
            <p v-if="member.email" class="text-xs text-dark-50/50 truncate">{{ member.email }}</p>
          </div>
          <span v-if="pendingId === member.id" class="text-xs text-brand-400 font-medium flex-shrink-0">
            Opening…
          </span>
        </button>
      </div>

      <div v-else class="empty-state !py-10">
        <div class="empty-state-icon">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <p class="text-dark-50/60 text-sm">
          {{ members.length === 0 ? 'No other members in this workspace yet.' : `No one matches "${search}".` }}
        </p>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import Modal from '../Common/Modal.vue';
import Avatar from '../Common/Avatar.vue';
import { usePresenceStore } from '../../Stores/usePresenceStore';

const props = defineProps({
  show: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();
const presenceStore = usePresenceStore();

const search = ref('');
const pendingId = ref(null);

const members = computed(() => page.props.workspaceMembers ?? []);

const filteredMembers = computed(() => {
  const sorted = [...members.value].sort((a, b) =>
    (a.display_name || a.name || '').localeCompare(b.display_name || b.name || ''),
  );

  const term = search.value.trim().toLowerCase();
  if (!term) return sorted;

  return sorted.filter(m =>
    (m.display_name || m.name || '').toLowerCase().includes(term) ||
    (m.email || '').toLowerCase().includes(term),
  );
});

function startDM(member) {
  if (pendingId.value) return;
  pendingId.value = member.id;

  router.post('/dm', { user_id: member.id }, {
    preserveScroll: true,
    onSuccess: () => close(),
    onFinish: () => { pendingId.value = null; },
  });
}

function close() {
  emit('close');
}

// Reset search each time the modal opens so it never opens on a stale filter.
watch(() => props.show, (open) => {
  if (open) search.value = '';
});
</script>
