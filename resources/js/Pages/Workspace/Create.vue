<template>
  <GuestLayout>
    <div>
      <!-- Step indicator -->
      <div class="flex items-center justify-center mb-8">
        <div class="flex items-center gap-2">
          <div
            v-for="(step, idx) in steps"
            :key="idx"
            class="flex items-center gap-2"
          >
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
              :class="
                idx < currentStep
                  ? 'bg-gradient-to-b from-brand-400 to-brand-600 text-white shadow-glow-brand'
                  : idx === currentStep
                  ? 'bg-brand-500/15 border-2 border-brand-500 text-brand-400'
                  : 'bg-white/[0.04] border border-white/[0.1] text-dark-50/60'
              "
            >
              <svg v-if="idx < currentStep" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
              </svg>
              <span v-else>{{ idx + 1 }}</span>
            </div>
            <div v-if="idx < steps.length - 1" class="w-12 h-px transition-colors duration-300"
              :class="idx < currentStep ? 'bg-brand-500' : 'bg-white/[0.08]'" />
          </div>
        </div>
      </div>

      <!-- Step content wrapper -->
      <div ref="stepContainer">
        <!-- Step 0: Name & Slug -->
        <div v-if="currentStep === 0" class="step-content">
          <div class="mb-6">
            <h2 class="text-xl font-bold text-white mb-1">Name your workspace</h2>
            <p class="text-dark-50/70 text-sm">This is how your team will identify your workspace.</p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-dark-50 mb-1.5">Workspace name</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="Acme Corporation"
                class="input-field"
                :class="{ 'border-red-500/70': form.errors.name }"
                @input="generateSlug"
                required
              />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-dark-50 mb-1.5">
                Workspace URL
              </label>
              <div class="flex rounded-xl overflow-hidden border border-white/[0.09] bg-white/[0.04] focus-within:border-brand-500/70 focus-within:ring-2 focus-within:ring-brand-500/25 transition-all duration-200">
                <span class="px-3.5 py-2.5 bg-white/[0.04] text-dark-50/60 text-sm border-r border-white/[0.08] whitespace-nowrap">
                  app.teamcollab.io/
                </span>
                <input
                  v-model="form.slug"
                  type="text"
                  placeholder="acme-corp"
                  class="flex-1 bg-transparent text-white placeholder-dark-100 text-sm px-3.5 py-2.5 outline-none min-w-0"
                  :class="{ 'border-red-500/70': form.errors.slug }"
                />
              </div>
              <p v-if="form.errors.slug" class="mt-1 text-xs text-red-400">{{ form.errors.slug }}</p>
              <p class="mt-1 text-xs text-dark-50/50">Only lowercase letters, numbers, and hyphens.</p>
            </div>
          </div>
        </div>

        <!-- Step 1: Details -->
        <div v-if="currentStep === 1" class="step-content">
          <div class="mb-6">
            <h2 class="text-xl font-bold text-white mb-1">Tell us about your team</h2>
            <p class="text-dark-50/70 text-sm">Help us personalise your experience.</p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-dark-50 mb-1.5">Timezone</label>
              <select v-model="form.timezone" class="input-field">
                <option value="">Select timezone…</option>
                <option v-for="tz in timezones" :key="tz.value" :value="tz.value">
                  {{ tz.label }}
                </option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-dark-50 mb-1.5">Company size</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  v-for="size in companySizes"
                  :key="size.value"
                  type="button"
                  @click="form.company_size = size.value"
                  class="px-4 py-3 rounded-xl border text-sm font-medium transition-all duration-200 text-left"
                  :class="
                    form.company_size === size.value
                      ? 'bg-brand-500/10 border-brand-500/70 text-brand-400'
                      : 'bg-white/[0.03] border-white/[0.08] text-dark-50 hover:border-white/[0.16] hover:bg-white/[0.05]'
                  "
                >
                  {{ size.label }}
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Success -->
        <div v-if="currentStep === 2" class="step-content text-center py-4">
          <div class="w-20 h-20 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h2 class="text-2xl font-bold text-white mb-2">Workspace created!</h2>
          <p class="text-dark-50/70 text-sm mb-6">
            <span class="text-white font-medium">{{ form.name }}</span> is ready. Let's invite your team.
          </p>
          <div class="flex items-center justify-center gap-3">
            <div class="w-2 h-2 rounded-full bg-brand-500 animate-bounce" style="animation-delay: 0ms" />
            <div class="w-2 h-2 rounded-full bg-brand-500 animate-bounce" style="animation-delay: 150ms" />
            <div class="w-2 h-2 rounded-full bg-brand-500 animate-bounce" style="animation-delay: 300ms" />
          </div>
          <p class="text-dark-50/50 text-xs mt-4">Redirecting to your workspace…</p>
        </div>
      </div>

      <!-- Navigation buttons -->
      <div v-if="currentStep < 2" class="flex items-center justify-between mt-8 pt-6 border-t border-white/[0.07]">
        <button
          v-if="currentStep > 0"
          type="button"
          @click="prevStep"
          class="btn-ghost"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back
        </button>
        <div v-else />

        <button
          type="button"
          @click="nextStep"
          :disabled="form.processing || !canProceed"
          class="btn-primary"
        >
          <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ currentStep === 1 ? 'Create workspace' : 'Continue' }}
          <svg v-if="!form.processing && currentStep < 1" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
          </svg>
        </button>
      </div>
    </div>
  </GuestLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const currentStep = ref(0);
const stepContainer = ref(null);

const steps = [
  { title: 'Name' },
  { title: 'Details' },
  { title: 'Done' },
];

const timezones = [
  { value: 'UTC', label: 'UTC (Coordinated Universal Time)' },
  { value: 'America/New_York', label: 'Eastern Time (US & Canada)' },
  { value: 'America/Chicago', label: 'Central Time (US & Canada)' },
  { value: 'America/Denver', label: 'Mountain Time (US & Canada)' },
  { value: 'America/Los_Angeles', label: 'Pacific Time (US & Canada)' },
  { value: 'Europe/London', label: 'London' },
  { value: 'Europe/Paris', label: 'Paris' },
  { value: 'Europe/Berlin', label: 'Berlin' },
  { value: 'Asia/Dubai', label: 'Dubai' },
  { value: 'Asia/Karachi', label: 'Karachi' },
  { value: 'Asia/Kolkata', label: 'Mumbai, Kolkata' },
  { value: 'Asia/Singapore', label: 'Singapore' },
  { value: 'Asia/Tokyo', label: 'Tokyo' },
  { value: 'Australia/Sydney', label: 'Sydney' },
];

const companySizes = [
  { value: '1-10', label: '1 – 10 people' },
  { value: '11-50', label: '11 – 50 people' },
  { value: '51-200', label: '51 – 200 people' },
  { value: '201-1000', label: '201 – 1000 people' },
  { value: '1000+', label: '1000+ people' },
  { value: 'other', label: 'Not sure yet' },
];

const form = useForm({
  name: '',
  slug: '',
  timezone: 'UTC',
  company_size: '',
});

function generateSlug() {
  form.slug = form.name
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9\s-]/g, '')
    .replace(/\s+/g, '-')
    .replace(/-+/g, '-');
}

const canProceed = computed(() => {
  if (currentStep.value === 0) return form.name.length > 0 && form.slug.length > 0;
  if (currentStep.value === 1) return form.timezone.length > 0;
  return true;
});

function animateStep(direction = 1) {
  const el = stepContainer.value;
  if (!el) return;
  gsap.fromTo(
    el,
    { opacity: 0, x: 30 * direction },
    { opacity: 1, x: 0, duration: 0.35, ease: 'power3.out' },
  );
}

function nextStep() {
  if (!canProceed.value) return;

  if (currentStep.value === 1) {
    // Submit form
    form.post('/workspaces', {
      onSuccess: () => {
        currentStep.value = 2;
        animateStep(1);
        setTimeout(() => {
          router.visit('/dashboard');
        }, 2500);
      },
    });
    return;
  }

  currentStep.value++;
  animateStep(1);
}

function prevStep() {
  if (currentStep.value > 0) {
    currentStep.value--;
    animateStep(-1);
  }
}
</script>
