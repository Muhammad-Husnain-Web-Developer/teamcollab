<template>
  <div class="relative" ref="containerEl">
    <!-- Trigger button -->
    <button
      @click="toggleDropdown"
      class="flex items-center gap-2.5 w-full px-3 py-3 hover:bg-white/[0.05] transition-colors border-b border-white/[0.06] group"
    >
      <!-- Logo -->
      <div
        class="w-8 h-8 rounded-lg flex-shrink-0 flex items-center justify-center overflow-hidden font-bold text-sm shadow-lg ring-1 ring-white/10"
        :style="workspaceColors"
      >
        <img v-if="currentWorkspace?.logo_url" :src="currentWorkspace.logo_url" alt="" class="w-full h-full object-cover" />
        <span v-else class="text-white">{{ workspaceInitial }}</span>
      </div>
      <!-- Name -->
      <div class="flex-1 min-w-0 text-left">
        <p class="text-sm font-bold text-white truncate leading-tight">
          {{ currentWorkspace?.name ?? 'Select Workspace' }}
        </p>
        <p class="text-xs text-dark-100 truncate">{{ memberCount }} members</p>
      </div>
      <!-- Chevron -->
      <svg
        class="w-4 h-4 text-dark-100 transition-transform duration-200 flex-shrink-0"
        :class="dropdownOpen ? 'rotate-180' : ''"
        fill="none" stroke="currentColor" viewBox="0 0 24 24"
      >
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
      </svg>
    </button>

    <!-- Dropdown -->
    <transition name="dropdown">
      <div
        v-if="dropdownOpen"
        ref="dropdownEl"
        class="glass-elevated absolute top-full left-0 right-0 z-50 mt-1.5 mx-2 overflow-hidden"
      >
        <!-- Workspace list -->
        <div class="p-1.5 max-h-64 overflow-y-auto">
          <button
            v-for="ws in workspaceStore.workspaces"
            :key="ws.id"
            @click="switchWorkspace(ws)"
            class="flex items-center gap-2.5 w-full px-3 py-2.5 rounded-lg hover:bg-white/[0.06] transition-colors group"
            :class="ws.id === currentWorkspace?.id ? 'bg-brand-500/10' : ''"
          >
            <div
              class="w-7 h-7 rounded-md flex-shrink-0 flex items-center justify-center overflow-hidden font-bold text-xs ring-1 ring-white/10"
              :style="wsColors(ws)"
            >
              <img v-if="ws.logo_url" :src="ws.logo_url" alt="" class="w-full h-full object-cover" />
              <span v-else class="text-white">{{ ws.name?.[0]?.toUpperCase() }}</span>
            </div>
            <div class="flex-1 min-w-0 text-left">
              <p class="text-sm font-medium text-white truncate">{{ ws.name }}</p>
            </div>
            <svg
              v-if="ws.id === currentWorkspace?.id"
              class="w-4 h-4 text-brand-400 flex-shrink-0"
              fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
          </button>
        </div>

        <!-- Divider -->
        <div class="border-t border-white/[0.06] p-1.5">
          <Link
            href="/emoji-settings"
            class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-white/[0.06] text-dark-50 hover:text-white transition-colors text-sm"
          >
            <div class="w-5 h-5 rounded flex items-center justify-center bg-white/[0.06]">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            Custom emoji
          </Link>
          <Link
            href="/workspaces/create"
            class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-white/[0.06] text-dark-50 hover:text-white transition-colors text-sm"
          >
            <div class="w-5 h-5 rounded flex items-center justify-center bg-white/[0.06] border border-dashed border-white/20">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
            </div>
            Create a workspace
          </Link>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useWorkspaceStore } from '../../Stores/useWorkspaceStore';

const workspaceStore = useWorkspaceStore();
const dropdownOpen = ref(false);
const containerEl = ref(null);

const currentWorkspace = computed(() => workspaceStore.currentWorkspace);

const memberCount = computed(
  () => currentWorkspace.value?.members_count ?? 0,
);

const workspaceInitial = computed(
  () => currentWorkspace.value?.name?.[0]?.toUpperCase() ?? 'W',
);

const workspaceColors = computed(() => {
  const colors = [
    'background: linear-gradient(135deg, #5c7cfa, #7c3aed)',
    'background: linear-gradient(135deg, #06b6d4, #0284c7)',
    'background: linear-gradient(135deg, #10b981, #059669)',
    'background: linear-gradient(135deg, #f59e0b, #d97706)',
    'background: linear-gradient(135deg, #ef4444, #dc2626)',
  ];
  const id = currentWorkspace.value?.id ?? 0;
  return colors[id % colors.length];
});

function wsColors(ws) {
  const colors = [
    'background: linear-gradient(135deg, #5c7cfa, #7c3aed)',
    'background: linear-gradient(135deg, #06b6d4, #0284c7)',
    'background: linear-gradient(135deg, #10b981, #059669)',
    'background: linear-gradient(135deg, #f59e0b, #d97706)',
    'background: linear-gradient(135deg, #ef4444, #dc2626)',
  ];
  return colors[(ws.id ?? 0) % colors.length];
}

function toggleDropdown() {
  dropdownOpen.value = !dropdownOpen.value;
}

function switchWorkspace(ws) {
  workspaceStore.setCurrentWorkspace(ws);
  dropdownOpen.value = false;
  router.visit(`/workspaces/${ws.slug ?? ws.id}/dashboard`);
}

function handleClickOutside(e) {
  if (containerEl.value && !containerEl.value.contains(e.target)) {
    dropdownOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.dropdown-enter-active,
.dropdown-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.dropdown-enter-from,
.dropdown-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.97);
}
</style>
