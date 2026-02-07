import { ref, computed } from 'vue';
import api from '../services/api';

export interface LimitInfo {
  current: number;
  max: number | null;
  unlimited: boolean;
}

export interface AccountLimitsData {
  stores: LimitInfo;
  ln_addresses: LimitInfo;
  api_keys: LimitInfo;
}

const cached = ref<AccountLimitsData | null>(null);
let fetchPromise: Promise<AccountLimitsData> | null = null;

export function useAccountLimits() {
  const limits = computed(() => cached.value);

  async function load(): Promise<AccountLimitsData> {
    if (cached.value) return cached.value;
    if (fetchPromise) return fetchPromise;
    fetchPromise = (async () => {
      try {
        const { data } = await api.get<AccountLimitsData>('/user/limits');
        cached.value = data;
        return data;
      } catch {
        cached.value = null;
        throw new Error('Failed to load limits');
      }
    })();
    return fetchPromise;
  }

  function clearCache() {
    cached.value = null;
    fetchPromise = null;
  }

  return {
    limits,
    load,
    clearCache,
  };
}
