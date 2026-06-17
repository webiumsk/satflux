import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useGuestUpgradeStore = defineStore('guestUpgrade', () => {
    const open = ref(false);
    const featureLabelKey = ref<string | null>(null);

    function openGuestUpgradeModal(labelKey?: string) {
        featureLabelKey.value = labelKey ?? null;
        open.value = true;
    }

    function closeGuestUpgradeModal() {
        open.value = false;
        featureLabelKey.value = null;
    }

    return {
        open,
        featureLabelKey,
        openGuestUpgradeModal,
        closeGuestUpgradeModal,
    };
});
