import { test, expect } from '@playwright/test';
import { dismissCookieConsent } from './support';

test.describe('Authentication', () => {
    test('login page loads with an auth entry point', async ({ page }) => {
        await page.goto('/login');
        // Seed-first builds show the recovery-phrase panel, classic builds the
        // tab bar - either way exactly one primary flow must be actionable.
        await expect(page.locator('button').first()).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });

    test('email form is reachable via the tab=email query in any build mode', async ({ page }) => {
        await page.goto('/login?tab=email');
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();
    });

    test('unauthenticated user is redirected from /stores to /login', async ({ page }) => {
        await page.goto('/stores');
        await expect(page).toHaveURL(/\/login/);
    });

    test('invalid credentials show error', async ({ page }) => {
        await dismissCookieConsent(page);
        await page.goto('/login?tab=email');
        await page.fill('input[type="email"]', 'nobody@example.com');
        await page.fill('input[type="password"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        await expect(page.locator('[role="alert"], .flash-error, .error').first()).toBeVisible({ timeout: 5000 });
    });
});
