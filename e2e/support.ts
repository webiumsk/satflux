import type { Page } from '@playwright/test';

/**
 * Credentials created by `php artisan db:seed --class=E2eTestSeeder`.
 * Scenarios that need them are gated behind E2E_SEEDED_USER=1 so an
 * unseeded local run skips them instead of failing confusingly.
 */
export const seededUser = {
    email: process.env.E2E_USER_EMAIL ?? 'e2e@satflux.test',
    password: process.env.E2E_USER_PASSWORD ?? 'E2e-password-123',
};

export const hasSeededUser = process.env.E2E_SEEDED_USER === '1';

/**
 * Set when the target was built with VITE_INVOICING_LOCAL_FIRST=true (the
 * production configuration) - gates scenarios that assert local-first
 * routing behavior.
 */
export const hasLocalFirstBuild = process.env.E2E_INVOICING_LOCAL_FIRST === '1';

/**
 * Set when the BTCPay Greenfield stub (e2e/btcpay-stub/server.mjs) is running
 * and BTCPAY_BASE_URL points at it - gates the BTCPay lifecycle scenarios.
 */
export const hasBtcpayStub = process.env.E2E_BTCPAY === '1';

/** Control-API base of the BTCPay stub. */
export const btcpayStubUrl = process.env.BTCPAY_STUB_URL ?? 'http://localhost:14142';

/** Storage-state file written by auth.setup.ts (one login per run). */
export const AUTH_STATE_PATH = 'e2e/.auth/user.json';

/**
 * Playwright's storageState carries cookies but page.request calls still need
 * Sanctum's stateful markers: a Referer on a stateful domain and the CSRF
 * token echoed from the XSRF-TOKEN cookie.
 */
export async function apiHeaders(page: Page): Promise<Record<string, string>> {
    return {
        Referer: page.url(),
        'X-XSRF-TOKEN': decodeURIComponent(
            (await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN')?.value ?? '',
        ),
    };
}

/**
 * Serial retries reuse the database and the free plan allows one store -
 * drop leftovers from earlier attempts before creating a fresh one.
 */
export async function deleteAllStores(page: Page): Promise<void> {
    const headers = await apiHeaders(page);
    const existing = (await (
        await page.request.get('/api/stores', { headers })
    ).json()) as { data?: Array<{ id: string }> };
    for (const stale of existing.data ?? []) {
        await page.request.delete(`/api/stores/${stale.id}`, { headers });
    }
}

/**
 * Create a store through the real UI (step 1 of the create wizard) and
 * return the panel store id from the POST /api/stores response.
 */
export async function createStoreViaUi(page: Page, name: string): Promise<string> {
    await page.goto('/stores/create');
    await page.fill('#name', name);
    await page.fill('#default_currency', 'EUR');

    const createResponse = page.waitForResponse(
        (r) => r.url().includes('/api/stores') && r.request().method() === 'POST',
    );
    // Step-1 submits via a type="button" labeled "Next Step" - not a submit button.
    await page.getByRole('button', { name: /next step/i }).click();
    const created = (await (await createResponse).json()) as { data?: { id: string } };
    if (!created.data?.id) {
        throw new Error('store creation did not return an id');
    }

    return created.data.id;
}

/**
 * The cookie consent banner (fixed bottom overlay) intercepts pointer events
 * over the login submit button. Pre-seeding the consent choice keeps it from
 * rendering at all. Must run before the first page.goto.
 */
export async function dismissCookieConsent(page: Page): Promise<void> {
    await page.addInitScript(() => {
        window.localStorage.setItem('satflux_cookie_consent_v1', 'essential');
    });
}

/**
 * `/login?tab=email` reveals the email form in BOTH build modes: with
 * VITE_SEED_FIRST_REGISTRATION=true the form hides behind a "legacy login"
 * link, without it behind the Email tab - the query param short-circuits
 * both (Login.vue applyAuthTabFromQuery).
 */
export async function loginWithEmail(page: Page, email: string, password: string): Promise<void> {
    await dismissCookieConsent(page);
    await page.goto('/login?tab=email');
    await page.fill('input[type="email"]', email);
    await page.fill('input[type="password"]', password);
    await page.click('button[type="submit"]');
    // Wait out the login POST + redirect: a page.goto right after the click
    // aborts the in-flight request and the session never materializes.
    await page.waitForURL('**/dashboard');
}
