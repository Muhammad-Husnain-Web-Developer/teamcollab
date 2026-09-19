import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useScheduledMessageStore = defineStore('scheduledMessage', () => {
    const items = ref([]);
    const loaded = ref(false);
    const loading = ref(false);

    async function fetchPending() {
        loading.value = true;
        try {
            const { data } = await axios.get('/scheduled-messages');
            items.value = data.scheduled_messages ?? [];
            loaded.value = true;
        } catch {
            // Non-critical — the tray just stays empty/stale on failure.
        } finally {
            loading.value = false;
        }
    }

    function addPending(item) {
        items.value = [...items.value, item].sort(
            (a, b) => new Date(a.scheduled_for) - new Date(b.scheduled_for),
        );
    }

    async function cancel(id) {
        const previous = items.value;
        items.value = items.value.filter(i => i.id !== id);
        try {
            await axios.delete(`/scheduled-messages/${id}`);
        } catch {
            items.value = previous; // restore on failure
            throw new Error('Failed to cancel scheduled message');
        }
    }

    const count = computed(() => items.value.length);

    return { items, loaded, loading, count, fetchPending, addPending, cancel };
});
