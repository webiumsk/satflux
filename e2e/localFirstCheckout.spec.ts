import { expect, test, type Page } from '@playwright/test';
import {
    btcpayStubUrl,
    createStoreViaUi,
    deleteAllStores,
    dismissCookieConsent,
    hasBtcpayStub,
    hasLocalFirstBuild,
    hasSeededUser,
} from './support';

/**
 * Local-first invoicing -> BTCPay checkout, end to end in the browser
 * (docs/BTCPAY_E2E_SCENARIOS.md): bind a recovery phrase (client-side only -
 * Evolu boots from it), create a company linked to a stub-provisioned store,
 * issue an invoice, mint the ephemeral BTCPay checkout and settle it through
 * the stub's signed webhook until the document shows as paid.
 *
 * Valid BIP39 test vector - clearly fake, never a real phrase.
 */
const FAKE_PHRASE =
    'abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon abandon art';

test.describe.serial('Local-first invoice BTCPay checkout (Greenfield stub)', () => {
    test.skip(
        !hasSeededUser || !hasBtcpayStub || !hasLocalFirstBuild,
        'requires E2E_SEEDED_USER=1, E2E_BTCPAY=1 (stub running) and a local-first build',
    );

    let page: Page;
    let storeId = '';
    let btcpayStoreId = '';
    let btcpayInvoiceId = '';

    async function stub<T = Record<string, unknown>>(path: string, init?: RequestInit): Promise<T> {
        const response = await fetch(`${btcpayStubUrl}${path}`, init);
        return response.json() as Promise<T>;
    }

    test.beforeAll(async ({ browser }) => {
        page = await browser.newPage();
        await dismissCookieConsent(page);
        await page.goto('/dashboard');
    });

    test.afterAll(async () => {
        await page?.close();
    });

    test('binds the recovery phrase and links a stub store', async () => {
        await deleteAllStores(page);
        storeId = await createStoreViaUi(page, 'E2E Checkout Store');
        expect(storeId).toBeTruthy();

        // The stub keeps state across specs/retries - remember which stub
        // store belongs to THIS run so later assertions do not pick up
        // leftovers from earlier stores.
        const state = await stub<{ stores: Array<{ id: string }> }>('/_stub/state');
        btcpayStoreId = state.stores[state.stores.length - 1]!.id;

        // No session phrase yet - the invoicing guard bounces to the account
        // restore flow and opens the restore modal.
        await page.goto('/invoicing');
        await expect(page).toHaveURL(/\/account/, { timeout: 15_000 });

        const phraseInput = page.getByPlaceholder(/word1 word2/i);
        await expect(phraseInput).toBeVisible({ timeout: 15_000 });
        await phraseInput.fill(FAKE_PHRASE);
        await page.getByRole('button', { name: /link invoicing/i }).click();

        // Success closes the modal; Evolu boots from the phrase (wasm - give
        // it room). The guard now lets /invoicing through.
        await expect(phraseInput).toBeHidden({ timeout: 30_000 });
        await page.goto('/invoicing');
        await expect(page).toHaveURL(/\/invoicing/, { timeout: 30_000 });
    });

    test('creates a company linked to the store, a contact and an issued invoice', async () => {
        // Company with the store selected for Bitcoin payments. The form has
        // no label-for wiring - the legal name is the FIRST textbox (the
        // fields above it are comboboxes).
        await page.goto('/invoicing/companies/new');
        const legalName = page.getByRole('textbox').first();
        await expect(legalName).toBeVisible({ timeout: 30_000 });
        await legalName.fill('E2E Checkout Company');

        const storeSelect = page.locator('select').filter({
            has: page.locator(`option[value="${storeId}"]`),
        }).first();
        await expect(storeSelect).toBeVisible({ timeout: 30_000 });
        await storeSelect.selectOption(storeId);
        await page.locator('button[type="submit"]').click();
        await page.waitForURL((url) => !url.pathname.includes('/companies/new'), { timeout: 30_000 });

        // The new company is the active one; its contacts live under it.
        const companyId = await activeCompanyId();
        await page.goto(`/invoicing/companies/${companyId}/contacts/new`);
        const contactName = page.getByRole('textbox').first();
        await expect(contactName).toBeVisible({ timeout: 30_000 });
        await contactName.fill('E2E Customer');
        await page.locator('button[type="submit"]').click();
        await page.waitForURL((url) => !url.pathname.endsWith('/contacts/new'), { timeout: 30_000 });

        // Invoice: contact select, one line, save-and-issue in one action.
        await page.goto(`/invoicing/companies/${companyId}/invoices/new`);
        const contactSelect = page.locator('select[required]').first();
        await expect(contactSelect).toBeVisible({ timeout: 30_000 });
        await contactSelect.selectOption({ index: 1 });

        const dueDate = page.locator('input[type="date"][required]').last();
        if ((await dueDate.inputValue()) === '') {
            await dueDate.fill(new Date().toISOString().slice(0, 10));
        }

        // The line table renders desktop + mobile variants; only one is
        // visible at the test viewport.
        const lineName = page.locator('input.invoicing-sf-input-table:visible').first();
        await expect(lineName).toBeVisible({ timeout: 15_000 });
        await lineName.fill('E2E Service');

        await page.locator('button[type="submit"]').click();
        await page.waitForURL((url) => /\/invoices\/(?!new)/.test(url.pathname) || /\/documents\//.test(url.pathname), { timeout: 30_000 });
    });

    test('mints the ephemeral BTCPay checkout against the stub', async () => {
        await page.getByRole('button', { name: /create checkout link/i }).click();

        // The checkout link points at the stub's checkout page.
        const link = page.locator(`a[href*="${btcpayStubUrl.replace(/^https?:\/\//, '')}"]`).first();
        await expect(link).toBeVisible({ timeout: 30_000 });

        const state = await stub<{
            invoices: Array<{ id: string; storeId: string; metadata?: { ephemeral?: boolean; evoluDocumentId?: string } }>;
        }>('/_stub/state');
        const ephemeral = state.invoices.find(
            (inv) => inv.storeId === btcpayStoreId && inv.metadata?.ephemeral === true,
        );
        expect(ephemeral).toBeTruthy();
        expect(ephemeral!.metadata!.evoluDocumentId).toBeTruthy();
        btcpayInvoiceId = ephemeral!.id;
    });

    test('settling through the signed webhook marks the document paid', async () => {
        const result = await stub<{ invoice: { status: string }; webhook: { status: number } }>(
            `/_stub/invoices/${btcpayInvoiceId}/settle`,
            { method: 'POST' },
        );
        expect(result.invoice.status).toBe('Settled');
        expect(result.webhook.status).toBe(200);

        // Reload the invoice page: the checkout poll fires immediately on
        // mount, sees paid and marks the local document - no 15 s interval.
        await page.reload();
        await expect(page.getByText(/paid/i).first()).toBeVisible({ timeout: 30_000 });
    });

    /**
     * The company id of the just-created company: the index renders companies
     * as buttons - clicking one routes to companies/{id}/invoices, which
     * carries the Evolu id in the URL.
     */
    async function activeCompanyId(): Promise<string> {
        const fromUrl = page.url().match(/companies\/([0-9a-zA-Z_-]+)/);
        if (fromUrl) {
            return fromUrl[1]!;
        }
        await page.goto('/invoicing');
        await page.getByRole('button', { name: /E2E Checkout Company/i }).first().click();
        await page.waitForURL(/companies\/[0-9a-zA-Z_-]+/, { timeout: 30_000 });
        const match = page.url().match(/companies\/([0-9a-zA-Z_-]+)/);
        if (!match) {
            throw new Error('could not resolve the active company id');
        }

        return match[1]!;
    }
});
