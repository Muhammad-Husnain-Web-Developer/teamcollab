<template>
  <div class="min-h-screen bg-dark-800 flex overflow-hidden">
    <!-- Animated gradient mesh background -->
    <div class="absolute inset-0 overflow-hidden pointer-events-none">
      <div class="mesh-blob mesh-blob-1" />
      <div class="mesh-blob mesh-blob-2" />
      <div class="mesh-blob mesh-blob-3" />
    </div>

    <!-- Left side: branding panel -->
    <div
      ref="brandPanel"
      class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-center px-16 xl:px-24 relative z-10"
    >
      <!-- Logo -->
      <div class="mb-12">
        <div class="flex items-center gap-3 mb-3">
          <div class="w-10 h-10 rounded-xl bg-brand-500 flex items-center justify-center shadow-lg shadow-brand-500/30">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
          </div>
          <span class="text-2xl font-bold text-white tracking-tight">TeamCollab</span>
        </div>
        <p class="eyebrow">
          Where teams move faster
        </p>
      </div>

      <!-- Tagline -->
      <h1 class="text-4xl xl:text-5xl font-bold text-white leading-tight mb-6">
        Collaborate without
        <span class="gradient-text">
          limits.
        </span>
      </h1>
      <p class="text-dark-50/70 text-lg leading-relaxed mb-12 max-w-md">
        Real-time messaging, file sharing, and team coordination — all in one beautiful workspace.
      </p>

      <!-- Feature bullets -->
      <div class="space-y-4">
        <div
          v-for="(feature, i) in features"
          :key="i"
          ref="featureItems"
          class="flex items-center gap-4 group"
        >
          <div class="flex-shrink-0 w-9 h-9 rounded-lg bg-white/[0.04] border border-white/[0.08] flex items-center justify-center group-hover:border-brand-500/50 transition-colors duration-200">
            <component :is="feature.icon" class="w-4.5 h-4.5 text-brand-400" />
          </div>
          <span class="text-dark-50 text-sm font-medium">{{ feature.text }}</span>
        </div>
      </div>

      <!-- Social proof -->
      <div class="mt-16 pt-8 border-t border-white/[0.07]">
        <p class="text-dark-50/50 text-xs mb-3">Trusted by teams at</p>
        <div class="flex items-center gap-6 opacity-60">
          <span class="text-white font-bold text-sm tracking-wider">ACME INC</span>
          <span class="text-white font-bold text-sm tracking-wider">TECHCORP</span>
          <span class="text-white font-bold text-sm tracking-wider">DEVCO</span>
        </div>
      </div>
    </div>

    <!-- Right side: form slot -->
    <div
      ref="formPanel"
      class="w-full lg:w-1/2 xl:w-2/5 flex items-center justify-center px-6 py-12 relative z-10"
    >
      <div class="w-full max-w-md">
        <!-- Mobile logo -->
        <div class="lg:hidden flex items-center gap-3 mb-10 justify-center">
          <div class="w-9 h-9 rounded-xl bg-brand-500 flex items-center justify-center">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
          </div>
          <span class="text-xl font-bold text-white">TeamCollab</span>
        </div>

        <!-- Glass card -->
        <div class="glass-elevated p-8">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, h } from 'vue';
import { gsap } from 'gsap';

const brandPanel = ref(null);
const formPanel = ref(null);
const featureItems = ref([]);

// Feature icon components (inline SVGs as render functions)
const CheckIcon = {
  render: () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M5 13l4 4L19 7' })
  ])
};
const BoltIcon = {
  render: () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M13 10V3L4 14h7v7l9-11h-7z' })
  ])
};
const LockIcon = {
  render: () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
    h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z' })
  ])
};

const features = [
  { icon: BoltIcon, text: 'Real-time messaging with sub-100ms delivery' },
  { icon: CheckIcon, text: 'Unlimited channels, threads, and direct messages' },
  { icon: LockIcon, text: 'Enterprise-grade security with end-to-end encryption' },
];

onMounted(() => {
  const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

  tl.fromTo(
    brandPanel.value,
    { opacity: 0, x: -40 },
    { opacity: 1, x: 0, duration: 0.7 },
  )
    .fromTo(
      formPanel.value,
      { opacity: 0, x: 40 },
      { opacity: 1, x: 0, duration: 0.7 },
      '-=0.5',
    )
    .fromTo(
      featureItems.value,
      { opacity: 0, y: 20 },
      { opacity: 1, y: 0, stagger: 0.1, duration: 0.5 },
      '-=0.3',
    );
});
</script>

<style scoped>
.mesh-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  opacity: 0.15;
  animation: drift 20s ease-in-out infinite alternate;
}

.mesh-blob-1 {
  width: 600px;
  height: 600px;
  background: radial-gradient(circle, #5c7cfa, transparent);
  top: -200px;
  left: -150px;
  animation-duration: 18s;
}

.mesh-blob-2 {
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, #7c3aed, transparent);
  bottom: -100px;
  left: 200px;
  animation-duration: 22s;
  animation-delay: -7s;
}

.mesh-blob-3 {
  width: 300px;
  height: 300px;
  background: radial-gradient(circle, #06b6d4, transparent);
  top: 40%;
  right: 10%;
  animation-duration: 15s;
  animation-delay: -3s;
}

@keyframes drift {
  0% { transform: translate(0, 0) scale(1); }
  33% { transform: translate(30px, -20px) scale(1.05); }
  66% { transform: translate(-20px, 30px) scale(0.95); }
  100% { transform: translate(10px, -10px) scale(1.02); }
}
</style>
