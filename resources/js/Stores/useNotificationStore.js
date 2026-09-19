import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useNotificationStore = defineStore('notification', () => {
    const notifications = ref([]);
    const serverUnreadCount = ref(0);
    const loading = ref(false);
    const loaded = ref(false);
    const error = ref(null);

    // ── Actions ───────────────────────────────────────────────────────────
    async function fetchNotifications() {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get('/notifications/feed');
            notifications.value = data.notifications ?? [];
            serverUnreadCount.value = data.unread_count ?? 0;
            loaded.value = true;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'Failed to fetch notifications';
            console.error('[NotificationStore] fetchNotifications:', err);
        } finally {
            loading.value = false;
        }
    }

    function addNotification(notification) {
        // Real-time push — dedupe against the fetched list
        if (notifications.value.some(n => n.id === notification.id)) return;
        notifications.value.unshift(notification);
        serverUnreadCount.value++;
    }

    async function markRead(id) {
        const notification = notifications.value.find(n => n.id === id);
        if (!notification || notification.read_at) return;
        notification.read_at = new Date().toISOString();
        serverUnreadCount.value = Math.max(0, serverUnreadCount.value - 1);
        try {
            await axios.post(`/notifications/${id}/read`);
        } catch (err) {
            console.error('[NotificationStore] markRead:', err);
        }
    }

    async function markAllRead() {
        const now = new Date().toISOString();
        notifications.value.forEach(n => { if (!n.read_at) n.read_at = now; });
        serverUnreadCount.value = 0;
        try {
            await axios.post('/notifications/read-all');
        } catch (err) {
            console.error('[NotificationStore] markAllRead:', err);
        }
    }

    async function removeNotification(id) {
        const target = notifications.value.find(n => n.id === id);
        notifications.value = notifications.value.filter(n => n.id !== id);
        if (target && !target.read_at) {
            serverUnreadCount.value = Math.max(0, serverUnreadCount.value - 1);
        }
        try {
            await axios.delete(`/notifications/${id}`);
        } catch (err) {
            console.error('[NotificationStore] removeNotification:', err);
        }
    }

    // ── Computed ─────────────────────────────────────────────────────────
    const unreadCount = computed(() => serverUnreadCount.value);

    const unreadNotifications = computed(
        () => notifications.value.filter(n => !n.read_at),
    );

    const recentNotifications = computed(() =>
        notifications.value.slice(0, 20),
    );

    return {
        notifications,
        loading,
        loaded,
        error,
        fetchNotifications,
        addNotification,
        markRead,
        markAllRead,
        removeNotification,
        unreadCount,
        unreadNotifications,
        recentNotifications,
    };
});
