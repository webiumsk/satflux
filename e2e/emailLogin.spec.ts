import { test, expect } from '@playwright/test';
import { hasSeededUser, loginWithEmail, seededUser } from './support';

// This spec exercises the login flow itself - start unauthenticated
// instead of inheriting the shared storage state.
test.use({ storageState: { cookies: [], origins: [] } });

test.describe('Email login (seeded user)', () => {
    test.skip(!hasSeededUser, 'requires E2E_SEEDED_USER=1 and `php artisan db:seed --class=E2eTestSeeder`');

    test('valid credentials sign in and land on the dashboard', async ({ page }) => {
        await loginWithEmail(page, seededUser.email, seededUser.password);
        await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
    });

    test('the Sanctum session survives a full page reload', async ({ page }) => {
        await loginWithEmail(page, seededUser.email, seededUser.password);
        await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });

        await page.reload();
        // The router guard bounces an unauthenticated user to /login - staying
        // on an app page proves the cookie session was honored end to end.
        // (Note: a FULL page load of /login itself deliberately skips fetchUser
        // in the router guard, so "authenticated user opens /login" is NOT
        // redirected - that redirect only applies to in-app navigation.)
        await expect(page).toHaveURL(/\/dashboard/, { timeout: 15000 });
    });
});
