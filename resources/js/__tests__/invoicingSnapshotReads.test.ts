import { afterEach, expect, it, vi } from "vitest";
import { snapshotInvoicingData } from "../evolu/invoicingSnapshot";
import { loadInvoicingRelayFingerprint } from "../evolu/invoicingRelayFingerprint";

vi.mock("@/evolu/client", () => Object.fromEntries([
    "allBankImportBatchesQuery", "allBankTransactionMatchesQuery", "allBankTransactionsQuery",
    "allCompaniesDetailQuery", "allCompanyStockBalancesQuery", "allCompanyStockItemsQuery",
    "allCompanyStockMovementsQuery", "allCompanyWarehousesQuery", "allContactsQuery",
    "allDocumentEventsQuery", "allInvoiceTemplatesQuery", "allDocumentLinesQuery",
    "allDocumentSnapshotsQuery", "allDocumentsQuery", "allExpensesQuery",
    "allExpenseAttachmentsQuery", "allNumberSeriesQuery", "allMigrationSourcesBackupQuery",
    "allMigrationChunksQuery", "allRecurringProfileLinesQuery", "allRecurringProfilesQuery",
].map(name => [name, name])));
vi.mock("@/evolu/expenseReconciliation", () => ({}));
afterEach(() => { vi.useRealTimers(); });

it("retains original bytes in complete snapshots by default", async () => {
    const source = { id: "source", checksum: "synthetic-checksum" };
    const chunk = { id: "chunk", contentBase64: "c3ludGhldGlj" };
    const loadQuery = vi.fn(async query => query === "allMigrationChunksQuery" ? [chunk]
        : query === "allMigrationSourcesBackupQuery" ? [source] : []);
    const snapshot = await snapshotInvoicingData({ loadQuery } as never);
    expect(snapshot.migrationSource).toEqual([source]);
    expect(snapshot.migrationChunk).toEqual([chunk]);
});

it("computes a fingerprint without reading potentially stalled archive contents", async () => {
    const loadQuery = vi.fn(query => {
        if (query === "allMigrationChunksQuery" || query === "allMigrationSourcesBackupQuery") {
            return new Promise(() => undefined);
        }
        return Promise.resolve([]);
    });
    const fingerprint = await loadInvoicingRelayFingerprint({ loadQuery } as never);
    expect(fingerprint).toContain("company:0:");
    expect(loadQuery.mock.calls.flat()).not.toContain("allMigrationChunksQuery");
    expect(loadQuery.mock.calls.flat()).not.toContain("allMigrationSourcesBackupQuery");
});

it("bounds passive snapshot reads and rejects a stalled core query", async () => {
    vi.useFakeTimers();
    const loadQuery = vi.fn(query => query === "allCompaniesDetailQuery" ? new Promise(() => undefined) : Promise.resolve([]));
    const pending = snapshotInvoicingData({ loadQuery } as never, { includeMigrationOriginals: false, queryTimeoutMs: 100 });
    const rejected = expect(pending).rejects.toThrow("evolu_query_timeout");
    await vi.advanceTimersByTimeAsync(101);
    await rejected;
});
