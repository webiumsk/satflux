import { ref, computed, getCurrentInstance, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { localeTagFor } from '@/i18n';
import api from '../services/api';
import { parsePurchaseAvailability, PURCHASES_UNAVAILABLE, type PublicAccessPolicy } from '../types/access';

export interface PricingFree {
  sats_per_year: number;
}

export interface PricingPro {
  sats_per_year: number;
  sats_per_month_display: number;
}

export interface CompanySlotPack {
  slots: number;
  sats: number;
}

export interface PricingData {
  trial_days: number;
  grace_days: number;
  free: PricingFree;
  pro: PricingPro;
  company_slot_packs: CompanySlotPack[];
  access_policy?: PublicAccessPolicy;
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
  company_slot_packs: [],
};

const cached = ref<PricingData | null>(null);
let fetchPromise: Promise<PricingData> | null = null;
let loadedAt = 0;
const POLICY_TTL_MS = 60_000;

export function usePricing() {
  const { locale } = useI18n();

  const pricing = computed(() => cached.value ?? fallback);

  const purchaseAvailability = computed(() => cached.value?.access_policy?.purchase_availability ?? PURCHASES_UNAVAILABLE);
  const promotionActive = computed(() => cached.value?.access_policy?.promotion_active === true);

  const loaded = computed(() => cached.value !== null);

  function formatSats(amount: number): string {
    return new Intl.NumberFormat(localeTagFor(locale.value)).format(amount) + ' sats';
  }

  async function load() {
    if (cached.value && Date.now() - loadedAt < POLICY_TTL_MS) return cached.value;
    if (fetchPromise) return fetchPromise;
    fetchPromise = (async () => {
      try {
        const { data } = await api.get<{
          trial_days?: number;
          grace_days?: number;
          free: PricingFree;
          pro: PricingPro;
          company_slot_packs?: CompanySlotPack[];
          access_policy?: PublicAccessPolicy;
        }>('/pricing');
        cached.value = {
          trial_days: data.trial_days ?? 30,
          grace_days: data.grace_days ?? 30,
          free: { sats_per_year: data.free?.sats_per_year ?? 0 },
          pro: {
            sats_per_year: data.pro?.sats_per_year ?? 210_000,
            sats_per_month_display: data.pro?.sats_per_month_display ?? 21_000,
          },
          company_slot_packs: Array.isArray(data.company_slot_packs) ? data.company_slot_packs : [],
          access_policy: data.access_policy ? {
            ...data.access_policy,
            purchase_availability: parsePurchaseAvailability(data.access_policy.purchase_availability),
          } : undefined,
        };
        loadedAt = Date.now();
        return cached.value!;
      } catch {
        cached.value = null;
        loadedAt = 0;
        return fallback;
      } finally {
        fetchPromise = null;
      }
    })();
    return fetchPromise;
  }

  if (getCurrentInstance()) {
    const refresh = () => { void load(); };
    const onVisible = () => { if (document.visibilityState === 'visible') refresh(); };
    onMounted(() => {
      window.addEventListener('focus', refresh);
      document.addEventListener('visibilitychange', onVisible);
    });
    onBeforeUnmount(() => {
      window.removeEventListener('focus', refresh);
      document.removeEventListener('visibilitychange', onVisible);
    });
  }

  return {
    pricing,
    purchaseAvailability,
    promotionActive,
    loaded,
    formatSats,
    load,
    proEffectiveMonthlySats,
    proHasMonthlyDiscount,
  };
}
