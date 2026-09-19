<template>
  <Modal :show="show" :title="`Members of #${channel?.name ?? ''}`" size="md" @close="emit('close')">
    <div class="px-6 py-4 space-y-5">
      <!-- Add someone (admins only) -->
      <section v-if="canManage">
        <label class="section-title block mb-2">
          Add a member
        </label>

        <div v-if="addable.length" class="space-y-2">
          <input
            v-model="search"
            type="text"
            placeholder="Search workspace members…"
            class="input-field"
          />

          <div class="max-h-44 overflow-y-auto rounded-xl border border-white/[0.07] divide-y divide-white/[0.06]">
            <button
              v-for="user in filteredAddable"
              :key="user.id"
              class="w-full flex items-center gap-3 px-3 py-2 text-left hover:bg-white/[0.05] transition-colors
                     disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="pendingId === user.id"
              @click="add(user)"
            >
              <Avatar :src="user.avatar_url" :name="user.display_name || user.name" size="sm" />
              <span class="flex-1 min-w-0 text-sm text-dark-50 truncate">
                {{ user.display_name || user.name }}
              </span>
              <span class="text-xs text-brand-400 font-medium">
                {{ pendingId === user.id ? 'Adding…' : 'Add' }}
              </span>
            </button>

            <p v-if="filteredAddable.length === 0" class="px-3 py-3 text-sm text-dark-50/50">
              No one matches "{{ search }}".
            </p>
          </div>
        </div>

        <p v-else class="text-sm text-dark-50/50">
          Everyone in this workspace is already in the channel.
        </p>
      </section>

      <!-- Current members -->
      <section>
        <label class="section-title block mb-2">
          In this channel ({{ members.length }})
        </label>

        <div v-if="loading" class="space-y-2">
          <SkeletonLoader v-for="n in 3" :key="n" />
        </div>

        <p v-else-if="error" class="text-sm text-red-400">{{ error }}</p>

        <ul v-else class="divide-y divide-white/[0.06] rounded-xl border border-white/[0.07]">
          <li
            v-for="member in members"
            :key="member.id"
            class="flex items-center gap-3 px-3 py-2"
          >
            <Avatar :src="member.avatar_url" :name="member.display_name || member.name" size="sm" />

            <span class="flex-1 min-w-0 text-sm text-dark-50 truncate">
              {{ member.display_name || member.name }}
              <span v-if="member.id === authStore.user?.id" class="text-dark-50/50">(you)</span>
            </span>

            <span
              v-if="member.role === 'admin'"
              class="role-badge role-admin flex-shrink-0"
            >
              Admin
            </span>

            <button
              v-if="canManage && member.id !== channel?.created_by"
              class="flex-shrink-0 p-1.5 rounded-lg text-dark-50/50 hover:text-red-400 hover:bg-white/[0.06]
                     transition-colors disabled:opacity-50"
              :disabled="pendingId === member.id"
              title="Remove from channel"
              @click="remove(member)"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </li>
        </ul>

        <p v-if="!canManage && !loading" class="mt-2 text-xs text-dark-50/50">
          Only channel admins can add or remove members.
        </p>
      </section>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '../Common/Modal.vue';
import Avatar from '../Common/Avatar.vue';
import SkeletonLoader from '../Common/SkeletonLoader.vue';
import { useAuthStore } from '../../Stores/useAuthStore';
import { useChannelStore } from '../../Stores/useChannelStore';
import { useUIStore } from '../../Stores/useUIStore';

const props = defineProps({
  show: { type: Boolean, default: false },
  channel: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const authStore = useAuthStore();
const channelStore = useChannelStore();
const uiStore = useUIStore();

const members   = ref([]);
const addable   = ref([]);
const canManage = ref(false);
const loading   = ref(false);
const error     = ref(null);
const search    = ref('');
const pendingId = ref(null);

const filteredAddable = computed(() => {
  const term = search.value.trim().toLowerCase();
  if (!term) return addable.value;

  return addable.value.filter(u =>
    (u.display_name || u.name || '').toLowerCase().includes(term),
  );
});

async function load() {
  if (!props.channel?.id) return;

  loading.value = true;
  error.value = null;

  try {
    const { data } = await axios.get(`/channels/${props.channel.id}/members`);
    members.value   = data.members ?? [];
    addable.value   = data.addable ?? [];
    canManage.value = Boolean(data.can_manage);
  } catch (err) {
    error.value = 'Could not load members.';
  } finally {
    loading.value = false;
  }
}

async function add(user) {
  pendingId.value = user.id;

  try {
    const { data } = await axios.post(`/channels/${props.channel.id}/members`, { user_id: user.id });

    members.value = [...members.value, data.member]
      .sort((a, b) => (a.name || '').localeCompare(b.name || ''));
    addable.value = addable.value.filter(u => u.id !== user.id);

    channelStore.adjustMemberCount(props.channel.id, 1);
    uiStore.toastSuccess(`${user.display_name || user.name} added to the channel.`);
  } catch (err) {
    uiStore.toastError(err.response?.data?.message ?? 'Could not add that member.');
  } finally {
    pendingId.value = null;
  }
}

async function remove(member) {
  pendingId.value = member.id;

  try {
    await axios.delete(`/channels/${props.channel.id}/members/${member.id}`);

    members.value = members.value.filter(m => m.id !== member.id);
    addable.value = [...addable.value, { ...member, role: null }]
      .sort((a, b) => (a.name || '').localeCompare(b.name || ''));

    channelStore.adjustMemberCount(props.channel.id, -1);
    uiStore.toastSuccess(`${member.display_name || member.name} removed.`);
  } catch (err) {
    uiStore.toastError(err.response?.data?.message ?? 'Could not remove that member.');
  } finally {
    pendingId.value = null;
  }
}

// Reload each time the modal opens so the list is never stale.
watch(() => props.show, (open) => {
  if (open) {
    search.value = '';
    load();
  }
});
</script>
