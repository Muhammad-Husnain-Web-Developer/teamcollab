<template>
  <div class="min-h-screen bg-dark-800 flex items-center justify-center px-4 py-12 relative overflow-hidden">
    <Atmosphere />

    <div class="relative z-10 w-full max-w-lg">
      <!-- Logo -->
      <div class="flex items-center justify-center gap-3 mb-8">
        <div class="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center shadow-lg shadow-brand-500/30">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
        </div>
        <span class="text-xl font-bold text-white">TeamCollab</span>
      </div>

      <!-- Content slot -->
      <slot />
    </div>
  </div>
</template>

<script setup>
</script>
