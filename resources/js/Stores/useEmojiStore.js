import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useEmojiStore = defineStore('emoji', () => {
    const emojis = ref([]); // [{id, shortcode, kind, image_url}]
    const loaded = ref(false);
    const loading = ref(false);

    async function ensureLoaded() {
        if (loaded.value || loading.value) return;
        loading.value = true;
        try {
            const { data } = await axios.get('/emojis');
            emojis.value = data.emojis ?? [];
            loaded.value = true;
        } catch {
            // Custom emoji are a nice-to-have — fail silently, unicode picker still works.
        } finally {
            loading.value = false;
        }
    }

    function addEmoji(emoji) {
        emojis.value = [...emojis.value.filter(e => e.id !== emoji.id), emoji]
            .sort((a, b) => a.shortcode.localeCompare(b.shortcode));
    }

    function removeEmoji(id) {
        emojis.value = emojis.value.filter(e => e.id !== id);
    }

    const emojiList = computed(() => emojis.value.filter(e => e.kind === 'emoji'));
    const stickerList = computed(() => emojis.value.filter(e => e.kind === 'sticker'));

    // Map<shortcode, image_url> — used by messageFormat.js to render :shortcode: tokens.
    const byShortcode = computed(() => {
        const map = new Map();
        emojis.value.forEach(e => map.set(e.shortcode, e.image_url));
        return map;
    });

    return {
        emojis, loaded, loading,
        ensureLoaded, addEmoji, removeEmoji,
        emojiList, stickerList, byShortcode,
    };
});
