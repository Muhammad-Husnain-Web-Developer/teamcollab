<template>
  <AuthLayout>
    <div>
      <div class="mb-8">
        <div class="w-12 h-12 rounded-xl bg-white/[0.04] border border-white/[0.08] flex items-center justify-center mb-4">
          <svg class="w-6 h-6 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-white mb-2">Reset your password</h2>
        <p class="text-dark-50/70 text-sm">
          Enter your email and we'll send you a reset link.
        </p>
      </div>

      <!-- Success state -->
      <div
        v-if="page.props.status"
        class="mb-6 rounded-lg bg-green-500/10 border border-green-500/20 px-4 py-3 flex items-start gap-3"
      >
        <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-green-400">{{ page.props.status }}</p>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Email address</label>
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            placeholder="you@company.com"
            class="input-field"
            :class="{ 'border-red-500/70': form.errors.email }"
            required
          />
          <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-400">{{ form.errors.email }}</p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="btn-primary w-full"
        >
          <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ form.processing ? 'Sending…' : 'Send reset link' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-dark-100">
        <Link href="/login" class="text-brand-400 hover:text-brand-300 font-medium inline-flex items-center gap-1 transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
          </svg>
          Back to sign in
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>

<script setup>
import { useForm, Link, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const page = usePage();
const form = useForm({ email: '' });

function submit() {
  form.post('/forgot-password');
}
</script>
