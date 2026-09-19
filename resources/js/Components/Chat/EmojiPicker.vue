<template>
  <div class="emoji-pop glass-elevated p-2 w-64" @click.stop>
    <div class="flex items-center gap-1 mb-1.5 px-0.5">
      <button
        v-for="tab in tabs"
        :key="tab.id"
        @click="activeTab = tab.id"
        type="button"
        class="flex-1 text-[11px] font-medium px-2 py-1 rounded-md transition-colors"
        :class="activeTab === tab.id ? 'bg-brand-500/15 text-brand-300' : 'text-dark-50/60 hover:text-white hover:bg-white/[0.06]'"
      >
        {{ tab.label }}
      </button>
    </div>

    <div class="grid grid-cols-8 gap-0.5 max-h-48 overflow-y-auto">
      <template v-if="activeTab === 'unicode'">
        <button
          v-for="emoji in UNICODE_EMOJIS"
          :key="emoji"
          @click="$emit('select', emoji)"
          type="button"
          class="w-7 h-7 flex items-center justify-center text-base rounded-lg hover:bg-white/[0.08] transition-colors"
        >
          {{ emoji }}
        </button>
      </template>

      <template v-else>
        <button
          v-for="e in emojiStore.emojiList"
          :key="e.id"
          @click="$emit('select', `:${e.shortcode}:`)"
          type="button"
          class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-white/[0.08] transition-colors"
          :title="e.shortcode"
        >
          <img :src="e.image_url" :alt="e.shortcode" class="w-5 h-5 object-contain" loading="lazy" />
        </button>
        <p v-if="!emojiStore.emojiList.length" class="col-span-8 text-center text-[11px] text-dark-50/40 py-3">
          No custom emoji yet.
        </p>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { UNICODE_EMOJIS } from '../../Utils/emojis';
import { useEmojiStore } from '../../Stores/useEmojiStore';

defineEmits(['select']);

const emojiStore = useEmojiStore();
const activeTab = ref('unicode');

const tabs = [
  { id: 'unicode', label: 'Emoji' },
  { id: 'custom', label: 'Custom' },
];

onMounted(() => {
  emojiStore.ensureLoaded();
});
</script>
