import { test, expect } from '@playwright/test';
import { hasLocalFirstBuild, hasSeededUser, loginWithEmail, seededUser } from './support';

/**
 * The local-first invoicing hard-gate (P0/P1): an authenticated user whose
 * device has no recovery phrase must not reach /invoicing - the router
 * redirects to the account page and opens the restore flow. This is the
 * data-loss guard that keeps a fresh browser from silently starting an
 * empty second dataset.
 */
test.describe('Invoicing recovery-phrase guard', () => {
    test.skip(!hasSeededUser, 'requires E2E_SEEDED_USER=1 and `php artisan db:seed --class=E2eTestSeeder`');
    test.skip(!hasLocalFirstBuild, 'requires a build with VITE_INVOICING_LOCAL_FIRST=true (set E2E_INVOICING_LOCAL_FIRST=1)');

    test('without a phrase on this device /invoicing redirects to the account restore flow', async ({ page }) => {
        await loginWithEmail(page, seededUser.email, seededUser.password);
        await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });

        await page.goto('/invoicing');
        await expect(page).toHaveURL(/\/account/, { timeout: 15000 });
    });
});
