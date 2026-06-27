import { computed, ref, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import type { DocumentAdvancedFilters } from "@/composables/useInvoicingDocumentListFilters";
import type { IssuePeriodState } from "@/composables/useInvoicingIssuePeriod";
import { allContactsQuery, allDocumentsQuery, useInvoicingEvolu } from "@/evolu/client";
import { evoluContactToApi } from "@/evolu/contactMap";
import { evoluDocumentToListRow, type EvoluDocumentRow } from "@/evolu/documentMap";
import { filterLocalDocumentRows } from "@/evolu/documentListFilters";
import type { CompanyId } from "@/evolu/schema";

export type LocalDocumentListRow = Record<string, unknown>;

export type LocalDocumentsRefreshOptions = {
    apiType?: string | undefined;
    statusFilter?: string;
    issuePeriod?: IssuePeriodState;
    advanced?: DocumentAdvancedFilters;
    contactId?: string;
};

function mapDocumentRow(
    row: EvoluDocumentRow,
    contactName?: string | null,
    allDocuments: EvoluDocumentRow[] = [],
): LocalDocumentListRow {
    return evoluDocumentToListRow(row, contactName, allDocuments);
}

export function useInvoicingDocumentsLocal(companyId: Ref<string>) {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);
    const filterOptions = ref<LocalDocumentsRefreshOptions>({});

    const documentsPromise = evolu.loadQuery(allDocumentsQuery);
    const contactsPromise = evolu.loadQuery(allContactsQuery);
    const documentRows = useQuery(allDocumentsQuery, { promise: documentsPromise });
    const contactRows = useQuery(allContactsQuery, { promise: contactsPromise });

    void Promise.all([documentsPromise, contactsPromise]).finally(() => {
        loading.value = false;
    });

    const contactNameById = computed(() => {
        const map = new Map<string, string>();
        for (const row of contactRows.value) {
            if (row.companyId !== companyId.value) continue;
            map.set(row.id, evoluContactToApi(row).name);
        }
        return map;
    });

    const filteredRows = computed(() => {
        const companyRows = documentRows.value.filter(
            (row) => row.companyId === (companyId.value as CompanyId),
        ) as EvoluDocumentRow[];

        return filterLocalDocumentRows(companyRows, {
            apiType: filterOptions.value.apiType,
            statusFilter: filterOptions.value.statusFilter,
            issuePeriod: filterOptions.value.issuePeriod,
            advanced: filterOptions.value.advanced,
            contactNameById: contactNameById.value,
            contactId: filterOptions.value.contactId,
        });
    });

    const documents = computed(() => {
        const all = documentRows.value as EvoluDocumentRow[];
        return filteredRows.value.map((row) =>
            mapDocumentRow(
                row,
                row.contactId ? contactNameById.value.get(row.contactId) : null,
                all,
            ),
        );
    });

    async function refresh(options?: LocalDocumentsRefreshOptions): Promise<void> {
        filterOptions.value = options ?? {};
        loading.value = true;
        try {
            await Promise.all([
                evolu.loadQuery(allDocumentsQuery),
                evolu.loadQuery(allContactsQuery),
            ]);
        } finally {
            loading.value = false;
        }
    }

    return {
        loading,
        documents,
        filteredRows,
        documentRows,
        contactRows,
        refresh,
        evolu,
    };
}
