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
        getQueryRows: vi.fn(() => []),
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

describe("applyReservedNumberToLocalDocument snapshot invariant", () => {
    it("cannot apply an ordinary number to a pending migration", async () => {
        const { applyReservedNumberToLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const pending = { ...draft, migrationFingerprint: "pending", migrationCompletedAt: null };
        const apply = (doc: EvoluDocumentRow, lines: EvoluDocumentLineRow[]) => applyReservedNumberToLocalDocument(
            evolu as never, documentId, companyId, "invoice", "20260071", series, null,
            { company, doc, allDocuments: [doc], lines, contact: null },
        );
        expect(apply(pending, [])).toEqual({ ok: false, error: "migration_pending" });
        expect(evolu.calls).toEqual([]);
    });

    it("issues and freezes a positive invoice with a deduction", async () => {
        const { applyReservedNumberToLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const doc = { ...draft, subtotal: "80.00", taxTotal: "16.00", total: "96.00" };
        const deduction = { ...line, id: "line-2" as DocumentLineId, sortOrder: "1", unitPrice: "-20.0000", lineTotal: "-24.00" };
        const result = applyReservedNumberToLocalDocument(
            evolu as never, documentId, companyId, "invoice", "20260071", series, null,
            { company: { ...company, jurisdiction: "eu_sk", vatPayer: 1, vatStatus: "payer" }, doc, allDocuments: [doc], lines: [line, deduction], contact: null },
        );
        expect(result.ok).toBe(true);
        const snapshot = evolu.calls.find(call => call.table === "documentSnapshot")?.row;
        const payload = JSON.parse(String(snapshot?.payloadJson));
        expect(payload.document.total).toBe("96.00");
        expect(payload.lines[1].unit_price).toBe("-20.0000");
        expect(evolu.calls.some(call => call.table === "document" && call.row.status === "issued")).toBe(true);
    });

    it.each(["0.00", "-1.00", "Infinity"])("does not issue a deduction with total %s", async total => {
        const { applyReservedNumberToLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const doc = { ...draft, total };
        const result = applyReservedNumberToLocalDocument(
            evolu as never, documentId, companyId, "invoice", "20260071", series, null,
            { company, doc, allDocuments: [doc], lines: [{ ...line, unitPrice: "-20.00" }], contact: null },
        );
        expect(result).toEqual({ ok: false, error: "positive_total_required" });
        expect(evolu.calls).toEqual([]);
    });

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

    it("refuses the snapshot (and the issue) when the draft has no lines", async () => {
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
                lines: [],
                contact: null,
            },
        );

        expect(result).toEqual({ ok: false, error: "snapshot_lines_missing" });
        expect(
            evolu.calls.some((c) => c.table === "document" && c.row.status === "issued"),
        ).toBe(false);
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

    it("blocks edits to a persisted pending migration", async () => {
        const { saveLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        evolu.getQueryRows.mockReturnValue([{ ...draft, migrationFingerprint: "pending", migrationCompletedAt: null }] as never);
        expect(saveLocalDocument(evolu as never, companyId, savePayload, { ...taxOptions, documentId }))
            .toEqual({ ok: false, error: "migration_pending" });
        expect(evolu.calls).toEqual([]);
    });

    it("saves a deduction with reduced VAT while retaining payment options", async () => {
        const { saveLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const signed = { ...savePayload, lines: [
            { ...savePayload.lines[0], unit_price: 100 },
            { ...savePayload.lines[0], name: "Deduction", unit_price: -20 },
        ] };
        const result = saveLocalDocument(evolu as never, companyId, signed, taxOptions);
        expect(result.ok).toBe(true);
        const document = evolu.calls.find(call => call.table === "document")?.row;
        expect(document).toMatchObject({ subtotal: "80.00", taxTotal: "16.00", total: "96.00" });
        const lines = evolu.calls.filter(call => call.table === "documentLine");
        expect(lines[1].row).toMatchObject({ unitPrice: "-20.0000", lineTotal: "-24.00" });
    });

    it.each([[0, "nonzero_quantity_required"], [-1, "unsupported_negative_lines"], [Number.POSITIVE_INFINITY, "nonzero_quantity_required"]] as const)("rejects a deduction with invalid quantity %s", async (quantity, error) => {
        const { saveLocalDocument } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const signed = { ...savePayload, lines: [{ ...savePayload.lines[0], unit_price: -20, quantity }] };
        expect(saveLocalDocument(evolu as never, companyId, signed, taxOptions))
            .toEqual({ ok: false, error });
        expect(evolu.calls).toEqual([]);
    });

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
