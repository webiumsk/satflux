import { ref, type Ref } from 'vue';
import { storesApi } from '../services/api';
import { useFlashStore } from '../store/flash';
import type { Store } from '../store/stores';

export function useBlinkMigrationAlert(storeRef: Ref<Store | null>) {
  const modalOpen = ref(false);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const flashStore = useFlashStore();

  function syncModalFromStore() {
    modalOpen.value = storeRef.value?.blink_migration_alert?.active === true;
  }

  async function acknowledge() {
    const store = storeRef.value;
    if (!store?.id || loading.value) return;
    loading.value = true;
    error.value = null;
    try {
      const result = await storesApi.snoozeBlinkMigrationAlert(store.id);
      if (storeRef.value) {
        storeRef.value.blink_migration_alert = result.blink_migration_alert;
      }
      modalOpen.value = false;
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Failed to snooze migration alert.';
      error.value = message;
      flashStore.error(message);
    } finally {
      loading.value = false;
    }
  }

  async function dismissOutsideEu() {
    const store = storeRef.value;
    if (!store?.id || loading.value) return;
    loading.value = true;
    error.value = null;
    try {
      const result = await storesApi.dismissBlinkMigrationAlert(store.id);
      if (storeRef.value) {
        storeRef.value.blink_migration_alert = result.blink_migration_alert;
      }
      modalOpen.value = false;
    } catch (err: unknown) {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Failed to dismiss migration alert.';
      error.value = message;
      flashStore.error(message);
    } finally {
      loading.value = false;
    }
  }

  return {
    modalOpen,
    loading,
    error,
    syncModalFromStore,
    acknowledge,
    dismissOutsideEu,
  };
}
