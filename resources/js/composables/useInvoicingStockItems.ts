import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import api from "@/services/api";
import type { StockItemRow, StockSummaryMeta } from "@/composables/useCompanyStockItem";
import {
    allCompanyStockBalancesQuery,
    allCompanyStockItemsQuery,
    allCompanyStockMovementsQuery,
    allCompanyWarehousesQuery,
    allDocumentLinesQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { EvoluDocumentLineRow } from "@/evolu/documentMap";
import {
    buildStockListMeta,
    evoluStockItemToListRow,
    evoluStockMovementToApi,
    filterCompanyStockItems,
    filterStockList,
    searchLocalStockItems,
    stockItemNeighborIds,
    type EvoluStockBalanceRow,
    type EvoluStockItemRow,
    type EvoluStockMovementRow,
    type StockSearchResult,
} from "@/evolu/stockMap";
import type { EvoluWarehouseRow } from "@/evolu/warehouseMap";
import type { CompanyId, InvoicingLocalSchema, StockItemId } from "@/evolu/schema";

export type StockListFilters = {
    q?: string;
    warehouse_id?: string;
};

export interface UseInvoicingStockItemsResult {
    localFirst: boolean;
    items: Ref<StockItemRow[]> | ComputedRef<StockItemRow[]>;
    meta: Ref<StockSummaryMeta | null> | ComputedRef<StockSummaryMeta | null>;
    loading: Ref<boolean>;
    refresh: (filters?: StockListFilters) => Promise<void>;
    evolu: Evolu<InvoicingLocalSchema> | null;
    search: (query: string, limit?: number, warehouseId?: string | null) => StockSearchResult[];
}

function useServerStockItems(companyId: Ref<string>): UseInvoicingStockItemsResult {
    const items = ref<StockItemRow[]>([]);
    const meta = ref<StockSummaryMeta | null>(null);
    const loading = ref(false);

    async function refresh(filters: StockListFilters = {}): Promise<void> {
        if (!companyId.value) {
            items.value = [];
            meta.value = null;
            return;
        }
        loading.value = true;
        try {
            const res = await api.get(`/invoicing/companies/${companyId.value}/stock-items`, {
                params: {
                    q: filters.q || undefined,
                    warehouse_id: filters.warehouse_id || undefined,
                },
            });
            items.value = res.data.data ?? [];
            meta.value = res.data.meta ?? null;
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: false,
        items,
        meta,
        loading,
        refresh,
        evolu: null,
        search: () => [],
    };
}

function useLocalStockItems(companyId: Ref<string>): UseInvoicingStockItemsResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const filters = ref<StockListFilters>({});

    const itemsPromise = evolu.loadQuery(allCompanyStockItemsQuery);
    const balancesPromise = evolu.loadQuery(allCompanyStockBalancesQuery);
    const warehousesPromise = evolu.loadQuery(allCompanyWarehousesQuery);

    const itemRows = useQuery(allCompanyStockItemsQuery, { promise: itemsPromise });
    const balanceRows = useQuery(allCompanyStockBalancesQuery, { promise: balancesPromise });
    const warehouseRows = useQuery(allCompanyWarehousesQuery, { promise: warehousesPromise });

    void Promise.all([itemsPromise, balancesPromise, warehousesPromise]).finally(() => {
        loading.value = false;
    });

    const listRows = computed(() => {
        const companyItems = filterCompanyStockItems(
            itemRows.value as EvoluStockItemRow[],
            companyId.value as CompanyId,
        );
        return companyItems.map((row) =>
            evoluStockItemToListRow(
                row,
                balanceRows.value as EvoluStockBalanceRow[],
                warehouseRows.value as EvoluWarehouseRow[],
                filters.value.warehouse_id || null,
            ),
        );
    });

    const items = computed(() =>
        filterStockList(listRows.value, filters.value.q ?? "", filters.value.warehouse_id ?? ""),
    );

    const meta = computed(() => buildStockListMeta(items.value));

    async function refresh(nextFilters: StockListFilters = filters.value): Promise<void> {
        filters.value = nextFilters;
        loading.value = true;
        try {
            await Promise.all([
                evolu.loadQuery(allCompanyStockItemsQuery),
                evolu.loadQuery(allCompanyStockBalancesQuery),
                evolu.loadQuery(allCompanyWarehousesQuery),
            ]);
        } finally {
            loading.value = false;
        }
    }

    function search(query: string, limit = 10, warehouseId?: string | null): StockSearchResult[] {
        return searchLocalStockItems(
            companyId.value as CompanyId,
            itemRows.value as EvoluStockItemRow[],
            balanceRows.value as EvoluStockBalanceRow[],
            warehouseRows.value as EvoluWarehouseRow[],
            query,
            limit,
            warehouseId,
        );
    }

    return { localFirst: true, items, meta, loading, refresh, evolu, search };
}

export function useInvoicingStockItems(companyId: Ref<string>): UseInvoicingStockItemsResult {
    const localFirst = isInvoicingLocalFirst();
    const server = useServerStockItems(companyId);
    const local = localFirst ? useLocalStockItems(companyId) : null;

    if (local) {
        watch(companyId, () => void local.refresh());
        return local;
    }

    watch(companyId, () => void server.refresh());
    return server;
}

export function useLocalStockItemDetail(companyId: Ref<string>) {
    const evolu = useInvoicingEvolu();
    const itemRows = useQuery(allCompanyStockItemsQuery);
    const balanceRows = useQuery(allCompanyStockBalancesQuery);
    const warehouseRows = useQuery(allCompanyWarehousesQuery);
    const movementRows = useQuery(allCompanyStockMovementsQuery);
    const documentLineRows = useQuery(allDocumentLinesQuery);

    async function refreshAll(): Promise<void> {
        await Promise.all([
            evolu.loadQuery(allCompanyStockItemsQuery),
            evolu.loadQuery(allCompanyStockBalancesQuery),
            evolu.loadQuery(allCompanyWarehousesQuery),
            evolu.loadQuery(allCompanyStockMovementsQuery),
            evolu.loadQuery(allDocumentLinesQuery),
        ]);
    }

    function itemApi(itemId: string) {
        const row = (itemRows.value as EvoluStockItemRow[]).find((entry) => entry.id === itemId);
        if (!row) return null;
        const apiRow = evoluStockItemToListRow(
            row,
            balanceRows.value as EvoluStockBalanceRow[],
            warehouseRows.value as EvoluWarehouseRow[],
        );
        const movements = (movementRows.value as EvoluStockMovementRow[])
            .filter((entry) => entry.companyStockItemId === itemId)
            .sort((a, b) => (b.movementAt ?? "").localeCompare(a.movementAt ?? ""))
            .slice(0, 100)
            .map((entry) => {
                const warehouse = (warehouseRows.value as EvoluWarehouseRow[]).find(
                    (w) => w.id === entry.companyWarehouseId,
                );
                return evoluStockMovementToApi(entry, warehouse?.name);
            });
        return {
            ...apiRow,
            neighbor_ids: stockItemNeighborIds(itemRows.value as EvoluStockItemRow[], companyId.value as CompanyId),
            movements,
        };
    }

    return {
        evolu,
        refreshAll,
        itemApi,
        itemRows,
        balanceRows,
        warehouseRows,
        movementRows,
        documentLineRows: computed(() => documentLineRows.value as EvoluDocumentLineRow[]),
    };
}

export type { StockItemId };
