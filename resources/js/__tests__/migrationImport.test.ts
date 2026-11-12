import { beforeEach, describe, expect, it, vi } from "vitest";
import { webcrypto } from "node:crypto";
import { readFileSync } from "node:fs";
import { createIdFromString } from "@evolu/common";
import { CompanyId, InvoicingLocalSchema } from "@/evolu/schema";
vi.mock("@/evolu/client", () => ({ migrationSourceProofQuery: "source", migrationChunkProofQuery: () => "chunk", migrationDocumentTargetsQuery: "targets", allCompaniesDetailQuery: "companies", allContactsQuery: "contacts", allDocumentLinesQuery: "lines", allNumberSeriesQuery: "series" }));
vi.mock("@/evolu/migrationSchema", () => ({ assertMigrationSchemaReady: vi.fn().mockResolvedValue(undefined) }));
vi.mock("@/evolu/migrationIsdocPdf", () => ({ readMigrationIsdocEvidence: vi.fn() }));
vi.mock("@/evolu/migrationIdokladPdf", () => ({ readIdokladPdfEvidence: vi.fn() }));
vi.mock("@/evolu/documentImportLocal", () => ({
    ContactImportWriteError: class ContactImportWriteError extends Error {},
    resolveLocalContact: vi.fn(() => ({ contactId: null, created: false, linked: false })),
}));
vi.mock("@/evolu/documentEventLog", () => ({ logDocumentEvent: vi.fn(() => ({ ok: true })) }));
vi.mock("@/evolu/numberSeriesCrud", () => ({ syncNumberSeriesCounterFromDocuments: () => undefined }));
vi.mock("@/evolu/expenseReconciliation", () => ({
    withExpenseReconciliation: async (evolu: { scope: unknown }, callback: (scope: unknown) => unknown) => callback(evolu.scope),
    assertExpenseCompanyOwner: (scope: { ownerId: string }, _company: unknown, owner: string) => { if (owner !== scope.ownerId) throw new Error("wrong_owner"); },
    checkpointExpenseMutations: async () => undefined,
}));
vi.mock("@/evolu/bankReconciliation", async importOriginal => ({
    ...await importOriginal<typeof import("@/evolu/bankReconciliation")>(),
    awaitWorkerOutcome: (_evolu: unknown, start: (complete: (value: unknown) => void, unknown: (e?: unknown) => void) => void) => new Promise((resolve, reject) => start(resolve, reject)),
    awaitBankMutation: async (_evolu: unknown, mutate: (done: () => void) => { ok: boolean }) => new Promise<boolean>(resolve => { const result = mutate(() => resolve(true)); if (!result.ok) resolve(false); }),
}));
import fakturoidFixture from "./fixtures/migration-fakturoid/export-unpaid.xml?raw";
import fakturoidPaymentsFixture from "./fixtures/migration-fakturoid/export-payments.xml?raw";
import { importMigrationFile } from "@/evolu/migrationImport";
import { assertMigrationSchemaReady } from "@/evolu/migrationSchema";
import { logDocumentEvent } from "@/evolu/documentEventLog";
import { ContactImportWriteError, resolveLocalContact } from "@/evolu/documentImportLocal";
import { readMigrationIsdocEvidence } from "@/evolu/migrationIsdocPdf";
import { readIdokladPdfEvidence, type IdokladPdfInvoice } from "@/evolu/migrationIdokladPdf";
import type { MigrationStructuredInvoice } from "@/evolu/migrationIsdoc";
import { buildMigrationPreview, buildPreparedMigrationPreview } from "@/evolu/migrationValidation";
import { parseFakturoidMigrationExport } from "@/evolu/migrationFakturoid";
const companyId = CompanyId.orThrow(createIdFromString("migration-import-test-company"));
const mapping = { invoice_number: 0, client_name: 1, issue_date: 2, due_date: 3, amount: 4, currency: 5, paid_at: 6, amount_paid: 7, payment_status: 8 };
const row = (number: string, paid = "0", state = "unpaid") => [number, "Example Customer", "2025-01-01", "2025-01-15", "100.00", "EUR", "", paid, state];
const structured: MigrationStructuredInvoice = {
    documentNumber: "A1", issueDate: "2025-01-01", currency: "EUR", supplierRegistrationNumber: "00012345",
    buyerRegistrationNumber: null, buyerName: "Example Customer", subtotal: "85.00", taxTotal: "15.00", total: "100.00",
    lines: [
        { name: "Consulting", description: null, quantity: "2.0000", unit: "h", unitPrice: "25.00", lineDiscountPercent: "0.00", taxRate: "23.00", lineTotal: "61.50" },
        { name: "Delivery", description: null, quantity: "1.0000", unit: null, unitPrice: "35.00", lineDiscountPercent: "0.00", taxRate: "10.00", lineTotal: "38.50" },
    ],
};
const exampleOriginal = () => {
    const bytes = new TextEncoder().encode("%PDF-1.7 Synthetic original");
    return { file: { name: "original.pdf", type: "application/pdf", size: bytes.length } as File, bytes };
};
function fixture() {
    const tables: Record<string, Record<string, unknown>[]> = {
        migrationSource: [], migrationChunk: [], document: [], documentLine: [],
        company: [{ id: companyId, ownerId: "owner-a", registrationNumber: "00012345" }],
    };
    const writes: string[] = [];
    const rejected: string[] = [];
    const upsert = (table: string, patch: Record<string, unknown>, options?: { onComplete?: () => void; ownerId?: string }) => {
        const schema = Reflect.get(InvoicingLocalSchema, table) as Record<string, { from: (value: unknown) => { ok: boolean } }>;
        for (const [field, value] of Object.entries(patch)) {
            if (!schema[field]?.from(value).ok) { rejected.push(table + "." + field); return { ok: false }; }
        }
        if (table === "document" && !tables.migrationSource.some(source => source.savedAt)) throw new Error("original_not_retained");
        const previous = tables[table].find(value => value.id === patch.id && value.ownerId === (options?.ownerId ?? "owner-a"));
        if (previous) Object.assign(previous, patch); else tables[table].push({ ...patch, ownerId: options?.ownerId ?? "owner-a" });
        writes.push(table);
        if (options?.onComplete) queueMicrotask(options.onComplete);
        return { ok: true, value: { id: patch.id } };
    };
    const evolu = { upsert: vi.fn(upsert), update: vi.fn(upsert),
        loadQuery: vi.fn(async (query: string) => query === "source" ? tables.migrationSource : query === "chunk" ? tables.migrationChunk : query === "targets" ? tables.document : []),
        getQueryRows: (query: string) => query === "lines" ? tables.documentLine : query === "companies" ? tables.company.filter(company => company.isDeleted !== 1) : [], scope: {} as unknown };
    evolu.scope = { evolu, ownerId: "owner-a", get context() { return { documents: tables.document.filter(document => document.isDeleted !== 1) }; } };
    const bytes = new TextEncoder().encode("Synthetic source export");
    const input = { companyId, ownerId: "owner-a", sourceKind: "superfaktura", bytes,
        file: { name: "source.csv", type: "text/csv", size: bytes.length } as File, rows: [row("A1")], mapping,
        defaultCurrency: "EUR", dateFormat: "ymd_dash" as const, createContacts: false, lineName: "Imported item", lineDescription: "", noteFooter: null,
        assertCurrent: () => undefined };
    return { evolu, input, tables, writes, rejected };
}
beforeEach(() => {
    vi.stubGlobal("crypto", webcrypto);
    vi.mocked(readMigrationIsdocEvidence).mockReset().mockResolvedValue(null);
    vi.mocked(readIdokladPdfEvidence).mockReset().mockResolvedValue([]);
    vi.mocked(assertMigrationSchemaReady).mockReset().mockResolvedValue(undefined);
    vi.mocked(logDocumentEvent).mockReset().mockReturnValue({ ok: true } as never);
    vi.mocked(resolveLocalContact).mockReset().mockReturnValue({ contactId: null, created: false, linked: false });
});
describe("migration import schema and recovery boundaries", () => {
    it("rejects an old storage schema before creating sources, invoices or reconciliation writes", async () => {
        const f = fixture();
        vi.mocked(assertMigrationSchemaReady).mockRejectedValue(new Error("migration_schema_outdated"));
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("migration_schema_outdated");
        expect(f.writes).toEqual([]);
        expect(readMigrationIsdocEvidence).not.toHaveBeenCalled();
    });
    it("returns a known contact rejection before writing an invoice", async () => {
        const f = fixture();
        vi.mocked(resolveLocalContact).mockImplementation(() => { throw new ContactImportWriteError(); });
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("contact_write_failed");
        expect(f.tables.document).toHaveLength(0);
        expect(f.tables.migrationSource.filter(source => source.savedAt)).toHaveLength(1);
    });
    it("checks a rejected audit event before the final status write", async () => {
        const f = fixture();
        vi.mocked(logDocumentEvent).mockReturnValue({ ok: false } as never);
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("document_write_failed");
        expect(f.tables.document[0]).toMatchObject({ status: "draft", migrationCompletedAt: null });
    });
    it("writes schema-valid invoice/line payloads only after verified source custody and skips an exact retry", async () => {
        const f = fixture();
        expect((await importMigrationFile(f.evolu as never, f.input)).imported).toBe(1);
        expect(f.rejected).toEqual([]); expect(f.tables.documentLine[0].unit).toBeNull();
        expect(f.writes.indexOf("migrationSource")).toBeLessThan(f.writes.indexOf("document"));
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("nothing_to_import");
        expect(f.tables.document).toHaveLength(1);
    });
    it("preserves partial/paid/cancelled amounts and missing dates rather than inventing settlement evidence", async () => {
        const f = fixture(); f.input.rows = [row("PARTIAL", "40", "partial"), row("PAID", "100", "paid"), row("VOID", "0", "cancelled")];
        expect((await importMigrationFile(f.evolu as never, f.input)).imported).toBe(3);
        expect(f.tables.document.map(document => [document.status, document.amountPaid, document.paidAt])).toEqual([
            ["issued", "40.00", null], ["paid", "100.00", null], ["cancelled", "0.00", null],
        ]);
    });
    it("revalidates original items before preserving exact VAT, totals and spreadsheet payment", async () => {
        const f = fixture();
        f.input.rows = [row("A1", "40", "partial")];
        vi.mocked(readMigrationIsdocEvidence).mockResolvedValue(structured);
        const expectedPreview = buildMigrationPreview(f.input.rows, mapping, {
            ...f.input, existing: [], companyRegistrationNumber: "00012345", structuredInvoices: [structured],
        });
        const onArchiveProgress = vi.fn();
        const input = { ...f.input, originals: [exampleOriginal()], onArchiveProgress };
        expect((await importMigrationFile(f.evolu as never, { ...input, expectedPreview })).imported).toBe(1);
        expect(f.rejected).toEqual([]);
        expect(f.tables.document[0]).toMatchObject({ subtotal: "85.00", taxTotal: "15.00", total: "100.00", status: "issued", amountPaid: "40.00", paidAt: null });
        expect(f.tables.documentLine.map(line => [line.name, line.quantity, line.unitPrice, line.taxRate, line.lineTotal])).toEqual([
            ["Consulting", "2.0000", "25.00", "23.00", "61.50"], ["Delivery", "1.0000", "35.00", "10.00", "38.50"],
        ]);
        expect(f.tables.migrationSource.filter(source => source.savedAt)).toHaveLength(2);
        expect(onArchiveProgress.mock.calls).toEqual([[1, 2], [2, 2]]);
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("nothing_to_import");
        expect(f.tables.document).toHaveLength(1);
        f.tables.documentLine[0].name = "Manually edited item";
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("preview_blocked");
        expect(f.tables.documentLine[0].name).toBe("Manually edited item");
    });
    it("blocks both changed original evidence and changed target identity before archiving", async () => {
        const f = fixture();
        const expectedPreview = buildMigrationPreview(f.input.rows, mapping, {
            ...f.input, existing: [], companyRegistrationNumber: "00012345", structuredInvoices: [structured],
        });
        const input = { ...f.input, originals: [exampleOriginal()], allowSummary: true, expectedPreview };
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("preview_changed");
        vi.mocked(readMigrationIsdocEvidence).mockResolvedValue(structured);
        f.tables.company[0].registrationNumber = "00098765";
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("preview_changed");
        expect(f.writes).toEqual([]);
    });

    it("retains an exact signed deduction as a separate line in an already paid invoice", async () => {
        const f = fixture();
        f.input.rows = [row("A1", "100", "paid")];
        const signed: MigrationStructuredInvoice = {
            ...structured, subtotal: "100.00", taxTotal: "0.00", requiresFullPayment: true,
            lines: [
                { ...structured.lines[0], quantity: "1.0000", unitPrice: "120.00", taxRate: "0.00", lineTotal: "120.00" },
                { ...structured.lines[1], name: "Service deduction", unitPrice: "-20.00", taxRate: "0.00", lineTotal: "-20.00" },
            ],
        };
        vi.mocked(readMigrationIsdocEvidence).mockResolvedValue(signed);
        const input = { ...f.input, originals: [exampleOriginal()] };
        expect((await importMigrationFile(f.evolu as never, input)).imported).toBe(1);
        expect(f.rejected).toEqual([]);
        expect(f.tables.document[0]).toMatchObject({ total: "100.00", amountPaid: "100.00", status: "paid" });
        expect(f.tables.documentLine[1]).toMatchObject({ name: "Service deduction", unitPrice: "-20.00", lineTotal: "-20.00" });
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("nothing_to_import");
        expect(f.tables.documentLine).toHaveLength(2);
    });
    it("requires consent for a summary when an original contains no supported item evidence", async () => {
        const f = fixture();
        const input = { ...f.input, originals: [exampleOriginal()] };
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("summary_confirmation_required");
        expect(f.writes).toEqual([]);
        expect((await importMigrationFile(f.evolu as never, { ...input, allowSummary: true })).imported).toBe(1);
        expect(f.tables.documentLine).toHaveLength(1);
    });
    it("never overwrites a completed summary with newly supplied invoice items", async () => {
        const f = fixture();
        await importMigrationFile(f.evolu as never, f.input);
        vi.mocked(readMigrationIsdocEvidence).mockResolvedValue(structured);
        await expect(importMigrationFile(f.evolu as never, { ...f.input, originals: [exampleOriginal()] })).rejects.toThrow("preview_blocked");
        expect(f.tables.document).toHaveLength(1);
        expect(f.tables.documentLine.map(line => line.name)).toEqual(["Imported item"]);
        expect(f.tables.migrationSource).toHaveLength(1);
    });
    it("repairs an interrupted item batch using its original document and item ids after rekeying", async () => {
        const f = fixture();
        const input = { ...f.input, originals: [exampleOriginal()] };
        vi.mocked(readMigrationIsdocEvidence).mockResolvedValue(structured);
        const write = f.evolu.upsert.getMockImplementation()!;
        let rejected = false;
        f.evolu.upsert.mockImplementation((table, patch, options) => {
            if (table === "documentLine" && patch.sortOrder === "1" && !rejected) { rejected = true; return { ok: false }; }
            return write(table, patch, options);
        });
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("document_write_failed");
        const draftId = f.tables.document[0].id;
        const firstLineId = f.tables.documentLine[0].id;
        expect(f.tables.document[0]).toMatchObject({ status: "draft", migrationCompletedAt: null, amountPaid: "0.00" });
        for (const rows of Object.values(f.tables)) {
            const copies = rows.map(value => ({ ...value, ownerId: "owner-b" }));
            rows.forEach(value => { value.isDeleted = 1; });
            rows.push(...copies);
        }
        Reflect.set(f.evolu.scope as object, "ownerId", "owner-b");
        expect((await importMigrationFile(f.evolu as never, { ...input, ownerId: "owner-b" })).imported).toBe(1);
        expect(f.tables.document.filter(doc => doc.isDeleted !== 1)).toEqual([
            expect.objectContaining({ id: draftId, ownerId: "owner-b", status: "issued" }),
        ]);
        const lines = f.tables.documentLine.filter(line => line.isDeleted !== 1);
        expect(lines).toHaveLength(2);
        expect(lines[0].id).toBe(firstLineId);
    });
    it("resumes an unfinished draft when the line write failed, without skipping or duplicating it", async () => {
        const f = fixture(); const write = f.evolu.upsert.getMockImplementation()!;
        let failed = false;
        f.evolu.upsert.mockImplementation((table, patch, options) => {
            if (table === "documentLine" && !failed) { failed = true; return { ok: false }; }
            return write(table, patch, options);
        });
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("document_write_failed");
        expect(f.tables.document).toHaveLength(1); expect(f.tables.document[0].status).toBe("draft");
        expect(f.tables.document[0].migrationCompletedAt).toBeNull(); expect(f.tables.documentLine).toHaveLength(0);
        expect((await importMigrationFile(f.evolu as never, f.input)).imported).toBe(1);
        expect(f.tables.document).toHaveLength(1); expect(f.tables.documentLine).toHaveLength(1);
        expect(f.tables.document[0].status).toBe("issued"); expect(f.tables.document[0].migrationCompletedAt).toBeTruthy();
    });
    it("repairs the preserved draft and reuses export/original ids after a company share or rekey", async () => {
        const f = fixture(); const write = f.evolu.upsert.getMockImplementation()!;
        const originalBytes = new TextEncoder().encode("%PDF-1.7 Synthetic original");
        const originals = [{ file: { name: "original.pdf", type: "application/pdf", size: originalBytes.length } as File, bytes: originalBytes }];
        let failLine = true;
        f.evolu.upsert.mockImplementation((table, patch, options) => {
            if (table === "documentLine" && failLine) { failLine = false; return { ok: false }; }
            return write(table, patch, options);
        });
        await expect(importMigrationFile(f.evolu as never, { ...f.input, originals, allowSummary: true })).rejects.toThrow("document_write_failed");
        const draftId = f.tables.document[0].id;
        const originalArchiveIds = f.tables.migrationSource.map(source => source.id);
        const originalChunkIds = f.tables.migrationChunk.map(chunk => chunk.id);
        // Actual company sharing/rekeying copies the same ids under the new
        // partition and tombstones the old copies; it does not renumber documents.
        for (const rows of Object.values(f.tables)) {
            const copies = rows.map(value => ({ ...value, ownerId: "owner-b" }));
            rows.forEach(value => { value.isDeleted = 1; });
            rows.push(...copies);
        }
        Reflect.set(f.evolu.scope as object, "ownerId", "owner-b");
        const before = Object.fromEntries(Object.entries(f.tables).map(([table, rows]) => [table, rows.length]));
        expect((await importMigrationFile(f.evolu as never, { ...f.input, ownerId: "owner-b", originals, allowSummary: true })).imported).toBe(1);
        const liveDocuments = f.tables.document.filter(document => document.isDeleted !== 1);
        expect(liveDocuments).toHaveLength(1); expect(liveDocuments[0]).toMatchObject({ id: draftId, ownerId: "owner-b", status: "issued" });
        expect(f.tables.document).toHaveLength(before.document);
        expect(f.tables.migrationSource).toHaveLength(before.migrationSource);
        expect(f.tables.migrationChunk).toHaveLength(before.migrationChunk);
        expect(f.tables.migrationSource.filter(source => source.ownerId === "owner-b").map(source => source.id)).toEqual(originalArchiveIds);
        expect(f.tables.migrationChunk.filter(chunk => chunk.ownerId === "owner-b").map(chunk => chunk.id)).toEqual(originalChunkIds);
        expect(f.tables.documentLine).toHaveLength(1);
        expect(f.tables.documentLine[0].documentId).toBe(draftId);
        await expect(importMigrationFile(f.evolu as never, { ...f.input, ownerId: "owner-b", originals, allowSummary: true })).rejects.toThrow("nothing_to_import");
    });
    it("blocks financial writes when source readback fails", async () => {
        const f = fixture();
        f.evolu.loadQuery.mockImplementation(async (query: string) => query === "source" ? f.tables.migrationSource : []);
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("archive_unverified");
        expect(f.tables.document).toEqual([]);
    });
    it("rejects invalid previews before archiving or mutating documents", async () => {
        const f = fixture(); f.input.rows = [row("BAD", "40", "paid")];
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("preview_blocked");
        expect(f.writes).toEqual([]);
    });
    it("retains already-confirmed originals when the company changes before invoice writes", async () => {
        const f = fixture();
        f.input.assertCurrent = () => { if (f.tables.migrationSource.some(source => source.savedAt)) throw new Error("preview_changed"); };
        await expect(importMigrationFile(f.evolu as never, f.input)).rejects.toThrow("preview_changed");
        expect(f.tables.migrationSource).toHaveLength(1); expect(f.tables.document).toEqual([]);
    });
});

function zapriteExport() {
    const customer = { id: "customer-zaprite-test", legalName: "Synthetic Customer", billingAddress: null, email: null, taxId: null };
    const order = (id: string, currency: string, paid: boolean) => ({
        id: `order-${id}`, orgId: "organization-test", orderType: "invoice", currency,
        totalAmount: 29900, status: paid ? "UNDERPAID" : "PENDING", paidAt: null, contact: customer, promo: null, coupons: [],
        invoice: { id: `invoice-${id}`, orgId: "organization-test", number: id, date: "2026-11-11", daysToPay: 30,
            status: paid ? "UNDERPAID" : "SENT", customer,
            lineItems: [
                { id: `line-${id}-1`, name: "Service", description: null, unitPrice: 10000, quantity: 2, taxRateBips: 0, taxAmount: 0, discountRateBips: 0, discountAmount: 0 },
                { id: `line-${id}-2`, name: "Support", description: null, unitPrice: 9900, quantity: 1, taxRateBips: 0, taxAmount: 0, discountRateBips: 0, discountAmount: 0 },
            ] },
        transactions: paid ? [{ id: `payment-${id}`, status: "CONFIRMED", date: "2026-11-11T14:25:00.000Z", amount: 5000, currency,
            amountInOrderCurrency: null, orderCurrency: null, discountsInOrderCurrency: [], method: "BANK" }] : [],
    });
    return new TextEncoder().encode(JSON.stringify({ version: 1, provider: "zaprite", pages: [{
        items: [order("Z-EUR", "EUR", true), order("Z-USD", "USD", false), order("Z-BTC", "BTC", false)],
        meta: { page: 1, pagesCount: 1, perPage: 100, itemsCount: 3 },
    }] }));
}
function zapriteInput(f: ReturnType<typeof fixture>) {
    const bytes = zapriteExport();
    return { ...f.input, sourceKind: "zaprite", bytes, file: { name: "zaprite-export.json", type: "application/json", size: bytes.length } as File,
        rows: [] as string[][], mapping: {}, excludeUnsupportedCurrencies: true };
}
describe("Zaprite migration custody and commit", () => {
    it("preserves detailed fiat and actual partial payment after explicitly excluding BTC", async () => {
        const f = fixture(); const input = zapriteInput(f);
        const result = await importMigrationFile(f.evolu as never, input);
        expect(result.imported).toBe(2); expect(f.rejected).toEqual([]);
        expect(f.tables.document.find(row => row.number === "Z-EUR")).toMatchObject({ total: "299.00", amountPaid: "50.00", status: "issued", paidAt: "2026-11-11" });
        expect(f.tables.document.find(row => row.number === "Z-USD")).toMatchObject({ total: "299.00", amountPaid: "0.00", status: "issued", paidAt: null });
        expect(f.tables.document.find(row => row.number === "Z-BTC")).toBeUndefined();
        expect(f.tables.documentLine).toHaveLength(4);
        expect(f.tables.documentLine[0]).toMatchObject({ quantity: "2.0000", unitPrice: "100.00", lineTotal: "200.00" });
        expect(f.tables.migrationSource).toHaveLength(1);
        expect(f.tables.migrationSource[0]).toMatchObject({ sourceKind: "zaprite", sizeBytes: String(input.bytes.length) });
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(Array.from(original.bytes)).toEqual(Array.from(input.bytes));
        expect(new TextDecoder().decode(original.bytes)).toContain('"currency":"BTC"');
    });
    it("requires explicit currency exclusion before archiving or writing any invoice", async () => {
        const f = fixture(); const input = zapriteInput(f); input.excludeUnsupportedCurrencies = false;
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("preview_blocked");
        expect(f.writes).toEqual([]);
    });
    it("reparses immutable source bytes rather than trusting caller rows or a mutable source buffer", async () => {
        const f = fixture(); const input = zapriteInput(f);
        const expected = new Uint8Array(input.bytes);
        input.rows = [row("INJECTED", "999")];
        input.assertCurrent = () => { input.bytes.fill(0); };
        const result = await importMigrationFile(f.evolu as never, input);
        expect(result.imported).toBe(2);
        expect(f.tables.document.some(doc => doc.number === "INJECTED")).toBe(false);
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(Array.from(original.bytes)).toEqual(Array.from(expected));
    });
    it("does not write duplicates on a repeated API export", async () => {
        const f = fixture(); const input = zapriteInput(f);
        await importMigrationFile(f.evolu as never, input);
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("nothing_to_import");
        expect(f.tables.document).toHaveLength(2); expect(f.tables.documentLine).toHaveLength(4); expect(f.tables.migrationSource).toHaveLength(1);
    });
});


function fakturoidInput(f: ReturnType<typeof fixture>, xml = fakturoidFixture) {
    const bytes = new TextEncoder().encode(xml);
    return { ...f.input, sourceKind: "fakturoid", bytes,
        file: { name: "fakturoid-export.xml", type: "application/xml", size: bytes.length } as File,
        rows: [] as string[][], mapping: {} };
}
describe("Fakturoid source binding and immutable custody", () => {
    it("imports verified detailed unpaid rows and retains exactly the source XML", async () => {
        const f = fixture(); const input = fakturoidInput(f);
        const result = await importMigrationFile(f.evolu as never, input);
        expect(result.imported).toBe(2); expect(f.rejected).toEqual([]);
        expect(f.tables.document).toHaveLength(2); expect(f.tables.documentLine).toHaveLength(3);
        expect(f.tables.document[0]).toMatchObject({ number: "FKT-TEST-1", subtotal: "100.00", taxTotal: "23.00", total: "123.00", amountPaid: "0.00", status: "issued", paidAt: null });
        expect(f.tables.document[1]).toMatchObject({ subtotal: "120.00", taxTotal: "25.00", total: "145.00" });
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(original.source.sourceKind).toBe("fakturoid");
        expect(Array.from(original.bytes)).toEqual(Array.from(input.bytes));
    });
    it("refuses another issuer or a missing destination registration before any custody or invoice write", async () => {
        for (const registration of ["00012346", null]) {
            const f = fixture(); f.tables.company[0].registrationNumber = registration;
            await expect(importMigrationFile(f.evolu as never, fakturoidInput(f))).rejects.toThrow("preview_blocked");
            expect(f.writes).toEqual([]);
        }
    });
    it("stops when the issuer binding changes after archival and resumes without duplicating the archive", async () => {
        const f = fixture();
        const input = { ...fakturoidInput(f), onArchiveProgress: () => { f.tables.company[0].registrationNumber = "00012346"; } };
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("preview_changed");
        expect(f.tables.document).toHaveLength(0); expect(f.tables.migrationSource).toHaveLength(1);
        f.tables.company[0].registrationNumber = "00012345";
        const result = await importMigrationFile(f.evolu as never, fakturoidInput(f));
        expect(result.imported).toBe(2); expect(f.tables.migrationSource).toHaveLength(1);
    });
    it("ignores injected caller rows and snapshots the source before an asynchronous caller mutation", async () => {
        const f = fixture(); const input = fakturoidInput(f); const expected = new Uint8Array(input.bytes);
        input.rows = [row("INJECTED", "999")]; input.assertCurrent = () => { input.bytes.fill(0); };
        const result = await importMigrationFile(f.evolu as never, input);
        expect(result.imported).toBe(2); expect(f.tables.document.some(doc => doc.number === "INJECTED")).toBe(false);
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(Array.from(original.bytes)).toEqual(Array.from(expected));
    });
    it("leaves private tokens and notes in exact custody rather than invoice or preview projections", async () => {
        const f = fixture();
        const sentinel = "SYNTHETIC-PRIVATE-WEBINVOICE-TOKEN";
        const xml = fakturoidFixture.replace("<token />", `<token>${sentinel}</token>`)
            .replace("<private_note />", `<private_note>${sentinel}</private_note>`);
        expect(xml).toContain(sentinel);
        const input = fakturoidInput(f, xml);
        const result = await importMigrationFile(f.evolu as never, input);
        expect(JSON.stringify(result.preview)).not.toContain(sentinel);
        expect(JSON.stringify(f.tables.document)).not.toContain(sentinel);
        expect(JSON.stringify(f.tables.documentLine)).not.toContain(sentinel);
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(new TextDecoder().decode(original.bytes)).toBe(xml);
    });
    it("does not write duplicate records on a repeated native export", async () => {
        const f = fixture(); const input = fakturoidInput(f);
        await importMigrationFile(f.evolu as never, input);
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("nothing_to_import");
        expect(f.tables.document).toHaveLength(2); expect(f.tables.documentLine).toHaveLength(3); expect(f.tables.migrationSource).toHaveLength(1);
    });
    it("preserves genuine partial/full payment semantics, unknown dates and exact original custody", async () => {
        const f = fixture(); const input = fakturoidInput(f, fakturoidPaymentsFixture);
        const parsed = parseFakturoidMigrationExport(input.bytes, { companyRegistrationNumber: "00012345" });
        const preview = buildPreparedMigrationPreview(parsed.rows, { companyId, ownerId: "owner-a", existing: [] });
        expect(preview.totals).toEqual([{ currency: "EUR", count: 4, total: "514.00", paid: "173.00", balance: "341.00" }]);
        const result = await importMigrationFile(f.evolu as never, { ...input, expectedPreview: preview });
        expect(result.imported).toBe(4); expect(f.rejected).toEqual([]);
        expect(f.tables.document).toHaveLength(4); expect(f.tables.documentLine).toHaveLength(5);
        expect(f.tables.document.find(doc => doc.number === "FKT-TEST-3")).toMatchObject({
            total: "123.00", amountPaid: "50.00", status: "issued", paidAt: null,
        });
        expect(f.tables.document.find(doc => doc.number === "FKT-TEST-4")).toMatchObject({
            total: "123.00", amountPaid: "123.00", status: "paid", paidAt: "2026-10-16",
        });
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(Array.from(original.bytes)).toEqual(Array.from(input.bytes));
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("nothing_to_import");
        expect(f.tables.document).toHaveLength(4); expect(f.tables.documentLine).toHaveLength(5); expect(f.tables.migrationSource).toHaveLength(1);
    });
    it("conflicts on changed payment evidence for a completed import without overwriting it", async () => {
        const f = fixture();
        await importMigrationFile(f.evolu as never, fakturoidInput(f));
        const writes = f.writes.length;
        const xml = fakturoidFixture.replace("<paid_amount>0.0</paid_amount>", "<paid_amount>50.0</paid_amount>");
        await expect(importMigrationFile(f.evolu as never, fakturoidInput(f, xml))).rejects.toThrow("preview_blocked");
        expect(f.writes).toHaveLength(writes);
        expect(f.tables.document[0]).toMatchObject({ amountPaid: "0.00", status: "issued", paidAt: null });
        expect(f.tables.migrationSource).toHaveLength(1);
    });
    it("blocks the whole batch when any native payment evidence is unsupported", async () => {
        const f = fixture();
        const xml = fakturoidFixture.replace("<paid_amount>0.0</paid_amount>", "<paid_amount>150.0</paid_amount>");
        await expect(importMigrationFile(f.evolu as never, fakturoidInput(f, xml))).rejects.toThrow("preview_blocked");
        expect(f.writes).toEqual([]);
    });
});

function idokladFixture(f: ReturnType<typeof fixture>) {
    const bytes = new Uint8Array(readFileSync("resources/js/__tests__/fixtures/migration-idoklad/export-payments.xlsx"));
    const details: IdokladPdfInvoice[] = [1, 2, 3, 4].map(index => ({
        dueDate: "2026-10-15", deliveryDate: "2026-10-01", invoice: {
            documentNumber: `ID-TEST-${index}`, issueDate: "2026-10-01", currency: "EUR",
            supplierRegistrationNumber: "00012345", buyerRegistrationNumber: "00054321", buyerName: "Synthetic buyer",
            subtotal: `${index * 100}.00`, taxTotal: `${index * 23}.00`, total: `${index * 123}.00`,
            lines: Array.from({ length: index === 2 ? 2 : 1 }, () => ({ name: "Synthetic service", description: null,
                quantity: `${index === 2 ? 1 : index}.0000`, unit: null, unitPrice: "100.0000",
                lineDiscountPercent: "0.00", taxRate: "23.00", lineTotal: `${index === 2 ? 123 : index * 123}.00` })),
        },
    }));
    const originals = details.map((_, index) => {
        const data = new TextEncoder().encode(`%PDF-Synthetic-original-${index + 1}`);
        return { file: { name: `original-${index + 1}.pdf`, type: "application/pdf", size: data.length } as File, bytes: data };
    });
    vi.mocked(readIdokladPdfEvidence).mockImplementation(async name => {
        const index = originals.findIndex(original => original.file.name === name);
        return index < 0 ? [] : [details[index]];
    });
    const input = { ...f.input, sourceKind: "idoklad", bytes, originals, rows: [] as string[][], mapping: {},
        file: { name: "idoklad-export.xlsx", type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", size: bytes.length } as File };
    return { input, details };
}

describe("iDoklad complete native source commit", () => {
    it("preserves four records, five VAT lines and exact full/partial payment evidence", async () => {
        const f = fixture(); const { input } = idokladFixture(f);
        const result = await importMigrationFile(f.evolu as never, input);
        expect(result.imported).toBe(4); expect(f.rejected).toEqual([]);
        expect(f.tables.document).toHaveLength(4); expect(f.tables.documentLine).toHaveLength(5);
        expect(f.tables.document.find(doc => doc.number === "ID-TEST-1")).toMatchObject({
            subtotal: "100.00", taxTotal: "23.00", total: "123.00", amountPaid: "123.00", status: "paid", paidAt: "2026-10-16",
        });
        expect(f.tables.document.find(doc => doc.number === "ID-TEST-2")).toMatchObject({
            subtotal: "200.00", taxTotal: "46.00", total: "246.00", amountPaid: "50.00", status: "issued", paidAt: null,
        });
        expect(f.tables.migrationSource).toHaveLength(5);
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(original.source.sourceKind).toBe("idoklad"); expect(Array.from(original.bytes)).toEqual(Array.from(input.bytes));
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("nothing_to_import");
        expect(f.tables.document).toHaveLength(4); expect(f.tables.documentLine).toHaveLength(5); expect(f.tables.migrationSource).toHaveLength(5);
    });
    it("blocks missing PDF even when summary fallback was requested", async () => {
        const f = fixture(); const { input } = idokladFixture(f); input.originals = input.originals.slice(0, 3);
        await expect(importMigrationFile(f.evolu as never, { ...input, allowSummary: true })).rejects.toThrow("preview_blocked");
        expect(f.writes).toEqual([]);
    });
    it("blocks unreadable originals rather than silently excluding one", async () => {
        const f = fixture(); const { input } = idokladFixture(f);
        vi.mocked(readIdokladPdfEvidence).mockRejectedValue(new Error("idoklad_pdf_unsupported"));
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("preview_blocked");
        expect(f.writes).toEqual([]);
    });
    it.each(["wrong_issuer", "net_tax_mismatch", "extra_invoice"])("blocks %s before custody", async reason => {
        const f = fixture(); const { input, details } = idokladFixture(f);
        if (reason === "wrong_issuer") f.tables.company[0].registrationNumber = "00012346";
        if (reason === "net_tax_mismatch") { details[0].invoice.subtotal = "90.00"; details[0].invoice.taxTotal = "33.00"; }
        if (reason === "extra_invoice") vi.mocked(readIdokladPdfEvidence).mockResolvedValue([
            ...details, { ...details[0], invoice: { ...details[0].invoice, documentNumber: "EXTRA" } },
        ]);
        await expect(importMigrationFile(f.evolu as never, input)).rejects.toThrow("preview_blocked");
        expect(f.writes).toEqual([]);
    });
    it("copies XLSX and every PDF before callbacks and ignores injected caller rows", async () => {
        const f = fixture(); const { input, details } = idokladFixture(f);
        const source = new Uint8Array(input.bytes), originals = input.originals.map(item => new Uint8Array(item.bytes));
        input.rows = [row("INJECTED", "999")];
        input.assertCurrent = () => { input.bytes.fill(0); input.originals.forEach(item => item.bytes.fill(0)); };
        vi.mocked(readIdokladPdfEvidence).mockImplementation(async (name, bytes) => {
            const index = input.originals.findIndex(item => item.file.name === name);
            expect(Array.from(bytes)).toEqual(Array.from(originals[index]));
            return [details[index]];
        });
        const result = await importMigrationFile(f.evolu as never, input);
        expect(result.imported).toBe(4); expect(f.tables.document.some(doc => doc.number === "INJECTED")).toBe(false);
        const { readMigrationOriginal } = await import("@/evolu/migrationArchive");
        const original = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", result.source.id);
        expect(Array.from(original.bytes)).toEqual(Array.from(source));
        for (const [index, record] of f.tables.migrationSource.filter(item => item.sourceKind === "original").entries()) {
            const retained = await readMigrationOriginal(f.evolu as never, companyId, "owner-a", String(record.id));
            expect(Array.from(retained.bytes)).toEqual(Array.from(originals[index]));
        }
    });
    it("revalidates issuer changes after archive and safely resumes the source", async () => {
        const f = fixture(); const { input } = idokladFixture(f);
        await expect(importMigrationFile(f.evolu as never, { ...input,
            onArchiveProgress: () => { f.tables.company[0].registrationNumber = "00012346"; },
        })).rejects.toThrow("preview_changed");
        expect(f.tables.document).toHaveLength(0); expect(f.tables.migrationSource).toHaveLength(1);
        f.tables.company[0].registrationNumber = "00012345";
        expect((await importMigrationFile(f.evolu as never, input)).imported).toBe(4);
        expect(f.tables.migrationSource).toHaveLength(5);
    });
});
