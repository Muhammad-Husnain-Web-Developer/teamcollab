<template>
  <div
    ref="cardEl"
    class="surface-card-hover group"
  >
    <!-- Subtle glow -->
    <div class="absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 rounded-2xl"
      :style="glowStyle" />

    <div class="relative z-10">
      <!-- Icon + trend row -->
      <div class="flex items-start justify-between mb-4">
        <!-- Icon circle -->
        <div
          class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
          :class="iconBgClass"
        >
          <!-- Users icon -->
          <svg v-if="icon === 'users'" class="w-5 h-5" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
          </svg>
          <!-- Activity icon -->
          <svg v-else-if="icon === 'activity'" class="w-5 h-5" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
          <!-- Chat icon -->
          <svg v-else-if="icon === 'chat' || icon === 'chat-active'" class="w-5 h-5" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
          </svg>
          <!-- Hash icon -->
          <svg v-else-if="icon === 'hash'" class="w-5 h-5" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
          </svg>
          <!-- Server icon -->
          <svg v-else-if="icon === 'server'" class="w-5 h-5" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
          </svg>
          <!-- Default -->
          <svg v-else class="w-5 h-5" :class="iconColorClass" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
          </svg>
        </div>

        <!-- Trend badge -->
        <div
          v-if="trend != null"
          class="flex items-center gap-1 text-xs font-semibold px-2 py-1 rounded-full"
          :class="trend >= 0 ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              :d="trend >= 0 ? 'M5 10l7-7m0 0l7 7m-7-7v18' : 'M19 14l-7 7m0 0l-7-7m7 7V3'" />
          </svg>
          {{ Math.abs(trend) }}%
        </div>
      </div>

      <!-- Value (animated counter) -->
      <p
        ref="valueEl"
        class="text-2xl font-bold text-white mb-1 tabular-nums"
      >
        {{ displayValue }}
      </p>

      <!-- Title -->
      <p class="text-xs text-dark-50/70 font-medium uppercase tracking-wider">{{ title }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { gsap } from 'gsap';

const props = defineProps({
  title: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: String, default: 'default' },
  color: { type: String, default: 'blue' },
  trend: { type: Number, default: null },
});

const cardEl = ref(null);
const valueEl = ref(null);
const displayValue = ref(typeof props.value === 'number' ? 0 : props.value);

const glowStyle = computed(() => {
  const map = {
    blue: 'radial-gradient(circle at center, rgba(92,124,250,0.06), transparent 70%)',
    green: 'radial-gradient(circle at center, rgba(16,185,129,0.06), transparent 70%)',
    purple: 'radial-gradient(circle at center, rgba(124,58,237,0.06), transparent 70%)',
    cyan: 'radial-gradient(circle at center, rgba(6,182,212,0.06), transparent 70%)',
    yellow: 'radial-gradient(circle at center, rgba(245,158,11,0.06), transparent 70%)',
    orange: 'radial-gradient(circle at center, rgba(249,115,22,0.06), transparent 70%)',
  };
  return `background: ${map[props.color] ?? map.blue}`;
});

const iconBgClass = computed(() => {
  const map = {
    blue: 'bg-brand-500/15',
    green: 'bg-green-500/15',
    purple: 'bg-purple-500/15',
    cyan: 'bg-cyan-500/15',
    yellow: 'bg-yellow-500/15',
    orange: 'bg-orange-500/15',
  };
  return map[props.color] ?? map.blue;
});

const iconColorClass = computed(() => {
  const map = {
    blue: 'text-brand-400',
    green: 'text-green-400',
    purple: 'text-purple-400',
    cyan: 'text-cyan-400',
    yellow: 'text-yellow-400',
    orange: 'text-orange-400',
  };
  return map[props.color] ?? map.blue;
});

// GSAP CountUp animation
onMounted(() => {
  if (cardEl.value) {
    gsap.fromTo(
      cardEl.value,
      { opacity: 0, y: 20 },
      { opacity: 1, y: 0, duration: 0.5, ease: 'power3.out' },
    );
  }

  if (typeof props.value === 'number') {
    const counter = { val: 0 };
    gsap.to(counter, {
      val: props.value,
      duration: 1.2,
      ease: 'power2.out',
      delay: 0.2,
      onUpdate: () => {
        displayValue.value = Math.round(counter.val).toLocaleString();
      },
    });
  }
});

watch(
  () => props.value,
  (newVal, oldVal) => {
    if (typeof newVal === 'number') {
      const counter = { val: typeof oldVal === 'number' ? oldVal : 0 };
      gsap.to(counter, {
        val: newVal,
        duration: 0.8,
        ease: 'power2.out',
        onUpdate: () => {
          displayValue.value = Math.round(counter.val).toLocaleString();
        },
      });
    } else {
      displayValue.value = newVal;
    }
  },
);
</script>
