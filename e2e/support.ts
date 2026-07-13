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
}
