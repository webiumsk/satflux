import { computed, nextTick, ref, watch, type Ref } from 'vue';
import { useAuthStore } from '../store/auth';
import type { Raffle } from '../store/raffles';
import api from '../services/api';
import { useAccountLimits } from './useAccountLimits';

/** UI hints from a verified store snapshot; writes still enforce server quotas. */
export function useRaffleCreationLimits(storeId: Readonly<Ref<string | undefined>>) {
    const auth = useAuthStore();
    const accountLimits = useAccountLimits();
    const raffles = ref<Raffle[]>([]);
    const snapshot = ref<{ max: number | null; allowed: boolean } | null>(null);
    const error = ref<unknown>(null);
    const status = ref<'loading' | 'ready' | 'error'>('loading');
    const resolvedScope = ref('');
    let request = 0;
    const scope = computed(() => JSON.stringify([
        storeId.value, auth.user?.id, auth.user?.is_guest, auth.user?.role,
        auth.user?.plan, auth.user?.effective_access,
    ]));
    const ready = computed(() => status.value === 'ready' && resolvedScope.value === scope.value);
    const loading = computed(() => !ready.value && status.value !== 'error');
    const raffleLimit = computed(() => ready.value ? snapshot.value?.max : undefined);
    const raffleCount = computed(() => ready.value ? raffles.value.length : 0);
    const canCreateRaffle = computed(() => ready.value && snapshot.value?.allowed === true
        && (snapshot.value.max === null || raffleCount.value < snapshot.value.max));
    const denied = computed(() => ready.value && !canCreateRaffle.value);

    async function reload() {
        const token = ++request;
        const requestedScope = scope.value;
        const id = storeId.value;
        status.value = 'loading';
        resolvedScope.value = '';
        snapshot.value = null;
        raffles.value = [];
        error.value = null;
        if (!id || !auth.user?.id) return;
        // Guest restriction is independent of plan hints. The shared quota cache
        // predates this flag, so never reuse a former full-account grant for a guest.
        if (auth.user.is_guest) {
            snapshot.value = { max: 0, allowed: false };
            resolvedScope.value = requestedScope;
            status.value = 'ready';
            return;
        }
        try {
            // Let the scoped cache's auth watcher clear its previous snapshot first.
            await nextTick();
            if (token !== request || requestedScope !== scope.value) return;
            // The limits endpoint can return current=0 after a provider failure.
            // Use the successful list for the count instead of trusting that fallback.
            await accountLimits.load(id);
            if (token !== request || requestedScope !== scope.value) return;
            const quota = accountLimits.limits.value?.raffles;
            const finite = typeof quota?.max === 'number' && Number.isSafeInteger(quota.max) && quota.max >= 0;
            const unlimited = quota?.max === null && quota.unlimited === true;
            if (!quota || typeof quota.allowed !== 'boolean'
                || !(unlimited || (finite && quota.unlimited === false))) {
                throw new Error('Raffle limits are unavailable');
            }
            // Guests are explicitly denied by quota and cannot list raffles.
            // Do not turn that confirmed denial into an unrelated API error.
            let list: Raffle[] = [];
            if (quota.allowed) {
                const response = await api.get<{ data: Raffle[] }>(`/stores/${id}/raffles`);
                if (token !== request || requestedScope !== scope.value) return;
                if (!Array.isArray(response.data?.data)) throw new Error('Raffle list is unavailable');
                list = response.data.data;
            }
            raffles.value = list;
            snapshot.value = { max: quota.max, allowed: quota.allowed };
            resolvedScope.value = requestedScope;
            status.value = 'ready';
        } catch (failure: unknown) {
            if (token !== request || requestedScope !== scope.value) return;
            error.value = failure;
            status.value = 'error';
        }
    }

    // Invalidate synchronously so event handlers cannot use a prior account/store's grant.
    watch(scope, () => { void reload(); }, { immediate: true, flush: 'sync' });
    return { raffles, ready, loading, error, raffleLimit, raffleCount, canCreateRaffle, denied, retry: reload };
}
