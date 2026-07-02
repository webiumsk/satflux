import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useStoresStore } from '../store/stores';
import { getApiErrorMessage } from './useApiError';

export function useStorePageShell() {
    const route = useRoute();
    const router = useRouter();
    const { t } = useI18n();
    const storesStore = useStoresStore();

    const storeId = computed(() => route.params.id as string);
    const store = computed(() => storesStore.currentStore);
    const error = ref('');

    async function loadStore() {
        const requestedId = storeId.value;
        error.value = '';
        try {
            await storesStore.fetchStore(requestedId);
        } catch (err: unknown) {
            if (storeId.value === requestedId) {
                error.value = getApiErrorMessage(err, t('stores.loading_store'));
            }
        }
    }

    function goSettings() {
        router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section: 'settings' } });
    }

    function goSection(section: string) {
        router.push({ name: 'stores-show', params: { id: storeId.value }, query: { section } });
    }

    onMounted(() => {
        void loadStore();
    });

    watch(storeId, () => {
        void loadStore();
    });

    return {
        storeId,
        store,
        error,
        loadStore,
        goSettings,
        goSection,
    };
}
