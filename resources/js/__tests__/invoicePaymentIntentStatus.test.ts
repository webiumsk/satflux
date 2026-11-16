import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { defineComponent, nextTick, reactive, ref } from "vue";
import { flushPromises, mount, type VueWrapper } from "@vue/test-utils";
import type { EphemeralPaymentLink } from "@/evolu/ephemeralBridge";
import type { EvoluDocumentRow } from "@/evolu/documentMap";

const mocks = vi.hoisted(() => ({
    support: {} as Record<string, unknown>, route: {} as Record<string, unknown>, auth: {} as { user: { id: number } | null },
    get: vi.fn(), post: vi.fn(), status: null as EphemeralPaymentLink | null,
}));
vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "companies", allContactsQuery: "contacts", allDocumentLinesQuery: "lines",
    allDocumentsQuery: "documents", allNumberSeriesQuery: "series", allDocumentSnapshotsQuery: "snapshots",
    allDocumentEventsQuery: "events", allCompanyStockBalancesQuery: "balances", allCompanyStockItemsQuery: "items",
    allCompanyStockMovementsQuery: "movements", allCompanyWarehousesQuery: "warehouses",
}));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => true }));
vi.mock("@/composables/useLocalInvoiceDocument", () => ({ useLocalInvoiceDocumentSupport: () => mocks.support }));
vi.mock("@/store/stores", () => ({ useStoresStore: () => ({ stores: [], fetchStores: vi.fn() }) }));
vi.mock("@/store/auth", () => ({ useAuthStore: () => mocks.auth }));
vi.mock("@/evolu/companySettingsCrud", () => ({ resolveLocalEmailSettingsForBridge: () => ({}) }));
vi.mock("@/evolu/companyShareRegistry", () => ({ isCompanyShareRegistryReady: () => true, companyShareInfo: () => undefined }));
vi.mock("vue-router", () => ({ useRoute: () => mocks.route, useRouter: () => ({ push: vi.fn(), replace: vi.fn() }) }));
vi.mock("@/services/api", () => ({ default: { get: mocks.get, post: mocks.post }, invoicingApi: { companies: { list: async () => [] } } }));

import { useInvoiceDocument } from "@/composables/useInvoiceDocument";
import { evoluDocumentToApi } from "@/evolu/documentMap";

const wrappers: VueWrapper[] = [];
const activeIntent = (): EphemeralPaymentLink => ({ id: "intent", evolu_document_id: "document", version: 1,
    state: "active", store_id: "store", number: "20260001", amount: "100.00", currency: "EUR", btcpay_invoice_id: null,
    payment_method: null, payment_link: "https://example.test/pay/opaque" });

beforeEach(() => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    mocks.route = reactive({ params: { companyId: "company", documentId: "document" }, meta: { documentKind: "invoice" } });
    mocks.auth = reactive({ user: { id: 1 } });
    mocks.status = activeIntent();
    mocks.get.mockImplementation(async () => ({ data: { data: mocks.status } }));
    mocks.post.mockResolvedValue({ data: { data: null } });
});
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers(); });

async function editor() {
    const document = { id: "document", ownerId: "owner", companyId: "company", documentType: "invoice", status: "issued",
        number: "20260001", title: "Synthetic invoice", currency: "EUR", subtotal: "100.00", taxTotal: "0.00", total: "100.00",
        paymentBtcEnabled: 1, paymentBankEnabled: 1, storeId: "store", amountPaid: null } as unknown as EvoluDocumentRow;
    const update = vi.fn((table: string, patch: Record<string, unknown>) => {
        if (table === "document") Object.assign(document, patch);
        return { ok: true, value: { id: patch.id } };
    });
    mocks.support = {
        evolu: {
            appOwner: Promise.resolve({ id: "owner" }),
            update, insert: vi.fn(() => ({ ok: true, value: { id: "event" } })),
            getQueryRows: () => [document], loadQuery: async () => [],
        },
        refreshAll: vi.fn(async () => {}), documentRows: ref([document]),
        companyRows: ref([{ id: "company", ownerId: "owner" }]),
        documentApi: () => evoluDocumentToApi(document, []),
    };
    let state!: ReturnType<typeof useInvoiceDocument>;
    const wrapper = mount(defineComponent({ setup() { state = useInvoiceDocument(); return () => null; } }));
    wrappers.push(wrapper);
    state.company.value = { id: "company", legal_name: "Synthetic Supplier", jurisdiction: "eu_sk" } as never;
    await state.reloadDocument();
    await flushPromises();
    return { state, document, update };
}

describe("local-first delayed customer checkout settlement", () => {
    it("recovers from an initial non-404 lookup failure and discovers payment without a remount", async () => {
        mocks.get.mockRejectedValue({ response: { status: 503 } });
        const { state, document, update } = await editor();
        expect(state.btcPayUrl.value).toBe("");
        expect(mocks.post).not.toHaveBeenCalled();
        mocks.get.mockImplementation(async () => ({ data: { data: mocks.status } }));
        await vi.advanceTimersByTimeAsync(15_000);
        expect(state.btcPayUrl.value).toBe("https://example.test/pay/opaque");
        expect(update).not.toHaveBeenCalled();
        mocks.status = { ...activeIntent(), state: "paid", btcpay_invoice_id: "provider", payment_method: "btcpay", payment_link: null };
        await vi.advanceTimersByTimeAsync(15_000);
        expect(document.status).toBe("paid");
        expect(mocks.post).not.toHaveBeenCalled();
    });

    it("discovers the first PDF's later registered intent and payment without a page remount", async () => {
        mocks.status = null;
        const { state, document, update } = await editor();
        expect(state.btcPayUrl.value).toBe("");
        expect(mocks.post.mock.calls.map(([path]) => path)).toEqual(["/invoicing/ephemeral/btcpay-checkout/existing"]);
        mocks.status = activeIntent();
        await vi.advanceTimersByTimeAsync(15_000);
        expect(state.btcPayUrl.value).toBe("https://example.test/pay/opaque");
        expect(update).not.toHaveBeenCalled();
        mocks.status = { ...activeIntent(), state: "paid", btcpay_invoice_id: "provider", payment_method: "btcpay", payment_link: null };
        await vi.advanceTimersByTimeAsync(15_000);
        expect(document.status).toBe("paid");
        expect(mocks.post).toHaveBeenCalledOnce();
    });

    it("shows and polls a public intent with no provider invoice ID, then records only verified BTC settlement", async () => {
        const { state, document, update } = await editor();
        expect(state.btcPayUrl.value).toBe("https://example.test/pay/opaque");
        expect(mocks.post).not.toHaveBeenCalled();
        expect(update).not.toHaveBeenCalled();
        mocks.status = { ...activeIntent(), state: "paid", btcpay_invoice_id: "provider", payment_method: "btcpay", payment_link: null };
        await vi.advanceTimersByTimeAsync(15_000);
        await flushPromises();
        expect(update).toHaveBeenCalledWith("document", expect.objectContaining({ status: "paid", paidPaymentMethod: "btcpay", amountPaid: "100.00" }), { ownerId: "owner" });
        expect(document.status).toBe("paid");
        expect(state.btcPayUrl.value).toBe("");
        expect(mocks.get.mock.calls.every(([path]) => path === "/invoicing/ephemeral/btcpay-payment-link-status")).toBe(true);
    });

    it("does not turn an external/manual payment into BTCPay provenance", async () => {
        mocks.status = { ...activeIntent(), state: "paid", payment_method: "external", payment_link: null };
        const { document, update } = await editor();
        await vi.advanceTimersByTimeAsync(30_000);
        expect(update).not.toHaveBeenCalled();
        expect(document.status).toBe("issued");
    });

    it("does not mark a changed live amount paid from the previous provider intent", async () => {
        const { document, update } = await editor();
        document.total = "120.00";
        mocks.status = { ...activeIntent(), state: "paid", payment_method: "btcpay", btcpay_invoice_id: "provider" };
        await vi.advanceTimersByTimeAsync(15_000);
        expect(update).not.toHaveBeenCalled();
        expect(document.status).toBe("issued");
    });

    it.each(["account", "document"] as const)("discards a paid response after the %s changes", async change => {
        const { state, update } = await editor();
        let resolve!: (value: unknown) => void;
        mocks.get.mockImplementationOnce(() => new Promise(done => { resolve = done; }));
        await vi.advanceTimersByTimeAsync(15_000);
        if (change === "account") mocks.auth.user = { id: 2 };
        else (mocks.route.params as { documentId: string }).documentId = "different-document";
        await nextTick();
        resolve({ data: { data: { ...activeIntent(), state: "paid", payment_method: "btcpay", btcpay_invoice_id: "provider" } } });
        await flushPromises();
        expect(update).not.toHaveBeenCalled();
        expect(state.success.value).toBe("");
    });

    it("rechecks the owner after a held provider status response", async () => {
        const { update } = await editor();
        let resolve!: (value: unknown) => void;
        mocks.get.mockImplementationOnce(() => new Promise(done => { resolve = done; }));
        await vi.advanceTimersByTimeAsync(15_000);
        (mocks.support.evolu as { appOwner: Promise<{ id: string }> }).appOwner = Promise.resolve({ id: "different-owner" });
        resolve({ data: { data: { ...activeIntent(), state: "paid", payment_method: "btcpay", btcpay_invoice_id: "provider" } } });
        await flushPromises();
        expect(update).not.toHaveBeenCalled();
    });

    it.each([{ paymentBtcEnabled: 0 }, { documentType: "quote" }, { number: "20260002" }] as const)("rejects a held paid response after live state changes to %j", async patch => {
        const { document, update } = await editor();
        let resolve!: (value: unknown) => void;
        mocks.get.mockImplementationOnce(() => new Promise(done => { resolve = done; }));
        await vi.advanceTimersByTimeAsync(15_000);
        Object.assign(document, patch);
        resolve({ data: { data: { ...activeIntent(), state: "paid", payment_method: "btcpay", btcpay_invoice_id: "provider" } } });
        await flushPromises();
        expect(update).not.toHaveBeenCalled();
    });

    it("does not target either partition when a document ID is ambiguous", async () => {
        const { document, update } = await editor();
        (mocks.support.evolu as { getQueryRows: () => unknown[] }).getQueryRows = () => [document, { ...document, ownerId: "foreign-owner" }];
        mocks.status = { ...activeIntent(), state: "paid", payment_method: "btcpay", btcpay_invoice_id: "provider" };
        await vi.advanceTimersByTimeAsync(15_000);
        expect(update).not.toHaveBeenCalled();
    });

    it("explains revocation and sends fresh CAS only when the merchant explicitly creates a new checkout", async () => {
        mocks.status = { ...activeIntent(), state: "revoked", version: 7, payment_link: null };
        const { state } = await editor();
        expect(state.error.value).toBe("invoicing.local_first_btcpay_link_revoked");
        expect(state.btcPayUrl.value).toBe("");
        expect(mocks.post).not.toHaveBeenCalled();
        mocks.post.mockResolvedValue({ data: { data: { checkout_link: "https://example.test/i/new", btcpay_invoice_id: "new-provider" } } });
        await state.createLocalBtcpayCheckout();
        expect(mocks.post.mock.calls[0][1]).toMatchObject({ reopen_version: 7 });
        expect(state.btcPayUrl.value).toBe("https://example.test/i/new");
    });
});
