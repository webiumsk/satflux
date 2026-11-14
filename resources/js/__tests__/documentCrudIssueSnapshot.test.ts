import { describe, expect, it, vi, beforeEach } from "vitest";
import type { EvoluCompanyRow } from "@/evolu/companyMap";
import type { EvoluContactRow } from "@/evolu/contactMap";
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
const allocator = vi.hoisted(() => ({ reserve: vi.fn(), confirm: vi.fn() }));
vi.mock("@/evolu/numberAllocatorBridge", () => ({ reserveIssueNumber: allocator.reserve, confirmIssueNumber: allocator.confirm }));

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
        getQueryRows: vi.fn((_query: unknown): unknown[] => []),
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
    allocator.reserve.mockReset();
    allocator.confirm.mockReset();
});

describe("primary local issue context admission", () => {
    it.each([false, true])("issues unchanged admitted content through the allocator (signed: %s)", async signed => {
        const { issueLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const supplier = { ...company, jurisdiction: "eu_sk", country: "SK", vatPayer: 1 as const, vatStatus: "payer" };
        const doc = signed ? { ...draft, subtotal: "80.00", taxTotal: "16.00", total: "96.00" } : draft;
        const rows: Record<string, unknown[]> = {
            allDocumentsQuery: [doc],
            allDocumentLinesQuery: signed ? [line, { ...line, id: "deduction", unitPrice: "-20.0000", lineTotal: "-24.00" }] : [line],
            allContactsQuery: [],
        };
        evolu.getQueryRows.mockImplementation((query: unknown) => rows[String(query)] ?? []);
        allocator.reserve.mockResolvedValue({ ok: true, value: { status: "reserved", counter: 71, periodKey: "2026", bridgeCompanyId: "synthetic-bridge" } });
        const result = await issueLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
            documentId, supplier);
        expect(result.ok).toBe(true);
        expect(allocator.reserve).toHaveBeenCalledOnce();
        expect(allocator.confirm).toHaveBeenCalledOnce();
        expect(evolu.calls.some(call => call.table === "documentSnapshot")).toBe(true);
        expect(evolu.calls.some(call => call.table === "document" && call.row.status === "issued")).toBe(true);
    });

    it.each(["company", "missing_buyer", "wrong_buyer_id", "wrong_buyer_company"])("rejects invalid %s before invoking the allocator", async mismatch => {
        const { issueLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const supplier = { ...company, jurisdiction: "eu_sk", country: "SK", vatPayer: 1 as const, vatStatus: "payer" };
        const doc = { ...draft, title: "Document", contactId: mismatch === "company" ? null : "buyer", subtotal: "80.00", taxTotal: "16.00", total: "96.00" };
        const contact = { id: mismatch === "wrong_buyer_id" ? "other-buyer" : "buyer", companyId: mismatch === "wrong_buyer_company" ? "other-company" : companyId, name: "Synthetic buyer", country: "SK" };
        const rows: Record<string, unknown[]> = {
            allDocumentsQuery: [doc],
            allDocumentLinesQuery: [line, { ...line, id: "deduction", unitPrice: "-20.0000", lineTotal: "-24.00" }],
            allContactsQuery: mismatch === "missing_buyer" ? [] : [contact],
        };
        evolu.getQueryRows.mockImplementation((query: unknown) => rows[String(query)] ?? []);
        allocator.reserve.mockResolvedValue({ ok: false, error: "reserve_failed" });
        const result = await issueLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
            documentId, mismatch === "company" ? { ...supplier, id: "other-company" as CompanyId } : supplier);
        expect(result).toEqual({ ok: false, error: "not_found" });
        expect(allocator.reserve).not.toHaveBeenCalled();
        expect(allocator.confirm).not.toHaveBeenCalled();
        expect(evolu.calls).toEqual([]);
    });

    it.each(["company", "buyer_reference", "missing_buyer", "buyer_profile", "title", "line"])("rejects %s changes during reservation before any local mutation", async changed => {
        const { issueLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const supplier = { ...company, jurisdiction: "eu_sk", country: "SK", vatPayer: 1 as const, vatStatus: "payer" };
        const doc = { ...draft, title: "Document", contactId: "buyer", subtotal: "80.00", taxTotal: "16.00", total: "96.00" };
        const contact = { id: "buyer", companyId, name: "Synthetic buyer", country: "SK" };
        const rows: Record<string, unknown[]> = {
            allDocumentsQuery: [doc],
            allDocumentLinesQuery: [line, { ...line, id: "deduction", unitPrice: "-20.0000", lineTotal: "-24.00" }],
            allContactsQuery: [contact],
        };
        evolu.getQueryRows.mockImplementation((query: unknown) => rows[String(query)] ?? []);
        allocator.reserve.mockImplementation(async () => {
            if (changed === "company") rows.allDocumentsQuery = [{ ...doc, companyId: "other-company" }];
            if (changed === "buyer_reference") rows.allDocumentsQuery = [{ ...doc, contactId: "other-buyer" }];
            if (changed === "missing_buyer") rows.allContactsQuery = [];
            if (changed === "buyer_profile") rows.allContactsQuery = [{ ...contact, name: "Changed buyer" }];
            if (changed === "title") rows.allDocumentsQuery = [{ ...doc, title: "Edited while reserving" }];
            if (changed === "line") rows.allDocumentLinesQuery = [{ ...line, name: "Edited while reserving" }, rows.allDocumentLinesQuery[1]];
            return { ok: true, value: { status: "reserved", counter: 71, periodKey: "2026", bridgeCompanyId: "synthetic-bridge" } };
        });
        const result = await issueLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
            documentId, supplier);
        expect(result.ok).toBe(false);
        expect(allocator.reserve).toHaveBeenCalledOnce();
        expect(allocator.confirm).not.toHaveBeenCalled();
        expect(evolu.calls).toEqual([]);
    });
});

describe("applyReservedNumberToLocalDocument snapshot invariant", () => {
    it.each([false, true])("loads company rounding context before reserved issue (supplied context: %s)", async supplied => {
        const { applyReservedNumberToLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const supplier = { ...company, jurisdiction: "eu_sk", country: "SK", vatPayer: 1 as const, vatStatus: "payer", appSettingsJson: '{"rounding_method":"per_document"}' };
        const doc = { ...draft, subtotal: "89.92", taxTotal: "20.68", total: "110.60" };
        const lines = [
            { ...line, taxRate: "23.0000", lineTotal: "123.00" },
            { ...line, id: "deduction" as DocumentLineId, sortOrder: "1", quantity: "-0.5000", unitPrice: "20.1500", taxRate: "23.0000", lineTotal: "-12.39" },
        ];
        const rows: Record<string, unknown[]> = {
            allDocumentsQuery: [doc], allDocumentLinesQuery: lines,
            allCompaniesDetailQuery: [supplier], allContactsQuery: [],
        };
        evolu.getQueryRows.mockImplementation((query: unknown) => (rows[String(query)] ?? []) as never);
        const local = { ...evolu, loadQuery: vi.fn(async (query: unknown) => rows[String(query)] ?? []) };
        const result = await applyReservedNumberToLocalDocumentAsync(local as never, documentId, companyId, "invoice", "20260071", [], null,
            supplied ? { company: supplier, doc, allDocuments: [doc], lines, contact: null } : undefined);
        expect(result.ok).toBe(true);
        const snapshot = JSON.parse(String(evolu.calls.find(call => call.table === "documentSnapshot")?.row.payloadJson));
        expect(snapshot.document.total).toBe("110.60");
        expect(snapshot.lines[1].line_total).toBe("-12.39");
    });

    it("resolves the buyer VAT context before checking signed reserved lines", async () => {
        const { applyReservedNumberToLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const supplier = { ...company, jurisdiction: "eu_sk", country: "SK", vatPayer: 1 as const, vatStatus: "payer" };
        const doc = { ...draft, contactId: "buyer", subtotal: "80.00", taxTotal: "0.00", total: "80.00" };
        const lines = [{ ...line, lineTotal: "100.00" }, { ...line, id: "deduction" as DocumentLineId, unitPrice: "-20.0000", lineTotal: "-20.00" }];
        const rows: Record<string, unknown[]> = {
            allDocumentsQuery: [doc], allDocumentLinesQuery: lines,
            allCompaniesDetailQuery: [supplier], allContactsQuery: [{ id: "buyer", companyId, name: "Synthetic buyer", country: "CZ", vatId: "CZ12345678" }],
        };
        evolu.getQueryRows.mockImplementation((query: unknown) => (rows[String(query)] ?? []) as never);
        const result = await applyReservedNumberToLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
            documentId, companyId, "invoice", "20260071", []);
        expect(result.ok).toBe(true);
        const snapshot = JSON.parse(String(evolu.calls.find(call => call.table === "documentSnapshot")?.row.payloadJson));
        expect(snapshot.contact.country).toBe("CZ");
        expect(snapshot.document.tax_total).toBe("0.00");
    });

    it.each(["missing", "wrong_id", "wrong_company"])("rejects a referenced buyer that is %s in loaded context", async mismatch => {
        const { applyReservedNumberToLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const supplier = { ...company, jurisdiction: "eu_sk", country: "SK", vatPayer: 1 as const, vatStatus: "payer" };
        const doc = { ...draft, contactId: "buyer", subtotal: "80.00", taxTotal: "16.00", total: "96.00" };
        const lines = [line, { ...line, id: "deduction", unitPrice: "-20.0000", lineTotal: "-24.00" }];
        const contact = { id: mismatch === "wrong_id" ? "other-buyer" : "buyer", companyId: mismatch === "wrong_company" ? "other-company" : companyId, name: "Synthetic buyer", country: "SK" };
        const rows: Record<string, unknown[]> = {
            allDocumentsQuery: [doc], allDocumentLinesQuery: lines,
            allCompaniesDetailQuery: [supplier], allContactsQuery: mismatch === "missing" ? [] : [contact],
        };
        evolu.getQueryRows.mockImplementation((query: unknown) => rows[String(query)] ?? []);
        const result = await applyReservedNumberToLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
            documentId, companyId, "invoice", "20260071", []);
        expect(result).toEqual({ ok: false, error: "not_found" });
        expect(evolu.calls).toEqual([]);
    });

    it.each(["missing", "wrong_id", "wrong_company", "changed_reference", "cleared_reference", "unexpected_contact"])(
        "rejects a supplied buyer context with %s before synchronous or asynchronous writes", async mismatch => {
            const { applyReservedNumberToLocalDocument, applyReservedNumberToLocalDocumentAsync } = await import("@/evolu/documentCrud");
            const evolu = fakeEvolu();
            const doc = { ...draft, contactId: mismatch === "unexpected_contact" ? null : "buyer" };
            const contextDoc = { ...doc, contactId: mismatch === "changed_reference" ? "other-buyer" : mismatch === "cleared_reference" ? null : doc.contactId };
            const contact = mismatch === "missing" || mismatch === "cleared_reference" ? null : {
                id: mismatch === "wrong_id" || mismatch === "changed_reference" ? "other-buyer" : "buyer",
                companyId: mismatch === "wrong_company" ? "other-company" : companyId,
                name: "Synthetic buyer", country: "SK",
            } as EvoluContactRow;
            const context = { company, doc: contextDoc, allDocuments: [doc], lines: [line], contact };
            const rows: Record<string, unknown[]> = { allDocumentsQuery: [doc], allDocumentLinesQuery: [line] };
            evolu.getQueryRows.mockImplementation((query: unknown) => rows[String(query)] ?? []);
            expect(applyReservedNumberToLocalDocument(evolu as never, documentId, companyId, "invoice", "20260071", [], null, context))
                .toEqual({ ok: false, error: "not_found" });
            expect(await applyReservedNumberToLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
                documentId, companyId, "invoice", "20260071", [], null, context)).toEqual({ ok: false, error: "not_found" });
            expect(evolu.calls).toEqual([]);
        },
    );

    it.each([false, true])("fails closed without company context only for signed lines (signed: %s)", async signed => {
        const { applyReservedNumberToLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const lines = signed ? [line, { ...line, id: "deduction", unitPrice: "-20.0000", lineTotal: "-24.00" }] : [line];
        const rows: Record<string, unknown[]> = { allDocumentsQuery: [draft], allDocumentLinesQuery: lines };
        evolu.getQueryRows.mockImplementation((query: unknown) => rows[String(query)] ?? []);
        const result = await applyReservedNumberToLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
            documentId, companyId, "invoice", "20260071", []);
        if (signed) {
            expect(result).toEqual({ ok: false, error: "unsupported_negative_lines" });
            expect(evolu.calls).toEqual([]);
        } else {
            expect(result.ok).toBe(true);
            expect(evolu.calls.some(call => call.table === "document" && call.row.status === "issued")).toBe(true);
        }
    });

    it.each(["company", "document", "context_document"])("rejects mismatched %s ownership before reserved issue writes", async mismatch => {
        const { applyReservedNumberToLocalDocument, applyReservedNumberToLocalDocumentAsync } = await import("@/evolu/documentCrud");
        const evolu = fakeEvolu();
        const doc = mismatch === "document" ? { ...draft, companyId: "other-company" as CompanyId } : draft;
        const context = {
            company: mismatch === "company" ? { ...company, id: "other-company" as CompanyId } : company,
            doc: mismatch === "context_document" ? { ...draft, companyId: "other-company" as CompanyId } : draft,
            lines: [line], allDocuments: [doc], contact: null,
        };
        const rows: Record<string, unknown[]> = { allDocumentsQuery: [doc], allDocumentLinesQuery: [line] };
        evolu.getQueryRows.mockImplementation((query: unknown) => rows[String(query)] ?? []);
        expect(applyReservedNumberToLocalDocument(evolu as never, documentId, companyId, "invoice", "20260071", [], null, context))
            .toEqual({ ok: false, error: "not_found" });
        expect(await applyReservedNumberToLocalDocumentAsync({ ...evolu, loadQuery: async (query: unknown) => rows[String(query)] ?? [] } as never,
            documentId, companyId, "invoice", "20260071", [], null, context)).toEqual({ ok: false, error: "not_found" });
        expect(evolu.calls).toEqual([]);
    });

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
