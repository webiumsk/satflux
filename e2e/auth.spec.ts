import { test, expect } from '@playwright/test';

test.describe('Authentication', () => {
    test('login page loads', async ({ page }) => {
        await page.goto('/login');
        await expect(page.locator('input[type="email"]')).toBeVisible();
        await expect(page.locator('input[type="password"]')).toBeVisible();
    });

    test('unauthenticated user is redirected from /stores to /login', async ({ page }) => {
        await page.goto('/stores');
        await expect(page).toHaveURL(/\/login/);
    });

    test('invalid credentials show error', async ({ page }) => {
        await page.goto('/login');
        await page.fill('input[type="email"]', 'nobody@example.com');
        await page.fill('input[type="password"]', 'wrongpassword');
        await page.click('button[type="submit"]');
        await expect(page.locator('[role="alert"], .flash-error, .error')).toBeVisible({ timeout: 5000 });
    });
});
