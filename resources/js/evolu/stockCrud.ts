import { booleanToSqliteBoolean, maxLength, NonEmptyString, sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { EvoluDocumentLineRow } from "./documentMap";
import {
    formatQty,
    parseStockQty,
    type EvoluStockBalanceRow,
    type EvoluStockItemRow,
    type EvoluStockMovementRow,
    type StockItemSavePayload,
} from "./stockMap";
import type {
    CompanyId,
    InvoicingLocalSchema,
    StockBalanceId,
    StockItemId,
    StockMovementId,
    WarehouseId,
} from "./schema";

const NameType = maxLength(255)(NonEmptyString);
const Opt32 = maxLength(32)(NonEmptyString);
const Opt64 = maxLength(64)(NonEmptyString);
const Opt4000 = maxLength(4000)(NonEmptyString);
const CurrencyType = maxLength(3)(NonEmptyString);

function parseOpt(value: string | null | undefined, type: ReturnType<typeof maxLength>) {
    if (value == null || value.trim() === "") return { ok: true as const, value: null };
    return type.from(value.trim());
}

function movementTimestamp(): string {
    return new Date().toISOString();
}

function recordManualMovement(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    itemId: StockItemId,
    warehouseId: WarehouseId,
    previousQty: number,
    nextQty: number,
    item: EvoluStockItemRow,
    note: string,
) {
    const delta = nextQty - previousQty;
    if (delta === 0) return;

    evolu.insert("companyStockMovement", {
        companyId,
        companyStockItemId: itemId,
        companyWarehouseId: warehouseId,
        quantityAfter: formatQty(nextQty),
        quantityDelta: formatQty(delta),
        purchaseUnitPrice: item.purchaseUnitPrice,
        saleUnitPrice: item.saleUnitPrice,
        note,
        source: "manual",
        businessDocumentId: null,
        documentNumber: null,
        documentType: null,
        movementAt: movementTimestamp(),
    });
}

function syncBalances(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    item: EvoluStockItemRow,
    balances: Array<{ warehouse_id: string; quantity_on_hand: number }>,
    existingBalances: EvoluStockBalanceRow[],
    isCreate: boolean,
) {
    const itemBalances = existingBalances.filter((row) => row.companyStockItemId === item.id);
    const touched = new Set<string>();

    for (const entry of balances) {
        const warehouseId = entry.warehouse_id as WarehouseId;
        const nextQty = Number(entry.quantity_on_hand) || 0;
        touched.add(warehouseId);

        const existing = itemBalances.find((row) => row.companyWarehouseId === warehouseId);
        const previousQty = existing ? parseStockQty(existing.quantityOnHand) : 0;

        if (existing) {
            evolu.update("companyStockBalance", {
                id: existing.id as StockBalanceId,
                quantityOnHand: formatQty(nextQty),
            });
        } else if (nextQty !== 0 || isCreate) {
            evolu.insert("companyStockBalance", {
                companyId,
                companyWarehouseId: warehouseId,
                companyStockItemId: item.id,
                quantityOnHand: formatQty(nextQty),
            });
        }

        if (!isCreate && previousQty !== nextQty) {
            recordManualMovement(
                evolu,
                companyId,
                item.id,
                warehouseId,
                previousQty,
                nextQty,
                item,
                "Manual stock adjustment",
            );
        } else if (isCreate && nextQty !== 0) {
            recordManualMovement(
                evolu,
                companyId,
                item.id,
                warehouseId,
                0,
                nextQty,
                item,
                "Initial stock level",
            );
        }
    }

    for (const row of itemBalances) {
        if (touched.has(row.companyWarehouseId)) continue;
        const previousQty = parseStockQty(row.quantityOnHand);
        if (previousQty === 0) {
            evolu.update("companyStockBalance", { id: row.id as StockBalanceId, isDeleted: sqliteTrue });
            continue;
        }
        evolu.update("companyStockBalance", {
            id: row.id as StockBalanceId,
            quantityOnHand: "0",
        });
        recordManualMovement(
            evolu,
            companyId,
            item.id,
            row.companyWarehouseId,
            previousQty,
            0,
            item,
            "Manual stock adjustment",
        );
    }
}

export function saveLocalStockItem(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    payload: StockItemSavePayload,
    options: {
        itemId?: StockItemId;
        existingItems: EvoluStockItemRow[];
        existingBalances: EvoluStockBalanceRow[];
    },
) {
    const name = NameType.from(payload.name.trim());
    if (!name.ok) return name;

    const sku = parseOpt(payload.sku, Opt64);
    if (!sku.ok) return sku;
    const description = parseOpt(payload.description, Opt4000);
    if (!description.ok) return description;
    const unit = parseOpt(payload.unit || "ks", Opt32);
    if (!unit.ok || !unit.value) return { ok: false as const, error: "unit" };
    const purchaseCurrency = parseOpt(payload.purchase_currency, CurrencyType);
    if (!purchaseCurrency.ok) return purchaseCurrency;
    const internalNote = parseOpt(payload.internal_note, Opt4000);
    if (!internalNote.ok) return internalNote;

    if (sku.value) {
        const duplicate = options.existingItems.find(
            (row) =>
                row.companyId === companyId
                && row.sku === sku.value
                && row.id !== options.itemId,
        );
        if (duplicate) return { ok: false as const, error: "duplicate_sku" };
    }

    const fields = {
        companyId,
        name: name.value,
        sku: sku.value,
        description: description.value,
        unit: unit.value,
        trackInventory: booleanToSqliteBoolean(payload.track_inventory),
        purchaseUnitPrice:
            payload.purchase_unit_price != null ? String(payload.purchase_unit_price) : null,
        purchaseCurrency: purchaseCurrency.value,
        saleUnitPrice: payload.sale_unit_price != null ? String(payload.sale_unit_price) : null,
        internalNote: internalNote.value,
        excludeFromSuggester: booleanToSqliteBoolean(payload.exclude_from_suggester),
    };

    let itemId = options.itemId;
    let itemRow: EvoluStockItemRow;

    if (itemId) {
        const result = evolu.update("companyStockItem", { id: itemId, ...fields });
        if (!result.ok) return result;
        itemRow = { id: itemId, ...fields } as EvoluStockItemRow;
    } else {
        const result = evolu.insert("companyStockItem", fields);
        if (!result.ok) return result;
        itemId = result.value.id;
        itemRow = { id: itemId, ...fields } as EvoluStockItemRow;
    }

    if (payload.track_inventory && payload.balances?.length) {
        syncBalances(
            evolu,
            companyId,
            itemRow,
            payload.balances,
            options.existingBalances,
            !options.itemId,
        );
    }

    return { ok: true as const, value: { id: itemId } };
}

export function deleteLocalStockItem(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    itemId: StockItemId,
    options: {
        documentLines: EvoluDocumentLineRow[];
        balanceRows: EvoluStockBalanceRow[];
        movementRows: EvoluStockMovementRow[];
    },
) {
    const referenced = options.documentLines.some((line) => line.companyStockItemId === itemId);
    if (referenced) return { ok: false as const, error: "referenced" };

    for (const row of options.balanceRows.filter((entry) => entry.companyStockItemId === itemId)) {
        evolu.update("companyStockBalance", { id: row.id as StockBalanceId, isDeleted: sqliteTrue });
    }
    for (const row of options.movementRows.filter((entry) => entry.companyStockItemId === itemId)) {
        evolu.update("companyStockMovement", { id: row.id as StockMovementId, isDeleted: sqliteTrue });
    }

    const result = evolu.update("companyStockItem", { id: itemId, isDeleted: sqliteTrue });
    if (!result.ok) return result;
    if (companyId) {
        /* companyId used for caller validation only */
    }
    return { ok: true as const, value: { id: itemId } };
}

export function transferLocalStock(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    itemId: StockItemId,
    fromWarehouseId: WarehouseId,
    toWarehouseId: WarehouseId,
    quantity: number,
    note: string | null,
    options: {
        item: EvoluStockItemRow;
        balanceRows: EvoluStockBalanceRow[];
    },
) {
    if (quantity <= 0) return { ok: false as const, error: "quantity" };
    if (fromWarehouseId === toWarehouseId) return { ok: false as const, error: "same_warehouse" };

    const fromRow = options.balanceRows.find(
        (row) => row.companyStockItemId === itemId && row.companyWarehouseId === fromWarehouseId,
    );
    const fromQty = fromRow ? parseStockQty(fromRow.quantityOnHand) : 0;
    if (fromQty < quantity) return { ok: false as const, error: "insufficient" };

    const toRow = options.balanceRows.find(
        (row) => row.companyStockItemId === itemId && row.companyWarehouseId === toWarehouseId,
    );
    const toQty = toRow ? parseStockQty(toRow.quantityOnHand) : 0;
    const nextFrom = fromQty - quantity;
    const nextTo = toQty + quantity;
    const ts = movementTimestamp();
    const transferNote = note?.trim() || "Stock transfer";

    if (fromRow) {
        evolu.update("companyStockBalance", {
            id: fromRow.id as StockBalanceId,
            quantityOnHand: formatQty(nextFrom),
        });
    }
    if (toRow) {
        evolu.update("companyStockBalance", {
            id: toRow.id as StockBalanceId,
            quantityOnHand: formatQty(nextTo),
        });
    } else {
        evolu.insert("companyStockBalance", {
            companyId,
            companyWarehouseId: toWarehouseId,
            companyStockItemId: itemId,
            quantityOnHand: formatQty(nextTo),
        });
    }

    evolu.insert("companyStockMovement", {
        companyId,
        companyStockItemId: itemId,
        companyWarehouseId: fromWarehouseId,
        quantityAfter: formatQty(nextFrom),
        quantityDelta: formatQty(-quantity),
        purchaseUnitPrice: options.item.purchaseUnitPrice,
        saleUnitPrice: options.item.saleUnitPrice,
        note: transferNote,
        source: "transfer",
        businessDocumentId: null,
        documentNumber: null,
        documentType: null,
        movementAt: ts,
    });
    evolu.insert("companyStockMovement", {
        companyId,
        companyStockItemId: itemId,
        companyWarehouseId: toWarehouseId,
        quantityAfter: formatQty(nextTo),
        quantityDelta: formatQty(quantity),
        purchaseUnitPrice: options.item.purchaseUnitPrice,
        saleUnitPrice: options.item.saleUnitPrice,
        note: transferNote,
        source: "transfer",
        businessDocumentId: null,
        documentNumber: null,
        documentType: null,
        movementAt: ts,
    });

    return { ok: true as const, value: { id: itemId } };
}
