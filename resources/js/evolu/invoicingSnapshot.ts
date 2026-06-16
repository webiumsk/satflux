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
    allDocumentLinesQuery,
    allDocumentsQuery,
    allExpensesQuery,
    allExpenseAttachmentsQuery,
    allNumberSeriesQuery,
    allRecurringProfileLinesQuery,
    allRecurringProfilesQuery,
} from "./client";
import type { InvoicingLocalSchema } from "./schema";

type InvoicingTable = keyof InvoicingLocalSchema;

const UPSERT_STRIP_KEYS = new Set(["createdAt", "updatedAt"]);

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
    expense: ReadonlyArray<Record<string, unknown>>;
    expenseAttachment: ReadonlyArray<Record<string, unknown>>;
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

const UPSERT_ORDER: InvoicingTable[] = [
    "company",
    "contact",
    "numberSeries",
    "document",
    "documentLine",
    "documentEvent",
    "expense",
    "expenseAttachment",
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
): Promise<InvoicingDataSnapshot> {
    const [
        company,
        contact,
        numberSeries,
        document,
        documentLine,
        documentEvent,
        expense,
        expenseAttachment,
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
        evolu.loadQuery(allCompaniesDetailQuery),
        evolu.loadQuery(allContactsQuery),
        evolu.loadQuery(allNumberSeriesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allDocumentLinesQuery),
        evolu.loadQuery(allDocumentEventsQuery),
        evolu.loadQuery(allExpensesQuery),
        evolu.loadQuery(allExpenseAttachmentsQuery),
        evolu.loadQuery(allRecurringProfilesQuery),
        evolu.loadQuery(allRecurringProfileLinesQuery),
        evolu.loadQuery(allCompanyWarehousesQuery),
        evolu.loadQuery(allCompanyStockItemsQuery),
        evolu.loadQuery(allCompanyStockBalancesQuery),
        evolu.loadQuery(allCompanyStockMovementsQuery),
        evolu.loadQuery(allBankImportBatchesQuery),
        evolu.loadQuery(allBankTransactionsQuery),
        evolu.loadQuery(allBankTransactionMatchesQuery),
    ]);

    return {
        company: company as ReadonlyArray<Record<string, unknown>>,
        contact: contact as ReadonlyArray<Record<string, unknown>>,
        numberSeries: numberSeries as ReadonlyArray<Record<string, unknown>>,
        document: document as ReadonlyArray<Record<string, unknown>>,
        documentLine: documentLine as ReadonlyArray<Record<string, unknown>>,
        documentEvent: documentEvent as ReadonlyArray<Record<string, unknown>>,
        expense: expense as ReadonlyArray<Record<string, unknown>>,
        expenseAttachment: expenseAttachment as ReadonlyArray<Record<string, unknown>>,
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

export function restoreInvoicingSnapshot(
    evolu: Evolu<InvoicingLocalSchema>,
    snapshot: InvoicingDataSnapshot,
): number {
    return restoreInvoicingSnapshotDetailed(evolu, snapshot).upserted;
}

export type SnapshotRestoreFailure = {
    table: InvoicingTable;
    id: unknown;
};

export type SnapshotRestoreReport = {
    upserted: number;
    failed: SnapshotRestoreFailure[];
};

export function restoreInvoicingSnapshotDetailed(
    evolu: Evolu<InvoicingLocalSchema>,
    snapshot: InvoicingDataSnapshot,
): SnapshotRestoreReport {
    let upserted = 0;
    const failed: SnapshotRestoreFailure[] = [];
    for (const table of UPSERT_ORDER) {
        const rows = snapshot[table];
        for (const row of rows) {
            const result = evolu.upsert(table, rowForUpsert(row) as never);
            if (result.ok) {
                upserted += 1;
            } else {
                failed.push({ table, id: row.id ?? null });
            }
        }
    }
    return { upserted, failed };
}

export function snapshotHasInvoicingData(snapshot: InvoicingDataSnapshot): boolean {
    return UPSERT_ORDER.some((table) => snapshot[table].length > 0);
}
