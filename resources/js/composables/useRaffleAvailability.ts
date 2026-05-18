import { ref, watch, type Ref } from 'vue';
import { useRafflesStore } from '../store/raffles';

const cacheByStore = new Map<string, boolean>();

export function useRaffleAvailability(storeId: Ref<string | undefined> | string) {
    const rafflesStore = useRafflesStore();
    const available = ref<boolean | null>(null);
    const loading = ref(false);

    async function load(refresh = false) {
        const id = typeof storeId === 'string' ? storeId : storeId.value;
        if (!id) {
            available.value = null;
            return;
        }

        if (!refresh && cacheByStore.has(id)) {
            available.value = cacheByStore.get(id) ?? false;
            rafflesStore.pluginAvailable = available.value;
            return;
        }

        loading.value = true;
        try {
            const result = await rafflesStore.fetchAvailability(id, refresh);
            available.value = result;
            cacheByStore.set(id, result);
        } catch {
            available.value = false;
            cacheByStore.set(id, false);
        } finally {
            loading.value = false;
        }
    }

    if (typeof storeId !== 'string') {
        watch(storeId, (id) => {
            if (id) load();
        }, { immediate: true });
    } else if (storeId) {
        load();
    }

    function invalidate() {
        const id = typeof storeId === 'string' ? storeId : storeId.value;
        if (id) {
            cacheByStore.delete(id);
        }
    }

    return { available, loading, load, invalidate };
}
