import { beforeEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ companies: vi.fn() }));
vi.mock("@/services/api", () => ({ default: {}, invoicingApi: { companies: { list: mocks.companies } } }));
vi.mock("@/evolu/client", () => ({ allDocumentSnapshotsQuery: "snapshots" }));
vi.mock("@/evolu/companySettingsCrud", () => ({ resolveLocalEmailSettingsForBridge: () => ({}) }));

import { buildLocalDocumentEphemeralSnapshot } from "@/evolu/ephemeralBridge";
import { buildIssuedSnapshotContentV1, type EvoluDocumentSnapshotRow } from "@/evolu/documentSnapshotCrud";

beforeEach(() => { vi.clearAllMocks(); mocks.companies.mockResolvedValue([]); });

function fixture() {
    const company = { legal_name: "Synthetic Supplier", jurisdiction: "eu_sk", country: "SK", vat_payer: false };
    const document = { id: "document", type: "invoice", status: "issued", title: "Synthetic invoice", number: "20260001",
        currency: "EUR", subtotal: "90.00", tax_total: "0.00", total: "90.00", discount_percent: "0",
        amount_paid: "10.00", store_id: "store", payment_btc_enabled: true, pdf_bank_qr: "epc",
        lines: [{ name: "Old service", quantity: "1", unit_price: "100", line_discount_percent: "0", tax_rate: "0", line_total: "100.00" },
            { name: "Old deduction", quantity: "-1", unit_price: "10", line_discount_percent: "0", tax_rate: "0", line_total: "-10.00" }] };
    let rows: EvoluDocumentSnapshotRow[] = [];
    const insert = vi.fn(() => ({ ok: true, value: { id: "snapshot" } }));
    const local = {
        refreshAll: vi.fn(async () => {}),
        documentApi: () => document, companyApi: () => company, contactsForCompany: () => [],
        payloadFromApiDocument: vi.fn(() => ({ lines: [] })),
        evolu: { loadQuery: vi.fn(async () => rows), insert },
    };
    function frozen(currency = "USD", price = "250", deduction = "25", total = "225.00") {
        const content = buildIssuedSnapshotContentV1({
            company, contact: null, document: { ...document, currency, subtotal: total, total },
            lines: [{ ...document.lines[0], name: "New frozen service", unit_price: price, line_total: price },
                { ...document.lines[1], name: "New frozen deduction", unit_price: deduction, line_total: `-${deduction}` }],
        });
        if (!content.ok) throw new Error(content.error);
        return { id: `snapshot-${currency}`, documentId: "document", formatVersion: "1", payloadJson: JSON.stringify(content.value),
            backfilled: null, createdAt: "2026-11-16T00:00:00Z" };
    }
    return { local, document, insert, frozen, setRows: (next: EvoluDocumentSnapshotRow[]) => { rows = next; } };
}

describe("issued payment render snapshot consistency", () => {
    it("keeps currency, amounts and lines from the same newest frozen version after a held bridge lookup", async () => {
        const f = fixture();
        f.setRows([f.frozen("EUR", "100", "10", "90.00")]);
        let resolve!: (value: unknown[]) => void;
        mocks.companies.mockImplementationOnce(() => new Promise(done => { resolve = done; }));
        const running = buildLocalDocumentEphemeralSnapshot(f.local as never, "company", "document");
        await vi.waitFor(() => expect(mocks.companies).toHaveBeenCalledOnce());
        f.setRows([f.frozen("EUR", "100", "10", "90.00"), f.frozen()]);
        resolve([]);
        const result = await running;
        expect(result?.snapshot.document.currency).toBe("USD");
        expect(result?.snapshot.source_totals).toEqual({ subtotal: "225.00", tax_total: "0.00", total: "225.00", line_totals: ["250", "-25"] });
        expect(result?.snapshot.lines.map(line => [line.name, line.unit_price])).toEqual([
            ["New frozen service", 250], ["New frozen deduction", 25],
        ]);
        expect(result?.snapshot).toMatchObject({ store_id: "store", evolu_document_id: "document",
            document: { status: "issued", amount_paid: 10, payment_btc_enabled: true, pdf_bank_qr: "epc" } });
        expect(f.local.payloadFromApiDocument).not.toHaveBeenCalled();
        expect(f.insert).not.toHaveBeenCalled();
    });

    it.each(["malformed", "unsupported"] as const)("fails closed for a %s newest frozen snapshot without replacing it from live content", async invalid => {
        const f = fixture();
        const latest = f.frozen();
        if (invalid === "malformed") latest.payloadJson = "{invalid";
        else latest.formatVersion = "2";
        f.setRows([latest]);
        await expect(buildLocalDocumentEphemeralSnapshot(f.local as never, "company", "document"))
            .rejects.toThrow(invalid === "malformed" ? "snapshot_json_invalid" : "snapshot_version_unsupported");
        expect(f.local.payloadFromApiDocument).not.toHaveBeenCalled();
        expect(f.insert).not.toHaveBeenCalled();
    });
});
