import { ref, computed, watch } from 'vue';
import api from '../services/api';
import { useAuthStore } from '../store/auth';
export interface LimitInfo { current: number; max: number | null; unlimited: boolean }
export interface AccountLimitsData {
  stores: LimitInfo;
  ln_addresses: LimitInfo;
  api_keys: LimitInfo;
  events?: { current?: number; max: number | null; unlimited: boolean };
  raffles?: { current?: number; max: number | null; unlimited: boolean; allowed?: boolean };
}
const TTL_MS = 60_000;
const cache = new Map<string, { data: AccountLimitsData; expires: number }>();
const requests = new Map<string, Promise<AccountLimitsData>>();
let generation = 0;
export function useAccountLimits() {
  const auth = useAuthStore();
  const selectedStore = ref<string | undefined>();
  const cached = ref<AccountLimitsData | null>(null);
  const cachedScope = ref('');
  const scope = computed(() => JSON.stringify([
    auth.user?.id ?? 'anonymous', auth.user?.effective_access?.policy_version ?? 'legacy',
    auth.user?.effective_access?.source, auth.user?.effective_access?.limits,
    auth.user?.role, auth.user?.plan,
  ]));
  watch(scope, () => { cached.value = null; });
  const limits = computed(() => cachedScope.value === scope.value ? cached.value : null);
  async function load(storeId?: string): Promise<AccountLimitsData> {
    selectedStore.value = storeId;
    const requestScope = scope.value;
    const loadGeneration = generation;
    cachedScope.value = requestScope;
    const key = `${requestScope}:${storeId ?? 'account'}`;
    const entry = cache.get(key);
    if (entry && entry.expires > Date.now()) { cached.value = entry.data; return entry.data; }
    cached.value = null;
    let promise = requests.get(key);
    if (!promise) {
      const requestGeneration = generation;
      promise = api.get<AccountLimitsData>('/user/limits', {
        params: storeId ? { store_id: storeId } : undefined,
      }).then(({ data }) => {
        if (requestGeneration === generation) cache.set(key, { data, expires: Date.now() + TTL_MS });
        return data;
      }).finally(() => { if (requests.get(key) === promise) requests.delete(key); });
      requests.set(key, promise);
    }
    const data = await promise;
    if (loadGeneration === generation && scope.value === requestScope && selectedStore.value === storeId) cached.value = data;
    return data;
  }
  function clearCache() {
    generation++;
    cache.clear(); requests.clear(); cached.value = null;
  }
  return { limits, load, clearCache };
}
