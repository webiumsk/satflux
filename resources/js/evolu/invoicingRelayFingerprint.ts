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
    allExpenseAttachmentsQuery,
    allExpensesQuery,
    allNumberSeriesQuery,
    allRecurringProfileLinesQuery,
    allRecurringProfilesQuery,
} from "./client";
import type { InvoicingLocalSchema } from "./schema";
import { snapshotInvoicingData } from "./invoicingSnapshot";
import {
    filterInvoicingSnapshotByCompany,
    fingerprintInvoicingSnapshot,
} from "./invoicingRelayFingerprintCore";

export {
    filterInvoicingSnapshotByCompany,
    fingerprintInvoicingSnapshot,
} from "./invoicingRelayFingerprintCore";

const INVOICING_RELAY_QUERY_LIST = [
    allCompaniesDetailQuery,
    allContactsQuery,
    allNumberSeriesQuery,
    allDocumentsQuery,
    allDocumentLinesQuery,
    allDocumentEventsQuery,
    allExpensesQuery,
    allExpenseAttachmentsQuery,
    allRecurringProfilesQuery,
    allRecurringProfileLinesQuery,
    allCompanyWarehousesQuery,
    allCompanyStockItemsQuery,
    allCompanyStockBalancesQuery,
    allCompanyStockMovementsQuery,
    allBankImportBatchesQuery,
    allBankTransactionsQuery,
    allBankTransactionMatchesQuery,
] as const;

/** Reload every local-first invoicing query (picks up relay merges in SQLite). */
export async function refreshAllInvoicingLocalQueries(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<void> {
    await Promise.all(INVOICING_RELAY_QUERY_LIST.map((query) => evolu.loadQuery(query)));
}

export async function loadInvoicingRelayFingerprint(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId?: string,
): Promise<string> {
    const snapshot = await snapshotInvoicingData(evolu);
    const scoped = companyId
        ? filterInvoicingSnapshotByCompany(snapshot, companyId)
        : snapshot;
    return fingerprintInvoicingSnapshot(scoped);
}
