import { computed } from 'vue';
import { useAuthStore, type User } from '../store/auth';
import { parsePurchaseAvailability, type PlanCode } from '../types/access';

/** Factual legacy plan only. Never infer promotional access from registration or email. */
export function resolveBillingPlanCode(user: User | null | undefined): PlanCode {
  if (!user) return 'free';
  const actualPlan = user.plan?.code;
  if (actualPlan === 'enterprise' || actualPlan === 'pro' || actualPlan === 'free') return actualPlan;
  // Manual legacy assignments predate canonical profile snapshots.
  if (user.role === 'enterprise') return 'enterprise';
  if (user.role === 'pro') return 'pro';
  return 'free';
}

export function resolveEffectivePlanCode(user: User | null | undefined): PlanCode {
  if (!user) return 'free';
  if (user.effective_access) {
    const code = user.effective_access.plan_code;
    return code === 'pro' || code === 'enterprise' ? code : 'free';
  }
  if (user.role === 'admin' || user.role === 'support' || user.role === 'enterprise') return 'enterprise';
  if (user.role === 'pro') return 'pro';
  if (user.plan_features?.business_invoicing || user.plan?.features?.includes('business_invoicing')) {
    return resolveBillingPlanCode(user) === 'enterprise' ? 'enterprise' : 'pro';
  }
  return resolveBillingPlanCode(user);
}

const legacyProFeatures = new Set([
  'advanced_statistics', 'automatic_csv_exports', 'offline_payment_methods', 'stripe',
  'business_invoicing', 'custom_branding', 'store_customization', 'advanced_pos',
  'app_archiving', 'xlsx_exports',
]);

/** UI hints only. Protected API operations always recheck server authorization. */
export function hasEffectiveFeature(user: User | null | undefined, feature: string): boolean {
  if (!user) return false;
  if (user.effective_access) return Array.isArray(user.effective_access.features)
    && user.effective_access.features.includes(feature);
  if (user.plan?.features?.includes(feature)) return true;
  const plan = resolveEffectivePlanCode(user);
  return (plan === 'pro' || plan === 'enterprise') && legacyProFeatures.has(feature);
}

/** Undefined means unknown; null is only an explicit unlimited value. */
export function effectiveCompanyLimit(user: User | null | undefined): number | null | undefined {
  if (!user) return undefined;
  if (user.effective_access) return user.effective_access.limits?.max_companies;
  if (!user.plan) return undefined;
  if (user.plan.companies_unlimited === true) return null;
  return user.plan.max_companies;
}

export function canCreateLocalCompany(user: User | null | undefined, current: number): boolean {
  if (!user || user.is_guest || !user.email_verified_at || !hasEffectiveFeature(user, 'business_invoicing')) return false;
  const max = effectiveCompanyLimit(user);
  return max === null || (typeof max === 'number' && current < max);
}

export function useEffectiveAccess() {
  const auth = useAuthStore();
  const planCode = computed(() => resolveEffectivePlanCode(auth.user));
  const billingPlanCode = computed(() => resolveBillingPlanCode(auth.user));
  const isPromotion = computed(() => auth.user?.effective_access?.source === 'promotion');
  const promotionActive = computed(() => auth.user?.effective_access?.promotion?.active === true);
  const purchases = computed(() => parsePurchaseAvailability(auth.user?.purchase_availability));
  const companyLimit = computed(() => effectiveCompanyLimit(auth.user));
  function hasFeature(feature: string): boolean { return hasEffectiveFeature(auth.user, feature); }
  return { planCode, billingPlanCode, isPromotion, promotionActive, purchases, companyLimit, hasFeature };
}
