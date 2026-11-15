import { describe, expect, it, vi } from "vitest";
vi.mock("@/evolu/client", () => Object.fromEntries([
    "allBankImportBatchesQuery", "allBankTransactionMatchesQuery", "allBankTransactionsQuery", "allCompaniesDetailQuery",
    "allCompanyStockBalancesQuery", "allCompanyStockItemsQuery", "allCompanyStockMovementsQuery", "allCompanyWarehousesQuery",
    "allContactsQuery", "allDocumentEventsQuery", "allDocumentLinesQuery", "allDocumentSnapshotsQuery", "allDocumentsQuery",
    "allExpensesQuery", "allExpenseAttachmentsQuery", "allNumberSeriesQuery", "allInvoiceTemplatesQuery",
    "allRecurringProfilesQuery", "allRecurringProfileLinesQuery", "allMigrationSourcesBackupQuery", "allMigrationChunksQuery",
    "allExpenseOriginalSourcesQuery", "allExpenseOriginalChunksQuery",
].map(name => [name, name])));
vi.mock("@/evolu/expenseReconciliation", () => ({
    withExpenseMaintenance: async (evolu: unknown, run: (scope: unknown) => unknown) => run({ evolu }),
}));
import { forceTouchAllInvoicingRowsForRelay } from "@/evolu/relayForcePushMutations";
import { deleteLocalCompany } from "@/evolu/companyDeleteLocal";
import { resetLocalCompanyData } from "@/evolu/companyResetLocal";
import { createExpenseOriginalReference, encodeExpenseOriginalReference, expenseOriginalChunkId } from "@/evolu/expenseOriginalReference";
import { verifyExpenseOriginalArchiveRows } from "@/evolu/expenseOriginalArchive";
import { migrationSha256 } from "@/evolu/migrationArchiveValues";
import {
    filterInvoicingSnapshotByCompany,
    fingerprintInvoicingSnapshot,
} from "@/evolu/invoicingRelayFingerprintCore";
import type { InvoicingDataSnapshot } from "@/evolu/invoicingSnapshot";

const EMPTY_INVOICING_SNAPSHOT: InvoicingDataSnapshot = {
    company: [],
    contact: [],
    numberSeries: [],
    document: [],
    documentLine: [],
    documentEvent: [],
    documentSnapshot: [],
    invoiceTemplate: [],
    expense: [],
    expenseAttachment: [],
    recurringProfile: [],
    recurringProfileLine: [],
    companyWarehouse: [],
    companyStockItem: [],
    companyStockBalance: [],
    companyStockMovement: [],
    bankImportBatch: [],
    bankTransaction: [],
    bankTransactionMatch: [],
};

describe("invoicingRelayFingerprint", () => {
    it("includes contacts and expenses in the global fingerprint", () => {
        const base = fingerprintInvoicingSnapshot(EMPTY_INVOICING_SNAPSHOT);
        const withContact = fingerprintInvoicingSnapshot({
            ...EMPTY_INVOICING_SNAPSHOT,
            contact: [{ id: "c-1", companyId: "co-1", name: "Buyer" }],
        });
        const withExpense = fingerprintInvoicingSnapshot({
            ...EMPTY_INVOICING_SNAPSHOT,
            expense: [{ id: "e-1", companyId: "co-1", internalNumber: "1" }],
        });

        expect(withContact).not.toBe(base);
        expect(withExpense).not.toBe(base);
        expect(withContact).not.toBe(withExpense);
    });

    it("scopes fingerprint to one company including settings row", () => {
        const snapshot = {
            ...EMPTY_INVOICING_SNAPSHOT,
            company: [
                { id: "co-a", legalName: "A", appSettingsJson: '{"runs_eshop":true}' },
                { id: "co-b", legalName: "B", appSettingsJson: "{}" },
            ],
            contact: [
                { id: "c-a", companyId: "co-a" },
                { id: "c-b", companyId: "co-b" },
            ],
            document: [
                { id: "d-a", companyId: "co-a" },
                { id: "d-b", companyId: "co-b" },
            ],
            documentSnapshot: [
                { id: "s-a", documentId: "d-a" },
                { id: "s-b", documentId: "d-b" },
            ],
            expense: [{ id: "e-a", companyId: "co-a" }],
        };

        const scoped = filterInvoicingSnapshotByCompany(snapshot, "co-a");
        expect(scoped.company).toHaveLength(1);
        expect(scoped.contact).toHaveLength(1);
        expect(scoped.document).toHaveLength(1);
        expect(scoped.documentSnapshot).toEqual([{ id: "s-a", documentId: "d-a" }]);
        expect(scoped.expense).toHaveLength(1);

        const fpA = fingerprintInvoicingSnapshot(scoped);
        const fpB = fingerprintInvoicingSnapshot(
            filterInvoicingSnapshotByCompany(snapshot, "co-b"),
        );
        expect(fpA).not.toBe(fpB);
    });
});


async function originalFixture() {
    const bytes = new TextEncoder().encode("<Invoice>synthetic relay original</Invoice>");
    const ref = createExpenseOriginalReference({ companyId: "co-a", receiptId: "receipt", expenseId: "expense", attachmentId: "attachment",
        sizeBytes: bytes.length, sha256: await migrationSha256(bytes) });
    const source = { id: ref.sourceId, companyId: ref.companyId, sourceKind: "efaktura-original", relatedExportId: null,
        originalFilename: "received.xml", mimeType: "application/xml", sizeBytes: String(ref.sizeBytes), checksum: ref.sha256,
        chunkCount: "1", savedAt: "2026-11-15T10:00:00Z" };
    const chunk = { id: expenseOriginalChunkId(ref.sourceId, 0), companyId: ref.companyId, sourceId: ref.sourceId,
        chunkIndex: "0", contentBase64: Buffer.from(bytes).toString("base64") };
    const attachment = { id: ref.attachmentId, expenseId: ref.expenseId, originalFilename: source.originalFilename,
        mimeType: source.mimeType, sizeBytes: source.sizeBytes, contentBase64: encodeExpenseOriginalReference(ref) };
    return { bytes, ref, source, chunk, attachment };
}

it("detects partial sync and changed contents or references even when row IDs stay identical", async () => {
    const x = await originalFixture();
    const snapshot = { ...EMPTY_INVOICING_SNAPSHOT, company: [{ id: "co-a" }], expense: [{ id: "expense", companyId: "co-a" }],
        expenseAttachment: [x.attachment], migrationSource: [x.source], migrationChunk: [x.chunk] };
    const full = fingerprintInvoicingSnapshot(snapshot);
    expect(fingerprintInvoicingSnapshot({ ...snapshot, migrationChunk: [] })).not.toBe(full);
    expect(fingerprintInvoicingSnapshot({ ...snapshot, migrationChunk: [{ ...x.chunk, contentBase64: btoa("changed") }] })).not.toBe(full);
    expect(fingerprintInvoicingSnapshot({ ...snapshot, expenseAttachment: [{ ...x.attachment, contentBase64: "satflux:expense-original:v9:{}" }] })).not.toBe(full);
    expect(filterInvoicingSnapshotByCompany(snapshot, "co-a").migrationChunk).toEqual([x.chunk]);
    expect(filterInvoicingSnapshotByCompany(snapshot, "co-b").migrationChunk).toEqual([]);
});

it("re-emits all archived columns unchanged and keeps the original readable throughout relay resend", async () => {
    const x = await originalFixture();
    const data: Record<string, unknown[]> = {
        allCompaniesDetailQuery: [{ id: "co-a", appSettingsJson: "{}" }],
        allExpensesQuery: [{ id: "expense", companyId: "co-a", internalNote: null }],
        allExpenseAttachmentsQuery: [x.attachment], allExpenseOriginalSourcesQuery: [x.source], allExpenseOriginalChunksQuery: [x.chunk],
    };
    const upsert = vi.fn(() => ({ ok: true }));
    const client = { loadQuery: async (query: string) => data[query] ?? [], update: vi.fn(() => ({ ok: true })), upsert };
    await forceTouchAllInvoicingRowsForRelay(client as never, "co-a");
    expect(upsert.mock.calls).toEqual([
        ["expenseAttachment", x.attachment], ["migrationSource", x.source], ["migrationChunk", x.chunk],
    ]);
    expect(Buffer.from(await verifyExpenseOriginalArchiveRows(x.ref, [x.source], [x.chunk]))).toEqual(Buffer.from(x.bytes));
});

it("company deletion cleans only that company's received originals and retains unrelated migration evidence", async () => {
    const x = await originalFixture();
    const data: Record<string, unknown[]> = {
        allExpenseOriginalSourcesQuery: [x.source, { ...x.source, id: "foreign", companyId: "co-b" }],
        allExpenseOriginalChunksQuery: [x.chunk, { ...x.chunk, id: "foreign-chunk", sourceId: "foreign", companyId: "co-b" }],
    };
    const update = vi.fn(() => ({ ok: true }));
    const client = { loadQuery: async (query: string) => data[query] ?? [], getQueryRows: (query: string) => data[query] ?? [], update };
    await deleteLocalCompany(client as never, "co-a" as never);
    expect(update.mock.calls).toEqual([
        ["migrationChunk", { id: x.chunk.id, companyId: "co-a", isDeleted: 1 }],
        ["migrationSource", { id: x.source.id, companyId: "co-a", isDeleted: 1 }],
        ["company", { id: "co-a", isDeleted: 1 }],
    ]);
    update.mockClear();
    resetLocalCompanyData(client as never, "co-a" as never);
    // Existing reset keeps received expenses and therefore also keeps their originals.
    expect(update).not.toHaveBeenCalled();
});

it("ignores unbound rows during relay resend and company deletion while preserving complete archive rows", async () => {
    const x = await originalFixture();
    const data: Record<string, unknown[]> = {
        allCompaniesDetailQuery: [{ id: "co-a", appSettingsJson: "{}" }],
        allExpensesQuery: [{ id: "expense", companyId: "co-a", internalNote: null }],
        allExpenseAttachmentsQuery: [x.attachment, { ...x.attachment, id: "unbound-attachment", expenseId: null }],
        allExpenseOriginalSourcesQuery: [x.source, { ...x.source, id: "unbound-source", companyId: null }],
        allExpenseOriginalChunksQuery: [
            x.chunk,
            { ...x.chunk, id: "missing-source", sourceId: null },
            { ...x.chunk, id: "missing-company", companyId: null },
            { ...x.chunk, id: "unbound-source-chunk", sourceId: "unbound-source" },
        ],
    };
    const update = vi.fn((_table: string, _row: unknown) => ({ ok: true }));
    const upsert = vi.fn((_table: string, _row: unknown) => ({ ok: true }));
    const client = { loadQuery: async (query: string) => data[query] ?? [], getQueryRows: (query: string) => data[query] ?? [], update, upsert };
    // Partial relay merges may have null bindings. Only complete rows may enter
    // the resend batch; their original values are re-emitted without alteration.
    await forceTouchAllInvoicingRowsForRelay(client as never);
    expect(upsert.mock.calls).toEqual([
        ["expenseAttachment", x.attachment], ["migrationSource", x.source], ["migrationChunk", x.chunk],
    ]);
    expect(Buffer.from(await verifyExpenseOriginalArchiveRows(x.ref, [x.source], [x.chunk]))).toEqual(Buffer.from(x.bytes));
    update.mockClear();
    await deleteLocalCompany(client as never, "co-a" as never);
    expect(update.mock.calls.filter(([table]) => table === "migrationChunk" || table === "migrationSource")).toEqual([
        ["migrationChunk", { id: x.chunk.id, companyId: "co-a", isDeleted: 1 }],
        ["migrationSource", { id: x.source.id, companyId: "co-a", isDeleted: 1 }],
    ]);
});
