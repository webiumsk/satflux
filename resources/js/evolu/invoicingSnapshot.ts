import type { Evolu } from "@evolu/common/local-first";
import {
    allBankImportBatchesQuery,
    allBankTransactionMatchesQuery,
    allBankTransactionsQuery,
    allCompaniesDetailQuery,
    allCompanyStockBalancesQuery,
    allCompanyStockItemsQuery,
    allCompanyStockMovementsQuery,
    allCompanyWarehousesQuery,
    allContactsQuery,
    allDocumentEventsQuery,
    allInvoiceTemplatesQuery,
    allDocumentLinesQuery,
    allDocumentSnapshotsQuery,
    allDocumentsQuery,
    allExpensesQuery,
    allExpenseAttachmentsQuery,
    allNumberSeriesQuery,
    allMigrationSourcesBackupQuery,
    allMigrationChunksQuery,
    allRecurringProfileLinesQuery,
    allRecurringProfilesQuery,
} from "./client";
import type { InvoicingLocalSchema } from "./schema";
import { validateMigrationSnapshot } from "./migrationSnapshot";
import { loadEvoluQueryWithTimeout } from "./queryLoad";
import { withExpenseMaintenance, commitExpenseMutation } from "./expenseReconciliation";

// `companyShare` holds SharedOwner secrets and is deliberately outside the
// snapshot / backup / restore cycle (docs/COMPANY_SHARING.md).
type InvoicingTable = Exclude<keyof InvoicingLocalSchema, "companyShare" | "_expenseReconciliation">;

// System metadata is not a writable column. Restore uses the live owner-scoping policy,
// never a partition supplied by an imported backup.
const UPSERT_STRIP_KEYS = new Set(["createdAt", "updatedAt", "ownerId"]);

function rowForUpsert<T extends Record<string, unknown>>(row: T): T {
    const out = { ...row };
    for (const key of UPSERT_STRIP_KEYS) {
        delete out[key];
    }
    return out;
}

export type InvoicingDataSnapshot = {
    company: ReadonlyArray<Record<string, unknown>>;
    contact: ReadonlyArray<Record<string, unknown>>;
    numberSeries: ReadonlyArray<Record<string, unknown>>;
    document: ReadonlyArray<Record<string, unknown>>;
    documentLine: ReadonlyArray<Record<string, unknown>>;
    documentEvent: ReadonlyArray<Record<string, unknown>>;
    documentSnapshot: ReadonlyArray<Record<string, unknown>>;
    invoiceTemplate: ReadonlyArray<Record<string, unknown>>;
    expense: ReadonlyArray<Record<string, unknown>>;
    expenseAttachment: ReadonlyArray<Record<string, unknown>>;
    migrationSource?: ReadonlyArray<Record<string, unknown>>;
    migrationChunk?: ReadonlyArray<Record<string, unknown>>;
    recurringProfile: ReadonlyArray<Record<string, unknown>>;
    recurringProfileLine: ReadonlyArray<Record<string, unknown>>;
    companyWarehouse: ReadonlyArray<Record<string, unknown>>;
    companyStockItem: ReadonlyArray<Record<string, unknown>>;
    companyStockBalance: ReadonlyArray<Record<string, unknown>>;
    companyStockMovement: ReadonlyArray<Record<string, unknown>>;
    bankImportBatch: ReadonlyArray<Record<string, unknown>>;
    bankTransaction: ReadonlyArray<Record<string, unknown>>;
    bankTransactionMatch: ReadonlyArray<Record<string, unknown>>;
};

export const EMPTY_INVOICING_SNAPSHOT: InvoicingDataSnapshot = {
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
    migrationSource: [],
    migrationChunk: [],
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

const UPSERT_ORDER: InvoicingTable[] = [
    "company",
    "contact",
    "numberSeries",
    "document",
    "documentLine",
    "documentEvent",
    "documentSnapshot",
    "invoiceTemplate",
    "expense",
    "expenseAttachment",
    "migrationSource",
    "migrationChunk",
    "recurringProfile",
    "recurringProfileLine",
    "companyWarehouse",
    "companyStockItem",
    "companyStockBalance",
    "companyStockMovement",
    "bankImportBatch",
    "bankTransaction",
    "bankTransactionMatch",
];

export async function snapshotInvoicingData(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: { includeMigrationOriginals?: boolean; queryTimeoutMs?: number },
): Promise<InvoicingDataSnapshot> {
    const includeOriginals = options?.includeMigrationOriginals !== false;
    const load: typeof evolu.loadQuery = query => options?.queryTimeoutMs === undefined
        ? evolu.loadQuery(query)
        : loadEvoluQueryWithTimeout(evolu, query, options.queryTimeoutMs);
    const [
        company,
        contact,
        numberSeries,
        document,
        documentLine,
        documentEvent,
        documentSnapshot,
        invoiceTemplate,
        expense,
        expenseAttachment,
        migrationSource,
        migrationChunk,
        recurringProfile,
        recurringProfileLine,
        companyWarehouse,
        companyStockItem,
        companyStockBalance,
        companyStockMovement,
        bankImportBatch,
        bankTransaction,
        bankTransactionMatch,
    ] = await Promise.all([
        load(allCompaniesDetailQuery),
        load(allContactsQuery),
        load(allNumberSeriesQuery),
        load(allDocumentsQuery),
        load(allDocumentLinesQuery),
        load(allDocumentEventsQuery),
        load(allDocumentSnapshotsQuery),
        load(allInvoiceTemplatesQuery),
        load(allExpensesQuery),
        load(allExpenseAttachmentsQuery),
        includeOriginals ? load(allMigrationSourcesBackupQuery) : Promise.resolve([]),
        includeOriginals ? load(allMigrationChunksQuery) : Promise.resolve([]),
        load(allRecurringProfilesQuery),
        load(allRecurringProfileLinesQuery),
        load(allCompanyWarehousesQuery),
        load(allCompanyStockItemsQuery),
        load(allCompanyStockBalancesQuery),
        load(allCompanyStockMovementsQuery),
        load(allBankImportBatchesQuery),
        load(allBankTransactionsQuery),
        load(allBankTransactionMatchesQuery),
    ]);

    return {
        company: company as ReadonlyArray<Record<string, unknown>>,
        contact: contact as ReadonlyArray<Record<string, unknown>>,
        numberSeries: numberSeries as ReadonlyArray<Record<string, unknown>>,
        document: document as ReadonlyArray<Record<string, unknown>>,
        documentLine: documentLine as ReadonlyArray<Record<string, unknown>>,
        documentEvent: documentEvent as ReadonlyArray<Record<string, unknown>>,
        documentSnapshot: documentSnapshot as ReadonlyArray<Record<string, unknown>>,
        invoiceTemplate: invoiceTemplate as ReadonlyArray<Record<string, unknown>>,
        expense: expense as ReadonlyArray<Record<string, unknown>>,
        expenseAttachment: expenseAttachment as ReadonlyArray<Record<string, unknown>>,
        migrationSource: migrationSource as ReadonlyArray<Record<string, unknown>>,
        migrationChunk: migrationChunk as ReadonlyArray<Record<string, unknown>>,
        recurringProfile: recurringProfile as ReadonlyArray<Record<string, unknown>>,
        recurringProfileLine: recurringProfileLine as ReadonlyArray<Record<string, unknown>>,
        companyWarehouse: companyWarehouse as ReadonlyArray<Record<string, unknown>>,
        companyStockItem: companyStockItem as ReadonlyArray<Record<string, unknown>>,
        companyStockBalance: companyStockBalance as ReadonlyArray<Record<string, unknown>>,
        companyStockMovement: companyStockMovement as ReadonlyArray<Record<string, unknown>>,
        bankImportBatch: bankImportBatch as ReadonlyArray<Record<string, unknown>>,
        bankTransaction: bankTransaction as ReadonlyArray<Record<string, unknown>>,
        bankTransactionMatch: bankTransactionMatch as ReadonlyArray<Record<string, unknown>>,
    };
}

export async function restoreInvoicingSnapshot(
    evolu: Evolu<InvoicingLocalSchema>,
    snapshot: InvoicingDataSnapshot,
): Promise<number> {
    return (await restoreInvoicingSnapshotDetailedAsync(evolu, snapshot)).upserted;
}

/** Restore committed rows with an optional current-context predicate forwarded to every mutation boundary. */
export async function restoreInvoicingSnapshotAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    snapshot: InvoicingDataSnapshot,
    isCurrent?: () => boolean,
): Promise<number> {
    return (await restoreInvoicingSnapshotDetailedAsync(evolu, snapshot, isCurrent)).upserted;
}

export type SnapshotRestoreFailure = {
    table: InvoicingTable;
    id: unknown;
    error?: unknown;
};

export type SnapshotRestoreReport = {
    upserted: number;
    failed: SnapshotRestoreFailure[];
};

function upsertRowAwait(
    evolu: Evolu<InvoicingLocalSchema>,
    table: InvoicingTable,
    row: Record<string, unknown>,
): Promise<{ ok: true } | { ok: false; error: unknown }> {
    const payload = rowForUpsert(row) as never;

    return commitExpenseMutation(evolu, (onComplete) => evolu.upsert(table, payload, { onComplete }))
        .then((result) => result.ok ? { ok: true as const } : { ok: false as const, error: result.error });
}

/** @deprecated Prefer restoreInvoicingSnapshotDetailedAsync - sync batch aborts on any invalid row. */
export async function restoreInvoicingSnapshotDetailed(
    evolu: Evolu<InvoicingLocalSchema>,
    snapshot: InvoicingDataSnapshot,
): Promise<SnapshotRestoreReport> {
    return restoreInvoicingSnapshotDetailedAsync(evolu, snapshot);
}

/**
 * Restore allowlisted rows in dependency order under the maintenance gate, awaiting each committed result.
 * Report known row rejections; share-secret rows and the local coordination marker are outside the restore allowlist.
 * Optional currentness checks stop newly scheduled rows after waits; already issued commits are not rolled back.
 */
export async function restoreInvoicingSnapshotDetailedAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    snapshot: InvoicingDataSnapshot,
    isCurrent?: () => boolean,
): Promise<SnapshotRestoreReport> {
    /** Stop newly scheduled writes without attempting to roll back already submitted row commits. */
    function assertCurrent(): void {
        if (isCurrent && !isCurrent()) throw new Error("recovery_context_changed");
    }
    assertCurrent();
    await validateMigrationSnapshot(snapshot);
    assertCurrent();
    return withExpenseMaintenance(evolu, async (scope) => {
    assertCurrent();
    evolu = scope.evolu;
    let upserted = 0;
    const failed: SnapshotRestoreFailure[] = [];
    for (const table of UPSERT_ORDER) {
        for (const row of snapshot[table] ?? []) {
            assertCurrent();
            const result = await upsertRowAwait(evolu, table, row as Record<string, unknown>);
            assertCurrent();
            if (result.ok) {
                upserted += 1;
            } else {
                failed.push({ table, id: row.id ?? null, error: result.error });
            }
        }
    }
    return { upserted, failed };
    });
}

export function snapshotHasInvoicingData(snapshot: InvoicingDataSnapshot): boolean {
    return UPSERT_ORDER.some((table) => (snapshot[table] ?? []).length > 0);
}
