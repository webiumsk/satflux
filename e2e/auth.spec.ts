import { test, expect } from '@playwright/test';
import { dismissCookieConsent, openMigrationLogin } from './support';

// This spec exercises the login flow itself - start unauthenticated
// instead of inheriting the shared storage state.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Authentication', () => {
    test('login page loads with an auth entry point', async ({ page }) => {
        await page.goto('/login');
        // Recovery/passkey remains the primary entry; migration is secondary.
        await expect(page.locator('button').first()).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });

    test('password migration needs an explicit action even with legacy query flags', async ({ page }) => {
        await dismissCookieConsent(page);
        await page.goto('/login?tab=email&legacy=1');
        await expect(page.locator('input[type="email"]')).not.toBeVisible();
        await expect(page.getByRole('link', { name: /forgot password/i })).toHaveCount(0);
        await page.getByRole('button', { name: 'Migrate an older account', exact: true }).click();
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();
    });

    test('unauthenticated user is redirected from /stores to /login', async ({ page }) => {
        await page.goto('/stores');
        await expect(page).toHaveURL(/\/login/);
    });

    test('invalid credentials show error', async ({ page }) => {
        await openMigrationLogin(page);
        await page.fill('input[type="email"]', 'nobody@example.com');
        await page.fill('input[type="password"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        await expect(page.locator('[role="alert"], .flash-error, .error').first()).toBeVisible({ timeout: 5000 });
    });
});
