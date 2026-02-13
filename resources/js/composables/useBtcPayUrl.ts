import { ref, computed } from 'vue';
import api from '../services/api';

const cached = ref<string | null>(null);
let fetchPromise: Promise<string> | null = null;

/**
 * Composable that returns the BTCPay Server base URL from the backend config.
 * Fetched once and cached for the session.
 */
export function useBtcPayUrl() {
  const btcPayUrl = computed(() => cached.value ?? '');
  const loaded = computed(() => cached.value !== null);

  async function load(): Promise<string> {
    if (cached.value) return cached.value;
    if (fetchPromise) return fetchPromise;
    fetchPromise = (async () => {
      try {
        const { data } = await api.get<{ btcpay_base_url: string }>('/config');
        cached.value = data.btcpay_base_url || '';
        return cached.value;
      } catch {
        cached.value = '';
        return '';
      }
    })();
    return fetchPromise;
  }

  return { btcPayUrl, loaded, load };
}
