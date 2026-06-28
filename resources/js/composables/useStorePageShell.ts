import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAppsStore } from '../store/apps';
import { useStoresStore } from '../store/stores';
import { getApiErrorMessage } from './useApiError';

export function useStorePageShell() {
    const route = useRoute();
    const router = useRouter();
    const { t } = useI18n();
    const appsStore = useAppsStore();
    const storesStore = useStoresStore();

    const storeId = computed(() => route.params.id as string);
    const store = computed(() => storesStore.currentStore);
    const error = ref('');

    async function loadStore() {
        error.value = '';
        try {
            await storesStore.fetchStore(storeId.value);
        } catch (err: unknown) {
            error.value = getApiErrorMessage(err, t('stores.loading_store'));
        }
    }

    function goSettings() {
        router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
    }

    function goSection(section: string) {
        router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
    }

    onMounted(async () => {
        await loadStore();
        if (store.value) {
            await appsStore.fetchApps(storeId.value);
        }
    });

    watch(storeId, async () => {
        await loadStore();
        if (store.value) {
            await appsStore.fetchApps(storeId.value);
        }
    });

    return {
        storeId,
        store,
        error,
        apps: computed(() => appsStore.apps),
        loadStore,
        goSettings,
        goSection,
    };
}
