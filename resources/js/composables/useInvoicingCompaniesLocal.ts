import { computed, ref } from "vue";
import { useQuery } from "@evolu/vue";
import {
    allCompaniesQuery,
    allDocumentsQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { loadEvoluQueryWithTimeout } from "@/evolu/queryLoad";
import {
    isEvoluRelaySyncPending,
    pullInvoicingFromRelay,
    waitForInvoicingRelaySync,
} from "@/evolu/relaySyncWait";
import type { CompanyId } from "@/evolu/schema";
import type { InvoicingCompanyListItem, UseInvoicingCompaniesResult } from "./useInvoicingCompanies";
import { countCompanyInvoicesForList } from "@/evolu/companyInvoiceCount";

function mapEvoluCompanies(
    rows: ReadonlyArray<{
        id: CompanyId;
        legalName: string;
        tradeName: string | null;
        registrationNumber?: string | null;
        logoDataUrl?: string | null;
    }>,
    documentRows: ReadonlyArray<{ companyId: CompanyId; documentType: string; status: string }>,
): InvoicingCompanyListItem[] {
    return rows.map((row) => ({
        id: row.id,
        legal_name: row.legalName,
        trade_name: row.tradeName,
        registration_number: row.registrationNumber ?? null,
        documents_count: countCompanyInvoicesForList(documentRows, row.id),
        logo_url: row.logoDataUrl ?? null,
        has_logo: Boolean(row.logoDataUrl),
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
            } else {
                await pullInvoicingFromRelay(evolu, { timeoutMs: 20_000 });
            }
            await loadCompanyQueries(evolu);
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
            if (isEvoluRelaySyncPending()) {
                await waitForInvoicingRelaySync(evolu);
            } else {
                await pullInvoicingFromRelay(evolu, { timeoutMs: 15_000 });
            }
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
