import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useChannelStore = defineStore('channel', () => {
    const channels = ref([]);
    const activeChannel = ref(null);
    const loading = ref(false);
    const error = ref(null);

    // Map<channelId, number>
    const unreadCounts = ref(new Map());

    // Map<conversationId, number> — live DM badge deltas layered on top of the
    // server count, which only refreshes on Inertia navigation.
    const dmUnread = ref(new Map());

    function incrementDmUnread(conversationId) {
        const current = dmUnread.value.get(conversationId) ?? 0;
        dmUnread.value.set(conversationId, current + 1);
    }

    function clearDmUnread(conversationId) {
        dmUnread.value.delete(conversationId);
    }

    function getDmUnread(conversationId) {
        return dmUnread.value.get(conversationId) ?? 0;
    }

    async function markDmReadRemote(conversationId) {
        clearDmUnread(conversationId);
        try {
            await axios.post(`/dm/${conversationId}/read`);
        } catch {
            // Badge already cleared locally; the next navigation re-syncs.
        }
    }

    function isMuted(channelId) {
        return Boolean(channels.value.find(c => c.id === channelId)?.is_muted);
    }

    /**
     * Nudge a channel's member count without a round trip — used when a
     * member.left event arrives.
     */
    function adjustMemberCount(channelId, delta) {
        const idx = channels.value.findIndex(c => c.id === channelId);
        if (idx === -1) return;

        const next = Math.max(0, (channels.value[idx].members_count ?? 0) + delta);
        channels.value[idx] = { ...channels.value[idx], members_count: next };

        if (activeChannel.value?.id === channelId) {
            activeChannel.value = { ...activeChannel.value, members_count: next };
        }
    }

    // ── Actions ───────────────────────────────────────────────────────────
    async function fetchChannels(workspaceId) {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get(`/api/workspaces/${workspaceId}/channels`);
            channels.value = data.data ?? data;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'Failed to fetch channels';
            console.error('[ChannelStore] fetchChannels:', err);
        } finally {
            loading.value = false;
        }
    }

    function setActiveChannel(channel) {
        activeChannel.value = channel;
        if (channel) {
            markChannelRead(channel.id);
        }
    }

    function addChannel(channel) {
        const exists = channels.value.find(c => c.id === channel.id);
        if (!exists) {
            channels.value.push(channel);
        }
    }

    function updateChannel(updated) {
        const idx = channels.value.findIndex(c => c.id === updated.id);
        if (idx !== -1) {
            channels.value[idx] = { ...channels.value[idx], ...updated };
        }
        if (activeChannel.value?.id === updated.id) {
            activeChannel.value = { ...activeChannel.value, ...updated };
        }
    }

    function removeChannel(channelId) {
        channels.value = channels.value.filter(c => c.id !== channelId);
        if (activeChannel.value?.id === channelId) {
            activeChannel.value = null;
        }
        unreadCounts.value.delete(channelId);
    }

    function markChannelRead(channelId) {
        unreadCounts.value.set(channelId, 0);
    }

    function incrementUnread(channelId) {
        const current = unreadCounts.value.get(channelId) ?? 0;
        unreadCounts.value.set(channelId, current + 1);
    }

    function setUnreadCount(channelId, count) {
        unreadCounts.value.set(channelId, count);
    }

    function getUnreadCount(channelId) {
        return unreadCounts.value.get(channelId) ?? 0;
    }

    /**
     * Seed unread counts from the server payload. The server is authoritative,
     * so this runs on every Inertia navigation — that's what makes badges
     * survive a refresh.
     *
     * The active channel is skipped: the user is looking at it, and a pending
     * markRead may not have landed yet.
     */
    function hydrateUnread(list) {
        if (!Array.isArray(list)) return;

        list.forEach(ch => {
            if (ch.unread_count === undefined) return;
            if (activeChannel.value?.id === ch.id) return;
            unreadCounts.value.set(ch.id, ch.unread_count);
        });
    }

    /**
     * Clear the badge locally and persist last_read_at server-side.
     */
    async function markChannelReadRemote(channelId) {
        markChannelRead(channelId);
        try {
            await axios.post(`/channels/${channelId}/read`);
        } catch {
            // Badge already cleared locally; the next navigation re-syncs.
        }
    }

    async function toggleMute(channelId) {
        const idx = channels.value.findIndex(c => c.id === channelId);
        const previous = idx !== -1 ? channels.value[idx].is_muted : false;

        // Optimistic — the toggle should feel instant
        if (idx !== -1) channels.value[idx] = { ...channels.value[idx], is_muted: !previous };

        try {
            const { data } = await axios.post(`/channels/${channelId}/mute`);
            if (idx !== -1) channels.value[idx] = { ...channels.value[idx], is_muted: data.is_muted };
            return data.is_muted;
        } catch (err) {
            if (idx !== -1) channels.value[idx] = { ...channels.value[idx], is_muted: previous };
            throw err;
        }
    }

    // ── Computed ─────────────────────────────────────────────────────────
    const publicChannels = computed(() =>
        channels.value.filter(c => c.type === 'public'),
    );

    const privateChannels = computed(() =>
        channels.value.filter(c => c.type === 'private'),
    );

    const totalUnread = computed(() => {
        let total = 0;
        unreadCounts.value.forEach(count => (total += count));
        return total;
    });

    return {
        channels,
        activeChannel,
        loading,
        error,
        unreadCounts,
        fetchChannels,
        setActiveChannel,
        addChannel,
        updateChannel,
        removeChannel,
        markChannelRead,
        markChannelReadRemote,
        hydrateUnread,
        toggleMute,
        isMuted,
        adjustMemberCount,
        dmUnread,
        incrementDmUnread,
        clearDmUnread,
        getDmUnread,
        markDmReadRemote,
        incrementUnread,
        setUnreadCount,
        getUnreadCount,
        publicChannels,
        privateChannels,
        totalUnread,
    };
});
