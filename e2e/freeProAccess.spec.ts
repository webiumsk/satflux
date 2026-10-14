import { expect, test } from '@playwright/test';
import { apiHeaders, hasSeededUser } from './support';

// Run only against the dedicated disposable promotion harness. Normal CI keeps
// promotion off; application activation is a separate release decision.
test.skip(process.env.E2E_FREE_PRO !== '1' || !hasSeededUser, 'Requires the isolated Free-PRO fixture.');

test('effective PRO keeps factual billing Free and closes direct purchases', async ({ page }) => {
    await page.route('**/api/subscriptions/details', route => route.fulfill({ json: {
        subscriber: null, billing: null, creditBalance: 0, creditHistory: [], creditPurchasesAvailable: false,
    } }));
    await page.goto('/account?tab=billing');
    await expect(page.getByText('PRO features temporarily free', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('link', { name: 'Access details', exact: true }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: /upgrade to pro|add credit|pay now|start.*trial/i })).toHaveCount(0);

    const headers = await apiHeaders(page);
    const profile = await (await page.request.get('/api/user', { headers })).json();
    expect(profile.plan.code).toBe('free');
    expect(profile.subscription).toBeNull();
    expect(profile.effective_access.plan_code).toBe('pro');
    expect(profile.effective_access.source).toBe('promotion');
    expect(profile.effective_access.features).not.toContain('per_store_user_management');
    expect(profile.effective_access.features).not.toContain('expense_isdoc_extract_unlimited');
    expect(profile.purchase_availability.pro_subscriptions).toBe(false);
    expect(profile.purchase_availability.company_slots).toBe(false);
    expect(profile.purchase_availability.isdoc_packs).toBe(false);
    const checkout = await page.request.post('/api/subscriptions/checkout', { headers, data: { plan: 'pro' } });
    expect(checkout.status()).toBe(403);
    expect((await checkout.json()).code).toBe('pro_purchase_temporarily_disabled');

    await page.reload();
    await expect(page.getByText('PRO features temporarily free', { exact: true }).first()).toBeVisible();
});

test('promotion grants no admin access and its localized terms remain readable', async ({ page }) => {
    await page.goto('/account');
    const denied = await page.request.get('/api/admin/users', { headers: await apiHeaders(page) });
    expect(denied.status()).toBe(403);
    await page.goto('/documentation/free-pro-access');
    await expect(page.getByRole('heading', { name: 'Temporary free PRO access', exact: true }).first()).toBeVisible();
    await expect(page.getByText(/until further notice/i).first()).toBeVisible();
});

test.describe('public promotion', () => {
    test.use({ storageState: { cookies: [], origins: [] } });
    test('landing uses live policy instead of trial or paid PRO checkout', async ({ page }) => {
        await page.goto('/#pricing');
        await expect(page.getByText('PRO features temporarily free', { exact: true }).first()).toBeVisible();
        const policy = await (await page.request.get('/api/pricing')).json();
        expect(policy.access_policy.promotion_active).toBe(true);
        expect(policy.access_policy.purchase_availability.pro_subscriptions).toBe(false);
        expect(policy.company_slot_packs).toEqual([]);
        await expect(page.getByRole('button', { name: /start.*trial|upgrade to pro/i })).toHaveCount(0);
    });
});
