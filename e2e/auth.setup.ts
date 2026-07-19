import { test as setup } from '@playwright/test';
import { AUTH_STATE_PATH, hasSeededUser, loginWithEmail, seededUser } from './support';

/**
 * One login for the whole run: /api/auth/* is throttled at 5/min per IP and
 * the suite sits right at that budget - every spec that only NEEDS a session
 * (not the login flow itself) reuses this stored state instead of signing in
 * again. Specs that test authentication (auth.spec, emailLogin.spec) opt out
 * with an empty storageState.
 */
setup('authenticate the seeded user', async ({ page }) => {
    if (!hasSeededUser) {
        // The chromium project still loads the state file - write an empty
        // one so unseeded runs (where those specs skip anyway) do not crash.
        await page.context().storageState({ path: AUTH_STATE_PATH });
        return;
    }

    await loginWithEmail(page, seededUser.email, seededUser.password);
    await page.context().storageState({ path: AUTH_STATE_PATH });
});
