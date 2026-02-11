import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '../services/api';

export interface PricingFree {
  sats_per_year: number;
}

export interface PricingPro {
  sats_per_year: number;
  sats_per_month_display: number;
}

export interface PricingData {
  free: PricingFree;
  pro: PricingPro;
}

const fallback: PricingData = {
  free: { sats_per_year: 0 },
  pro: { sats_per_year: 99_000, sats_per_month_display: 16_500 },
};

const cached = ref<PricingData | null>(null);
let fetchPromise: Promise<PricingData> | null = null;

export function usePricing() {
  const { locale } = useI18n();

  const pricing = computed(() => cached.value ?? fallback);

  const loaded = computed(() => cached.value !== null);

  function formatSats(amount: number): string {
    const localeTag = locale.value === 'sk' ? 'sk-SK' : locale.value === 'es' ? 'es-ES' : 'en-US';
    return new Intl.NumberFormat(localeTag).format(amount) + ' sats';
  }

  async function load() {
    if (cached.value) return cached.value;
    if (fetchPromise) return fetchPromise;
    fetchPromise = (async () => {
      try {
        const { data } = await api.get<{
          free: PricingFree;
          pro: PricingPro;
        }>('/pricing');
        cached.value = {
          free: { sats_per_year: data.free?.sats_per_year ?? 0 },
          pro: {
            sats_per_year: data.pro?.sats_per_year ?? 99_000,
            sats_per_month_display: data.pro?.sats_per_month_display ?? 16_500,
          },
        };
        return cached.value!;
      } catch {
        cached.value = fallback;
        return fallback;
      }
    })();
    return fetchPromise;
  }

  return {
    pricing,
    loaded,
    formatSats,
    load,
  };
}
