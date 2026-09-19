import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';

export const useWorkspaceStore = defineStore('workspace', () => {
    const workspaces = ref([]);
    const currentWorkspace = ref(null);
    const loading = ref(false);
    const error = ref(null);

    // ── Actions ───────────────────────────────────────────────────────────
    async function fetchWorkspaces() {
        loading.value = true;
        error.value = null;
        try {
            const { data } = await axios.get('/api/workspaces');
            workspaces.value = data.data ?? data;
        } catch (err) {
            error.value = err.response?.data?.message ?? 'Failed to fetch workspaces';
            console.error('[WorkspaceStore] fetchWorkspaces:', err);
        } finally {
            loading.value = false;
        }
    }

    function setCurrentWorkspace(ws) {
        currentWorkspace.value = ws;
    }

    function addWorkspace(ws) {
        workspaces.value.push(ws);
    }

    function updateWorkspace(updated) {
        const idx = workspaces.value.findIndex(w => w.id === updated.id);
        if (idx !== -1) {
            workspaces.value[idx] = { ...workspaces.value[idx], ...updated };
        }
        if (currentWorkspace.value?.id === updated.id) {
            currentWorkspace.value = { ...currentWorkspace.value, ...updated };
        }
    }

    function removeWorkspace(wsId) {
        workspaces.value = workspaces.value.filter(w => w.id !== wsId);
        if (currentWorkspace.value?.id === wsId) {
            currentWorkspace.value = workspaces.value[0] ?? null;
        }
    }

    // ── Computed ─────────────────────────────────────────────────────────
    const workspaceCount = computed(() => workspaces.value.length);

    const currentWorkspaceName = computed(
        () => currentWorkspace.value?.name ?? '',
    );

    const currentWorkspaceLogo = computed(
        () => currentWorkspace.value?.logo_url ?? null,
    );

    return {
        workspaces,
        currentWorkspace,
        loading,
        error,
        fetchWorkspaces,
        setCurrentWorkspace,
        addWorkspace,
        updateWorkspace,
        removeWorkspace,
        workspaceCount,
        currentWorkspaceName,
        currentWorkspaceLogo,
    };
});
