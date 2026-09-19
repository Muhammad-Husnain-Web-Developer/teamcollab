<template>
  <AppLayout>
    <div class="flex-1 overflow-y-auto">
      <!-- Header -->
      <div class="sticky top-0 z-10 bg-dark-800/70 backdrop-blur-xl border-b border-white/[0.06] px-6 py-4">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-lg font-semibold text-white">Workspace Settings</h1>
            <p class="text-dark-50/70 text-sm">Manage your workspace configuration</p>
          </div>
        </div>

        <!-- Tabs -->
        <nav class="flex gap-1 mt-4">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            class="px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200"
            :class="
              activeTab === tab.id
                ? 'bg-brand-500/15 text-brand-300 ring-1 ring-brand-500/25'
                : 'text-dark-50/60 hover:text-white hover:bg-white/[0.06]'
            "
          >
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <div class="max-w-3xl mx-auto px-6 py-8">
        <!-- General Tab -->
        <div v-if="activeTab === 'general'">
          <form @submit.prevent="saveGeneral" class="space-y-6">
            <div class="glass-card p-6">
              <h3 class="text-base font-semibold text-white mb-4">Workspace Identity</h3>

              <!-- Logo upload -->
              <div class="flex items-start gap-6 mb-6">
                <div class="relative group">
                  <div class="w-20 h-20 rounded-xl bg-white/[0.04] border-2 border-white/[0.1] flex items-center justify-center overflow-hidden">
                    <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="w-full h-full object-cover" />
                    <span v-else class="text-2xl font-bold text-dark-50/60">
                      {{ (generalForm.name?.[0] ?? 'W').toUpperCase() }}
                    </span>
                  </div>
                  <label
                    class="absolute inset-0 rounded-xl bg-black/50 flex items-center justify-center cursor-pointer opacity-0 group-hover:opacity-100 transition-opacity"
                  >
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <input type="file" class="hidden" accept="image/*" @change="handleLogoUpload" />
                  </label>
                </div>
                <div class="text-sm">
                  <p class="text-dark-50 font-medium mb-1">Workspace logo</p>
                  <p class="text-dark-50/60">PNG, JPG, GIF up to 2MB.</p>
                  <p class="text-dark-50/60">Recommended: 256×256px</p>
                </div>
              </div>

              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-dark-50 mb-1.5">Workspace name</label>
                  <input v-model="generalForm.name" type="text" class="input-field" required />
                  <p v-if="generalForm.errors.name" class="mt-1 text-xs text-red-400">{{ generalForm.errors.name }}</p>
                </div>
                <div>
                  <label class="block text-sm font-medium text-dark-50 mb-1.5">Timezone</label>
                  <select v-model="generalForm.timezone" class="input-field">
                    <option v-for="tz in timezones" :key="tz.value" :value="tz.value">{{ tz.label }}</option>
                  </select>
                </div>
                <div>
                  <label class="block text-sm font-medium text-dark-50 mb-1.5">Description <span class="text-dark-100">(optional)</span></label>
                  <textarea
                    v-model="generalForm.description"
                    rows="3"
                    placeholder="What does your team work on?"
                    class="input-field resize-none"
                  />
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <button type="submit" :disabled="generalForm.processing" class="btn-primary">
                <svg v-if="generalForm.processing" class="animate-spin w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                Save changes
              </button>
            </div>
          </form>
        </div>

        <!-- Members Tab -->
        <div v-if="activeTab === 'members'">
          <div class="glass-card p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-base font-semibold text-white">Members ({{ members.length }})</h3>
              <button @click="showInviteModal = true" class="btn-primary btn-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Invite members
              </button>
            </div>

            <div class="space-y-2">
              <div
                v-for="member in members"
                :key="member.id"
                class="flex items-center gap-3 py-3 px-3 rounded-xl hover:bg-white/[0.05] transition-colors"
              >
                <Avatar :src="member.avatar_url" :name="member.name" size="sm" />
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-medium text-white truncate">{{ member.name }}</p>
                  <p class="text-xs text-dark-50/60 truncate">{{ member.email }}</p>
                </div>
                <span class="role-badge" :class="roleBadgeClass(member.role)">
                  {{ member.role }}
                </span>
              </div>
            </div>

            <div v-if="members.length === 0" class="text-center py-8">
              <p class="text-dark-50/60 text-sm">No members found.</p>
            </div>
          </div>
        </div>

        <!-- Danger Zone Tab -->
        <div v-if="activeTab === 'danger'">
          <div class="glass-card p-6 !border-red-500/25">
            <h3 class="text-base font-semibold text-red-400 mb-4">Danger Zone</h3>
            <div class="flex items-center justify-between py-4 border-b border-white/[0.07]">
              <div>
                <p class="text-sm font-medium text-white mb-0.5">Delete this workspace</p>
                <p class="text-xs text-dark-50/60">Once deleted, all data is permanently removed. This action cannot be undone.</p>
              </div>
              <button
                @click="showDeleteConfirm = true"
                class="ml-4 flex-shrink-0 px-4 py-2 bg-red-500/10 hover:bg-red-500/15 text-red-400 hover:text-red-300 border border-red-500/25 hover:border-red-500/40 rounded-xl text-sm font-medium transition-all duration-200"
              >
                Delete workspace
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Invite Modal -->
    <Modal :show="showInviteModal" title="Invite Members" size="md" @close="showInviteModal = false">
      <form @submit.prevent="sendInvites" class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Email addresses</label>
          <textarea
            v-model="inviteEmails"
            rows="4"
            placeholder="alice@example.com&#10;bob@example.com"
            class="input-field resize-none font-mono text-xs"
          />
          <p class="text-xs text-dark-50/50 mt-1">One email per line.</p>
        </div>
        <div class="flex justify-end gap-3">
          <button type="button" @click="showInviteModal = false" class="btn-ghost">Cancel</button>
          <button type="submit" :disabled="inviteForm.processing" class="btn-primary">Send invites</button>
        </div>
      </form>
    </Modal>

    <!-- Delete confirmation modal -->
    <Modal :show="showDeleteConfirm" title="Delete Workspace" size="sm" @close="showDeleteConfirm = false">
      <div class="p-6">
        <p class="text-dark-50/70 text-sm mb-4">
          Type <strong class="text-white">{{ $page.props.currentWorkspace?.name }}</strong> to confirm deletion.
        </p>
        <input v-model="deleteConfirmName" type="text" class="input-field mb-4" placeholder="Type workspace name…" />
        <div class="flex justify-end gap-3">
          <button @click="showDeleteConfirm = false" class="btn-ghost">Cancel</button>
          <button
            @click="deleteWorkspace"
            :disabled="deleteConfirmName !== $page.props.currentWorkspace?.name"
            class="btn-danger"
          >
            Delete permanently
          </button>
        </div>
      </div>
    </Modal>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import Modal from '../../Components/Common/Modal.vue';
import Avatar from '../../Components/Common/Avatar.vue';
import { useUIStore } from '../../Stores/useUIStore';

const page = usePage();
const uiStore = useUIStore();

const props = defineProps({
  workspace: Object,
  members: { type: Array, default: () => [] },
});

const activeTab = ref('general');
const showInviteModal = ref(false);
const showDeleteConfirm = ref(false);
const deleteConfirmName = ref('');
const logoPreview = ref(props.workspace?.logo_url ?? null);
const inviteEmails = ref('');

const tabs = [
  { id: 'general', label: 'General' },
  { id: 'members', label: 'Members' },
  { id: 'roles', label: 'Roles' },
  { id: 'danger', label: 'Danger Zone' },
];

const timezones = [
  { value: 'UTC', label: 'UTC' },
  { value: 'America/New_York', label: 'Eastern Time' },
  { value: 'America/Los_Angeles', label: 'Pacific Time' },
  { value: 'Europe/London', label: 'London' },
  { value: 'Europe/Paris', label: 'Paris' },
  { value: 'Asia/Karachi', label: 'Karachi' },
  { value: 'Asia/Kolkata', label: 'Mumbai' },
  { value: 'Asia/Singapore', label: 'Singapore' },
  { value: 'Asia/Tokyo', label: 'Tokyo' },
];

const generalForm = useForm({
  name: props.workspace?.name ?? '',
  timezone: props.workspace?.timezone ?? 'UTC',
  description: props.workspace?.description ?? '',
  logo: null,
});

const inviteForm = useForm({ emails: [] });

function handleLogoUpload(e) {
  const file = e.target.files[0];
  if (!file) return;
  generalForm.logo = file;
  logoPreview.value = URL.createObjectURL(file);
}

function saveGeneral() {
  generalForm.post(`/workspaces/${props.workspace?.id}`, {
    preserveScroll: true,
    onSuccess: () => uiStore.toastSuccess('Workspace updated successfully'),
  });
}

function sendInvites() {
  const emails = inviteEmails.value
    .split('\n')
    .map(e => e.trim())
    .filter(Boolean);
  inviteForm.emails = emails;
  inviteForm.post(`/workspaces/${props.workspace?.id}/invitations`, {
    onSuccess: () => {
      showInviteModal.value = false;
      inviteEmails.value = '';
      uiStore.toastSuccess(`Invitations sent to ${emails.length} email(s).`);
    },
  });
}

function deleteWorkspace() {
  router.delete(`/workspaces/${props.workspace?.id}`, {
    onSuccess: () => {
      router.visit('/workspaces/create');
    },
  });
}

function roleBadgeClass(role) {
  const map = {
    owner: 'role-owner',
    admin: 'role-admin',
    member: 'role-member',
  };
  return map[role] ?? 'role-member';
}
</script>
