import type { InvoicingDataSnapshot } from "./invoicingSnapshot";

const SNAPSHOT_TABLE_KEYS = [
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
] as const satisfies ReadonlyArray<keyof InvoicingDataSnapshot>;

function rowIdFingerprint(rows: ReadonlyArray<Record<string, unknown>>): string {
    return rows
        .map((row) => String(row.id ?? ""))
        .filter(Boolean)
        .sort()
        .join("|");
}

/** Stable fingerprint across all invoicing tables (documents, expenses, stock, settings, …). */
export function fingerprintInvoicingSnapshot(snapshot: InvoicingDataSnapshot): string {
    return SNAPSHOT_TABLE_KEYS.map(
        (table) => `${table}:${snapshot[table].length}:${rowIdFingerprint(snapshot[table])}`,
    ).join(";");
}

export function filterInvoicingSnapshotByCompany(
    snapshot: InvoicingDataSnapshot,
    companyId: string,
): InvoicingDataSnapshot {
    const documents = snapshot.document.filter((row) => row.companyId === companyId);
    const documentIds = new Set(documents.map((row) => String(row.id)));
    const expenses = snapshot.expense.filter((row) => row.companyId === companyId);
    const expenseIds = new Set(expenses.map((row) => String(row.id)));
    const recurringProfiles = snapshot.recurringProfile.filter(
        (row) => row.companyId === companyId,
    );
    const recurringProfileIds = new Set(recurringProfiles.map((row) => String(row.id)));

    return {
        company: snapshot.company.filter((row) => row.id === companyId),
        contact: snapshot.contact.filter((row) => row.companyId === companyId),
        numberSeries: snapshot.numberSeries.filter((row) => row.companyId === companyId),
        document: documents,
        documentLine: snapshot.documentLine.filter((row) =>
            documentIds.has(String(row.documentId)),
        ),
        documentEvent: snapshot.documentEvent.filter((row) =>
            documentIds.has(String(row.documentId)),
        ),
        expense: expenses,
        expenseAttachment: snapshot.expenseAttachment.filter((row) =>
            expenseIds.has(String(row.expenseId)),
        ),
        recurringProfile: recurringProfiles,
        recurringProfileLine: snapshot.recurringProfileLine.filter((row) =>
            recurringProfileIds.has(String(row.recurringProfileId)),
        ),
        companyWarehouse: snapshot.companyWarehouse.filter((row) => row.companyId === companyId),
        companyStockItem: snapshot.companyStockItem.filter((row) => row.companyId === companyId),
        companyStockBalance: snapshot.companyStockBalance.filter(
            (row) => row.companyId === companyId,
        ),
        companyStockMovement: snapshot.companyStockMovement.filter(
            (row) => row.companyId === companyId,
        ),
        bankImportBatch: snapshot.bankImportBatch.filter((row) => row.companyId === companyId),
        bankTransaction: snapshot.bankTransaction.filter((row) => row.companyId === companyId),
        bankTransactionMatch: snapshot.bankTransactionMatch.filter((row) =>
            documentIds.has(String(row.businessDocumentId)),
        ),
    };
}
