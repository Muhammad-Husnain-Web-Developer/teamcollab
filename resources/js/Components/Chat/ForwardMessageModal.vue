<template>
  <Modal :show="show" title="Forward message" size="md" @close="close">
    <div class="px-6 py-4 space-y-4">
      <!-- Preview of the message being forwarded -->
      <div v-if="message" class="px-3 py-2.5 rounded-xl border-l-2 border-brand-400 bg-white/[0.04] text-xs">
        <p class="font-semibold text-brand-400 mb-0.5">
          {{ message.user?.display_name || message.user?.name || 'Unknown' }}
        </p>
        <p class="text-dark-50/70 line-clamp-2">{{ message.body || '📎 Attachment' }}</p>
      </div>

      <!-- Search -->
      <div class="relative">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-dark-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="search"
          type="text"
          placeholder="Search channels or people…"
          class="input-field pl-10"
          autofocus
        />
      </div>

      <!-- Target list -->
      <div v-if="filteredTargets.length" class="max-h-64 overflow-y-auto rounded-xl border border-white/[0.07] divide-y divide-white/[0.06]">
        <button
          v-for="target in filteredTargets"
          :key="target.key"
          type="button"
          class="w-full flex items-center gap-3 px-3 py-2.5 text-left hover:bg-white/[0.05] transition-colors"
          @click="toggleTarget(target)"
        >
          <div
            class="w-5 h-5 rounded-md border flex items-center justify-center flex-shrink-0"
            :class="isSelected(target) ? 'bg-brand-500 border-brand-500' : 'border-white/20'"
          >
            <svg v-if="isSelected(target)" class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
          </div>

          <template v-if="target.type === 'channel'">
            <span class="w-8 h-8 rounded-lg bg-white/[0.06] flex items-center justify-center text-dark-100 flex-shrink-0">#</span>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-dark-50 truncate">{{ target.name }}</p>
              <p class="text-xs text-dark-50/50">{{ target.channelType === 'private' ? 'Private channel' : 'Channel' }}</p>
            </div>
          </template>
          <template v-else>
            <Avatar :src="target.avatar_url" :name="target.name" size="sm" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-dark-50 truncate">{{ target.name }}</p>
              <p class="text-xs text-dark-50/50">Direct message</p>
            </div>
          </template>
        </button>
      </div>

      <div v-else class="empty-state !py-8">
        <p class="text-dark-50/60 text-sm">No matches for "{{ search }}".</p>
      </div>

      <!-- Optional comment -->
      <textarea
        v-model="comment"
        rows="2"
        maxlength="2000"
        placeholder="Add a comment (optional)…"
        class="input-field resize-none"
      />
    </div>

    <template #footer>
      <div class="flex items-center justify-between px-6 py-4 border-t border-white/[0.07]">
        <span class="text-xs text-dark-50/50">
          {{ selected.length }} selected
        </span>
        <button
          type="button"
          class="btn-primary"
          :disabled="!selected.length || sending"
          @click="submit"
        >
          {{ sending ? 'Forwarding…' : 'Forward' }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Modal from '../Common/Modal.vue';
import Avatar from '../Common/Avatar.vue';
import { useUIStore } from '../../Stores/useUIStore';

const props = defineProps({
  show: { type: Boolean, default: false },
  message: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const page = usePage();
const uiStore = useUIStore();

const search = ref('');
const comment = ref('');
const selected = ref([]);
const sending = ref(false);

const channels = computed(() => page.props.channels ?? []);
const members = computed(() => page.props.workspaceMembers ?? []);

const targets = computed(() => {
  const channelTargets = channels.value.map(c => ({
    key: `channel:${c.id}`,
    type: 'channel',
    id: c.id,
    name: c.name,
    channelType: c.type,
  }));

  const userTargets = members.value.map(m => ({
    key: `user:${m.id}`,
    type: 'user',
    id: m.id,
    name: m.display_name || m.name,
    avatar_url: m.avatar_url,
  }));

  return [...channelTargets, ...userTargets];
});

const filteredTargets = computed(() => {
  const term = search.value.trim().toLowerCase();
  if (!term) return targets.value;
  return targets.value.filter(t => t.name?.toLowerCase().includes(term));
});

function isSelected(target) {
  return selected.value.some(t => t.key === target.key);
}

function toggleTarget(target) {
  if (isSelected(target)) {
    selected.value = selected.value.filter(t => t.key !== target.key);
  } else {
    if (selected.value.length >= 10) {
      uiStore.toastWarning('You can forward to at most 10 targets at once.');
      return;
    }
    selected.value = [...selected.value, target];
  }
}

async function submit() {
  if (!props.message || !selected.value.length || sending.value) return;

  sending.value = true;
  try {
    const channel_ids = selected.value.filter(t => t.type === 'channel').map(t => t.id);
    const user_ids = selected.value.filter(t => t.type === 'user').map(t => t.id);

    await axios.post(`/messages/${props.message.id}/forward`, {
      channel_ids,
      user_ids,
      comment: comment.value.trim() || null,
    });

    uiStore.toastSuccess('Message forwarded.');
    close();
  } catch (e) {
    uiStore.toastError(e.response?.data?.message ?? 'Failed to forward message.');
  } finally {
    sending.value = false;
  }
}

function close() {
  emit('close');
}

// Reset state each time the modal opens so it never opens on stale picks.
watch(() => props.show, open => {
  if (open) {
    search.value = '';
    comment.value = '';
    selected.value = [];
  }
});
</script>
