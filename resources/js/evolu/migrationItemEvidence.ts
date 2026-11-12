import { createIdFromString } from "@evolu/common";
import type { EvoluDocumentLineRow } from "./documentMap";
import type { MigrationStructuredInvoice } from "./migrationIsdoc";
import { DocumentLineId, type DocumentId } from "./schema";

type ObservedLine = EvoluDocumentLineRow & { ownerId?: string; isDeleted?: number | null };

/** Deterministic ids survive a shared-company owner change without duplicating items. */
export function migrationStructuredLinePatches(documentId: DocumentId, invoice: MigrationStructuredInvoice) {
    return invoice.lines.map((line, index) => ({
        id: DocumentLineId.orThrow(createIdFromString(`satflux.migrated-structured-line.v1.${documentId}.${index}`)),
        documentId,
        sortOrder: String(index),
        name: line.name,
        description: line.description,
        quantity: line.quantity,
        unit: line.unit,
        unitPrice: line.unitPrice,
        lineDiscountPercent: line.lineDiscountPercent,
        taxRate: line.taxRate,
        lineTotal: line.lineTotal,
        companyStockItemId: null,
        companyWarehouseId: null,
    }));
}

/** Exact proof of the complete set, or of an unchanged subset in a resumable draft. */
export function migrationStructuredLinesMatch(
    documentId: DocumentId,
    expected: ReturnType<typeof migrationStructuredLinePatches>,
    rows: readonly ObservedLine[],
    ownerId: string,
    complete: boolean,
): boolean {
    const expectedById = new Map<string, ReturnType<typeof migrationStructuredLinePatches>[number]>(
        expected.map(line => [line.id, line]),
    );
    const seen = new Set<string>();
    for (const row of rows) {
        if (row.ownerId !== ownerId || row.isDeleted === 1) continue;
        const patch = expectedById.get(row.id);
        if (row.documentId !== documentId && !patch) continue;
        if (!patch || seen.has(row.id) || Object.entries(patch).some(([key, value]) => Reflect.get(row, key) !== value)) return false;
        seen.add(row.id);
    }
    return !complete || seen.size === expected.length;
}
