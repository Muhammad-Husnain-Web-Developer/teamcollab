<template>
  <div class="relative inline-flex flex-shrink-0" :class="sizeClass">
    <!-- Image -->
    <img
      v-if="src && !imgError"
      :src="src"
      :alt="name ?? 'Avatar'"
      class="rounded-full object-cover w-full h-full ring-1 ring-white/10"
      @error="imgError = true"
    />
    <!-- Initials fallback -->
    <div
      v-else
      class="rounded-full w-full h-full flex items-center justify-center font-semibold select-none ring-1 ring-white/10"
      :style="initialsStyle"
    >
      <span :class="initialsTextSize">{{ initials }}</span>
    </div>

    <!-- Status dot -->
    <div
      v-if="status"
      class="status-dot absolute"
      :class="[status, statusDotSize]"
    />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  src: { type: String, default: null },
  name: { type: String, default: null },
  size: {
    type: String,
    default: 'md',
    validator: v => ['xs', 'sm', 'md', 'lg', 'xl'].includes(v),
  },
  status: {
    type: String,
    default: null,
    validator: v => v === null || ['online', 'away', 'offline', 'dnd'].includes(v),
  },
});

const imgError = ref(false);

// Dimensions
const sizeClass = computed(() => {
  const map = {
    xs: 'w-6 h-6',
    sm: 'w-8 h-8',
    md: 'w-10 h-10',
    lg: 'w-12 h-12',
    xl: 'w-16 h-16',
  };
  return map[props.size] ?? map.md;
});

const initialsTextSize = computed(() => {
  const map = {
    xs: 'text-[9px]',
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-base',
    xl: 'text-xl',
  };
  return map[props.size] ?? map.md;
});

// Generate initials from name
const initials = computed(() => {
  if (!props.name) return '?';
  return props.name
    .split(' ')
    .map(n => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2);
});

// Generate deterministic background color from name
const initialsStyle = computed(() => {
  const colors = [
    ['#5c7cfa', '#4c6ef5'],
    ['#7c3aed', '#6d28d9'],
    ['#06b6d4', '#0891b2'],
    ['#10b981', '#059669'],
    ['#f59e0b', '#d97706'],
    ['#ef4444', '#dc2626'],
    ['#ec4899', '#db2777'],
    ['#8b5cf6', '#7c3aed'],
  ];
  const str = props.name ?? '?';
  const idx =
    str.split('').reduce((acc, c) => acc + c.charCodeAt(0), 0) % colors.length;
  const [from, to] = colors[idx];
  return {
    background: `linear-gradient(135deg, ${from}, ${to})`,
    color: '#ffffff',
  };
});

const statusDotSize = computed(() => {
  const map = {
    xs: 'w-1.5 h-1.5 -bottom-0 -right-0',
    sm: 'w-2 h-2 bottom-0 right-0',
    md: 'w-2.5 h-2.5 bottom-0 right-0',
    lg: 'w-3 h-3 bottom-0 right-0',
    xl: 'w-3.5 h-3.5 bottom-0.5 right-0.5',
  };
  return map[props.size] ?? map.md;
});
</script>
