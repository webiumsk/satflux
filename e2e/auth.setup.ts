import { test as setup } from '@playwright/test';
import {
    AUTH_STATE_PATH, CHECKOUT_AUTH_STATE_PATH, checkoutUser,
    hasBtcpayStub, hasLocalFirstBuild, hasSeededUser, loginWithEmail, seededUser,
} from './support';

/**
 * One login per enabled fixture: /api/auth/* is throttled at 5/min per IP.
 * Specs needing only a session reuse stored state instead of signing in
 * again. Checkout has its own account/API budget. Authentication specs
 * (auth.spec, emailLogin.spec) opt out with an empty storageState.
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

setup('authenticate the isolated checkout user', async ({ page }) => {
    if (hasSeededUser && hasBtcpayStub && hasLocalFirstBuild) {
        await loginWithEmail(page, checkoutUser.email, checkoutUser.password);
    }
    await page.context().storageState({ path: CHECKOUT_AUTH_STATE_PATH });
});
