import { sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import {
    allBankImportBatchesQuery,
    allBankTransactionMatchesQuery,
    allBankTransactionsQuery,
    allCompanyStockBalancesQuery,
    allCompanyStockItemsQuery,
    allCompanyStockMovementsQuery,
    allCompanyWarehousesQuery,
    allContactsQuery,
    allDocumentEventsQuery,
    allDocumentLinesQuery,
    allDocumentsQuery,
    allExpensesQuery,
    allNumberSeriesQuery,
    allRecurringProfileLinesQuery,
    allRecurringProfilesQuery,
} from "./client";
import type {
    BankImportBatchId,
    BankTransactionId,
    BankTransactionMatchId,
    CompanyId,
    ContactId,
    DocumentEventId,
    DocumentId,
    DocumentLineId,
    ExpenseId,
    InvoicingLocalSchema,
    NumberSeriesId,
    RecurringProfileId,
    RecurringProfileLineId,
    StockBalanceId,
    StockItemId,
    StockMovementId,
    WarehouseId,
} from "./schema";

/** Soft-delete company and all related local-first rows (syncs via Evolu relay). */
export function deleteLocalCompany(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
): void {
    const documents = evolu.getQueryRows(allDocumentsQuery);
    const companyDocIds = new Set(
        documents.filter((doc) => doc.companyId === companyId).map((doc) => doc.id),
    );

    for (const doc of documents) {
        if (doc.companyId !== companyId) continue;
        evolu.update("document", { id: doc.id as DocumentId, isDeleted: sqliteTrue });
    }

    for (const line of evolu.getQueryRows(allDocumentLinesQuery)) {
        if (!companyDocIds.has(line.documentId as DocumentId)) continue;
        evolu.update("documentLine", { id: line.id as DocumentLineId, isDeleted: sqliteTrue });
    }

    for (const contact of evolu.getQueryRows(allContactsQuery)) {
        if (contact.companyId !== companyId) continue;
        evolu.update("contact", { id: contact.id as ContactId, isDeleted: sqliteTrue });
    }

    for (const series of evolu.getQueryRows(allNumberSeriesQuery)) {
        if (series.companyId !== companyId) continue;
        evolu.update("numberSeries", { id: series.id as NumberSeriesId, isDeleted: sqliteTrue });
    }

    for (const event of evolu.getQueryRows(allDocumentEventsQuery)) {
        if (!companyDocIds.has(event.documentId as DocumentId)) continue;
        evolu.update("documentEvent", { id: event.id as DocumentEventId, isDeleted: sqliteTrue });
    }

    for (const expense of evolu.getQueryRows(allExpensesQuery)) {
        if (expense.companyId !== companyId) continue;
        evolu.update("expense", { id: expense.id as ExpenseId, isDeleted: sqliteTrue });
    }

    const companyProfileIds = new Set(
        evolu
            .getQueryRows(allRecurringProfilesQuery)
            .filter((row) => row.companyId === companyId)
            .map((row) => row.id),
    );

    for (const profile of evolu.getQueryRows(allRecurringProfilesQuery)) {
        if (profile.companyId !== companyId) continue;
        evolu.update("recurringProfile", { id: profile.id as RecurringProfileId, isDeleted: sqliteTrue });
    }

    for (const line of evolu.getQueryRows(allRecurringProfileLinesQuery)) {
        if (!companyProfileIds.has(line.recurringProfileId)) continue;
        evolu.update("recurringProfileLine", {
            id: line.id as RecurringProfileLineId,
            isDeleted: sqliteTrue,
        });
    }

    for (const warehouse of evolu.getQueryRows(allCompanyWarehousesQuery)) {
        if (warehouse.companyId !== companyId) continue;
        evolu.update("companyWarehouse", { id: warehouse.id as WarehouseId, isDeleted: sqliteTrue });
    }

    for (const item of evolu.getQueryRows(allCompanyStockItemsQuery)) {
        if (item.companyId !== companyId) continue;
        evolu.update("companyStockItem", { id: item.id as StockItemId, isDeleted: sqliteTrue });
    }

    for (const balance of evolu.getQueryRows(allCompanyStockBalancesQuery)) {
        if (balance.companyId !== companyId) continue;
        evolu.update("companyStockBalance", { id: balance.id as StockBalanceId, isDeleted: sqliteTrue });
    }

    for (const movement of evolu.getQueryRows(allCompanyStockMovementsQuery)) {
        if (movement.companyId !== companyId) continue;
        evolu.update("companyStockMovement", { id: movement.id as StockMovementId, isDeleted: sqliteTrue });
    }

    for (const batch of evolu.getQueryRows(allBankImportBatchesQuery)) {
        if (batch.companyId !== companyId) continue;
        evolu.update("bankImportBatch", { id: batch.id as BankImportBatchId, isDeleted: sqliteTrue });
    }

    const companyTxIds = new Set(
        evolu
            .getQueryRows(allBankTransactionsQuery)
            .filter((row) => row.companyId === companyId)
            .map((row) => row.id),
    );

    for (const tx of evolu.getQueryRows(allBankTransactionsQuery)) {
        if (tx.companyId !== companyId) continue;
        evolu.update("bankTransaction", { id: tx.id as BankTransactionId, isDeleted: sqliteTrue });
    }

    for (const match of evolu.getQueryRows(allBankTransactionMatchesQuery)) {
        const linkedToCompany =
            companyTxIds.has(match.bankTransactionId)
            || companyDocIds.has(match.businessDocumentId as DocumentId);
        if (!linkedToCompany) continue;
        evolu.update("bankTransactionMatch", { id: match.id as BankTransactionMatchId, isDeleted: sqliteTrue });
    }

    evolu.update("company", { id: companyId, isDeleted: sqliteTrue });
}
