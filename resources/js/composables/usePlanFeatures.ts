import { ref, computed } from 'vue';
import api from '../services/api';

export interface PlanFeaturesData {
  free: { feature_keys: string[] };
  pro: { feature_keys: string[] };
  enterprise: { feature_keys: string[] };
}

const fallback: PlanFeaturesData = {
  free: { feature_keys: ['store_1', 'pos_unlimited', 'ln_addresses_2', 'api_key_1', 'manual_csv', 'basic_stats', 'no_tx_fees'] },
  pro: { feature_keys: ['stores_3', 'pos_unlimited', 'ln_unlimited', 'api_keys_3', 'manual_auto_csv', 'advanced_stats', 'custom_branding', 'priority_support', 'stripe'] },
  enterprise: { feature_keys: ['stores_unlimited', 'webhooks', 'multi_user_roles', 'custom_reporting_formats', 'integration_support', 'pos_cash_card'] },
};

const cached = ref<PlanFeaturesData | null>(null);
let fetchPromise: Promise<PlanFeaturesData> | null = null;

export function usePlanFeatures() {
  const planFeatures = computed(() => cached.value ?? fallback);

  async function load() {
    if (cached.value) return cached.value;
    if (fetchPromise) return fetchPromise;
    fetchPromise = (async () => {
      try {
        const { data } = await api.get<PlanFeaturesData>('/plan-features');
        cached.value = {
          free: { feature_keys: data.free?.feature_keys ?? fallback.free.feature_keys },
          pro: { feature_keys: data.pro?.feature_keys ?? fallback.pro.feature_keys },
          enterprise: { feature_keys: data.enterprise?.feature_keys ?? fallback.enterprise.feature_keys },
        };
        return cached.value;
      } catch {
        cached.value = fallback;
        return fallback;
      }
    })();
    return fetchPromise;
  }

  return {
    planFeatures,
    load,
  };
}
