import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAppsStore } from '../store/apps';
import api from '../services/api';

export function useStorePageShell() {
    const route = useRoute();
    const router = useRouter();
    const appsStore = useAppsStore();

    const storeId = computed(() => route.params.id as string);
    const store = ref<Record<string, unknown> | null>(null);
    const error = ref('');

    async function loadStore() {
        error.value = '';
        try {
            const response = await api.get(`/stores/${storeId.value}`);
            store.value = response.data.data;
        } catch (err: unknown) {
            const e = err as { response?: { data?: { message?: string } } };
            error.value = e?.response?.data?.message || 'Failed to load store';
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
