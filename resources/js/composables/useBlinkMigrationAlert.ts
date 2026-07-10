import { ref } from 'vue';
import { storesApi } from '../services/api';
import type { Store } from '../store/stores';

export function useBlinkMigrationAlert(storeRef: { value: Store | null }) {
  const modalOpen = ref(false);
  const loading = ref(false);

  function syncModalFromStore() {
    modalOpen.value = storeRef.value?.blink_migration_alert?.active === true;
  }

  async function acknowledge() {
    const store = storeRef.value;
    if (!store?.id || loading.value) return;
    loading.value = true;
    try {
      const result = await storesApi.snoozeBlinkMigrationAlert(store.id);
      if (storeRef.value) {
        storeRef.value.blink_migration_alert = result.blink_migration_alert;
      }
      modalOpen.value = false;
    } finally {
      loading.value = false;
    }
  }

  async function dismissOutsideEu() {
    const store = storeRef.value;
    if (!store?.id || loading.value) return;
    loading.value = true;
    try {
      const result = await storesApi.dismissBlinkMigrationAlert(store.id);
      if (storeRef.value) {
        storeRef.value.blink_migration_alert = result.blink_migration_alert;
      }
      modalOpen.value = false;
    } finally {
      loading.value = false;
    }
  }

  return {
    modalOpen,
    loading,
    syncModalFromStore,
    acknowledge,
    dismissOutsideEu,
  };
}
