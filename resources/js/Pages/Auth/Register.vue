<template>
  <AuthLayout>
    <div>
      <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-2">
          {{ isTenant && workspaceName ? `Join ${workspaceName}` : 'Create your account' }}
        </h2>
        <p class="text-dark-50/70 text-sm">
          {{ isTenant ? 'Create an account to access this workspace' : 'Start collaborating with your team today' }}
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <!-- Name -->
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Full name</label>
          <input
            v-model="form.name"
            type="text"
            autocomplete="name"
            placeholder="John Doe"
            class="input-field"
            :class="{ 'border-red-500/70': form.errors.name }"
            required
          />
          <p v-if="form.errors.name" class="mt-1 text-xs text-red-400">{{ form.errors.name }}</p>
        </div>

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Work email</label>
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            placeholder="you@company.com"
            class="input-field"
            :class="{ 'border-red-500/70': form.errors.email }"
            required
          />
          <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
        </div>

        <!-- Password -->
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Password</label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Min. 8 characters"
              class="input-field pr-10"
              :class="{ 'border-red-500/70': form.errors.password }"
              required
              @input="checkPasswordStrength"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-dark-100 hover:text-white transition-colors"
            >
              <svg v-if="!showPassword" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            </button>
          </div>

          <!-- Password strength indicator -->
          <div v-if="form.password" class="mt-2">
            <div class="flex gap-1 mb-1">
              <div
                v-for="i in 4"
                :key="i"
                class="h-1 flex-1 rounded-full transition-all duration-300"
                :class="i <= passwordStrength ? strengthColor : 'bg-white/[0.08]'"
              />
            </div>
            <p class="text-xs" :class="strengthTextColor">{{ strengthLabel }}</p>
          </div>

          <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
        </div>

        <!-- Confirm Password -->
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Confirm password</label>
          <div class="relative">
            <input
              v-model="form.password_confirmation"
              :type="showConfirm ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Re-enter your password"
              class="input-field pr-10"
              :class="{
                'border-red-500/70': form.errors.password_confirmation,
                'border-green-500/70': form.password_confirmation && form.password === form.password_confirmation
              }"
            />
            <button
              type="button"
              @click="showConfirm = !showConfirm"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-dark-100 hover:text-white transition-colors"
            >
              <svg v-if="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
              <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
              </svg>
            </button>
          </div>
          <div v-if="form.password_confirmation && form.password !== form.password_confirmation" class="mt-1 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            <p class="text-xs text-red-400">Passwords do not match</p>
          </div>
          <div v-if="form.password_confirmation && form.password === form.password_confirmation" class="mt-1 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-xs text-green-400">Passwords match</p>
          </div>
        </div>

        <!-- Terms -->
        <p class="text-xs text-dark-100">
          By creating an account, you agree to our
          <a href="#" class="text-brand-400 hover:text-brand-300">Terms of Service</a>
          and
          <a href="#" class="text-brand-400 hover:text-brand-300">Privacy Policy</a>.
        </p>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="form.processing || !isFormValid"
          class="btn-primary w-full mt-2"
        >
          <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ form.processing ? 'Creating account…' : 'Create account' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-dark-100">
        Already have an account?
        <Link href="/login" class="text-brand-400 hover:text-brand-300 font-medium transition-colors ml-1">
          {{ isTenant ? 'Sign in to the workspace' : 'Sign in' }}
        </Link>
      </p>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const props = defineProps({
  isTenant: { type: Boolean, default: false },
  workspaceName: { type: String, default: null },
});

const showPassword = ref(false);
const showConfirm = ref(false);
const passwordStrength = ref(0);

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
});

function checkPasswordStrength() {
  const pwd = form.password;
  let score = 0;
  if (pwd.length >= 8) score++;
  if (/[A-Z]/.test(pwd)) score++;
  if (/[0-9]/.test(pwd)) score++;
  if (/[^A-Za-z0-9]/.test(pwd)) score++;
  passwordStrength.value = score;
}

const strengthColor = computed(() => {
  const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
  return colors[passwordStrength.value - 1] ?? 'bg-dark-600';
});

const strengthTextColor = computed(() => {
  const colors = ['text-red-400', 'text-orange-400', 'text-yellow-400', 'text-green-400'];
  return colors[passwordStrength.value - 1] ?? 'text-dark-100';
});

const strengthLabel = computed(() => {
  const labels = ['Weak', 'Fair', 'Good', 'Strong'];
  return labels[passwordStrength.value - 1] ?? '';
});

const isFormValid = computed(
  () =>
    form.name.length > 0 &&
    form.email.length > 0 &&
    form.password.length >= 8 &&
    form.password === form.password_confirmation,
);

function submit() {
  form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>
