<template>
  <AppLayout>
    <div class="flex-1 overflow-y-auto">
      <!-- Header -->
      <div class="px-6 py-5 border-b border-white/[0.06]">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-xl font-bold text-white">Members</h1>
            <p class="text-dark-50/70 text-sm">{{ filteredMembers.length }} member{{ filteredMembers.length !== 1 ? 's' : '' }} in this workspace</p>
          </div>
          <button class="btn-primary btn-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Invite people
          </button>
        </div>

        <!-- Search + filter row -->
        <div class="flex gap-3 mt-4">
          <div class="relative flex-1">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-dark-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search members…"
              class="input-field pl-10"
            />
          </div>
          <select
            v-model="roleFilter"
            class="input-field !w-auto"
          >
            <option value="">All roles</option>
            <option value="owner">Owner</option>
            <option value="admin">Admin</option>
            <option value="member">Member</option>
          </select>
          <select
            v-model="statusFilter"
            class="input-field !w-auto"
          >
            <option value="">All statuses</option>
            <option value="online">Online</option>
            <option value="away">Away</option>
            <option value="offline">Offline</option>
          </select>
        </div>
      </div>

      <!-- Members grid -->
      <div class="p-6">
        <div
          v-if="filteredMembers.length > 0"
          class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"
        >
          <div
            v-for="member in filteredMembers"
            :key="member.id"
            ref="memberCardRefs"
            @click="openProfile(member)"
            class="surface-card-hover cursor-pointer group"
          >
            <div class="flex flex-col items-center text-center">
              <!-- Avatar with online status -->
              <div class="relative mb-3">
                <Avatar
                  :src="member.avatar_url"
                  :name="member.name"
                  :status="presenceStore.getStatus(member.id)"
                  size="lg"
                />
              </div>

              <!-- Name + role -->
              <p class="font-semibold text-white text-sm mb-1 group-hover:text-brand-300 transition-colors">
                {{ member.name }}
              </p>
              <span class="role-badge mb-2" :class="roleBadgeClass(member.role)">
                {{ member.role }}
              </span>

              <!-- Email -->
              <p class="text-dark-50/60 text-xs truncate max-w-full">{{ member.email }}</p>

              <!-- Status dot + label -->
              <div class="flex items-center gap-1.5 mt-2">
                <div
                  class="w-2 h-2 rounded-full"
                  :class="statusDotClass(presenceStore.getStatus(member.id))"
                />
                <span class="text-xs capitalize" :class="statusTextClass(presenceStore.getStatus(member.id))">
                  {{ presenceStore.getStatus(member.id) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-else class="empty-state">
          <div class="empty-state-icon">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <p class="text-white font-medium mb-1">No members found</p>
          <p class="text-dark-50/60 text-sm">Try adjusting your search or filters.</p>
        </div>
      </div>
    </div>

    <!-- Member profile slide-over -->
    <transition name="slideover">
      <div
        v-if="selectedMember"
        class="fixed inset-0 z-50 flex justify-end"
      >
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="selectedMember = null" />
        <div class="relative w-80 bg-dark-750/90 backdrop-blur-2xl border-l border-white/[0.09] shadow-glass-lg overflow-y-auto">
          <!-- Close -->
          <button
            @click="selectedMember = null"
            class="icon-btn absolute top-4 right-4 bg-white/[0.05]"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Profile header -->
          <div class="p-6 border-b border-white/[0.07]">
            <div class="flex flex-col items-center text-center">
              <Avatar
                :src="selectedMember.avatar_url"
                :name="selectedMember.name"
                :status="presenceStore.getStatus(selectedMember.id)"
                size="xl"
                class="mb-4"
              />
              <h3 class="text-lg font-bold text-white mb-1">{{ selectedMember.name }}</h3>
              <span class="role-badge mb-2" :class="roleBadgeClass(selectedMember.role)">
                {{ selectedMember.role }}
              </span>
              <p class="text-dark-50/70 text-sm">{{ selectedMember.email }}</p>
            </div>
          </div>

          <!-- Profile details -->
          <div class="p-6 space-y-4">
            <div v-if="selectedMember.title">
              <p class="section-title mb-1">Title</p>
              <p class="text-sm text-dark-50">{{ selectedMember.title }}</p>
            </div>
            <div v-if="selectedMember.timezone">
              <p class="section-title mb-1">Timezone</p>
              <p class="text-sm text-dark-50">{{ selectedMember.timezone }}</p>
            </div>
            <div v-if="selectedMember.joined_at">
              <p class="section-title mb-1">Member since</p>
              <p class="text-sm text-dark-50">{{ formatDate(selectedMember.joined_at) }}</p>
            </div>

            <div class="pt-2">
              <button class="w-full btn-primary text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                Send message
              </button>
            </div>
          </div>
        </div>
      </div>
    </transition>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { gsap } from 'gsap';
import AppLayout from '../../Layouts/AppLayout.vue';
import Avatar from '../../Components/Common/Avatar.vue';
import { usePresenceStore } from '../../Stores/usePresenceStore';

const props = defineProps({
  members: { type: Array, default: () => [] },
});

const presenceStore = usePresenceStore();
const searchQuery = ref('');
const roleFilter = ref('');
const statusFilter = ref('');
const selectedMember = ref(null);
const memberCardRefs = ref([]);

const filteredMembers = computed(() => {
  return props.members.filter(m => {
    const matchSearch =
      !searchQuery.value ||
      m.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      m.email.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchRole = !roleFilter.value || m.role === roleFilter.value;
    const matchStatus =
      !statusFilter.value ||
      presenceStore.getStatus(m.id) === statusFilter.value;
    return matchSearch && matchRole && matchStatus;
  });
});

function openProfile(member) {
  selectedMember.value = member;
}

function roleBadgeClass(role) {
  const map = {
    owner: 'role-owner',
    admin: 'role-admin',
    member: 'role-member',
  };
  return map[role] ?? 'role-member';
}

function statusDotClass(status) {
  const map = {
    online: 'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.6)]',
    away: 'bg-amber-400',
    offline: 'bg-dark-400',
  };
  return map[status] ?? map.offline;
}

function statusTextClass(status) {
  const map = {
    online: 'text-emerald-400',
    away: 'text-amber-400',
    offline: 'text-dark-100',
  };
  return map[status] ?? map.offline;
}

function formatDate(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleDateString('en-US', {
    month: 'long',
    year: 'numeric',
    day: 'numeric',
  });
}

onMounted(() => {
  gsap.fromTo(
    memberCardRefs.value,
    { opacity: 0, y: 20 },
    { opacity: 1, y: 0, stagger: 0.04, duration: 0.4, ease: 'power2.out' },
  );
});
</script>

<style scoped>
.slideover-enter-active,
.slideover-leave-active {
  transition: opacity 0.25s ease;
}
.slideover-enter-from,
.slideover-leave-to {
  opacity: 0;
}
</style>
