import type { Evolu } from "@evolu/common/local-first";
import { computed, ref, watch, type ComputedRef, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import api from "@/services/api";
import {
    allCompanyStockBalancesQuery,
    allCompanyWarehousesQuery,
    allContactsQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { evoluContactToApi } from "@/evolu/contactMap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import { ensureDefaultLocalWarehouse } from "@/evolu/warehouseCrud";
import {
    evoluWarehouseToApi,
    filterCompanyWarehouses,
    type EvoluWarehouseRow,
} from "@/evolu/warehouseMap";
import type { WarehouseRow } from "@/composables/useCompanyWarehouse";
import type { CompanyId, InvoicingLocalSchema, WarehouseId } from "@/evolu/schema";

export interface UseInvoicingWarehousesResult {
    localFirst: boolean;
    warehouses: Ref<WarehouseRow[]> | ComputedRef<WarehouseRow[]>;
    loading: Ref<boolean>;
    refresh: (activeOnly?: boolean) => Promise<void>;
    evolu: Evolu<InvoicingLocalSchema> | null;
    warehouseRows: Ref<EvoluWarehouseRow[]> | ComputedRef<EvoluWarehouseRow[]>;
}

function useServerWarehouses(companyId: Ref<string>): UseInvoicingWarehousesResult {
    const warehouses = ref<WarehouseRow[]>([]);
    const loading = ref(false);

    async function refresh(activeOnly = false): Promise<void> {
        if (!companyId.value) {
            warehouses.value = [];
            return;
        }
        loading.value = true;
        try {
            const res = await api.get(`/invoicing/companies/${companyId.value}/warehouses`, {
                params: activeOnly ? { active_only: 1 } : undefined,
            });
            warehouses.value = res.data.data ?? [];
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: false,
        warehouses,
        loading,
        refresh,
        evolu: null,
        warehouseRows: computed(() => [] as EvoluWarehouseRow[]),
    };
}

function useLocalWarehouses(companyId: Ref<string>): UseInvoicingWarehousesResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const activeOnly = ref(false);

    const warehousesPromise = evolu.loadQuery(allCompanyWarehousesQuery);
    const contactsPromise = evolu.loadQuery(allContactsQuery);
    const warehouseRows = useQuery(allCompanyWarehousesQuery, { promise: warehousesPromise });
    const contactRows = useQuery(allContactsQuery, { promise: contactsPromise });

    void Promise.all([warehousesPromise, contactsPromise]).finally(() => {
        loading.value = false;
    });

    const warehouses = computed(() => {
        const contacts = contactRows.value.map((row) => evoluContactToApi(row));
        const contactNames = new Map(contacts.map((c) => [c.id, c.name]));
        return filterCompanyWarehouses(
            warehouseRows.value as EvoluWarehouseRow[],
            companyId.value as CompanyId,
            activeOnly.value,
        ).map((row) => evoluWarehouseToApi(row, row.companyContactId ? contactNames.get(row.companyContactId) : null));
    });

    async function refresh(nextActiveOnly = activeOnly.value): Promise<void> {
        activeOnly.value = nextActiveOnly;
        loading.value = true;
        try {
            await Promise.all([
                evolu.loadQuery(allCompanyWarehousesQuery),
                evolu.loadQuery(allContactsQuery),
                evolu.loadQuery(allCompanyStockBalancesQuery),
            ]);
            const rows = warehouseRows.value as EvoluWarehouseRow[];
            if (companyId.value && !rows.some((row) => row.companyId === companyId.value)) {
                ensureDefaultLocalWarehouse(evolu, companyId.value as CompanyId, rows);
                await evolu.loadQuery(allCompanyWarehousesQuery);
            }
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: true,
        warehouses,
        loading,
        refresh,
        evolu,
        warehouseRows: computed(() => warehouseRows.value as EvoluWarehouseRow[]),
    };
}

export function useInvoicingWarehouses(companyId: Ref<string>): UseInvoicingWarehousesResult {
    const localFirst = isInvoicingLocalFirst();
    const server = useServerWarehouses(companyId);
    const local = localFirst ? useLocalWarehouses(companyId) : null;

    if (local) {
        watch(companyId, () => void local.refresh());
        return local;
    }

    watch(companyId, () => void server.refresh());
    return server;
}

export type { WarehouseId };
