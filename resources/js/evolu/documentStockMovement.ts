import type { Evolu } from "@evolu/common/local-first";
import {
    allCompanyStockBalancesQuery,
    allCompanyStockItemsQuery,
    allCompanyStockMovementsQuery,
    allCompanyWarehousesQuery,
    allDocumentLinesQuery,
    allDocumentsQuery,
} from "./client";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "./documentMap";
import { formatQty, parseStockQty, type EvoluStockBalanceRow, type EvoluStockItemRow, type EvoluStockMovementRow } from "./stockMap";
import type { CompanyId, DocumentId, InvoicingLocalSchema, StockBalanceId, StockItemId, WarehouseId } from "./schema";
import type { EvoluWarehouseRow } from "./warehouseMap";

export type DocumentStockContext = {
    documents: EvoluDocumentRow[];
    documentLines: EvoluDocumentLineRow[];
    stockItems: EvoluStockItemRow[];
    warehouses: EvoluWarehouseRow[];
    balanceRows: EvoluStockBalanceRow[];
    movementRows: EvoluStockMovementRow[];
};

const STOCK_AFFECTED_TYPES = new Set(["invoice", "credit_note", "delivery_note"]);

function movementTimestamp(): string {
    return new Date().toISOString();
}

export async function loadDocumentStockContext(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<DocumentStockContext> {
    const [documents, documentLines, stockItems, warehouses, balanceRows, movementRows] = await Promise.all([
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allDocumentLinesQuery),
        evolu.loadQuery(allCompanyStockItemsQuery),
        evolu.loadQuery(allCompanyWarehousesQuery),
        evolu.loadQuery(allCompanyStockBalancesQuery),
        evolu.loadQuery(allCompanyStockMovementsQuery),
    ]);

    return {
        documents: documents as EvoluDocumentRow[],
        documentLines: documentLines as EvoluDocumentLineRow[],
        stockItems: stockItems as EvoluStockItemRow[],
        warehouses: warehouses as EvoluWarehouseRow[],
        balanceRows: balanceRows as EvoluStockBalanceRow[],
        movementRows: movementRows as EvoluStockMovementRow[],
    };
}

function resolveWarehouseForLine(
    companyId: CompanyId,
    warehouseId: string | null,
    warehouses: EvoluWarehouseRow[],
): EvoluWarehouseRow | null {
    if (warehouseId) {
        const match = warehouses.find(
            (row) => row.id === warehouseId && row.companyId === companyId && row.isActive !== 0,
        );
        if (match) return match;
    }

    const companyWarehouses = warehouses
        .filter((row) => row.companyId === companyId && row.isActive !== 0)
        .sort((a, b) => a.name.localeCompare(b.name));

    return companyWarehouses.find((row) => row.isDefault === 1) ?? companyWarehouses[0] ?? null;
}

function findBalance(
    itemId: StockItemId,
    warehouseId: WarehouseId,
    balanceRows: EvoluStockBalanceRow[],
): EvoluStockBalanceRow | undefined {
    return balanceRows.find(
        (row) => row.companyStockItemId === itemId && row.companyWarehouseId === warehouseId,
    );
}

function applyStockDeltaForDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    document: EvoluDocumentRow,
    item: EvoluStockItemRow,
    warehouse: EvoluWarehouseRow,
    delta: number,
    source: "document_issue" | "document_cancel",
    ctx: DocumentStockContext,
) {
    if (Math.abs(delta) < 0.00001) return;

    const existing = findBalance(item.id, warehouse.id, ctx.balanceRows);
    const previousQty = existing ? parseStockQty(existing.quantityOnHand) : 0;
    const nextQty = previousQty + delta;

    if (existing) {
        evolu.update("companyStockBalance", {
            id: existing.id as StockBalanceId,
            quantityOnHand: formatQty(nextQty),
        });
        existing.quantityOnHand = formatQty(nextQty);
    } else {
        const inserted = evolu.insert("companyStockBalance", {
            companyId: document.companyId,
            companyWarehouseId: warehouse.id,
            companyStockItemId: item.id,
            quantityOnHand: formatQty(nextQty),
        });
        if (inserted.ok) {
            ctx.balanceRows.push({
                id: inserted.value.id,
                companyId: document.companyId,
                companyWarehouseId: warehouse.id,
                companyStockItemId: item.id,
                quantityOnHand: formatQty(nextQty),
            });
        }
    }

    const movement = evolu.insert("companyStockMovement", {
        companyId: document.companyId,
        companyStockItemId: item.id,
        companyWarehouseId: warehouse.id,
        quantityAfter: formatQty(nextQty),
        quantityDelta: formatQty(delta),
        purchaseUnitPrice: item.purchaseUnitPrice,
        saleUnitPrice: item.saleUnitPrice,
        note: null,
        source,
        businessDocumentId: document.id,
        documentNumber: document.number,
        documentType: document.documentType,
        movementAt: movementTimestamp(),
    });

    if (movement.ok) {
        ctx.movementRows.push({
            id: movement.value.id,
            companyId: document.companyId,
            companyStockItemId: item.id,
            companyWarehouseId: warehouse.id,
            quantityAfter: formatQty(nextQty),
            quantityDelta: formatQty(delta),
            purchaseUnitPrice: item.purchaseUnitPrice,
            saleUnitPrice: item.saleUnitPrice,
            note: null,
            source,
            businessDocumentId: document.id,
            documentNumber: document.number,
            documentType: document.documentType,
            movementAt: movementTimestamp(),
        });
    }
}

export function applyLocalDocumentIssueStock(
    evolu: Evolu<InvoicingLocalSchema>,
    document: EvoluDocumentRow,
    ctx: DocumentStockContext,
): void {
    if (!STOCK_AFFECTED_TYPES.has(document.documentType)) return;

    const lines = ctx.documentLines.filter((line) => line.documentId === document.id);
    const aggregated = new Map<
        string,
        { item: EvoluStockItemRow; warehouse: EvoluWarehouseRow; quantity: number }
    >();

    for (const line of lines) {
        if (!line.companyStockItemId) continue;

        const item = ctx.stockItems.find((row) => row.id === line.companyStockItemId);
        if (!item || item.trackInventory !== 1) continue;

        const warehouse = resolveWarehouseForLine(
            document.companyId,
            line.companyWarehouseId,
            ctx.warehouses,
        );
        if (!warehouse || warehouse.deductOnIssue !== 1) continue;

        const key = `${line.companyStockItemId}:${warehouse.id}`;
        const quantity = parseStockQty(line.quantity);
        const existing = aggregated.get(key);
        if (existing) {
            existing.quantity += quantity;
        } else {
            aggregated.set(key, { item, warehouse, quantity });
        }
    }

    for (const { item, warehouse, quantity } of aggregated.values()) {
        const alreadyIssued = ctx.movementRows.some(
            (movement) =>
                movement.businessDocumentId === document.id
                && movement.companyStockItemId === item.id
                && movement.companyWarehouseId === warehouse.id
                && movement.source === "document_issue",
        );
        if (alreadyIssued) continue;

        const delta = document.documentType === "credit_note" ? quantity : -quantity;
        applyStockDeltaForDocument(evolu, document, item, warehouse, delta, "document_issue", ctx);
    }
}

export function reverseLocalDocumentCancelStock(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
    ctx: DocumentStockContext,
): void {
    const document = ctx.documents.find((row) => row.id === documentId);
    if (!document || !STOCK_AFFECTED_TYPES.has(document.documentType)) return;

    const alreadyReversed = ctx.movementRows.some(
        (row) => row.businessDocumentId === documentId && row.source === "document_cancel",
    );
    if (alreadyReversed) return;

    const issueMovements = ctx.movementRows.filter(
        (row) => row.businessDocumentId === documentId && row.source === "document_issue",
    );
    if (issueMovements.length === 0) return;

    for (const movement of issueMovements) {
        const item = ctx.stockItems.find((row) => row.id === movement.companyStockItemId);
        if (!item || item.trackInventory !== 1) continue;

        const warehouse = ctx.warehouses.find((row) => row.id === movement.companyWarehouseId);
        if (!warehouse) continue;

        const delta = -parseStockQty(movement.quantityDelta);
        applyStockDeltaForDocument(evolu, document, item, warehouse, delta, "document_cancel", ctx);
    }
}

export async function applyDocumentStockOnIssueAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
): Promise<void> {
    const ctx = await loadDocumentStockContext(evolu);
    const document = ctx.documents.find((row) => row.id === documentId);
    if (!document || document.status !== "issued") return;
    applyLocalDocumentIssueStock(evolu, document, ctx);
}

export async function reverseDocumentStockOnCancelAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    documentId: DocumentId,
): Promise<void> {
    const ctx = await loadDocumentStockContext(evolu);
    reverseLocalDocumentCancelStock(evolu, documentId, ctx);
}
