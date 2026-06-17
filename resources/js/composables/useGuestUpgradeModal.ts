import { ref, readonly } from 'vue';

const open = ref(false);
const featureLabelKey = ref<string | null>(null);

export function useGuestUpgradeModal() {
  function openGuestUpgradeModal(labelKey?: string) {
    featureLabelKey.value = labelKey ?? null;
    open.value = true;
  }

  function closeGuestUpgradeModal() {
    open.value = false;
    featureLabelKey.value = null;
  }

  return {
    open: readonly(open),
    featureLabelKey: readonly(featureLabelKey),
    openGuestUpgradeModal,
    closeGuestUpgradeModal,
  };
}
