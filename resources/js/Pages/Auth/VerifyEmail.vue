<template>
  <AuthLayout>
    <div>
      <div class="mb-8 text-center">
        <div class="w-16 h-16 rounded-2xl bg-white/[0.04] border border-white/[0.08] flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <h2 class="text-2xl font-bold text-white mb-2">Check your email</h2>
        <p class="text-dark-50/70 text-sm leading-relaxed max-w-xs mx-auto">
          We sent a verification link to your email address. Click the link to activate your account.
        </p>
      </div>

      <!-- Status message -->
      <div
        v-if="page.props.status === 'verification-link-sent'"
        class="mb-6 rounded-lg bg-green-500/10 border border-green-500/20 px-4 py-3 flex items-center gap-3"
      >
        <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <p class="text-sm text-green-400">A new verification link has been sent to your email.</p>
      </div>

      <div class="space-y-3">
        <!-- Resend button -->
        <form @submit.prevent="resend">
          <button
            type="submit"
            :disabled="resendForm.processing"
            class="btn-primary w-full"
          >
            <svg v-if="resendForm.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            {{ resendForm.processing ? 'Sending…' : 'Resend verification email' }}
          </button>
        </form>

        <!-- Logout button -->
        <form @submit.prevent="logout">
          <button
            type="submit"
            class="btn-ghost w-full"
          >
            Sign out
          </button>
        </form>
      </div>

      <p class="mt-6 text-center text-xs text-dark-100">
        Didn't receive the email? Check your spam folder or try a different email address.
      </p>
    </div>
  </AuthLayout>
</template>

<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const page = usePage();

const resendForm = useForm({});
const logoutForm = useForm({});

function resend() {
  resendForm.post('/email/resend');
}

function logout() {
  logoutForm.post('/logout');
}
</script>
