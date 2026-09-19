import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useMessageStore = defineStore('message', () => {
    // Map<channelId, Message[]>
    const messages = ref(new Map());

    // Map<channelId, Map<userId, {userId, userName, timeout}>>
    const typingUsers = ref(new Map());

    // Map<channelId, {currentPage, lastPage, hasMore}>
    const pagination = ref(new Map());

    const loading = ref(false);
    const sendingMessage = ref(false);

    // ── Message Actions ───────────────────────────────────────────────────
    async function fetchMessages(channelId, page = 1, url = null) {
        loading.value = true;
        try {
            const apiUrl = url ?? `/channels/${channelId}/messages`;
            const { data } = await axios.get(
                apiUrl,
                { params: { page, per_page: 50 } },
            );

            const fetched = data.data ?? data.messages ?? data;
            const meta = data.meta ?? {};

            if (page === 1) {
                messages.value.set(channelId, fetched.reverse());
            } else {
                // Prepend older messages
                const existing = messages.value.get(channelId) ?? [];
                messages.value.set(channelId, [...fetched.reverse(), ...existing]);
            }

            pagination.value.set(channelId, {
                currentPage: meta.current_page ?? page,
                lastPage: meta.last_page ?? 1,
                hasMore: (meta.current_page ?? page) < (meta.last_page ?? 1),
            });
        } catch (err) {
            console.error('[MessageStore] fetchMessages:', err);
        } finally {
            loading.value = false;
        }
    }

    function appendMessage(channelId, msg) {
        const existing = messages.value.get(channelId) ?? [];
        // Avoid duplicates
        if (!existing.find(m => m.id === msg.id)) {
            messages.value.set(channelId, [...existing, msg]);
        }
    }

    function updateMessage(updated) {
        messages.value.forEach((msgs, channelId) => {
            const idx = msgs.findIndex(m => m.id === updated.id);
            if (idx !== -1) {
                const newMsgs = [...msgs];
                newMsgs[idx] = { ...newMsgs[idx], ...updated };
                messages.value.set(channelId, newMsgs);
            }
        });
    }

    /**
     * Bump a parent message's reply counter so its "N replies" link appears or
     * updates without refetching the message.
     */
    function bumpReplyCount(parentId, delta = 1) {
        messages.value.forEach((msgs, channelId) => {
            const idx = msgs.findIndex(m => m.id === parentId);
            if (idx === -1) return;

            const newMsgs = [...msgs];
            const current = newMsgs[idx].replies_count ?? 0;
            newMsgs[idx] = { ...newMsgs[idx], replies_count: Math.max(0, current + delta) };
            messages.value.set(channelId, newMsgs);
        });
    }

    function removeMessage(msgId) {
        messages.value.forEach((msgs, channelId) => {
            const idx = msgs.findIndex(m => m.id === msgId);
            if (idx !== -1) {
                const newMsgs = [...msgs];
                newMsgs[idx] = { ...newMsgs[idx], deleted_at: new Date().toISOString() };
                messages.value.set(channelId, newMsgs);
            }
        });
    }

    // Completely removes a message from the list (used for optimistic rollback)
    function discardOptimistic(tempId) {
        messages.value.forEach((msgs, channelId) => {
            const filtered = msgs.filter(m => m.id !== tempId);
            if (filtered.length !== msgs.length) {
                messages.value.set(channelId, filtered);
            }
        });
    }

    function getMessages(channelId) {
        return messages.value.get(channelId) ?? [];
    }

    function hasMore(channelId) {
        return pagination.value.get(channelId)?.hasMore ?? false;
    }

    // ── Typing Actions ────────────────────────────────────────────────────
    function setTyping(channelId, userId, userName) {
        if (!typingUsers.value.has(channelId)) {
            typingUsers.value.set(channelId, new Map());
        }

        const channelTypers = typingUsers.value.get(channelId);

        // Clear existing timeout for this user
        if (channelTypers.has(userId)) {
            clearTimeout(channelTypers.get(userId).timeout);
        }

        // Auto-clear after 5 seconds of no activity
        const timeout = setTimeout(() => {
            clearTyping(channelId, userId);
        }, 5000);

        channelTypers.set(userId, { userId, userName, timeout });

        // Trigger reactivity
        typingUsers.value.set(channelId, new Map(channelTypers));
    }

    function clearTyping(channelId, userId) {
        const channelTypers = typingUsers.value.get(channelId);
        if (!channelTypers) return;

        const typer = channelTypers.get(userId);
        if (typer?.timeout) clearTimeout(typer.timeout);

        channelTypers.delete(userId);
        typingUsers.value.set(channelId, new Map(channelTypers));
    }

    function getTypingUsers(channelId) {
        const channelTypers = typingUsers.value.get(channelId);
        if (!channelTypers) return [];
        return Array.from(channelTypers.values());
    }

    // ── Reply state ───────────────────────────────────────────────────────
    // Message currently being replied to (single active composer per page)
    const replyTo = ref(null);

    function setReplyTo(message) {
        replyTo.value = message;
    }

    function clearReplyTo() {
        replyTo.value = null;
    }

    // ── Reaction Actions ──────────────────────────────────────────────────
    /** Replace a message's full reactions array (from HTTP toggle response). */
    function setReactions(messageId, reactions) {
        messages.value.forEach((msgs, channelId) => {
            const idx = msgs.findIndex(m => m.id === messageId);
            if (idx !== -1) {
                const newMsgs = [...msgs];
                newMsgs[idx] = { ...newMsgs[idx], reactions };
                messages.value.set(channelId, newMsgs);
            }
        });
    }

    /**
     * Apply a broadcast reaction event incrementally.
     * evt: { message_id, emoji, user_id, user_name, action }
     */
    function applyReactionEvent(evt, myUserId) {
        messages.value.forEach((msgs, channelId) => {
            const idx = msgs.findIndex(m => m.id === evt.message_id);
            if (idx === -1) return;

            const newMsgs = [...msgs];
            const msg = { ...newMsgs[idx] };
            const reactions = (msg.reactions ?? []).map(r => ({ ...r, users: [...(r.users ?? [])] }));
            const rIdx = reactions.findIndex(r => r.emoji === evt.emoji);

            if (evt.action === 'added') {
                if (rIdx !== -1) {
                    if (!reactions[rIdx].users.some(u => u.id === evt.user_id)) {
                        reactions[rIdx].count = (reactions[rIdx].count ?? 0) + 1;
                        reactions[rIdx].users.push({ id: evt.user_id, name: evt.user_name });
                        if (evt.user_id === myUserId) reactions[rIdx].reacted_by_me = true;
                    }
                } else {
                    reactions.push({
                        emoji: evt.emoji,
                        count: 1,
                        users: [{ id: evt.user_id, name: evt.user_name }],
                        reacted_by_me: evt.user_id === myUserId,
                    });
                }
            } else {
                if (rIdx !== -1) {
                    reactions[rIdx].count = Math.max(0, (reactions[rIdx].count ?? 1) - 1);
                    reactions[rIdx].users = reactions[rIdx].users.filter(u => u.id !== evt.user_id);
                    if (evt.user_id === myUserId) reactions[rIdx].reacted_by_me = false;
                    if (reactions[rIdx].count === 0) reactions.splice(rIdx, 1);
                }
            }

            msg.reactions = reactions;
            newMsgs[idx] = msg;
            messages.value.set(channelId, newMsgs);
        });
    }

    function updateReaction(messageId, reaction) {
        messages.value.forEach((msgs, channelId) => {
            const idx = msgs.findIndex(m => m.id === messageId);
            if (idx !== -1) {
                const newMsgs = [...msgs];
                const msg = { ...newMsgs[idx] };
                const reactions = msg.reactions ? [...msg.reactions] : [];

                const reactionIdx = reactions.findIndex(
                    r => r.emoji === reaction.emoji,
                );

                if (reactionIdx !== -1) {
                    reactions[reactionIdx] = { ...reaction };
                    if (reactions[reactionIdx].count === 0) {
                        reactions.splice(reactionIdx, 1);
                    }
                } else if (reaction.count > 0) {
                    reactions.push(reaction);
                }

                msg.reactions = reactions;
                newMsgs[idx] = msg;
                messages.value.set(channelId, newMsgs);
            }
        });
    }

    // ── Computed ─────────────────────────────────────────────────────────
    const totalMessages = computed(() => {
        let total = 0;
        messages.value.forEach(msgs => (total += msgs.length));
        return total;
    });

    return {
        messages,
        typingUsers,
        pagination,
        loading,
        sendingMessage,
        fetchMessages,
        appendMessage,
        updateMessage,
        bumpReplyCount,
        removeMessage,
        discardOptimistic,
        getMessages,
        hasMore,
        setTyping,
        clearTyping,
        getTypingUsers,
        updateReaction,
        setReactions,
        applyReactionEvent,
        replyTo,
        setReplyTo,
        clearReplyTo,
        totalMessages,
    };
});
