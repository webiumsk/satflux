import type { StockBalanceRow, StockItemRow, StockSummaryMeta } from "@/composables/useCompanyStockItem";
import type { StockItemMovementRow } from "@/composables/useCompanyStockItem";
import type { EvoluWarehouseRow } from "./warehouseMap";
import type { CompanyId, StockItemId, WarehouseId } from "./schema";

export type EvoluStockItemRow = {
    id: StockItemId;
    companyId: CompanyId;
    name: string;
    sku: string | null;
    description: string | null;
    unit: string | null;
    trackInventory: 0 | 1 | null;
    purchaseUnitPrice: string | null;
    purchaseCurrency: string | null;
    saleUnitPrice: string | null;
    internalNote: string | null;
    excludeFromSuggester: 0 | 1 | null;
};

export type EvoluStockBalanceRow = {
    id: string;
    companyId: CompanyId;
    companyWarehouseId: WarehouseId;
    companyStockItemId: StockItemId;
    quantityOnHand: string | null;
};

export type EvoluStockMovementRow = {
    id: string;
    companyId: CompanyId;
    companyStockItemId: StockItemId;
    companyWarehouseId: WarehouseId;
    quantityAfter: string | null;
    quantityDelta: string | null;
    purchaseUnitPrice: string | null;
    saleUnitPrice: string | null;
    note: string | null;
    source: string;
    businessDocumentId: string | null;
    documentNumber: string | null;
    documentType: string | null;
    movementAt: string | null;
};

export type StockItemSavePayload = {
    name: string;
    sku: string | null;
    description: string | null;
    unit: string;
    track_inventory: boolean;
    purchase_unit_price: number | null;
    purchase_currency: string | null;
    sale_unit_price: number | null;
    internal_note: string | null;
    exclude_from_suggester: boolean;
    balances?: Array<{ warehouse_id: string; quantity_on_hand: number }>;
};

export type StockSearchResult = {
    id: string;
    name: string;
    description: string | null;
    sku: string | null;
    unit: string;
    sale_unit_price: number | null;
    quantity_on_hand: number;
    total_on_hand: number;
    quantities_by_warehouse: Record<string, number>;
    track_inventory: boolean;
    deduct_on_issue: boolean | null;
};

function sqliteBool(value: 0 | 1 | null | undefined, fallback = false): boolean {
    if (value === 1) return true;
    if (value === 0) return false;
    return fallback;
}

function qty(value: string | null | undefined): number {
    const n = parseFloat(value || "0");
    return Number.isFinite(n) ? n : 0;
}

function formatQty(value: number): string {
    return value.toFixed(4).replace(/\.?0+$/, "") || "0";
}

export function filterCompanyStockItems(
    rows: EvoluStockItemRow[],
    companyId: CompanyId,
): EvoluStockItemRow[] {
    return rows.filter((row) => row.companyId === companyId).sort((a, b) => a.name.localeCompare(b.name));
}

function balancesForItem(
    itemId: StockItemId,
    balanceRows: EvoluStockBalanceRow[],
    warehouses: EvoluWarehouseRow[],
): StockBalanceRow[] {
    return balanceRows
        .filter((row) => row.companyStockItemId === itemId)
        .map((row) => {
            const warehouse = warehouses.find((w) => w.id === row.companyWarehouseId);
            return {
                warehouse_id: row.companyWarehouseId,
                warehouse_name: warehouse?.name,
                warehouse_type: warehouse?.type as StockBalanceRow["warehouse_type"],
                deduct_on_issue: warehouse ? warehouse.deductOnIssue === 1 : undefined,
                quantity_on_hand: qty(row.quantityOnHand),
            };
        });
}

function totalOnHand(balances: StockBalanceRow[]): number {
    return balances.reduce((sum, row) => sum + Number(row.quantity_on_hand || 0), 0);
}

export function evoluStockItemToListRow(
    row: EvoluStockItemRow,
    balanceRows: EvoluStockBalanceRow[],
    warehouses: EvoluWarehouseRow[],
    warehouseFilter?: string | null,
): StockItemRow {
    const balances = balancesForItem(row.id, balanceRows, warehouses);
    const filteredQty = warehouseFilter
        ? balances.find((b) => b.warehouse_id === warehouseFilter)?.quantity_on_hand ?? 0
        : totalOnHand(balances);

    return {
        id: row.id,
        name: row.name,
        sku: row.sku,
        description: row.description,
        unit: row.unit || "ks",
        track_inventory: sqliteBool(row.trackInventory, true),
        quantity_on_hand: filteredQty,
        balances,
        purchase_unit_price: row.purchaseUnitPrice,
        purchase_currency: row.purchaseCurrency,
        sale_unit_price: row.saleUnitPrice,
        internal_note: row.internalNote,
        exclude_from_suggester: sqliteBool(row.excludeFromSuggester),
    };
}

export function buildStockListMeta(
    items: StockItemRow[],
    defaultCurrency = "EUR",
): StockSummaryMeta {
    let purchaseTotal = 0;
    let saleTotal = 0;
    for (const item of items) {
        const onHand = Number(item.quantity_on_hand) || 0;
        purchaseTotal += onHand * (Number(item.purchase_unit_price) || 0);
        saleTotal += onHand * (Number(item.sale_unit_price) || 0);
    }
    return {
        item_count: items.length,
        purchase_value_total: purchaseTotal,
        sale_value_total: saleTotal,
        summary_currency: defaultCurrency,
    };
}

export function filterStockList(
    items: StockItemRow[],
    search: string,
    warehouseId: string,
): StockItemRow[] {
    const q = search.trim().toLowerCase();
    return items.filter((item) => {
        if (warehouseId) {
            const qtyInWarehouse = item.balances?.find((b) => b.warehouse_id === warehouseId)?.quantity_on_hand ?? 0;
            if (Number(qtyInWarehouse) === 0) return false;
        }
        if (!q) return true;
        return (
            item.name.toLowerCase().includes(q)
            || (item.sku ?? "").toLowerCase().includes(q)
            || (item.description ?? "").toLowerCase().includes(q)
        );
    });
}

export function evoluStockMovementToApi(
    row: EvoluStockMovementRow,
    warehouseName?: string | null,
): StockItemMovementRow {
    return {
        id: row.id,
        created_at: row.movementAt ?? undefined,
        quantity_after: qty(row.quantityAfter),
        quantity_delta: qty(row.quantityDelta),
        purchase_unit_price: row.purchaseUnitPrice,
        sale_unit_price: row.saleUnitPrice,
        note: row.note,
        source: row.source,
        document_number: row.documentNumber,
        document_type: row.documentType,
        business_document_id: row.businessDocumentId,
        company_warehouse_id: row.companyWarehouseId,
        warehouse_name: warehouseName ?? null,
    };
}

export function stockItemNeighborIds(items: EvoluStockItemRow[], companyId: CompanyId): string[] {
    return filterCompanyStockItems(items, companyId).map((row) => row.id);
}

export function searchLocalStockItems(
    companyId: CompanyId,
    items: EvoluStockItemRow[],
    balanceRows: EvoluStockBalanceRow[],
    warehouses: EvoluWarehouseRow[],
    query: string,
    limit: number,
    warehouseId?: string | null,
): StockSearchResult[] {
    const q = query.trim().toLowerCase();
    if (!q) return [];

    const companyWarehouses = warehouses.filter((row) => row.companyId === companyId);
    const matches = filterCompanyStockItems(items, companyId)
        .filter((row) => !sqliteBool(row.excludeFromSuggester))
        .filter(
            (row) =>
                row.name.toLowerCase().includes(q)
                || (row.sku ?? "").toLowerCase().includes(q),
        )
        .slice(0, limit);

    return matches.map((row) => {
        const balances = balancesForItem(row.id, balanceRows, companyWarehouses);
        const quantitiesByWarehouse = Object.fromEntries(
            balances.map((b) => [b.warehouse_id, Number(b.quantity_on_hand) || 0]),
        );
        const total = totalOnHand(balances);
        const warehouseQty = warehouseId ? quantitiesByWarehouse[warehouseId] ?? 0 : total;
        const warehouse = warehouseId ? companyWarehouses.find((w) => w.id === warehouseId) : null;

        return {
            id: row.id,
            name: row.name,
            description: row.description,
            sku: row.sku,
            unit: row.unit || "ks",
            sale_unit_price: row.saleUnitPrice != null ? Number(row.saleUnitPrice) : null,
            quantity_on_hand: warehouseQty,
            total_on_hand: total,
            quantities_by_warehouse: quantitiesByWarehouse,
            track_inventory: sqliteBool(row.trackInventory, true),
            deduct_on_issue: warehouse ? warehouse.deductOnIssue === 1 : null,
        };
    });
}

export { formatQty, qty as parseStockQty };
