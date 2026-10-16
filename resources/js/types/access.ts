export type PlanCode = 'free' | 'pro' | 'enterprise';
export interface PurchaseAvailability {
  pro_subscriptions: boolean;
  pro_credits: boolean;
  company_slots: boolean;
  isdoc_packs: boolean;
}
export interface EffectiveAccess {
  plan_code: PlanCode;
  source: 'free' | 'subscription' | 'promotion' | 'privileged' | 'legacy';
  features: string[];
  limits: {
    max_stores: number | null;
    max_api_keys: number | null;
    max_user_api_keys: number | null;
    max_ln_addresses: number | null;
    max_events: number | null;
    max_raffles: number | null;
    max_companies: number | null;
    included_companies: number | null;
    extra_company_slots: number;
  };
  policy_version: string;
  promotion: { active: boolean; id: string | null; ends_at: string | null };
}
export interface PublicAccessPolicy {
  promotion_active: boolean;
  promotion_ends_at: string | null;
  policy_version: string;
  purchase_availability: PurchaseAvailability;
}
export const PURCHASES_UNAVAILABLE: PurchaseAvailability = Object.freeze({
  pro_subscriptions: false, pro_credits: false, company_slots: false, isdoc_packs: false,
});
/** Missing or malformed entries never enable a purchase. */
export function parsePurchaseAvailability(value: unknown): PurchaseAvailability {
  const data = value && typeof value === 'object' ? value as Record<string, unknown> : {};
  return {
    pro_subscriptions: data.pro_subscriptions === true,
    pro_credits: data.pro_credits === true,
    company_slots: data.company_slots === true,
    isdoc_packs: data.isdoc_packs === true,
  };
}
