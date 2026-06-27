import { onMounted, ref, watch, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import api from "@/services/api";
import { allCompaniesDetailQuery, useInvoicingEvolu } from "@/evolu/client";
import {
    evoluCompanyToApi,
    type EvoluCompanyRow,
    type InvoicingCompanyRecord,
} from "@/evolu/companyMap";
import { isInvoicingLocalFirst } from "@/evolu/flags";
import type { CompanyId } from "@/evolu/schema";
import { useStoresStore } from "@/store/stores";

export interface UseInvoicingCompanyResult {
    localFirst: boolean;
    company: Ref<InvoicingCompanyRecord | null>;
    loading: Ref<boolean>;
    refresh: () => Promise<void>;
    applyCompanyPatch: (patch: Partial<InvoicingCompanyRecord>) => void;
}

function useServerInvoicingCompany(companyId: Ref<string>): UseInvoicingCompanyResult {
    const company = ref<InvoicingCompanyRecord | null>(null);
    const loading = ref(false);

    async function refresh(): Promise<void> {
        if (!companyId.value) {
            company.value = null;
            return;
        }
        loading.value = true;
        try {
            const res = await api.get(`/invoicing/companies/${companyId.value}`);
            company.value = res.data.data;
        } catch {
            company.value = null;
        } finally {
            loading.value = false;
        }
    }

    function applyCompanyPatch(patch: Partial<InvoicingCompanyRecord>): void {
        if (!company.value) return;
        company.value = { ...company.value, ...patch };
    }

    watch(companyId, () => void refresh(), { immediate: true });

    return {
        localFirst: false,
        company,
        loading,
        refresh,
        applyCompanyPatch,
    };
}

function useLocalInvoicingCompany(companyId: Ref<string>): UseInvoicingCompanyResult {
    const evolu = useInvoicingEvolu();
    const storesStore = useStoresStore();
    const company = ref<InvoicingCompanyRecord | null>(null);
    const loading = ref(true);

    const detailPromise = evolu.loadQuery(allCompaniesDetailQuery);
    const companyRows = useQuery(allCompaniesDetailQuery, { promise: detailPromise });

    void detailPromise.finally(() => {
        loading.value = false;
    });

    function syncCompanyFromRows(): void {
        const row = companyRows.value.find((c) => c.id === companyId.value);
        if (!row) {
            company.value = null;
            return;
        }
        company.value = evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
            const store = storesStore.stores.find((s) => s.id === storeId);
            return store
                ? { id: store.id, name: store.name, default_currency: store.default_currency }
                : undefined;
        });
    }

    watch([companyRows, companyId, () => storesStore.stores], syncCompanyFromRows, {
        immediate: true,
    });

    async function refresh(): Promise<void> {
        loading.value = true;
        try {
            await evolu.loadQuery(allCompaniesDetailQuery);
            syncCompanyFromRows();
        } finally {
            loading.value = false;
        }
    }

    function applyCompanyPatch(patch: Partial<InvoicingCompanyRecord>): void {
        if (!company.value) return;
        company.value = { ...company.value, ...patch };
    }

    onMounted(async () => {
        if (!storesStore.stores.length) {
            await storesStore.fetchStores();
        }
        syncCompanyFromRows();
    });

    return {
        localFirst: true,
        company,
        loading,
        refresh,
        applyCompanyPatch,
    };
}

export function useInvoicingCompany(companyId: Ref<string>): UseInvoicingCompanyResult {
    if (isInvoicingLocalFirst()) {
        return useLocalInvoicingCompany(companyId);
    }
    return useServerInvoicingCompany(companyId);
}

export function asCompanyId(id: string): CompanyId {
    return id as CompanyId;
}
