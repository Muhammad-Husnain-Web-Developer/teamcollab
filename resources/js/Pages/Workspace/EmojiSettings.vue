<template>
  <AppLayout>
    <div class="flex-1 overflow-y-auto">
      <div class="sticky top-0 z-10 bg-dark-800/70 backdrop-blur-xl border-b border-white/[0.06] px-6 py-4">
        <h1 class="text-lg font-semibold text-white">Custom Emoji</h1>
        <p class="text-dark-50/70 text-sm">Upload workspace emoji for reactions and messages</p>
      </div>

      <div class="max-w-3xl mx-auto px-6 py-8">
        <CustomEmojiManager />
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { onMounted } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import CustomEmojiManager from '../../Components/Settings/CustomEmojiManager.vue';
import { useEmojiStore } from '../../Stores/useEmojiStore';

const props = defineProps({
  emojis: { type: Array, default: () => [] },
});

const emojiStore = useEmojiStore();

// Seed from the initial Inertia payload so the grid renders immediately
// instead of waiting on a redundant GET /emojis round trip.
onMounted(() => {
  if (!emojiStore.loaded) {
    emojiStore.emojis = props.emojis;
    emojiStore.loaded = true;
  }
});
</script>
