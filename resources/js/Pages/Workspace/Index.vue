<template>
  <div class="relative min-h-screen bg-dark-800 flex flex-col items-center justify-center p-6 overflow-hidden isolate">
    <Atmosphere />

    <div class="relative z-10 w-full max-w-md">
      <!-- Header -->
      <div class="mb-10 text-center">
        <div class="w-14 h-14 rounded-2xl bg-brand-500/10 border border-brand-500/20 flex items-center justify-center mx-auto mb-4 shadow-glow-brand">
          <svg class="w-7 h-7 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        </div>
        <h1 class="text-3xl font-bold text-white tracking-tight">
          Your <span class="gradient-text">Workspaces</span>
        </h1>
        <p class="text-dark-50/70 mt-1.5 text-sm">Select a workspace to continue</p>
      </div>

      <!-- Workspace list -->
      <div class="w-full space-y-3">
        <template v-if="tenants.length">
          <a
            v-for="tenant in tenants"
            :key="tenant.id"
            :href="tenantUrl(tenant)"
            class="surface-card-hover flex items-center gap-4 group"
          >
            <!-- Logo / Avatar -->
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-brand-500/25 to-brand-500/5 border border-brand-500/25 flex items-center justify-center flex-shrink-0 overflow-hidden">
              <img v-if="tenant.logo_url" :src="tenant.logo_url" :alt="tenant.name" class="w-10 h-10 rounded-lg object-cover" />
              <span v-else class="text-brand-400 text-lg font-bold">{{ tenant.name[0].toUpperCase() }}</span>
            </div>

            <div class="flex-1 min-w-0">
              <p class="text-white font-semibold truncate group-hover:text-brand-300 transition-colors">{{ tenant.name }}</p>
              <p class="text-dark-50/60 text-xs mt-0.5">{{ tenant.domain }}</p>
            </div>

            <svg class="w-5 h-5 text-dark-100 group-hover:text-brand-400 group-hover:translate-x-0.5 transition-all duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </a>
        </template>

        <!-- Empty state -->
        <div v-else class="empty-state !py-10">
          <div class="empty-state-icon">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
          </div>
          <p class="text-dark-50/70 text-sm">You don't have any workspaces yet.</p>
        </div>

        <!-- Create new workspace -->
        <Link
          :href="route('workspaces.create')"
          class="flex items-center justify-center gap-2 w-full p-4 rounded-2xl border border-dashed border-white/[0.14] hover:border-brand-500/50 text-dark-50/70 hover:text-brand-400 hover:bg-white/[0.02] transition-all duration-200 text-sm font-medium"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
          </svg>
          Create a new workspace
        </Link>
      </div>

      <!-- Logout -->
      <form @submit.prevent="logout" class="mt-8 text-center">
        <button type="submit" class="text-dark-50/50 hover:text-dark-50 text-xs transition-colors">
          Sign out
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
  tenants: {
    type: Array,
    default: () => [],
  },
});

const logoutForm = useForm({});

function logout() {
  logoutForm.post(route('logout'));
}

function tenantUrl(tenant) {
  const port = window.location.port ? `:${window.location.port}` : '';
  return `${window.location.protocol}//${tenant.domain}${port}`;
}
</script>
