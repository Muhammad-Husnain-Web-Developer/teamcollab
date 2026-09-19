import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const usePresenceStore = defineStore('presence', () => {
    // Map<userId, {status: 'online'|'away'|'offline', last_seen: string}>
    const onlineUsers = ref(new Map());

    // ── Actions ───────────────────────────────────────────────────────────
    function setUserStatus(userId, status, lastSeen = null) {
        onlineUsers.value.set(userId, {
            status,
            last_seen: lastSeen ?? new Date().toISOString(),
        });
        // Trigger reactivity
        onlineUsers.value = new Map(onlineUsers.value);
    }

    function removeUser(userId) {
        onlineUsers.value.delete(userId);
        onlineUsers.value = new Map(onlineUsers.value);
    }

    function setOnlineUsers(users) {
        const map = new Map();
        users.forEach(u => {
            map.set(u.id ?? u.user_id, {
                status: u.status ?? 'online',
                last_seen: u.last_seen ?? new Date().toISOString(),
            });
        });
        onlineUsers.value = map;
    }

    function getStatus(userId) {
        return onlineUsers.value.get(userId)?.status ?? 'offline';
    }

    function isOnline(userId) {
        return getStatus(userId) === 'online';
    }

    function getLastSeen(userId) {
        return onlineUsers.value.get(userId)?.last_seen ?? null;
    }

    // ── Computed ─────────────────────────────────────────────────────────
    const onlineCount = computed(() => {
        let count = 0;
        onlineUsers.value.forEach(u => {
            if (u.status === 'online') count++;
        });
        return count;
    });

    const onlineUserIds = computed(() => {
        const ids = [];
        onlineUsers.value.forEach((val, key) => {
            if (val.status === 'online') ids.push(key);
        });
        return ids;
    });

    return {
        onlineUsers,
        setUserStatus,
        removeUser,
        setOnlineUsers,
        getStatus,
        isOnline,
        getLastSeen,
        onlineCount,
        onlineUserIds,
    };
});
