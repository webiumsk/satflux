import { sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import {
    allBankImportBatchesQuery,
    allBankTransactionsQuery,
    allCompanyStockBalancesQuery,
    allCompanyStockItemsQuery,
    allCompanyStockMovementsQuery,
    allCompanyWarehousesQuery,
    allContactsQuery,
    allDocumentsQuery,
    allExpensesQuery,
    allNumberSeriesQuery,
    allRecurringProfilesQuery,
} from "./client";
import { findDuplicateCompanyGroups } from "./duplicateCompanies";
import type {
    BankImportBatchId,
    BankTransactionId,
    CompanyId,
    ContactId,
    DocumentId,
    ExpenseId,
    InvoicingLocalSchema,
    NumberSeriesId,
    RecurringProfileId,
    StockBalanceId,
    StockItemId,
    StockMovementId,
    WarehouseId,
} from "./schema";

export type CompanyMergeListRow = {
    id: string;
    legal_name: string;
    registration_number?: string | null;
    documents_count?: number;
};

export type MergeDuplicateCompaniesResult = {
    mergedGroups: number;
    removedCompanies: number;
};

function issuedDocumentCount(
    companyId: string,
    documents: ReadonlyArray<{ companyId: CompanyId; status: string }>,
): number {
    return documents.filter(
        (doc) => doc.companyId === companyId && doc.status === "issued",
    ).length;
}

function totalDocumentCount(
    companyId: string,
    documents: ReadonlyArray<{ companyId: CompanyId }>,
): number {
    return documents.filter((doc) => doc.companyId === companyId).length;
}

function pickCanonicalCompanyId<T extends { id: string }>(
    group: readonly T[],
    documents: ReadonlyArray<{ companyId: CompanyId; status: string }>,
): string {
    return [...group].sort((a, b) => {
        const issuedDiff =
            issuedDocumentCount(b.id, documents) - issuedDocumentCount(a.id, documents);
        if (issuedDiff !== 0) return issuedDiff;
        return totalDocumentCount(b.id, documents) - totalDocumentCount(a.id, documents);
    })[0]?.id ?? group[0]?.id ?? "";
}

function reassignCompanyForeignKeys(
    evolu: Evolu<InvoicingLocalSchema>,
    fromId: CompanyId,
    toId: CompanyId,
): void {
    for (const row of evolu.getQueryRows(allDocumentsQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("document", { id: row.id as DocumentId, companyId: toId });
    }

    for (const row of evolu.getQueryRows(allContactsQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("contact", { id: row.id as ContactId, companyId: toId });
    }

    for (const row of evolu.getQueryRows(allExpensesQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("expense", { id: row.id as ExpenseId, companyId: toId });
    }

    for (const row of evolu.getQueryRows(allRecurringProfilesQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("recurringProfile", {
            id: row.id as RecurringProfileId,
            companyId: toId,
        });
    }

    for (const row of evolu.getQueryRows(allCompanyWarehousesQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("companyWarehouse", { id: row.id as WarehouseId, companyId: toId });
    }

    for (const row of evolu.getQueryRows(allCompanyStockItemsQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("companyStockItem", { id: row.id as StockItemId, companyId: toId });
    }

    for (const row of evolu.getQueryRows(allCompanyStockBalancesQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("companyStockBalance", { id: row.id as StockBalanceId, companyId: toId });
    }

    for (const row of evolu.getQueryRows(allCompanyStockMovementsQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("companyStockMovement", {
            id: row.id as StockMovementId,
            companyId: toId,
        });
    }

    for (const row of evolu.getQueryRows(allBankImportBatchesQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("bankImportBatch", {
            id: row.id as BankImportBatchId,
            companyId: toId,
        });
    }

    for (const row of evolu.getQueryRows(allBankTransactionsQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("bankTransaction", {
            id: row.id as BankTransactionId,
            companyId: toId,
        });
    }

    for (const row of evolu.getQueryRows(allNumberSeriesQuery)) {
        if (row.companyId !== fromId) continue;
        evolu.update("numberSeries", { id: row.id as NumberSeriesId, companyId: toId });
    }
}

function dedupeNumberSeriesForCompany(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
): void {
    const rows = evolu
        .getQueryRows(allNumberSeriesQuery)
        .filter((row) => row.companyId === companyId);
    const byType = new Map<string, typeof rows>();

    for (const row of rows) {
        const key = row.documentType ?? "";
        const bucket = byType.get(key) ?? [];
        bucket.push(row);
        byType.set(key, bucket);
    }

    for (const group of byType.values()) {
        if (group.length <= 1) continue;
        const sorted = [...group].sort((a, b) => {
            const aNum = parseInt(a.lastNumber || "0", 10) || 0;
            const bNum = parseInt(b.lastNumber || "0", 10) || 0;
            return bNum - aNum;
        });
        for (const row of sorted.slice(1)) {
            evolu.update("numberSeries", {
                id: row.id as NumberSeriesId,
                isDeleted: sqliteTrue,
            });
        }
    }
}

/** Merge duplicate company profiles (same legal name) into the row with the most documents. */
export async function mergeDuplicateCompaniesLocal(
    evolu: Evolu<InvoicingLocalSchema>,
    companies: readonly CompanyMergeListRow[],
): Promise<MergeDuplicateCompaniesResult> {
    await Promise.all([
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allContactsQuery),
        evolu.loadQuery(allNumberSeriesQuery),
        evolu.loadQuery(allExpensesQuery),
        evolu.loadQuery(allRecurringProfilesQuery),
        evolu.loadQuery(allCompanyWarehousesQuery),
        evolu.loadQuery(allCompanyStockItemsQuery),
        evolu.loadQuery(allCompanyStockBalancesQuery),
        evolu.loadQuery(allCompanyStockMovementsQuery),
        evolu.loadQuery(allBankImportBatchesQuery),
        evolu.loadQuery(allBankTransactionsQuery),
    ]);

    const documents = evolu.getQueryRows(allDocumentsQuery);
    const groups = findDuplicateCompanyGroups(companies);
    let removedCompanies = 0;

    for (const group of groups) {
        const keepId = pickCanonicalCompanyId(group, documents) as CompanyId;
        if (!keepId) continue;

        for (const row of group) {
            if (row.id === keepId) continue;
            reassignCompanyForeignKeys(evolu, row.id as CompanyId, keepId);
            evolu.update("company", { id: row.id as CompanyId, isDeleted: sqliteTrue });
            removedCompanies += 1;
        }

        dedupeNumberSeriesForCompany(evolu, keepId);
    }

    return {
        mergedGroups: groups.length,
        removedCompanies,
    };
}
