<template>
  <AuthLayout>
    <div>
      <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-2">
          {{ isTenant && workspaceName ? `Sign in to ${workspaceName}` : 'Welcome back' }}
        </h2>
        <p class="text-dark-300 text-sm">
          {{ isTenant ? 'Enter your credentials to access the workspace' : 'Sign in to your workspace' }}
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-dark-200 mb-1.5">
            Email address
          </label>
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            placeholder="you@company.com"
            class="input-field"
            :class="{ 'border-red-500/70 focus:border-red-500': form.errors.email }"
            required
          />
          <p v-if="form.errors.email" class="mt-1.5 text-xs text-red-400">
            {{ form.errors.email }}
          </p>
        </div>

        <!-- Password -->
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-sm font-medium text-dark-200">Password</label>
            <Link
              href="/forgot-password"
              class="text-xs text-brand-400 hover:text-brand-300 transition-colors"
            >
              Forgot password?
            </Link>
          </div>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="••••••••"
              class="input-field pr-10"
              :class="{ 'border-red-500/70 focus:border-red-500': form.errors.password }"
              required
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-dark-400 hover:text-dark-200 transition-colors"
            >
              <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            </button>
          </div>
          <p v-if="form.errors.password" class="mt-1.5 text-xs text-red-400">
            {{ form.errors.password }}
          </p>
        </div>

        <!-- Remember me -->
        <div class="flex items-center gap-2">
          <input
            v-model="form.remember"
            id="remember"
            type="checkbox"
            class="w-4 h-4 rounded border-dark-500 bg-dark-600 text-brand-500 focus:ring-brand-500/30 focus:ring-2"
          />
          <label for="remember" class="text-sm text-dark-300 cursor-pointer">
            Keep me signed in for 30 days
          </label>
        </div>

        <!-- General error -->
        <div v-if="form.errors.email || page.props.errors?.error" class="rounded-lg bg-red-500/10 border border-red-500/20 px-4 py-3">
          <p class="text-sm text-red-400">{{ page.props.errors?.error ?? 'Invalid credentials.' }}</p>
        </div>

        <!-- Submit button -->
        <button
          type="submit"
          :disabled="form.processing"
          class="btn-primary w-full"
        >
          <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ form.processing ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <!-- OAuth buttons (central domain only) -->
      <template v-if="!isTenant">
        <div class="flex items-center gap-3 my-6">
          <div class="flex-1 h-px bg-dark-600" />
          <span class="text-dark-400 text-xs">or continue with</span>
          <div class="flex-1 h-px bg-dark-600" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <button class="btn-oauth">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Google
          </button>
          <button class="btn-oauth">
            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/>
            </svg>
            GitHub
          </button>
        </div>
      </template>

      <!-- Register link -->
      <p class="mt-6 text-center text-sm text-dark-400">
        Don't have an account?
        <Link href="/register" class="text-brand-400 hover:text-brand-300 font-medium transition-colors ml-1">
          {{ isTenant ? 'Join this workspace' : 'Create one free' }}
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, Link, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const props = defineProps({
  isTenant: { type: Boolean, default: false },
  workspaceName: { type: String, default: null },
});

const page = usePage();
const showPassword = ref(false);

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

function submit() {
  form.post('/login', {
    onFinish: () => form.reset('password'),
  });
}
</script>

<style scoped>
.input-field {
  @apply w-full bg-dark-600/70 border border-dark-500/70 text-white placeholder-dark-400
         rounded-lg px-3.5 py-2.5 text-sm
         focus:outline-none focus:ring-2 focus:ring-brand-500/40 focus:border-brand-500/70
         transition-all duration-200;
}

.btn-primary {
  @apply flex items-center justify-center gap-2 w-full bg-brand-500 hover:bg-brand-600
         text-white font-semibold text-sm rounded-lg px-4 py-2.5
         transition-all duration-200 shadow-lg shadow-brand-500/25
         disabled:opacity-60 disabled:cursor-not-allowed;
}

.btn-oauth {
  @apply flex items-center justify-center gap-2 w-full bg-dark-600/70 border border-dark-500/70
         text-dark-200 hover:text-white hover:border-dark-400 font-medium text-sm rounded-lg px-4 py-2.5
         transition-all duration-200;
}
</style>
