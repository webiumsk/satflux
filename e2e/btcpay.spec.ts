import { expect, test } from '@playwright/test';
import { btcpayStubUrl, hasBtcpayStub, hasSeededUser, loginWithEmail, seededUser } from './support';

/**
 * BTCPay store + invoice lifecycle against the Greenfield stub
 * (e2e/btcpay-stub/server.mjs, docs/BTCPAY_E2E_SCENARIOS.md).
 *
 * Serial: the suite provisions ONE store through the real UI and walks it
 * through invoice creation, settlement (signed webhook back into the panel),
 * expiry and deletion. Requires E2E_BTCPAY=1 + the stub running + the seeder
 * run with E2E_BTCPAY=1 (merchant key fixture).
 */
test.describe.serial('BTCPay lifecycle (Greenfield stub)', () => {
    test.skip(!hasSeededUser || !hasBtcpayStub, 'requires E2E_SEEDED_USER=1 and E2E_BTCPAY=1 with the stub running');

    let storeId = '';
    let btcpayStoreId = '';
    let invoiceId = '';

    async function stub<T = Record<string, unknown>>(path: string, init?: RequestInit): Promise<T> {
        const response = await fetch(`${btcpayStubUrl}${path}`, init);
        return response.json() as Promise<T>;
    }

    test('store creation provisions through the stub and registers a webhook', async ({ page }) => {
        // Email login lands on /dashboard; the goto below does the navigation.
        await loginWithEmail(page, seededUser.email, seededUser.password);
        await page.waitForURL('**/dashboard');

        await page.goto('/stores/create');
        await page.fill('#name', 'E2E Stub Store');
        await page.fill('#default_currency', 'EUR');

        const createResponse = page.waitForResponse(
            (r) => r.url().includes('/api/stores') && r.request().method() === 'POST',
        );
        // Step-1 of the create wizard submits via a type="button" labeled
        // "Next Step" (create_store.next_step) - not a submit button.
        await page.getByRole('button', { name: /next step/i }).click();
        const created = (await (await createResponse).json()) as { data?: { id: string } };
        expect(created.data?.id).toBeTruthy();
        storeId = created.data!.id;

        // The provisioning chain ran against the stub: the store exists there
        // and the panel webhook is registered with a stub-minted secret.
        const state = await stub<{ stores: Array<{ id: string }> }>('/_stub/state');
        expect(state.stores.length).toBeGreaterThan(0);
        btcpayStoreId = state.stores[state.stores.length - 1]!.id;

        await page.goto('/stores');
        await expect(page.getByText('E2E Stub Store').first()).toBeVisible();
    });

    test('an invoice created on the BTCPay side appears in the store invoice list', async ({ page }) => {
        const invoice = await stub<{ id: string; status: string }>(`/_stub/stores/${btcpayStoreId}/invoices`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount: '21.00', currency: 'EUR' }),
        });
        expect(invoice.id).toBeTruthy();
        expect(invoice.status).toBe('New');
        invoiceId = invoice.id;

        await loginWithEmail(page, seededUser.email, seededUser.password);
        await page.goto(`/stores/${storeId}`);
        // Invoice lists render ids truncated to 8 chars (StoreInvoices/RecentInvoices).
        await expect(page.getByText(invoiceId.substring(0, 8)).first()).toBeVisible({ timeout: 15_000 });
    });

    test('settling fires a correctly signed webhook the panel accepts', async () => {
        const result = await stub<{ invoice: { status: string }; webhook: { status: number; body: string } }>(`/_stub/invoices/${invoiceId}/settle`, { method: 'POST' });
        expect(result.invoice.status).toBe('Settled');
        // End-to-end signature chain: the panel verified BTCPay-Sig against
        // the webhook secret it stored during provisioning and accepted the
        // delivery (duplicate deliveryIds would return status "duplicate").
        expect(result.webhook.status).toBe(200);
        expect(String(result.webhook.body)).toContain('received');
    });

    test('the settled status is visible in the invoice list', async ({ page }) => {
        await loginWithEmail(page, seededUser.email, seededUser.password);
        await page.goto(`/stores/${storeId}`);
        const row = page.getByText(invoiceId.substring(0, 8)).first();
        await expect(row).toBeVisible({ timeout: 15_000 });
        await expect(page.getByText(/settled/i).first()).toBeVisible({ timeout: 15_000 });
    });

    test('an expired invoice shows as expired', async ({ page }) => {
        const invoice = await stub<{ id: string }>(`/_stub/stores/${btcpayStoreId}/invoices`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ amount: '5.00', currency: 'EUR' }),
        });
        await stub(`/_stub/invoices/${invoice.id}/expire`, { method: 'POST' });

        await loginWithEmail(page, seededUser.email, seededUser.password);
        await page.goto(`/stores/${storeId}`);
        await expect(page.getByText(invoice.id.substring(0, 8)).first()).toBeVisible({ timeout: 15_000 });
        await expect(page.getByText(/expired/i).first()).toBeVisible({ timeout: 15_000 });
    });

    test('deleting the store removes it from the stub too', async ({ page }) => {
        await loginWithEmail(page, seededUser.email, seededUser.password);
        await page.goto(`/stores/${storeId}?section=settings`);

        // Open the confirmation modal, type the required word, confirm.
        await page.getByRole('button', { name: /delete store|zmazať obchod/i }).first().click();
        await page.fill('input[placeholder="DELETE"], [role="dialog"] input', 'DELETE');

        const deleteResponse = page.waitForResponse(
            (r) => r.url().includes(`/api/stores/${storeId}`) && r.request().method() === 'DELETE',
        );
        await page.locator('[role="dialog"] button', { hasText: /delete store|zmazať/i }).last().click();
        expect((await deleteResponse).ok()).toBe(true);

        const state = await stub<{ stores: Array<{ id: string }> }>('/_stub/state');
        expect(state.stores.map((store) => store.id)).not.toContain(btcpayStoreId);
    });
});
