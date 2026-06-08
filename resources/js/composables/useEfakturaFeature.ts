import { computed, ref } from 'vue';
import api from '../services/api';

const cachedEnabled = ref<boolean | null>(null);
let fetchPromise: Promise<boolean> | null = null;

/**
 * Global SK e-faktura module switch from GET /api/config (EFAKTURA_ENABLED).
 */
export function useEfakturaFeature() {
  const enabled = computed(() => cachedEnabled.value === true);
  const loaded = computed(() => cachedEnabled.value !== null);

  async function load(): Promise<boolean> {
    if (cachedEnabled.value !== null) {
      return cachedEnabled.value;
    }

    if (fetchPromise) {
      return fetchPromise;
    }

    fetchPromise = (async () => {
      try {
        const { data } = await api.get<{ efaktura_enabled?: boolean }>('/config');
        cachedEnabled.value = Boolean(data.efaktura_enabled);
      } catch {
        cachedEnabled.value = false;
      }

      return cachedEnabled.value;
    })();

    return fetchPromise;
  }

  return { enabled, loaded, load };
}
