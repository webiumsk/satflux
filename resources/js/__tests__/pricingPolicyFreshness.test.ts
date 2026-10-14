import { describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
const mocks = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('../services/api', () => ({ default: { get: mocks.get } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ locale: ref('en') }) }));
vi.mock('../i18n', () => ({ localeTagFor: () => 'en-US' }));
import { usePricing } from '../composables/usePricing';
describe('public pricing policy freshness', () => {
  it('keeps failed loading closed, retries and refreshes a stale successful policy', async () => {
    let now = 1_000_000;
    vi.spyOn(Date, 'now').mockImplementation(() => now);
    const pricing = usePricing();
    mocks.get.mockRejectedValueOnce(new Error('offline'));
    await pricing.load();
    expect(pricing.loaded.value).toBe(false);
    expect(pricing.promotionActive.value).toBe(false);
    expect(pricing.purchaseAvailability.value.pro_subscriptions).toBe(false);
    mocks.get.mockResolvedValueOnce({ data: { free: { sats_per_year: 0 }, pro: { sats_per_year: 210000 }, access_policy: {
      promotion_active: true, promotion_ends_at: null, policy_version: 'v1',
      purchase_availability: { pro_subscriptions: false, company_slots: 'true' },
    } } });
    await pricing.load();
    expect(pricing.loaded.value).toBe(true);
    expect(pricing.promotionActive.value).toBe(true);
    expect(pricing.pricing.value.pro.sats_per_year).toBe(210000);
    expect(pricing.purchaseAvailability.value.company_slots).toBe(false);
    await pricing.load();
    expect(mocks.get).toHaveBeenCalledTimes(2);
    now += 60_001;
    mocks.get.mockResolvedValueOnce({ data: { free: { sats_per_year: 0 }, pro: { sats_per_year: 210000 }, access_policy: {
      promotion_active: false, promotion_ends_at: null, policy_version: 'v2',
      purchase_availability: { pro_subscriptions: true },
    } } });
    await pricing.load();
    expect(pricing.promotionActive.value).toBe(false);
    expect(pricing.purchaseAvailability.value.pro_subscriptions).toBe(true);
    vi.restoreAllMocks();
  });
});
