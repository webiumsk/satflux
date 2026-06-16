import { computed, ref } from "vue";
import { useQuery } from "@evolu/vue";
import {
    allCompaniesQuery,
    allDocumentsQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { isEvoluRelaySyncPending, waitForInvoicingRelayData } from "@/evolu/relaySyncWait";
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

/** Local-first company list (requires EvoluProvider). */
export function useLocalInvoicingCompanies(): UseInvoicingCompaniesResult {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const forbidden = ref(false);

    const companiesPromise = evolu.loadQuery(allCompaniesQuery);
    const documentsPromise = evolu.loadQuery(allDocumentsQuery);

    const companyRows = useQuery(allCompaniesQuery, { promise: companiesPromise });
    const documentRows = useQuery(allDocumentsQuery, { promise: documentsPromise });

    void (async () => {
        try {
            await Promise.all([companiesPromise, documentsPromise]);
            if (isEvoluRelaySyncPending()) {
                const companies = await evolu.loadQuery(allCompaniesQuery);
                if (companies.length === 0) {
                    await waitForInvoicingRelayData(evolu);
                    await Promise.all([
                        evolu.loadQuery(allCompaniesQuery),
                        evolu.loadQuery(allDocumentsQuery),
                    ]);
                }
            }
        } finally {
            loading.value = false;
        }
    })();

    const companies = computed(() =>
        mapEvoluCompanies(companyRows.value, documentRows.value),
    );

    async function refresh(): Promise<void> {
        loading.value = true;
        try {
            await Promise.all([
                evolu.loadQuery(allCompaniesQuery),
                evolu.loadQuery(allDocumentsQuery),
            ]);
        } finally {
            loading.value = false;
        }
    }

    return {
        localFirst: true,
        companies,
        loading,
        forbidden,
        refresh,
    };
}
