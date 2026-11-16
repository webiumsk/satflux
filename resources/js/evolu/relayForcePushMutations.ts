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
    allExpenseOriginalSourcesQuery,
    allExpenseOriginalChunksQuery,
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
    ExpenseAttachmentId,
    ExpenseId,
    InvoicingLocalSchema,
    MigrationSourceId,
    MigrationChunkId,
    NumberSeriesId,
    RecurringProfileId,
    RecurringProfileLineId,
    StockBalanceId,
    StockItemId,
    StockMovementId,
    WarehouseId,
} from "./schema";

import { isExpenseOriginalReference } from "./expenseOriginalReference";

const FORCE_PUSH_AT_KEY = "satfluxForceRelayPushAt";

/** Longer warmup for large datasets (66+ invoices). */
export const RELAY_FORCE_CONNECTION_WARMUP_MS = 20_000;

type RowWithId = { id: string; companyId?: string | null };

function matchesCompanyFilter(companyId: string | undefined, row: RowWithId): boolean {
    if (!companyId) {
        return true;
    }
    if ("companyId" in row && row.companyId != null) {
        return String(row.companyId) === companyId;
    }
    return true;
}

function touchJsonBlob(
    current: unknown,
    timestamp: number,
): string {
    let settings: Record<string, unknown> = {};
    try {
        settings = JSON.parse(String(current ?? "{}")) as Record<string, unknown>;
    } catch {
        settings = {};
    }
    return JSON.stringify({
        ...settings,
        [FORCE_PUSH_AT_KEY]: timestamp,
    });
}

function touchOptionalText(current: unknown, timestamp: number): string {
    const base = String(current ?? "").replace(/\s+$/, "");
    return `${base}\u200b${timestamp}`;
}

/**
 * Re-mutate every invoicing row so CRDT ops are re-emitted to the relay.
 * Use after switching relays or when bump-only push did not upload legacy history.
 */
export async function forceTouchAllInvoicingRowsForRelay(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId?: string,
): Promise<number> {
    const timestamp = Date.now();
    let touched = 0;

    const companies = await evolu.loadQuery(allCompaniesDetailQuery);
    for (const row of companies) {
        if (companyId && String(row.id) !== companyId) {
            continue;
        }
        const result = evolu.update("company", {
            id: row.id as CompanyId,
            appSettingsJson: touchJsonBlob(row.appSettingsJson, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const contacts = await evolu.loadQuery(allContactsQuery);
    for (const row of contacts) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("contact", {
            id: row.id as ContactId,
            notes: touchOptionalText(row.notes, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const numberSeries = await evolu.loadQuery(allNumberSeriesQuery);
    for (const row of numberSeries) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("numberSeries", {
            id: row.id as NumberSeriesId,
            periodKey: touchOptionalText(row.periodKey ?? "", timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const documents = await evolu.loadQuery(allDocumentsQuery);
    for (const row of documents) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("document", {
            id: row.id as DocumentId,
            internalNote: touchOptionalText(row.internalNote, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const documentLines = await evolu.loadQuery(allDocumentLinesQuery);
    for (const row of documentLines) {
        const result = evolu.update("documentLine", {
            id: row.id as DocumentLineId,
            description: touchOptionalText(row.description, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const documentEvents = await evolu.loadQuery(allDocumentEventsQuery);
    for (const row of documentEvents) {
        const result = evolu.update("documentEvent", {
            id: row.id as DocumentEventId,
            metadataJson: touchJsonBlob(row.metadataJson, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const expenses = await evolu.loadQuery(allExpensesQuery);
    for (const row of expenses) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("expense", {
            id: row.id as ExpenseId,
            internalNote: touchOptionalText(row.internalNote, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const expenseIds = new Set(expenses.filter(row => !companyId || String(row.companyId) === companyId).map(row => row.id));
    const expenseAttachments = await evolu.loadQuery(allExpenseAttachmentsQuery);
    for (const row of expenseAttachments) {
        if (row.expenseId === null || !expenseIds.has(row.expenseId)) continue;
        // Evolu emits new CRDT messages for every submitted value, including
        // unchanged values. Re-send all columns without changing bound metadata.
        const result = isExpenseOriginalReference(row.contentBase64)
            ? evolu.upsert("expenseAttachment", {
                id: row.id as ExpenseAttachmentId, expenseId: row.expenseId,
                originalFilename: row.originalFilename, mimeType: row.mimeType,
                sizeBytes: row.sizeBytes, contentBase64: row.contentBase64,
            })
            : evolu.update("expenseAttachment", {
                id: row.id as ExpenseAttachmentId,
                originalFilename: touchOptionalText(row.originalFilename, timestamp),
            });
        if (result.ok) {
            touched += 1;
        }
    }

    const originalSources = await evolu.loadQuery(allExpenseOriginalSourcesQuery);
    const sourceIds = new Set(originalSources.filter(row => row.companyId !== null
        && (!companyId || row.companyId === companyId)).map(row => row.id));
    for (const row of originalSources) {
        if (row.companyId === null || !sourceIds.has(row.id)) continue;
        const result = evolu.upsert("migrationSource", {
            id: row.id as MigrationSourceId, companyId: row.companyId,
            sourceKind: row.sourceKind, relatedExportId: row.relatedExportId,
            originalFilename: row.originalFilename, mimeType: row.mimeType,
            sizeBytes: row.sizeBytes, checksum: row.checksum,
            chunkCount: row.chunkCount, savedAt: row.savedAt,
        });
        if (result.ok) touched += 1;
    }
    for (const row of await evolu.loadQuery(allExpenseOriginalChunksQuery)) {
        if (row.sourceId === null || row.companyId === null || !sourceIds.has(row.sourceId)
            || (companyId && row.companyId !== companyId)) continue;
        const result = evolu.upsert("migrationChunk", {
            id: row.id as MigrationChunkId, companyId: row.companyId, sourceId: row.sourceId,
            chunkIndex: row.chunkIndex, contentBase64: row.contentBase64,
        });
        if (result.ok) touched += 1;
    }

    const recurringProfiles = await evolu.loadQuery(allRecurringProfilesQuery);
    for (const row of recurringProfiles) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("recurringProfile", {
            id: row.id as RecurringProfileId,
            internalNote: touchOptionalText(row.internalNote, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const recurringProfileLines = await evolu.loadQuery(allRecurringProfileLinesQuery);
    for (const row of recurringProfileLines) {
        const result = evolu.update("recurringProfileLine", {
            id: row.id as RecurringProfileLineId,
            description: touchOptionalText(row.description, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const warehouses = await evolu.loadQuery(allCompanyWarehousesQuery);
    for (const row of warehouses) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("companyWarehouse", {
            id: row.id as WarehouseId,
            notes: touchOptionalText(row.notes, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const stockItems = await evolu.loadQuery(allCompanyStockItemsQuery);
    for (const row of stockItems) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("companyStockItem", {
            id: row.id as StockItemId,
            internalNote: touchOptionalText(row.internalNote, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const stockBalances = await evolu.loadQuery(allCompanyStockBalancesQuery);
    for (const row of stockBalances) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.upsert("companyStockBalance", {
            id: row.id as StockBalanceId,
            companyId: row.companyId as CompanyId,
            companyWarehouseId: row.companyWarehouseId as WarehouseId,
            companyStockItemId: row.companyStockItemId as StockItemId,
            quantityOnHand: row.quantityOnHand,
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const stockMovements = await evolu.loadQuery(allCompanyStockMovementsQuery);
    for (const row of stockMovements) {
        const result = evolu.update("companyStockMovement", {
            id: row.id as StockMovementId,
            note: touchOptionalText(row.note, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const bankBatches = await evolu.loadQuery(allBankImportBatchesQuery);
    for (const row of bankBatches) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("bankImportBatch", {
            id: row.id as BankImportBatchId,
            filename: touchOptionalText(row.filename, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const bankTransactions = await evolu.loadQuery(allBankTransactionsQuery);
    for (const row of bankTransactions) {
        if (!matchesCompanyFilter(companyId, row as RowWithId)) {
            continue;
        }
        const result = evolu.update("bankTransaction", {
            id: row.id as BankTransactionId,
            reference: touchOptionalText(row.reference, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    const bankMatches = await evolu.loadQuery(allBankTransactionMatchesQuery);
    for (const row of bankMatches) {
        const result = evolu.update("bankTransactionMatch", {
            id: row.id as BankTransactionMatchId,
            matchedAt: touchOptionalText(row.matchedAt, timestamp),
        });
        if (result.ok) {
            touched += 1;
        }
    }

    return touched;
}
