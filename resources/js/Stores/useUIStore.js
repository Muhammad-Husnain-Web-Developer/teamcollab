import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { useWindowSize } from '@vueuse/core';

export const useUIStore = defineStore('ui', () => {
    const { width } = useWindowSize();

    // On a phone the sidebar is an overlay covering the conversation, so it
    // starts closed; on desktop it is a permanent column.
    const sidebarOpen = ref(typeof window === 'undefined' || window.innerWidth >= 768);
    const rightPanelOpen = ref(false);
    const activeModal = ref(null);
    const activeModalProps = ref({});
    const toasts = ref([]);
    let toastIdCounter = 0;

    // ── Modal ─────────────────────────────────────────────────────────────
    function openModal(name, props = {}) {
        activeModal.value = name;
        activeModalProps.value = props;
    }

    function closeModal() {
        activeModal.value = null;
        activeModalProps.value = {};
    }

    // ── Toast ─────────────────────────────────────────────────────────────
    function addToast(message, type = 'info', duration = 4000) {
        const id = ++toastIdCounter;
        toasts.value.push({ id, message, type, duration });

        if (duration > 0) {
            setTimeout(() => removeToast(id), duration);
        }

        return id;
    }

    function removeToast(id) {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }

    // Convenience helpers
    function toastSuccess(message, duration = 4000) {
        return addToast(message, 'success', duration);
    }

    function toastError(message, duration = 5000) {
        return addToast(message, 'error', duration);
    }

    function toastWarning(message, duration = 4500) {
        return addToast(message, 'warning', duration);
    }

    function toastInfo(message, duration = 4000) {
        return addToast(message, 'info', duration);
    }

    // ── Sidebar ───────────────────────────────────────────────────────────
    function toggleSidebar() {
        sidebarOpen.value = !sidebarOpen.value;
    }

    function toggleRightPanel() {
        rightPanelOpen.value = !rightPanelOpen.value;
    }

    /**
     * Called after navigating to a channel or DM: on mobile the sidebar is an
     * overlay, so leaving it open would hide the conversation just opened.
     */
    function closeSidebarOnMobile() {
        if (width.value < 768) sidebarOpen.value = false;
    }

    // ── Computed ─────────────────────────────────────────────────────────
    const isMobile = computed(() => width.value < 768);
    const isTablet = computed(() => width.value >= 768 && width.value < 1024);
    const isDesktop = computed(() => width.value >= 1024);

    return {
        sidebarOpen,
        rightPanelOpen,
        activeModal,
        activeModalProps,
        toasts,
        openModal,
        closeModal,
        addToast,
        removeToast,
        toastSuccess,
        toastError,
        toastWarning,
        toastInfo,
        toggleSidebar,
        toggleRightPanel,
        closeSidebarOnMobile,
        isMobile,
        isTablet,
        isDesktop,
    };
});
