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
  trial_days: number;
  grace_days: number;
  free: PricingFree;
  pro: PricingPro;
}

export function proEffectiveMonthlySats(pro: PricingPro): number {
  return Math.round(pro.sats_per_year / 12);
}

export function proHasMonthlyDiscount(pro: PricingPro): boolean {
  return pro.sats_per_month_display > proEffectiveMonthlySats(pro);
}

const fallback: PricingData = {
  trial_days: 30,
  grace_days: 30,
  free: { sats_per_year: 0 },
  pro: { sats_per_year: 210_000, sats_per_month_display: 21_000 },
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
          trial_days?: number;
          grace_days?: number;
          free: PricingFree;
          pro: PricingPro;
        }>('/pricing');
        cached.value = {
          trial_days: data.trial_days ?? 30,
          grace_days: data.grace_days ?? 30,
          free: { sats_per_year: data.free?.sats_per_year ?? 0 },
          pro: {
            sats_per_year: data.pro?.sats_per_year ?? 210_000,
            sats_per_month_display: data.pro?.sats_per_month_display ?? 21_000,
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
    proEffectiveMonthlySats,
    proHasMonthlyDiscount,
  };
}
