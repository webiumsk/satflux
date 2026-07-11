import { describe, expect, it, vi, beforeEach } from "vitest";
import type { EvoluCompanyRow } from "@/evolu/companyMap";
import type { EvoluDocumentRow, EvoluDocumentLineRow } from "@/evolu/documentMap";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import type { CompanyId, DocumentId, DocumentLineId } from "@/evolu/schema";

// documentCrud transitively imports the real Evolu client (WASM sqlite) via
// ./client - replace it with inert query tokens before importing the module.
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

const companyId = "cmp-1" as CompanyId;
const documentId = "doc-1" as DocumentId;

const company = {
    id: companyId,
    legalName: "ACME s.r.o.",
    registrationNumber: "12345678",
    vatRateDefault: "20",
} as unknown as EvoluCompanyRow;

const draft = {
    id: documentId,
    companyId,
    documentType: "invoice",
    status: "draft",
    title: "Invoice",
    number: null,
    variableSymbol: null,
    quoteStatus: null,
    currency: "EUR",
    subtotal: "100.00",
    taxTotal: "20.00",
    discountPercent: "0.00",
    total: "120.00",
} as unknown as EvoluDocumentRow;

const line = {
    id: "line-1" as DocumentLineId,
    documentId,
    sortOrder: "0",
    name: "Work",
    quantity: "1.0000",
    unit: "ks",
    unitPrice: "100.0000",
    lineDiscountPercent: "0.00",
    taxRate: "20.00",
    lineTotal: "120.00",
} as unknown as EvoluDocumentLineRow;

const series = [
    {
        id: "series-1",
        companyId,
        documentType: "invoice",
        format: "YYYYNNNN",
        isDefault: 1,
        name: "Default",
        resetPeriod: "yearly",
    } as unknown as EvoluNumberSeriesRow,
];

type Call = { table: string; row: Record<string, unknown> };
type MutationResult =
    | { ok: true; value: { id: string } }
    | { ok: false; error: string };

function fakeEvolu() {
    const calls: Call[] = [];
    return {
        calls,
        insert: vi.fn((table: string, row: Record<string, unknown>): MutationResult => {
            calls.push({ table, row });
            return { ok: true, value: { id: `${table}-id` } };
        }),
        update: vi.fn((table: string, row: Record<string, unknown>) => {
            calls.push({ table, row });
            return { ok: true, value: { id: row.id } };
        }),
        upsert: vi.fn((table: string, row: Record<string, unknown>) => {
            calls.push({ table, row });
            return { ok: true, value: { id: row.id ?? `${table}-id` } };
        }),
    };
}

beforeEach(() => {
    vi.resetModules();
});

describe("issueLocalDocument snapshot invariant", () => {
    it("persists the frozen snapshot before flipping the document to issued", async () => {
        const { issueLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();

        const result = issueLocalDocument(
            evolu as never,
            documentId,
            company,
            [draft],
            series,
            { lines: [line], contact: null },
        );

        expect(result.ok).toBe(true);
        const snapshotIndex = evolu.calls.findIndex((c) => c.table === "documentSnapshot");
        const issueIndex = evolu.calls.findIndex(
            (c) => c.table === "document" && c.row.status === "issued",
        );
        expect(snapshotIndex).toBeGreaterThanOrEqual(0);
        expect(issueIndex).toBeGreaterThan(snapshotIndex);

        const snapshotRow = evolu.calls[snapshotIndex].row;
        const payload = JSON.parse(String(snapshotRow.payloadJson));
        expect(payload.company.legal_name).toBe("ACME s.r.o.");
        expect(payload.document.number).toBeTruthy();
        expect(payload.document.number).toBe(
            evolu.calls[issueIndex].row.number,
        );
        expect(payload.lines).toHaveLength(1);
        expect(payload.lines[0].line_total).toBe("120.00");
    });

    it("does not issue when the snapshot cannot be persisted", async () => {
        const { issueLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        evolu.insert.mockImplementation((table: string): MutationResult =>
            table === "documentSnapshot"
                ? { ok: false, error: "boom" }
                : { ok: true, value: { id: `${table}-id` } },
        );

        const result = issueLocalDocument(
            evolu as never,
            documentId,
            company,
            [draft],
            series,
            { lines: [line], contact: null },
        );

        expect(result.ok).toBe(false);
        expect(
            evolu.calls.some((c) => c.table === "document" && c.row.status === "issued"),
        ).toBe(false);
    });

    it("refuses the snapshot (and the issue) when the draft has no lines", async () => {
        const { issueLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();

        const result = issueLocalDocument(
            evolu as never,
            documentId,
            company,
            [draft],
            series,
            { lines: [], contact: null },
        );

        expect(result).toEqual({ ok: false, error: "snapshot_lines_missing" });
        expect(
            evolu.calls.some((c) => c.table === "document" && c.row.status === "issued"),
        ).toBe(false);
    });
});

describe("applyReservedNumberToLocalDocument snapshot invariant", () => {
    it("fails without issuing or advancing the series counter when the snapshot insert fails", async () => {
        const { applyReservedNumberToLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        evolu.insert.mockImplementation((table: string): MutationResult =>
            table === "documentSnapshot"
                ? { ok: false, error: "boom" }
                : { ok: true, value: { id: `${table}-id` } },
        );

        const result = applyReservedNumberToLocalDocument(
            evolu as never,
            documentId,
            companyId,
            "invoice",
            "20260071",
            series,
            null,
            {
                company,
                doc: draft,
                allDocuments: [draft],
                lines: [line],
                contact: null,
            },
        );

        expect(result.ok).toBe(false);
        expect(
            evolu.calls.some((c) => c.table === "document" && c.row.status === "issued"),
        ).toBe(false);
        expect(evolu.calls.some((c) => c.table === "numberSeries")).toBe(false);
    });

    it("issues, then advances the counter, when the snapshot persists", async () => {
        const { applyReservedNumberToLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();

        const result = applyReservedNumberToLocalDocument(
            evolu as never,
            documentId,
            companyId,
            "invoice",
            "20260071",
            series,
            null,
            {
                company,
                doc: draft,
                allDocuments: [draft],
                lines: [line],
                contact: null,
            },
        );

        expect(result.ok).toBe(true);
        const snapshotIndex = evolu.calls.findIndex((c) => c.table === "documentSnapshot");
        const issueIndex = evolu.calls.findIndex(
            (c) => c.table === "document" && c.row.status === "issued",
        );
        const counterIndex = evolu.calls.findIndex((c) => c.table === "numberSeries");
        expect(snapshotIndex).toBeGreaterThanOrEqual(0);
        expect(issueIndex).toBeGreaterThan(snapshotIndex);
        expect(counterIndex).toBeGreaterThan(issueIndex);
    });
});

describe("saveLocalDocument re-freeze", () => {
    const savePayload = {
        type: "invoice",
        company_contact_id: "",
        store_id: "",
        title: "Invoice",
        issue_date: "2026-07-11",
        delivery_date: "",
        due_date: "2026-07-25",
        variable_symbol: "20260071",
        constant_symbol: "",
        specific_symbol: "",
        currency: "EUR",
        discount_percent: 0,
        note_above_lines: "",
        note_footer: "",
        internal_note: "",
        pdf_locale: "sk",
        pdf_show_signature: true,
        pdf_show_payment_info: true,
        payment_bank_enabled: true,
        payment_btc_enabled: false,
        tags: [],
        lines: [
            {
                name: "Work",
                description: null,
                quantity: 1,
                unit: "ks",
                unit_price: 150,
                line_discount_percent: 0,
                tax_rate: 20,
                company_stock_item_id: null,
                company_warehouse_id: null,
            },
        ],
    };

    const taxOptions = {
        defaultVat: 20,
        lineTaxApplies: () => true,
        lineTaxRate: (l: { tax_rate: number }) => l.tax_rate,
        existingLines: [] as EvoluDocumentLineRow[],
    };

    it("appends a new snapshot version when an issued document is saved", async () => {
        const { saveLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const issued = { ...draft, status: "issued", number: "20260071" } as EvoluDocumentRow;

        const result = saveLocalDocument(evolu as never, companyId, savePayload, {
            ...taxOptions,
            documentId,
            existingDocument: issued,
            refreezeContext: {
                company: { legal_name: "ACME s.r.o." },
                contact: null,
            },
        });

        expect(result.ok).toBe(true);
        const snapshot = evolu.calls.find((c) => c.table === "documentSnapshot");
        expect(snapshot).toBeTruthy();
        const payload = JSON.parse(String(snapshot?.row.payloadJson));
        expect(payload.document.number).toBe("20260071");
        expect(payload.document.total).toBe("180.00");
        expect(payload.lines[0].unit_price).toBe("150.0000");
    });

    it("does not write snapshots for draft saves", async () => {
        const { saveLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();

        const result = saveLocalDocument(evolu as never, companyId, savePayload, {
            ...taxOptions,
            documentId,
            existingDocument: draft,
            refreezeContext: {
                company: { legal_name: "ACME s.r.o." },
                contact: null,
            },
        });

        expect(result.ok).toBe(true);
        expect(evolu.calls.some((c) => c.table === "documentSnapshot")).toBe(false);
    });
});
