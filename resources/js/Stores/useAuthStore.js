import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const currentTenant = ref(null);

    // ── Setters ──────────────────────────────────────────────────────────
    function setUser(userData) {
        user.value = userData;
    }

    function setTenant(tenantData) {
        currentTenant.value = tenantData;
    }

    function logout() {
        user.value = null;
        currentTenant.value = null;
        router.post('/logout');
    }

    // ── Computed ─────────────────────────────────────────────────────────
    const isAuthenticated = computed(() => !!user.value);

    const userAvatar = computed(() => user.value?.avatar_url ?? null);

    const userName = computed(() =>
        user.value?.name ?? user.value?.email ?? 'Unknown',
    );

    const userInitials = computed(() => {
        if (!user.value?.name) return '?';
        return user.value.name
            .split(' ')
            .map(n => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    });

    const userRole = computed(() => user.value?.role ?? 'member');

    const isOwner = computed(() => userRole.value === 'owner');
    const isAdmin = computed(() =>
        ['owner', 'admin'].includes(userRole.value),
    );

    return {
        user,
        currentTenant,
        setUser,
        setTenant,
        logout,
        isAuthenticated,
        userAvatar,
        userName,
        userInitials,
        userRole,
        isOwner,
        isAdmin,
    };
});
