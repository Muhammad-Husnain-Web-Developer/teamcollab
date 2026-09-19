<template>
  <div class="gif-pop glass-elevated w-80 max-h-96 flex flex-col" @click.stop>
    <div class="flex items-center gap-1 p-2 border-b border-white/[0.06] flex-shrink-0">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        type="button"
        class="flex-1 text-[11px] font-medium px-2 py-1.5 rounded-md transition-colors"
        :class="activeTab === tab.id ? 'bg-brand-500/15 text-brand-300' : 'text-dark-50/60 hover:text-white hover:bg-white/[0.06]'"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- GIFs tab -->
    <template v-if="activeTab === 'gifs'">
      <div class="p-2 border-b border-white/[0.06] flex-shrink-0">
        <input
          v-model="query"
          @input="onSearchInput"
          type="text"
          placeholder="Search GIFs…"
          class="input-field !py-1.5 text-xs"
        />
      </div>

      <div class="flex-1 overflow-y-auto p-2 min-h-[140px]">
        <p v-if="!configured" class="text-center text-[11px] text-dark-50/50 py-6 px-3 leading-relaxed">
          GIF search isn't set up yet — ask a workspace admin to add a Giphy API key.
        </p>
        <div v-else-if="loading" class="text-center text-[11px] text-dark-50/40 py-6">Loading…</div>
        <div v-else-if="!gifs.length" class="text-center text-[11px] text-dark-50/40 py-6">
          {{ query.trim() ? 'No GIFs found.' : 'No trending GIFs right now.' }}
        </div>
        <div v-else class="grid grid-cols-2 gap-1.5">
          <button
            v-for="gif in gifs"
            :key="gif.id"
            @click="$emit('select-gif', gif)"
            type="button"
            class="rounded-lg overflow-hidden hover:ring-2 hover:ring-brand-400 transition-all bg-white/[0.03]"
          >
            <img :src="gif.preview_url" :alt="gif.title" class="w-full h-20 object-cover" loading="lazy" />
          </button>
        </div>
      </div>
    </template>

    <!-- Stickers tab -->
    <div v-else class="flex-1 overflow-y-auto p-2 min-h-[140px]">
      <div v-if="emojiStore.stickerList.length" class="grid grid-cols-4 gap-1.5">
        <button
          v-for="sticker in emojiStore.stickerList"
          :key="sticker.id"
          @click="$emit('select-sticker', sticker)"
          type="button"
          class="rounded-lg overflow-hidden hover:bg-white/[0.08] p-1.5 transition-colors aspect-square flex items-center justify-center"
          :title="sticker.shortcode"
        >
          <img :src="sticker.image_url" :alt="sticker.shortcode" class="w-full h-full object-contain" loading="lazy" />
        </button>
      </div>
      <p v-else class="text-center text-[11px] text-dark-50/40 py-6">No stickers yet.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { useEmojiStore } from '../../Stores/useEmojiStore';

const props = defineProps({
  initialQuery: { type: String, default: '' },
});

defineEmits(['select-gif', 'select-sticker']);

const emojiStore = useEmojiStore();

const activeTab = ref('gifs');
const tabs = [
  { id: 'gifs', label: 'GIFs' },
  { id: 'stickers', label: 'Stickers' },
];

const query = ref(props.initialQuery);
const gifs = ref([]);
const loading = ref(false);
const configured = ref(true);

async function loadTrending() {
  loading.value = true;
  try {
    const { data } = await axios.get('/gifs/trending');
    configured.value = data.configured ?? true;
    gifs.value = data.gifs ?? [];
  } catch {
    gifs.value = [];
  } finally {
    loading.value = false;
  }
}

async function search() {
  if (!query.value.trim()) {
    await loadTrending();
    return;
  }

  loading.value = true;
  try {
    const { data } = await axios.get('/gifs/search', { params: { q: query.value.trim() } });
    configured.value = data.configured ?? true;
    gifs.value = data.gifs ?? [];
  } catch {
    gifs.value = [];
  } finally {
    loading.value = false;
  }
}

const onSearchInput = useDebounceFn(search, 400);

onMounted(() => {
  if (props.initialQuery) {
    search();
  } else {
    loadTrending();
  }
  emojiStore.ensureLoaded();
});
</script>
