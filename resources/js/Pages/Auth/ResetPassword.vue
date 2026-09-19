<template>
  <AuthLayout>
    <div>
      <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-2">Set new password</h2>
        <p class="text-dark-50/70 text-sm">Choose a strong, unique password.</p>
      </div>

      <form @submit.prevent="submit" class="space-y-5">
        <!-- Hidden token -->
        <input type="hidden" :value="form.token" />

        <!-- Email -->
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Email address</label>
          <input
            v-model="form.email"
            type="email"
            autocomplete="email"
            class="input-field"
            :class="{ 'border-red-500/70': form.errors.email }"
            required
          />
          <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
        </div>

        <!-- New Password -->
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">New password</label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Min. 8 characters"
              class="input-field pr-10"
              :class="{ 'border-red-500/70': form.errors.password }"
              required
            />
            <button type="button" @click="showPassword = !showPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-dark-100 hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  :d="showPassword ? 'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21' : 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'"
                />
              </svg>
            </button>
          </div>
          <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
        </div>

        <!-- Confirm Password -->
        <div>
          <label class="block text-sm font-medium text-dark-50 mb-1.5">Confirm new password</label>
          <input
            v-model="form.password_confirmation"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="new-password"
            placeholder="Re-enter password"
            class="input-field"
            required
          />
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="btn-primary w-full"
        >
          <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
          </svg>
          {{ form.processing ? 'Resetting…' : 'Reset password' }}
        </button>
      </form>
    </div>
  </AuthLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Layouts/AuthLayout.vue';

const page = usePage();
const showPassword = ref(false);

const form = useForm({
  token: page.props.token ?? '',
  email: page.props.email ?? '',
  password: '',
  password_confirmation: '',
});

function submit() {
  form.post('/reset-password', {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
}
</script>
