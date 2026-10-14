import { describe, expect, it } from 'vitest';
import { canCreateLocalCompany, hasEffectiveFeature, resolveBillingPlanCode, resolveEffectivePlanCode } from '../composables/useEffectiveAccess';
import { parsePurchaseAvailability } from '../types/access';
import type { User } from '../store/auth';

function account(overrides: Partial<User> = {}): User {
  return { id: 1, email: 'synthetic@example.test', role: 'free', is_guest: false,
    email_verified_at: '2026-01-01', ...overrides };
}
function promoted(): User {
  return account({ effective_access: {
    plan_code: 'pro', source: 'promotion', policy_version: 'promo-v1',
    promotion: { active: true, id: 'temporary-free-pro', ends_at: null },
    features: ['business_invoicing', 'stripe', 'xlsx_exports'],
    limits: { max_stores: 3, max_api_keys: 3, max_user_api_keys: 3,
      max_ln_addresses: null, max_events: 3, max_raffles: null,
      max_companies: 2, included_companies: 2, extra_company_slots: 0 },
  } });
}

describe('effective access and factual billing separation', () => {
  it('renders promotion as PRO features while keeping actual billing Free', () => {
    const user = promoted();
    const before = JSON.stringify(user);
    expect(resolveEffectivePlanCode(user)).toBe('pro');
    expect(resolveBillingPlanCode(user)).toBe('free');
    expect(hasEffectiveFeature(user, 'stripe')).toBe(true);
    expect(hasEffectiveFeature(user, 'per_store_user_management')).toBe(false);
    expect(hasEffectiveFeature(user, 'expense_isdoc_extract_unlimited')).toBe(false);
    expect(JSON.stringify(user)).toBe(before);
  });
  it('prioritizes the factual billing plan over role and feature hints', () => {
    const user = promoted();
    user.role = 'pro';
    user.plan = { code: 'free', name: 'Free', features: [], max_stores: 1, max_api_keys: 1, max_ln_addresses: 1 };
    expect(resolveBillingPlanCode(user)).toBe('free');
    expect(resolveEffectivePlanCode(user)).toBe('pro');
    expect(resolveBillingPlanCode(account({ role: 'admin' }))).toBe('free');
    expect(resolveEffectivePlanCode(account({ role: 'admin' }))).toBe('enterprise');
  });
  it('does not infer promo from email or registration with a legacy server', () => {
    expect(resolveEffectivePlanCode(account())).toBe('free');
    expect(hasEffectiveFeature(account(), 'business_invoicing')).toBe(false);
    expect(resolveEffectivePlanCode(account({ is_guest: true }))).toBe('free');
    expect(resolveEffectivePlanCode(null)).toBe('free');
  });
  it('preserves manual PRO and Enterprise factual plans on the legacy API', () => {
    expect(resolveEffectivePlanCode(account({ role: 'pro' }))).toBe('pro');
    expect(resolveBillingPlanCode(account({ role: 'pro' }))).toBe('pro');
    expect(resolveEffectivePlanCode(account({ role: 'enterprise' }))).toBe('enterprise');
    expect(hasEffectiveFeature(account({ role: 'pro' }), 'per_store_user_management')).toBe(false);
  });
  it('uses explicit server features rather than inferring all features from plan_code', () => {
    const user = promoted();
    user.effective_access!.features = [];
    user.role = 'admin';
    expect(hasEffectiveFeature(user, 'stripe')).toBe(false);
  });
  it('blocks direct local company creation at and above quota without deleting existing data', () => {
    const user = promoted();
    expect(canCreateLocalCompany(user, 1)).toBe(true);
    expect(canCreateLocalCompany(user, 2)).toBe(false);
    expect(canCreateLocalCompany(user, 9)).toBe(false);
    expect(canCreateLocalCompany({ ...user, is_guest: true }, 0)).toBe(false);
    expect(canCreateLocalCompany(account({ role: 'pro' }), 0)).toBe(false);
    user.effective_access!.limits.max_companies = null;
    expect(canCreateLocalCompany(user, 9)).toBe(true);
  });
  it('never enables missing or malformed purchase flags', () => {
    expect(Object.values(parsePurchaseAvailability(undefined))).toEqual([false, false, false, false]);
    expect(parsePurchaseAvailability({ pro_subscriptions: 'true', pro_credits: 1, company_slots: true }).company_slots).toBe(true);
    expect(parsePurchaseAvailability({ pro_subscriptions: 'true' }).pro_subscriptions).toBe(false);
  });
});
