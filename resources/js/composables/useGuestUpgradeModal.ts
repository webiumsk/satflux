import { storeToRefs } from 'pinia';
import { useGuestUpgradeStore } from '../store/guestUpgrade';

/** Pinia-backed modal state (safe across Vite production chunks). */
export function useGuestUpgradeModal() {
    const store = useGuestUpgradeStore();
    const { open, featureLabelKey } = storeToRefs(store);

    return {
        open,
        featureLabelKey,
        openGuestUpgradeModal: store.openGuestUpgradeModal,
        closeGuestUpgradeModal: store.closeGuestUpgradeModal,
    };
}
