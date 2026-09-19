<template>
  <div class="animate-pulse" :class="wrapperClass">
    <!-- Message skeleton -->
    <template v-if="type === 'message'">
      <div class="flex items-start gap-3 px-4 py-2">
        <div class="w-8 h-8 rounded-full bg-dark-600 flex-shrink-0" />
        <div class="flex-1 space-y-2">
          <div class="flex items-center gap-2">
            <div class="h-3 bg-dark-600 rounded w-24" />
            <div class="h-2.5 bg-dark-700 rounded w-12" />
          </div>
          <div class="h-3 bg-dark-600 rounded w-full max-w-sm" />
          <div class="h-3 bg-dark-600/60 rounded w-3/4" />
        </div>
      </div>
    </template>

    <!-- Channel skeleton -->
    <template v-else-if="type === 'channel'">
      <div class="space-y-1 px-1">
        <div v-for="i in 5" :key="i" class="flex items-center gap-2 px-2 py-2 rounded-lg">
          <div class="w-4 h-3 bg-dark-600 rounded flex-shrink-0" />
          <div class="h-3 bg-dark-600 rounded flex-1" :style="{ width: `${60 + Math.random() * 30}%` }" />
        </div>
      </div>
    </template>

    <!-- Member skeleton -->
    <template v-else-if="type === 'member'">
      <div class="bg-dark-700/40 border border-dark-600/50 rounded-xl p-5">
        <div class="flex flex-col items-center gap-3">
          <div class="w-12 h-12 rounded-full bg-dark-600" />
          <div class="h-3 bg-dark-600 rounded w-24" />
          <div class="h-2.5 bg-dark-600/60 rounded w-16" />
          <div class="h-2.5 bg-dark-600/60 rounded w-32" />
        </div>
      </div>
    </template>

    <!-- Card skeleton -->
    <template v-else-if="type === 'card'">
      <div class="bg-dark-700/40 border border-dark-600/50 rounded-xl p-5 space-y-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-dark-600 flex-shrink-0" />
          <div class="flex-1 space-y-2">
            <div class="h-3 bg-dark-600 rounded w-3/4" />
            <div class="h-2.5 bg-dark-600/60 rounded w-1/2" />
          </div>
        </div>
        <div class="h-6 bg-dark-600 rounded w-1/3" />
      </div>
    </template>

    <!-- Default / generic skeleton -->
    <template v-else>
      <div class="space-y-2">
        <div class="h-4 bg-dark-600 rounded w-full" />
        <div class="h-4 bg-dark-600/60 rounded w-5/6" />
        <div class="h-4 bg-dark-600/40 rounded w-4/5" />
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  type: {
    type: String,
    default: 'default',
    validator: v => ['message', 'channel', 'member', 'card', 'default'].includes(v),
  },
  count: { type: Number, default: 1 },
});

const wrapperClass = computed(() => ({
  'space-y-3': props.type !== 'channel',
}));
</script>

<style scoped>
@keyframes shimmer {
  0% { background-position: -200% 0; }
  100% { background-position: 200% 0; }
}

.animate-pulse div[class*="bg-dark-6"] {
  background-image: linear-gradient(
    90deg,
    #2a2a2e 25%,
    #333338 50%,
    #2a2a2e 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
}
</style>
