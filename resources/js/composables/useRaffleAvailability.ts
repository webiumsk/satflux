import { ref, watch, type Ref } from 'vue';
import { useRafflesStore } from '../store/raffles';

const cacheByStore = new Map<string, boolean>();

export function useRaffleAvailability(
    storeId: Ref<string | undefined> | string,
    skipProbe?: Ref<boolean>,
) {
    const rafflesStore = useRafflesStore();
    const available = ref<boolean | null>(null);
    const loading = ref(false);

    async function load(refresh = false) {
        const id = typeof storeId === 'string' ? storeId : storeId.value;
        if (!id) {
            available.value = null;
            return;
        }

        if (skipProbe?.value) {
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
        } catch (e: unknown) {
            const err = e as { response?: { status?: number; data?: { code?: string } } };
            if (
                err.response?.status === 403
                && err.response?.data?.code === 'guest_feature_locked'
            ) {
                available.value = null;
                return;
            }
            available.value = false;
            cacheByStore.set(id, false);
        } finally {
            loading.value = false;
        }
    }

    if (typeof storeId !== 'string') {
        const sources: Array<Ref<unknown>> = [storeId];
        if (skipProbe) {
            sources.push(skipProbe);
        }
        watch(sources, () => {
            if (storeId.value) {
                void load();
            }
        }, { immediate: true });
    } else if (storeId) {
        void load();
    }

    function invalidate() {
        const id = typeof storeId === 'string' ? storeId : storeId.value;
        if (id) {
            cacheByStore.delete(id);
        }
    }

    return { available, loading, load, invalidate };
}
