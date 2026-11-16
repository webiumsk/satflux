import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), companies: vi.fn() }));
vi.mock("@/services/api", () => ({ default: { get: mocks.get, post: mocks.post }, invoicingApi: { companies: { list: mocks.companies } } }));
vi.mock("@/evolu/client", () => ({ allDocumentSnapshotsQuery: "snapshots" }));
vi.mock("@/evolu/companySettingsCrud", () => ({ resolveLocalEmailSettingsForBridge: () => ({}) }));

import {
    buildBulkEphemeralRequest,
    buildEphemeralSnapshot,
    fetchEphemeralBtcpayCheckout,
    fetchEphemeralPaymentLinkStatus,
    fetchEphemeralPaymentLinks,
    updateEphemeralPaymentLinkState,
} from "@/evolu/ephemeralBridge";

beforeEach(() => { vi.clearAllMocks(); });

describe("minimal authenticated payment-link API", () => {
    it("lists registered intents and looks up by local document before a provider ID exists", async () => {
        mocks.get.mockResolvedValueOnce({ data: { data: [] } }).mockResolvedValueOnce({ data: { data: null } });
        await expect(fetchEphemeralPaymentLinks()).resolves.toEqual([]);
        await expect(fetchEphemeralPaymentLinkStatus("local/document")).resolves.toBeNull();
        expect(mocks.get.mock.calls).toEqual([
            ["/invoicing/ephemeral/btcpay-payment-links", { signal: undefined, skipErrorFlash: true }],
            ["/invoicing/ephemeral/btcpay-payment-link-status", {
                params: { evolu_document_id: "local/document" }, signal: undefined, skipErrorFlash: true,
            }],
        ]);
        expect(mocks.post).not.toHaveBeenCalled();
    });

    it("sends only an encoded intent identifier, CAS version and terminal state", async () => {
        mocks.post.mockResolvedValue({ data: { data: { state: "paid" } } });
        const controller = new AbortController();
        await updateEphemeralPaymentLinkState("intent/one", 8, "paid", controller.signal);
        expect(mocks.post).toHaveBeenCalledExactlyOnceWith(
            "/invoicing/ephemeral/btcpay-payment-links/intent%2Fone/state",
            { expected_version: 8, state: "paid" },
            { signal: controller.signal, skipErrorFlash: true },
        );
    });

    it("passes reopen CAS only for the separate explicit merchant checkout call", async () => {
        mocks.post.mockResolvedValue({ data: { data: { checkout_link: "https://example.test/i", btcpay_invoice_id: "provider" } } });
        const snapshot = buildEphemeralSnapshot(null, null, { type: "invoice", status: "issued" }, []);
        await fetchEphemeralBtcpayCheckout(snapshot, "store", "document", null, 5);
        expect(mocks.post.mock.calls[0][0]).toBe("/invoicing/ephemeral/btcpay-checkout");
        expect(mocks.post.mock.calls[0][1]).toMatchObject({ evolu_document_id: "document", store_id: "store", reopen_version: 5 });
    });
});

describe("bulk PDF payment-link identity", () => {
    it("preserves each local document/store identity without minting or calling checkout", async () => {
        mocks.companies.mockResolvedValue([]);
        const local = {
            evolu: {}, refreshAll: vi.fn(async () => {}),
            companyApi: () => ({ legal_name: "Synthetic Supplier", jurisdiction: "eu_sk" }),
            contactsForCompany: () => [],
            documentApi: (id: string) => ({ id, type: "invoice", status: "issued", number: id,
                currency: "EUR", total: "100", payment_btc_enabled: true, store_id: "store", lines: [] }),
            payloadFromApiDocument: () => ({ lines: [] }),
        };
        const result = await buildBulkEphemeralRequest(local as never, "company", ["document-one", "document-two"]);
        expect(result?.body.documents.map(doc => [doc.evolu_document_id, doc.store_id])).toEqual([
            ["document-one", "store"], ["document-two", "store"],
        ]);
        expect(mocks.post).not.toHaveBeenCalled();
    });
});
