<template>
  <div class="glass-card p-6">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h3 class="text-base font-semibold text-white">Custom Emoji ({{ emojiStore.emojiList.length }})</h3>
        <p class="text-xs text-dark-50/60 mt-0.5">Usable in reactions and messages as <code class="text-brand-300">:shortcode:</code>.</p>
      </div>
    </div>

    <!-- Upload form (admins only) -->
    <form v-if="isAdmin" @submit.prevent="upload" class="flex items-start gap-3 mb-6 p-4 rounded-xl bg-white/[0.03] border border-white/[0.07]">
      <div
        class="w-14 h-14 rounded-lg bg-white/[0.05] border-2 border-dashed border-white/[0.12] flex items-center justify-center flex-shrink-0 cursor-pointer overflow-hidden hover:border-white/25 transition-colors"
        @click="fileInput?.click()"
      >
        <img v-if="preview" :src="preview" class="w-full h-full object-contain" alt="" />
        <svg v-else class="w-5 h-5 text-dark-50/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
        <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleFile" />
      </div>

      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <span class="text-dark-50/50 text-sm">:</span>
          <input
            v-model="shortcode"
            type="text"
            placeholder="party_parrot"
            maxlength="32"
            class="input-field !py-1.5 flex-1"
            pattern="[a-z0-9_]{2,32}"
          />
          <span class="text-dark-50/50 text-sm">:</span>
        </div>
        <p class="text-[11px] text-dark-50/40 mt-1">Lowercase letters, numbers, underscores only. Max 512KB.</p>
        <p v-if="error" class="text-[11px] text-red-400 mt-1">{{ error }}</p>
      </div>

      <button type="submit" class="btn-primary btn-sm flex-shrink-0" :disabled="!canSubmit || uploading">
        {{ uploading ? 'Uploading…' : 'Add' }}
      </button>
    </form>

    <!-- Grid -->
    <div v-if="emojiStore.emojiList.length" class="grid grid-cols-4 sm:grid-cols-6 gap-2">
      <div
        v-for="emoji in emojiStore.emojiList"
        :key="emoji.id"
        class="group relative flex flex-col items-center gap-1 p-2.5 rounded-xl bg-white/[0.03] border border-white/[0.07] hover:bg-white/[0.05] transition-colors"
      >
        <img :src="emoji.image_url" :alt="emoji.shortcode" class="w-8 h-8 object-contain" loading="lazy" />
        <span class="text-[10px] text-dark-50/60 truncate max-w-full">:{{ emoji.shortcode }}:</span>

        <button
          v-if="isAdmin"
          @click="remove(emoji)"
          type="button"
          class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-dark-500 hover:bg-red-500 text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all shadow-md"
          title="Delete"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <div v-else class="text-center py-8">
      <p class="text-dark-50/60 text-sm">No custom emoji yet.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { useEmojiStore } from '../../Stores/useEmojiStore';
import { useUIStore } from '../../Stores/useUIStore';

const page = usePage();
const emojiStore = useEmojiStore();
const uiStore = useUIStore();

const isAdmin = computed(() => Boolean(page.props.is_workspace_admin));

const fileInput = ref(null);
const file = ref(null);
const preview = ref(null);
const shortcode = ref('');
const uploading = ref(false);
const error = ref('');

const canSubmit = computed(() => Boolean(file.value) && /^[a-z0-9_]{2,32}$/.test(shortcode.value));

function handleFile(e) {
  const f = e.target.files?.[0];
  error.value = '';
  if (!f) return;
  if (f.size > 512 * 1024) {
    error.value = 'Image must be under 512KB.';
    e.target.value = '';
    return;
  }
  file.value = f;
  preview.value = URL.createObjectURL(f);
}

async function upload() {
  if (!canSubmit.value || uploading.value) return;

  uploading.value = true;
  error.value = '';
  try {
    const fd = new FormData();
    fd.append('file', file.value);
    fd.append('shortcode', shortcode.value.toLowerCase());

    const { data } = await axios.post('/emojis', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    emojiStore.addEmoji(data.emoji);
    uiStore.toastSuccess(`:${data.emoji.shortcode}: added.`);

    shortcode.value = '';
    file.value = null;
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = null;
    if (fileInput.value) fileInput.value.value = '';
  } catch (e) {
    error.value = e.response?.data?.errors?.shortcode?.[0]
      ?? e.response?.data?.errors?.file?.[0]
      ?? e.response?.data?.message
      ?? 'Upload failed. Please try again.';
  } finally {
    uploading.value = false;
  }
}

async function remove(emoji) {
  if (!confirm(`Remove :${emoji.shortcode}:? Existing messages that use it will stop rendering it.`)) return;
  try {
    await axios.delete(`/emojis/${emoji.id}`);
    emojiStore.removeEmoji(emoji.id);
  } catch {
    uiStore.toastError('Failed to remove emoji.');
  }
}

onMounted(() => {
  emojiStore.ensureLoaded();
});
</script>
