<template>
  <AppLayout>
    <div class="flex-1 overflow-y-auto">
      <div class="max-w-2xl mx-auto py-10 px-6">

        <!-- Page title -->
        <div class="mb-8">
          <h1 class="text-2xl font-bold text-white tracking-tight">Account Settings</h1>
          <p class="text-sm text-dark-50/70 mt-1">Manage your profile, status and security</p>
        </div>

        <!-- ── Avatar + name card ──────────────────────────────────── -->
        <div class="glass-card p-6 mb-6">
          <div class="flex items-center gap-6">
            <!-- Avatar with cropper trigger -->
            <div class="relative group flex-shrink-0">
              <div class="w-20 h-20 rounded-full overflow-hidden bg-gradient-to-br from-brand-500 to-aurora-violet ring-1 ring-white/10">
                <img v-if="localAvatar" :src="localAvatar" class="w-full h-full object-cover" alt="" />
                <span v-else class="flex items-center justify-center w-full h-full text-2xl font-bold text-white">
                  {{ initials }}
                </span>
              </div>
              <button
                type="button"
                class="absolute inset-0 flex flex-col items-center justify-center gap-1 rounded-full bg-black/60 opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity"
                title="Change profile photo"
                @click="showCropper = true"
              >
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="text-white text-[9px] font-semibold">Edit</span>
              </button>
            </div>

            <div class="flex-1 min-w-0">
              <p class="text-lg font-bold text-white truncate">{{ user.display_name || user.name }}</p>
              <p class="text-sm text-dark-50/70">@{{ user.name }}</p>
              <div class="flex items-center gap-1.5 mt-2">
                <span class="w-2 h-2 rounded-full" :class="statusDotColor(user.status)"></span>
                <span class="text-xs text-dark-50/70 capitalize">{{ user.status || 'online' }}</span>
              </div>
            </div>

            <button @click="saveProfile" :disabled="profileForm.processing" class="btn-primary flex-shrink-0">
              <svg v-if="profileForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
              </svg>
              Save Changes
            </button>
          </div>
        </div>

        <!-- Flash message -->
        <div v-if="$page.props.flash?.success" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm mb-6">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          {{ $page.props.flash.success }}
        </div>

        <!-- ── Profile info ─────────────────────────────────────────── -->
        <div class="glass-card p-6 mb-6">
          <h2 class="section-title">Profile Information</h2>

          <div class="space-y-4 mt-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-dark-50/80 mb-1.5">Username</label>
                <input v-model="profileForm.name" type="text" class="input-field" placeholder="username" />
                <p v-if="profileForm.errors.name" class="text-xs text-red-400 mt-1">{{ profileForm.errors.name }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-dark-50/80 mb-1.5">Display Name</label>
                <input v-model="profileForm.display_name" type="text" class="input-field" placeholder="How others see you" />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-dark-50/80 mb-1.5">Bio <span class="text-dark-50/50 font-normal">(optional)</span></label>
              <textarea
                v-model="profileForm.bio"
                rows="3"
                class="input-field resize-none"
                placeholder="Tell your team a little about yourself…"
              ></textarea>
              <p class="text-xs text-dark-50/50 mt-1 text-right">{{ (profileForm.bio || '').length }}/1000</p>
            </div>
          </div>
        </div>

        <!-- ── Status ───────────────────────────────────────────────── -->
        <div class="glass-card p-6 mb-6">
          <h2 class="section-title">Status</h2>

          <div class="grid grid-cols-2 gap-2 mt-4">
            <button
              v-for="s in statusOptions"
              :key="s.value"
              class="flex items-center gap-3 px-4 py-3 rounded-xl border transition-all text-left"
              :class="selectedStatus === s.value
                ? 'border-brand-500/60 bg-brand-500/10'
                : 'border-white/[0.08] hover:border-white/[0.16] hover:bg-white/[0.04]'"
              @click="selectedStatus = s.value"
            >
              <span class="w-3 h-3 rounded-full flex-shrink-0" :class="s.dot"></span>
              <div>
                <p class="text-sm font-medium text-white">{{ s.label }}</p>
                <p class="text-xs text-dark-50/60">{{ s.hint }}</p>
              </div>
              <svg v-if="selectedStatus === s.value" class="w-4 h-4 text-brand-400 ml-auto flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
              </svg>
            </button>
          </div>

          <button @click="saveStatus" :disabled="savingStatus" class="btn-secondary mt-4">
            Update Status
          </button>
        </div>

        <!-- ── Notifications ────────────────────────────────────────── -->
        <div class="glass-card p-6 mb-6">
          <h2 class="section-title">Notifications</h2>

          <div class="flex items-center justify-between mt-4">
            <div class="pr-4">
              <p class="text-sm font-medium text-white">Desktop notifications</p>
              <p class="text-xs text-dark-50/60 mt-0.5">
                Get notified about mentions and DMs even when TeamCollab isn't open.
              </p>
              <p v-if="!push.isSupported" class="text-xs text-amber-400 mt-1">
                Not supported in this browser.
              </p>
              <p v-else-if="!$page.props.vapid_public_key" class="text-xs text-amber-400 mt-1">
                Not set up yet — ask a workspace admin to configure this.
              </p>
            </div>

            <button
              type="button"
              role="switch"
              :aria-checked="push.isSubscribed.value"
              :disabled="!push.isSupported || !$page.props.vapid_public_key || push.loading.value"
              @click="togglePush"
              class="relative flex-shrink-0 w-11 h-6 rounded-full transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
              :class="push.isSubscribed.value ? 'bg-brand-500' : 'bg-white/[0.12]'"
            >
              <span
                class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white transition-transform"
                :class="push.isSubscribed.value ? 'translate-x-5' : ''"
              />
            </button>
          </div>
        </div>

        <!-- ── Change password ─────────────────────────────────────── -->
        <div class="glass-card p-6">
          <h2 class="section-title">Change Password</h2>

          <div class="space-y-4 mt-4">
            <div>
              <label class="block text-sm font-medium text-dark-50/80 mb-1.5">Current Password</label>
              <input v-model="passwordForm.current_password" type="password" class="input-field" placeholder="••••••••" autocomplete="current-password" />
              <p v-if="passwordForm.errors.current_password" class="text-xs text-red-400 mt-1">{{ passwordForm.errors.current_password }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-dark-50/80 mb-1.5">New Password</label>
                <input v-model="passwordForm.password" type="password" class="input-field" placeholder="••••••••" autocomplete="new-password" />
                <p v-if="passwordForm.errors.password" class="text-xs text-red-400 mt-1">{{ passwordForm.errors.password }}</p>
              </div>
              <div>
                <label class="block text-sm font-medium text-dark-50/80 mb-1.5">Confirm Password</label>
                <input v-model="passwordForm.password_confirmation" type="password" class="input-field" placeholder="••••••••" autocomplete="new-password" />
              </div>
            </div>
          </div>

          <button @click="changePassword" :disabled="passwordForm.processing" class="btn-primary mt-5">
            <svg v-if="passwordForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            Change Password
          </button>
        </div>

      </div>
    </div>

    <!-- Avatar Cropper Modal -->
    <AvatarCropModal
      :show="showCropper"
      @close="showCropper = false"
      @uploaded="onAvatarUploaded"
    />
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AvatarCropModal from '../../Components/Common/AvatarCropModal.vue';
import { usePushSubscription } from '../../Composables/usePushSubscription';
import { useUIStore } from '../../Stores/useUIStore';

const props = defineProps({
  user: { type: Object, required: true },
});

const page = usePage();
const uiStore = useUIStore();

// ── Desktop push notifications ──────────────────────────────────────────
const push = usePushSubscription();

onMounted(() => {
  push.checkSubscribed();
});

async function togglePush() {
  if (push.isSubscribed.value) {
    await push.unsubscribe();
    uiStore.toastSuccess('Desktop notifications turned off.');
    return;
  }

  const result = await push.subscribe(page.props.vapid_public_key);
  if (result.ok) {
    uiStore.toastSuccess('Desktop notifications turned on.');
  } else if (result.reason === 'denied') {
    uiStore.toastError('Notification permission denied — allow it in the browser and try again.');
  } else if (result.reason !== 'not_configured' && result.reason !== 'unsupported') {
    uiStore.toastError('Could not enable desktop notifications.');
  }
}

// ── Avatar cropper ────────────────────────────────────────────────────
const showCropper   = ref(false);
const localAvatar   = ref(props.user.avatar_url ?? null);

function onAvatarUploaded(dataUrl) {
  localAvatar.value = dataUrl;
}

// ── Profile form ──────────────────────────────────────────────────────
const profileForm = useForm({
  name:         props.user.name         ?? '',
  display_name: props.user.display_name ?? '',
  bio:          props.user.bio          ?? '',
});

const initials = computed(() => {
  const n = props.user.display_name || props.user.name || '?';
  return n.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
});

function saveProfile() {
  profileForm.put('/profile', { preserveScroll: true });
}

// ── Status ────────────────────────────────────────────────────────────
const statusOptions = [
  { value: 'online',  label: 'Online',          hint: 'Available',         dot: 'bg-emerald-400' },
  { value: 'away',    label: 'Away',             hint: 'Be right back',     dot: 'bg-amber-400' },
  { value: 'busy',    label: 'Do Not Disturb',  hint: 'No notifications',  dot: 'bg-red-400' },
  { value: 'offline', label: 'Appear Offline',  hint: 'Hidden from others',dot: 'bg-dark-300' },
];

const selectedStatus = ref(props.user.status ?? 'online');
const savingStatus   = ref(false);

function statusDotColor(status) {
  const map = {
    online:  'bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,0.6)]',
    away:    'bg-amber-400',
    busy:    'bg-red-400',
    offline: 'bg-dark-300',
  };
  return map[status] ?? map.online;
}

async function saveStatus() {
  savingStatus.value = true;
  router.put('/profile/status', { status: selectedStatus.value }, {
    preserveScroll: true,
    onFinish: () => { savingStatus.value = false; },
  });
}

// ── Password form ─────────────────────────────────────────────────────
const passwordForm = useForm({
  current_password:      '',
  password:              '',
  password_confirmation: '',
});

function changePassword() {
  passwordForm.put('/profile/password', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
  });
}
</script>
