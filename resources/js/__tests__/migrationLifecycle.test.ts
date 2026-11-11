import { beforeEach, describe, expect, it, vi } from "vitest";
import { webcrypto } from "node:crypto";
vi.mock("@/evolu/client", () => Object.fromEntries([
    "allBankImportBatchesQuery", "allBankTransactionMatchesQuery", "allBankTransactionsQuery", "allCompaniesDetailQuery", "allCompanySharesQuery",
    "allCompanyStockBalancesQuery", "allCompanyStockItemsQuery", "allCompanyStockMovementsQuery", "allCompanyWarehousesQuery", "allContactsQuery",
    "allDocumentEventsQuery", "allDocumentLinesQuery", "allDocumentSnapshotsQuery", "allDocumentsQuery", "allExpenseAttachmentsQuery", "allExpensesQuery",
    "allInvoiceTemplatesQuery", "allNumberSeriesQuery", "allRecurringProfileLinesQuery", "allRecurringProfilesQuery", "allMigrationSourcesBackupQuery", "allMigrationChunksQuery",
].map(name => [name, name])));
vi.mock("@/evolu/expenseReconciliation", () => ({
    withExpenseMaintenance: async (evolu: unknown, callback: (scope: unknown) => unknown) => callback({ evolu }),
    commitExpenseMutation: async (_evolu: unknown, mutate: (complete: () => void) => { ok: boolean }) => new Promise(resolve => { const result = mutate(() => resolve({ ok: true })); if (!result.ok) resolve(result); }),
    checkpointExpenseMutations: async () => undefined,
}));
import { collectCompanyRows, MIGRATION_ORDER, rowForSharedUpsert, verifyMigrated } from "@/evolu/companyShareMigration";
import { EMPTY_INVOICING_SNAPSHOT, restoreInvoicingSnapshotDetailedAsync, snapshotInvoicingData } from "@/evolu/invoicingSnapshot";
import { migrationSha256 } from "@/evolu/migrationArchiveValues";

beforeEach(() => vi.stubGlobal("crypto", webcrypto));
describe("migration originals in company sharing and backups", () => {
    it("includes source and chunk records in company copy, verifies both, and strips only system ownership metadata", () => {
        const all = Object.fromEntries(MIGRATION_ORDER.map(table => [table, []])) as never;
        Object.assign(all, {
            company: [{ id: "company-a", ownerId: "private" }],
            migrationSource: [{ id: "source", companyId: "company-a", ownerId: "private", originalFilename: "source.csv" }, { id: "foreign", companyId: "company-b", ownerId: "private" }],
            migrationChunk: [{ id: "chunk", sourceId: "source", companyId: "company-a", ownerId: "private", contentBase64: "YWJj" }],
        });
        const selected = collectCompanyRows(all, "company-a");
        expect(selected.migrationSource.map(row => row.id)).toEqual(["source"]);
        expect(selected.migrationChunk.map(row => row.id)).toEqual(["chunk"]);
        expect(verifyMigrated(selected, "private", "shared").missing).toMatchObject({ migrationSource: ["source"], migrationChunk: ["chunk"] });
        for (const table of MIGRATION_ORDER) selected[table].push(...selected[table].map(row => ({ ...row, ownerId: "shared" })));
        expect(verifyMigrated(selected, "private", "shared").ok).toBe(true);
        expect(rowForSharedUpsert(selected.migrationChunk[0])).toMatchObject({ sourceId: "source", companyId: "company-a", contentBase64: "YWJj" });
        expect(rowForSharedUpsert(selected.migrationChunk[0])).not.toHaveProperty("ownerId");
    });
    it("loads original bytes into the backup snapshot and restores them without imported owner metadata", async () => {
        const bytes = new TextEncoder().encode("abc");
        const source = { id: "source", companyId: "company-a", ownerId: "old-owner", sourceKind: "superfaktura", relatedExportId: null,
            originalFilename: "source.csv", mimeType: "text/csv", sizeBytes: "3", checksum: await migrationSha256(bytes), chunkCount: "1", savedAt: "2025-01-01T00:00:00Z" };
        const chunk = { id: "chunk", companyId: "company-a", ownerId: "old-owner", sourceId: "source", chunkIndex: "0", contentBase64: "YWJj" };
        const evolu = { loadQuery: async (query: string) => query === "allCompaniesDetailQuery" ? [{ id: "company-a" }]
            : query === "allMigrationSourcesBackupQuery" ? [source] : query === "allMigrationChunksQuery" ? [chunk] : [] };
        const snapshot = await snapshotInvoicingData(evolu as never);
        expect(snapshot.migrationSource).toEqual([source]); expect(snapshot.migrationChunk).toEqual([chunk]);
        const upsert = vi.fn((_table: string, row: unknown, options: { onComplete: () => void }) => { queueMicrotask(options.onComplete); return { ok: true, value: { id: (row as { id: string }).id } }; });
        const result = await restoreInvoicingSnapshotDetailedAsync({ upsert } as never, snapshot);
        expect(result).toMatchObject({ upserted: 3, failed: [] });
        const restored = upsert.mock.calls.find(call => call[0] === "migrationChunk")![1];
        expect(restored).toMatchObject({ sourceId: "source", companyId: "company-a", contentBase64: "YWJj" }); expect(restored).not.toHaveProperty("ownerId");
    });
    it("rejects corrupt complete originals before any restore writes and accepts older backup tables", async () => {
        const snapshot = { ...EMPTY_INVOICING_SNAPSHOT, company: [{ id: "company-a" }],
            migrationSource: [{ id: "source", companyId: "company-a", sourceKind: "superfaktura", originalFilename: "source.csv", mimeType: "text/csv", sizeBytes: "3", checksum: "0".repeat(64), chunkCount: "1", savedAt: "2025-01-01" }],
            migrationChunk: [{ id: "chunk", companyId: "company-a", sourceId: "source", chunkIndex: "0", contentBase64: "YWJj" }] };
        const upsert = vi.fn();
        await expect(restoreInvoicingSnapshotDetailedAsync({ upsert } as never, snapshot)).rejects.toThrow("archive_unverified"); expect(upsert).not.toHaveBeenCalled();
        await expect(restoreInvoicingSnapshotDetailedAsync({ upsert } as never, { ...EMPTY_INVOICING_SNAPSHOT })).resolves.toMatchObject({ upserted: 0 });
    });
});
