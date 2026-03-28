import { ref, computed } from 'vue';
import api from '../services/api';

function hostFromBaseUrl(base: string): string {
  if (!base || typeof base !== 'string') return '';
  try {
    const withScheme = /^[a-z][a-z0-9+.-]*:\/\//i.test(base) ? base : `https://${base}`;
    return new URL(withScheme).hostname;
  } catch {
    return '';
  }
}

const cachedBase = ref<string | null>(null);
const cachedLnDomain = ref<string | null>(null);
let fetchPromise: Promise<string> | null = null;

/**
 * BTCPay base URL and Lightning Address domain from GET /api/config
 * (BTCPAY_BASE_URL; LN host = BTCPAY_LIGHTNING_ADDRESS_DOMAIN or BTCPay URL hostname).
 */
export function useBtcPayUrl() {
  const btcPayUrl = computed(() => (cachedBase.value === null ? '' : cachedBase.value));
  const lightningAddressDomain = computed(() =>
    cachedLnDomain.value === null ? '' : cachedLnDomain.value
  );
  /**
   * LN domain for UI: API value, else hostname parsed from btcpay_base_url.
   * Before /config loads, optional VITE_BTCPAY_BASE_URL hostname for dev-only hints.
   */
  const displayLightningDomain = computed(() => {
    if (cachedBase.value === null) {
      return hostFromBaseUrl((import.meta.env.VITE_BTCPAY_BASE_URL as string) || '');
    }
    const fromApi = cachedLnDomain.value ?? '';
    if (fromApi !== '') return fromApi;
    return hostFromBaseUrl(btcPayUrl.value);
  });
  const loaded = computed(() => cachedBase.value !== null);

  async function load(): Promise<string> {
    if (cachedBase.value !== null) return cachedBase.value;
    if (fetchPromise) return fetchPromise;
    fetchPromise = (async () => {
      try {
        const { data } = await api.get<{
          btcpay_base_url: string;
          btcpay_lightning_address_domain?: string;
        }>('/config');
        cachedBase.value = (data.btcpay_base_url || '').replace(/\/$/, '');
        cachedLnDomain.value = (data.btcpay_lightning_address_domain || '').trim();
        return cachedBase.value;
      } catch {
        cachedBase.value = '';
        cachedLnDomain.value = '';
        return '';
      }
    })();
    return fetchPromise;
  }

  return {
    btcPayUrl,
    lightningAddressDomain,
    displayLightningDomain,
    loaded,
    load,
  };
}
