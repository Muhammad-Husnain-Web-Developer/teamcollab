import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

/**
 * Side-panel thread view.
 *
 * A thread is just the replies hanging off one root message, so opening a
 * reply resolves to the same thread as opening its root — the server decides
 * which message is the root.
 */
export const useThreadStore = defineStore('thread', () => {
    const isOpen  = ref(false);
    const root    = ref(null);
    const replies = ref([]);
    const loading = ref(false);
    const sending = ref(false);
    const error   = ref(null);

    const repliesCount = computed(() => replies.value.length);

    async function open(message) {
        if (!message?.id) return;

        isOpen.value  = true;
        loading.value = true;
        error.value   = null;
        // Show the clicked message immediately; the server may swap in its root.
        root.value    = message;
        replies.value = [];

        try {
            const { data } = await axios.get(`/messages/${message.id}/thread`);
            root.value    = data.root;
            replies.value = data.replies ?? [];
        } catch (err) {
            error.value = 'Could not load this thread.';
        } finally {
            loading.value = false;
        }
    }

    function close() {
        isOpen.value  = false;
        root.value    = null;
        replies.value = [];
        error.value   = null;
    }

    async function sendReply(body, files = []) {
        if (!root.value || sending.value) return null;

        sending.value = true;
        try {
            const { data } = await axios.post(`/messages/${root.value.id}/thread`, { body, files });
            // Server echoes the saved reply — append rather than refetching.
            if (data.message) replies.value.push(data.message);
            return data.message;
        } catch (err) {
            error.value = 'Could not send that reply.';
            throw err;
        } finally {
            sending.value = false;
        }
    }

    /**
     * A reply that arrived over Echo. Ignored unless it belongs to the open
     * thread, and de-duplicated against replies we appended ourselves.
     */
    function applyIncomingReply(message) {
        if (!isOpen.value || !root.value) return;
        if (message?.parent_id !== root.value.id) return;
        if (replies.value.some(r => r.id === message.id)) return;

        replies.value.push(message);
    }

    return {
        isOpen, root, replies, loading, sending, error, repliesCount,
        open, close, sendReply, applyIncomingReply,
    };
});
