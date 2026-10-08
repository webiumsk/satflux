import { expect, test, type Page } from '@playwright/test';
import { validateMnemonic } from '@scure/bip39';
import { wordlist } from '@scure/bip39/wordlists/english.js';
import fixtures from './fixtures/recovery-accounts.json' with { type: 'json' };
import {
    apiHeaders,
    createStoreViaUi,
    dismissCookieConsent,
    hasBtcpayStub,
    hasSeededUser,
} from './support';

test.use({ storageState: { cookies: [], origins: [] }, actionTimeout: 15_000, locale: 'en-US' });

test('recovery enrollment respects shared sessions across tabs and restores the right account', async ({ page, context, browser, baseURL }, testInfo) => {
    test.skip(!hasSeededUser || !hasBtcpayStub || process.env.E2E_AUTH_RECOVERY !== '1',
        'requires E2E_SEEDED_USER=1, E2E_BTCPAY=1, E2E_AUTH_RECOVERY=1 and their seeded fixtures');
    test.setTimeout(240_000);

    // One enrolling account per execution: repeats and retries share the DB.
    const executionsPerRepeat = testInfo.project.retries + 1;
    expect(fixtures.enrollment.length, 'seed enough recovery accounts for the configured repeats and retries')
        .toBeGreaterThanOrEqual(testInfo.project.repeatEach * executionsPerRepeat);
    const recoveryEmail = fixtures.enrollment[testInfo.repeatEachIndex * executionsPerRepeat + testInfo.retry];
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);

    // Other specs share the IP auth quota, but use different user API quotas.
    // A throttled login is safe to resubmit: the middleware has not run login.
    let remaining = 5;
    let nextWindow = 0;
    function observeAuthResponse(headers: Record<string, string>): void {
        const value = headers['x-ratelimit-remaining'];
        if (value !== undefined) {
            remaining = Number(value);
            const retryAfter = Number(headers['retry-after']);
            nextWindow = Date.now() + (retryAfter > 0 ? retryAfter + 1 : 61) * 1000;
        }
    }
    function observe(tab: Page): void {
        tab.on('response', response => {
            if (new URL(response.url()).pathname.startsWith('/api/auth/')) {
                observeAuthResponse(response.headers());
            }
        });
    }
    async function authCapacity(count = 1): Promise<void> {
        if (remaining < count) {
            await test.step('Wait for the real authentication rate-limit window', async () => {
                await page.waitForTimeout(Math.max(0, nextWindow - Date.now()));
            });
            remaining = 5;
        }
    }
    async function login(tab: Page, email: string): Promise<void> {
        await dismissCookieConsent(tab);
        await tab.goto('/login?tab=email');
        await tab.getByRole('textbox', { name: /email/i }).fill(email);
        await tab.locator('input[type="password"]').fill(fixtures.password);
        for (let attempt = 0; attempt < 2; attempt++) {
            await authCapacity();
            const [response] = await Promise.all([
                tab.waitForResponse(r => new URL(r.url()).pathname === '/api/auth/login' && r.request().method() === 'POST'),
                tab.locator('button[type="submit"]').click(),
            ]);
            if (response.status() === 429 && attempt === 0) {
                expect(Number(response.headers()['retry-after'])).toBeGreaterThan(0);
                continue;
            }
            expect(response.status()).toBe(200);
            await expect(tab).toHaveURL(/\/dashboard/);
            return;
        }
    }
    async function api(tab: Page, path: string, body?: object, csrf = true) {
        const result = await tab.evaluate(async ({ path, body, csrf }) => {
            const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
            const response = await fetch(path, {
                method: body === undefined ? 'GET' : 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrf && cookie ? { 'X-XSRF-TOKEN': decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) } : {}),
                },
                ...(body === undefined ? {} : { body: JSON.stringify(body) }),
            });
            return { status: response.status, data: await response.json(), headers: Object.fromEntries(response.headers) };
        }, { path, body, csrf });
        if (path.startsWith('/api/auth/')) observeAuthResponse(result.headers);
        return result;
    }
    async function user(tab: Page) {
        const result = await api(tab, '/api/user');
        expect(result.status).toBe(200);
        return result.data;
    }
    async function preparePhrase(tab: Page): Promise<string> {
        const dialog = tab.getByRole('dialog');
        await dialog.getByRole('button', { name: 'Continue', exact: true }).click();
        const previous = await tab.evaluate(() => navigator.clipboard.readText());
        await dialog.getByRole('button', { name: 'Copy phrase to clipboard', exact: true }).click();
        let phrase = '';
        await expect.poll(async () => {
            phrase = await tab.evaluate(() => navigator.clipboard.readText());
            return phrase;
        }).not.toBe(previous);
        expect(phrase.split(/\s+/)).toHaveLength(24);
        expect(validateMnemonic(phrase, wordlist)).toBe(true);
        await dialog.getByRole('button', { name: 'Continue', exact: true }).click();
        await dialog.getByRole('checkbox').check();
        return phrase;
    }
    async function finishPhrase(tab: Page, endpoint: string) {
        const [response] = await Promise.all([
            tab.waitForResponse(r => new URL(r.url()).pathname === endpoint && r.request().method() === 'POST'),
            tab.getByRole('dialog').getByRole('button', { name: 'Start guest session', exact: true }).click(),
        ]);
        return response;
    }
    async function clearRecoveryStores(tab: Page): Promise<void> {
        // Cleanup is restricted to this scenario's dedicated enrolling account.
        expect((await user(tab)).email).toBe(recoveryEmail);
        const headers = await apiHeaders(tab);
        const response = await tab.request.get('/api/stores', { headers });
        expect(response.ok()).toBe(true);
        const stores = await response.json() as { data: Array<{ id: string }> };
        for (const store of stores.data) {
            const deletion = await tab.request.delete('/api/stores/' + store.id, { headers });
            expect(deletion.ok()).toBe(true);
        }
    }

    observe(page);
    const otherTab = await context.newPage();
    observe(otherTab);
    const original = await test.step('Reject guest signup after another tab signs in', async () => {
        await dismissCookieConsent(page);
        await page.goto('/register');
        await page.getByRole('button', { name: 'Create account with recovery phrase', exact: true }).click();
        await preparePhrase(page);
        await login(otherTab, fixtures.original);
        const account = await user(otherTab);
        expect(account.guest_recovery_enrolled).toBe(false);

        await authCapacity();
        const signup = await finishPhrase(page, '/api/auth/guest');
        expect(signup.status()).toBe(409);
        expect((await signup.json()).code).toBe('already_authenticated');
        await expect(page.getByText(/already signed in to another account/i)).toBeVisible();
        const unchanged = await user(page);
        expect(unchanged.id).toBe(account.id);
        expect(unchanged.guest_recovery_enrolled).toBe(false);
        expect(unchanged.can_use_password_login).toBe(true);
        return account;
    });

    const current = await test.step('Reject Profile enrollment after another tab changes account', async () => {
        await page.goto('/account');
        await page.getByRole('button', { name: 'Set up recovery phrase', exact: true }).click();
        await preparePhrase(page);
        await login(otherTab, recoveryEmail);
        const account = await user(otherTab);
        expect(account.id).not.toBe(original.id);

        const enrollment = await finishPhrase(page, '/api/account/recovery-key');
        expect(enrollment.status()).toBe(409);
        expect((await enrollment.json()).code).toBe('account_changed');
        await expect(page.getByText(/signed-in account changed/i)).toBeVisible();
        const unchanged = await user(otherTab);
        expect(unchanged.id).toBe(account.id);
        expect(unchanged.guest_recovery_enrolled).toBe(false);
        expect(unchanged.can_use_password_login).toBe(true);
        return account;
    });

    await test.step('Reject cookie-authenticated enrollment without CSRF', async () => {
        const response = await api(otherTab, '/api/account/recovery-key', {
            expected_user_id: current.id,
            recovery_public_key: 'a'.repeat(64),
        }, false);
        expect(response.status).toBe(419);
        expect((await user(otherTab)).guest_recovery_enrolled).toBe(false);
    });

    const phrase = await test.step('Deliberately enroll the current account through Profile', async () => {
        await otherTab.goto('/account');
        await otherTab.getByRole('button', { name: 'Set up recovery phrase', exact: true }).click();
        const savedPhrase = await preparePhrase(otherTab);
        expect((await finishPhrase(otherTab, '/api/account/recovery-key')).status()).toBe(200);
        await expect.poll(async () => (await user(otherTab)).can_use_password_login).toBe(false);
        return savedPhrase;
    });

    await test.step('Reject the correct password with the recovery-required error and no session', async () => {
        await authCapacity();
        expect((await api(otherTab, '/api/auth/logout', {})).status).toBe(200);
        await authCapacity();
        const blocked = await api(otherTab, '/api/auth/login', { email: recoveryEmail, password: fixtures.password });
        expect(blocked.status).toBe(422);
        expect(blocked.data.errors.email).toEqual([
            'This account uses a recovery phrase for sign-in. Use "Restore with recovery phrase" on the login page.',
        ]);
        expect((await api(otherTab, '/api/user')).status).toBe(401);
    });

    await authCapacity(2);
    const freshContext = await browser.newContext({
        baseURL, locale: 'en-US', storageState: { cookies: [], origins: [] },
    });
    freshContext.setDefaultTimeout(15_000);
    const restoredTab = await freshContext.newPage();
    observe(restoredTab);
    try {
        await test.step('Restore on a fresh device and keep the session after reload', async () => {
            await dismissCookieConsent(restoredTab);
            await restoredTab.goto('/register');
            await restoredTab.getByRole('button', { name: 'Restore with recovery phrase', exact: true }).click();
            await restoredTab.getByRole('dialog').locator('textarea').fill(phrase);
            const [response] = await Promise.all([
                restoredTab.waitForResponse(r => new URL(r.url()).pathname === '/api/auth/guest/recovery'),
                restoredTab.getByRole('dialog').getByRole('button', { name: 'Restore and sign in', exact: true }).click(),
            ]);
            expect(response.status()).toBe(200);
            await expect(restoredTab).toHaveURL(/\/dashboard/, { timeout: 30_000 });
            expect((await user(restoredTab)).id).toBe(current.id);
            await restoredTab.reload();
            await expect(restoredTab).toHaveURL(/\/dashboard/);
            expect((await user(restoredTab)).id).toBe(current.id);
        });

        await test.step('Create a store after restore and always clean up', async () => {
            // Recover from interrupted prior runs without deleting other users' stores.
            await clearRecoveryStores(restoredTab);
            try {
                const storeId = await createStoreViaUi(restoredTab, 'Recovery onboarding');
                const stores = await api(restoredTab, '/api/stores');
                expect(stores.status).toBe(200);
                expect(stores.data.data.some((store: { id: string }) => store.id === storeId)).toBe(true);
            } finally {
                await clearRecoveryStores(restoredTab);
            }
        });
    } catch (error) {
        // Playwright traces all browser contexts. Attach this page explicitly:
        // the fixture page's failure snapshot otherwise shows the stale tab.
        await restoredTab.screenshot({ timeout: 5000 }).then(body =>
            testInfo.attach('fresh-device-failure', { body, contentType: 'image/png' }),
        ).catch(() => { /* Diagnostic failure must not hide the original error. */ });
        throw error;
    } finally {
        await freshContext.close();
    }
});
