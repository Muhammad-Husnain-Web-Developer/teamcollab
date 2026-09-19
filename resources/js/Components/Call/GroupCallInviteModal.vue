<template>
  <Modal :show="show" title="Start a call" size="md" @close="close">
    <div class="px-6 py-4 space-y-4">
      <!-- Call type -->
      <div class="grid grid-cols-2 gap-2">
        <button
          v-for="t in types"
          :key="t.value"
          type="button"
          class="flex items-center gap-2.5 px-4 py-3 rounded-xl border transition-all text-left"
          :class="callType === t.value
            ? 'border-brand-500/60 bg-brand-500/10'
            : 'border-white/[0.08] hover:border-white/[0.16] hover:bg-white/[0.04]'"
          @click="callType = t.value"
        >
          <svg class="w-4 h-4 text-brand-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="t.icon" />
          </svg>
          <span class="text-sm font-medium text-white">{{ t.label }}</span>
        </button>
      </div>

      <!-- Search -->
      <div class="relative">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-dark-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input v-model="search" type="text" placeholder="Search people…" class="input-field pl-10" autofocus />
      </div>

      <!-- Member list -->
      <div v-if="filtered.length" class="max-h-64 overflow-y-auto rounded-xl border border-white/[0.07] divide-y divide-white/[0.06]">
        <button
          v-for="member in filtered"
          :key="member.id"
          type="button"
          class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-white/[0.05] transition-colors"
          @click="toggle(member)"
        >
          <div
            class="w-5 h-5 rounded-md border flex items-center justify-center flex-shrink-0"
            :class="isSelected(member) ? 'bg-brand-500 border-brand-500' : 'border-white/20'"
          >
            <svg v-if="isSelected(member)" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <Avatar :src="member.avatar_url" :name="member.display_name || member.name" :status="presenceStore.getStatus(member.id)" size="sm" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-dark-50 truncate">{{ member.display_name || member.name }}</p>
          </div>
        </button>
      </div>
      <div v-else class="empty-state !py-8">
        <p class="text-dark-50/60 text-sm">No one matches "{{ search }}".</p>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-between px-6 py-4 border-t border-white/[0.07]">
        <span class="text-xs text-dark-50/50">
          {{ selected.length }} of {{ maxSelectable }} selected
        </span>
        <button type="button" class="btn-primary" :disabled="!selected.length || starting" @click="start">
          {{ starting ? 'Starting…' : 'Start call' }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from '../Common/Modal.vue';
import Avatar from '../Common/Avatar.vue';
import { useCallStore } from '../../Stores/useCallStore';
import { usePresenceStore } from '../../Stores/usePresenceStore';
import { useUIStore } from '../../Stores/useUIStore';

const props = defineProps({
  show: { type: Boolean, default: false },
  channel: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const page = usePage();
const callStore = useCallStore();
const presenceStore = usePresenceStore();
const uiStore = useUIStore();

// Mesh cap is 6 including me → up to 5 invitees.
const maxSelectable = 5;

const types = [
  { value: 'audio', label: 'Audio call', icon: 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z' },
  { value: 'video', label: 'Video call', icon: 'M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z' },
];

const callType = ref('video');
const search = ref('');
const selected = ref([]);
const starting = ref(false);

const members = computed(() => page.props.workspaceMembers ?? []);

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase();
  const sorted = [...members.value].sort((a, b) =>
    (a.display_name || a.name || '').localeCompare(b.display_name || b.name || ''),
  );
  if (!term) return sorted;
  return sorted.filter(m => (m.display_name || m.name || '').toLowerCase().includes(term));
});

function isSelected(member) {
  return selected.value.some(m => m.id === member.id);
}

function toggle(member) {
  if (isSelected(member)) {
    selected.value = selected.value.filter(m => m.id !== member.id);
    return;
  }
  if (selected.value.length >= maxSelectable) {
    uiStore.toastWarning(`Calls are limited to ${maxSelectable + 1} participants.`);
    return;
  }
  selected.value = [...selected.value, member];
}

async function start() {
  if (!selected.value.length || starting.value) return;
  starting.value = true;
  try {
    const context = props.channel?.id
      ? { context_type: 'channel', context_id: props.channel.id }
      : { context_type: 'adhoc' };
    await callStore.initiateCall(selected.value, callType.value, context);
    close();
  } finally {
    starting.value = false;
  }
}

function close() {
  emit('close');
}

watch(() => props.show, open => {
  if (open) {
    search.value = '';
    selected.value = [];
    callType.value = 'video';
  }
});
</script>
