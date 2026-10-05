import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { EvoluCompanyRow } from "@/evolu/companyMap";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "@/evolu/documentMap";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import type { CompanyId, DocumentId, DocumentLineId } from "@/evolu/schema";

// documentCrud transitively imports the real Evolu client (WASM sqlite).
vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "allCompaniesDetailQuery",
    allContactsQuery: "allContactsQuery",
    allDocumentLinesQuery: "allDocumentLinesQuery",
    allDocumentsQuery: "allDocumentsQuery",
    allNumberSeriesQuery: "allNumberSeriesQuery",
    allCompanyStockBalancesQuery: "allCompanyStockBalancesQuery",
    allCompanyStockItemsQuery: "allCompanyStockItemsQuery",
    allCompanyStockMovementsQuery: "allCompanyStockMovementsQuery",
    allCompanyWarehousesQuery: "allCompanyWarehousesQuery",
}));

const reserveMock = vi.hoisted(() => vi.fn());
vi.mock("@/evolu/numberAllocatorBridge", () => ({
    reserveIssueNumber: reserveMock,
    confirmIssueNumber: vi.fn(),
}));

const companyId = "cmp-1" as CompanyId;
const company = { id: companyId, legalName: "ACME s.r.o.", vatRateDefault: "20" } as unknown as EvoluCompanyRow;
const series = [{
    id: "series-1",
    companyId,
    documentType: "invoice",
    format: "INVYYYYNNNN",
    isDefault: 1,
    name: "Default",
    resetPeriod: "yearly",
    periodKey: null,
    lastNumber: "0",
}] as unknown as EvoluNumberSeriesRow[];

function doc(overrides: Partial<Record<string, unknown>>): EvoluDocumentRow {
    return {
        id: "doc-1",
        companyId,
        documentType: "invoice",
        status: "draft",
        title: "Document",
        number: null,
        variableSymbol: null,
        quoteStatus: null,
        sourceDocumentId: null,
        currency: "EUR",
        subtotal: "100.00",
        taxTotal: "20.00",
        discountPercent: "0.00",
        total: "120.00",
        issueDate: "2027-01-02",
        ...overrides,
    } as unknown as EvoluDocumentRow;
}

const line = {
    id: "line-1" as DocumentLineId,
    documentId: "doc-1",
    sortOrder: "0",
    name: "Work",
    quantity: "1.0000",
    unit: "ks",
    unitPrice: "100.0000",
    lineDiscountPercent: "0.00",
    taxRate: "20.00",
    lineTotal: "120.00",
} as unknown as EvoluDocumentLineRow;

type Call = { table: string; row: Record<string, unknown> };

function fakeEvolu(documents: EvoluDocumentRow[]) {
    const calls: Call[] = [];
    const rowsFor: Record<string, unknown[]> = {
        allDocumentsQuery: documents,
        allNumberSeriesQuery: series,
        allDocumentLinesQuery: [line],
        allContactsQuery: [],
    };
    return {
        calls,
        loadQuery: vi.fn(async (query: string) => rowsFor[query] ?? []),
        insert: vi.fn((table: string, row: Record<string, unknown>) => {
            calls.push({ table, row });
            return { ok: true, value: { id: `${table}-id` } };
        }),
        update: vi.fn((table: string, row: Record<string, unknown>) => {
            calls.push({ table, row });
            return { ok: true, value: { id: row.id } };
        }),
    };
}

function reservation(overrides: Record<string, unknown> = {}) {
    return {
        ok: true,
        value: { number: "x", counter: 57, status: "reserved", bridgeCompanyId: "bridge-1", periodKey: "2027", ...overrides },
    };
}

beforeEach(() => {
    vi.resetModules();
    reserveMock.mockReset();
    vi.useFakeTimers();
    vi.setSystemTime(new Date(2027, 0, 2, 10, 0));
});

afterEach(() => {
    vi.useRealTimers();
});

describe("prepareCopiedPayload", () => {
    it("drops the source's number-derived title and VS and restarts the dates today", async () => {
        const { prepareCopiedPayload } = await import("@/evolu/documentCrud");
        const copy = prepareCopiedPayload({
            title: "Faktúra INV20260005",
            variable_symbol: "20260005",
            issue_date: "2026-03-01",
            due_date: "2026-03-15",
            delivery_date: "2026-03-01",
        } as never, "2027-01-02");

        expect(copy.title).toBe("");
        expect(copy.variable_symbol).toBe("");
        expect(copy.issue_date).toBe("2027-01-02");
        // The source's 14-day payment term carries over.
        expect(copy.due_date).toBe("2027-01-16");
        expect(copy.delivery_date).toBe("2027-01-02");
    });
});

describe("conversion guards", () => {
    const saveOptions = { defaultVat: 20, lineTaxApplies: () => true, lineTaxRate: () => 20, existingLines: [] };

    it("refuses an invoice from a quote that is not approved, or already converted", async () => {
        const { createLocalInvoiceFromQuote } = await import("@/evolu/documentCrud");
        const quote = doc({ id: "q-1", documentType: "quote", status: "issued", number: "QT20260003", quoteStatus: "pending" });
        const evolu = fakeEvolu([quote]);

        expect(createLocalInvoiceFromQuote(evolu as never, "q-1" as DocumentId, [quote], [], () => ({}) as never, saveOptions as never))
            .toEqual({ ok: false, error: "not_approved" });

        const approved = doc({ ...quote, quoteStatus: "approved" });
        const existing = doc({ id: "inv-1", sourceDocumentId: "q-1", status: "draft" });
        expect(createLocalInvoiceFromQuote(evolu as never, "q-1" as DocumentId, [approved, existing], [], () => ({}) as never, saveOptions as never))
            .toEqual({ ok: false, error: "already_exists" });
        expect(evolu.calls).toEqual([]);
    });

    it("refuses a final invoice from an unpaid proforma", async () => {
        const { createLocalFinalInvoiceFromProforma } = await import("@/evolu/documentCrud");
        const proforma = doc({ id: "pf-1", documentType: "proforma", status: "issued", number: "PF20260002" });
        expect(createLocalFinalInvoiceFromProforma(fakeEvolu([proforma]) as never, "pf-1" as DocumentId, [proforma], [], () => ({}) as never, saveOptions as never))
            .toEqual({ ok: false, error: "not_paid" });
    });

    it("creates the final invoice of a paid proforma without payment instructions", async () => {
        const { createLocalFinalInvoiceFromProforma } = await import("@/evolu/documentCrud");
        const proforma = doc({ id: "pf-1", documentType: "proforma", status: "paid", number: "PF20260002" });
        const evolu = fakeEvolu([proforma]);
        const payload = {
            type: "proforma", company_contact_id: "", store_id: "", title: "Zálohová faktúra PF20260002",
            issue_date: "2026-12-20", delivery_date: "", due_date: "2027-01-03", variable_symbol: "20260002",
            constant_symbol: "", specific_symbol: "", currency: "EUR", discount_percent: 0,
            note_above_lines: "", note_footer: "", internal_note: "", pdf_locale: "sk",
            pdf_show_signature: true, pdf_show_payment_info: true, payment_bank_enabled: true,
            payment_btc_enabled: true, tags: [],
            lines: [{ name: "Work", description: null, quantity: 1, unit: "ks", unit_price: 100, line_discount_percent: 0, tax_rate: 0, company_stock_item_id: null, company_warehouse_id: null }],
        };

        const result = createLocalFinalInvoiceFromProforma(
            evolu as never, "pf-1" as DocumentId, [proforma], [], () => payload as never, saveOptions as never,
        );

        expect(result.ok).toBe(true);
        const inserted = evolu.calls.find((c) => c.table === "document")?.row;
        expect(inserted?.pdfShowPaymentInfo).toBe(0);
        expect(inserted?.paymentBankEnabled).toBe(0);
        expect(inserted?.paymentBtcEnabled).toBe(0);
        expect(inserted?.variableSymbol).toBeNull();
        expect(inserted?.issueDate).toBe("2027-01-02");
    });
});

describe("issueLocalDocumentAsync", () => {
    it("formats a retried reservation in its own period, not today's", async () => {
        const { issueLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const draft = doc({});
        const evolu = fakeEvolu([draft]);
        // Reserved on Dec 31 2026, applied on Jan 2 2027.
        reserveMock.mockResolvedValue(reservation({ counter: 57, periodKey: "2026" }));

        const result = await issueLocalDocumentAsync(evolu as never, "doc-1" as DocumentId, company);

        expect(result.ok).toBe(true);
        const issued = evolu.calls.find((c) => c.table === "document" && c.row.status === "issued");
        expect(issued?.row.number).toBe("INV20260057");
    });

    it("never applies a voided reservation", async () => {
        const { issueLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu([doc({})]);
        reserveMock.mockResolvedValue(reservation({ status: "voided" }));

        expect(await issueLocalDocumentAsync(evolu as never, "doc-1" as DocumentId, company))
            .toEqual({ ok: false, error: "reserve_failed" });
    });

    it("titles a fallback-titled document with its type and the real number", async () => {
        const { issueLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu([doc({ title: "Document" })]);
        reserveMock.mockResolvedValue(reservation({ counter: 3 }));

        await issueLocalDocumentAsync(evolu as never, "doc-1" as DocumentId, company);

        const titled = evolu.calls.find((c) => c.table === "document" && typeof c.row.title === "string");
        expect(String(titled?.row.title)).toMatch(/INV20270003$/);
        expect(titled?.row.title).not.toBe("Document");
    });

    it("issues a final invoice of a paid proforma as paid with the proforma's payment", async () => {
        const { issueLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const proforma = doc({
            id: "pf-1",
            documentType: "proforma",
            status: "paid",
            number: "PF20260002",
            paidAt: "2026-12-20T10:00:00.000Z",
            amountPaid: "120.00",
        });
        const finalInvoice = doc({ sourceDocumentId: "pf-1" });
        const evolu = fakeEvolu([finalInvoice, proforma]);
        reserveMock.mockResolvedValue(reservation({ counter: 4 }));

        await issueLocalDocumentAsync(evolu as never, "doc-1" as DocumentId, company);

        expect(evolu.calls).toContainEqual({
            table: "document",
            row: { id: "doc-1", status: "paid", paidAt: "2026-12-20T10:00:00.000Z", amountPaid: "120.00" },
        });
    });
});
