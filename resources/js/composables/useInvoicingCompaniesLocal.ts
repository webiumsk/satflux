import { computed, ref } from "vue";
import { useQuery } from "@evolu/vue";
import {
    allCompaniesQuery,
    allDocumentsQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { loadEvoluQueryWithTimeout } from "@/evolu/queryLoad";
import { isEvoluRelaySyncPending, waitForInvoicingRelaySync } from "@/evolu/relaySyncWait";
import type { CompanyId } from "@/evolu/schema";
import type { InvoicingCompanyListItem, UseInvoicingCompaniesResult } from "./useInvoicingCompanies";

function mapEvoluCompanies(
    rows: ReadonlyArray<{ id: CompanyId; legalName: string; tradeName: string | null }>,
    documentRows: ReadonlyArray<{ companyId: CompanyId; status: string }>,
): InvoicingCompanyListItem[] {
    return rows.map((row) => ({
        id: row.id,
        legal_name: row.legalName,
        trade_name: row.tradeName,
        documents_count: documentRows.filter(
            (doc) => doc.companyId === row.id && doc.status === "issued",
        ).length,
    }));
}

async function loadCompanyQueries(evolu: ReturnType<typeof useInvoicingEvolu>): Promise<void> {
    await Promise.all([
        loadEvoluQueryWithTimeout(evolu, allCompaniesQuery),
        loadEvoluQueryWithTimeout(evolu, allDocumentsQuery),
    ]);
}

/** Local-first company list (requires EvoluProvider). */
export function useLocalInvoicingCompanies(): UseInvoicingCompaniesResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const forbidden = ref(false);
    const loadError = ref(false);

    const companiesPromise = loadEvoluQueryWithTimeout(evolu, allCompaniesQuery);
    const documentsPromise = loadEvoluQueryWithTimeout(evolu, allDocumentsQuery);

    const companyRows = useQuery(allCompaniesQuery, { promise: companiesPromise });
    const documentRows = useQuery(allDocumentsQuery, { promise: documentsPromise });

    void (async () => {
        try {
            await Promise.all([companiesPromise, documentsPromise]);
            if (isEvoluRelaySyncPending()) {
                await waitForInvoicingRelaySync(evolu);
                await loadCompanyQueries(evolu);
            }
        } catch {
            loadError.value = true;
        } finally {
            loading.value = false;
        }
    })();

    const companies = computed(() =>
        mapEvoluCompanies(companyRows.value, documentRows.value),
    );

    async function refresh(): Promise<void> {
        loading.value = true;
        loadError.value = false;
        try {
            await loadCompanyQueries(evolu);
        } catch {
            loadError.value = true;
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: true,
        companies,
        loading,
        forbidden,
        loadError,
        refresh,
    };
}
