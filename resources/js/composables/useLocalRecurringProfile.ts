import { computed, type Ref } from "vue";
import { useQuery } from "@evolu/vue";
import {
    allCompaniesDetailQuery,
    allContactsQuery,
    allDocumentLinesQuery,
    allDocumentsQuery,
    allNumberSeriesQuery,
    allRecurringProfileLinesQuery,
    allRecurringProfilesQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { evoluCompanyToApi, type EvoluCompanyRow } from "@/evolu/companyMap";
import { evoluContactToApi } from "@/evolu/contactMap";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "@/evolu/documentMap";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import {
    evoluRecurringProfileToApi,
    type EvoluRecurringProfileLineRow,
    type EvoluRecurringProfileRow,
    type RecurringProfileApiRow,
} from "@/evolu/recurringMap";
import {
    deleteLocalRecurringProfile,
    generateLocalRecurringDocument,
    saveLocalRecurringProfile,
    type RecurringProfileSavePayload,
} from "@/evolu/recurringCrud";
import type { CompanyId, RecurringProfileId } from "@/evolu/schema";
import { useStoresStore } from "@/store/stores";
import type { DocumentLinePayload } from "@/evolu/documentCrud";

export function useLocalRecurringProfileSupport(companyId: Ref<string>) {
    const evolu = useInvoicingEvolu();
    const storesStore = useStoresStore();

    const companyRows = useQuery(allCompaniesDetailQuery);
    const contactRows = useQuery(allContactsQuery);
    const profileRows = useQuery(allRecurringProfilesQuery);
    const profileLineRows = useQuery(allRecurringProfileLinesQuery);
    const documentRows = useQuery(allDocumentsQuery);
    const documentLineRows = useQuery(allDocumentLinesQuery);
    const seriesRows = useQuery(allNumberSeriesQuery);

    async function refreshAll(): Promise<void> {
        await Promise.all([
            evolu.loadQuery(allCompaniesDetailQuery),
            evolu.loadQuery(allContactsQuery),
            evolu.loadQuery(allRecurringProfilesQuery),
            evolu.loadQuery(allRecurringProfileLinesQuery),
            evolu.loadQuery(allDocumentsQuery),
            evolu.loadQuery(allDocumentLinesQuery),
            evolu.loadQuery(allNumberSeriesQuery),
        ]);
    }

    function companyApi() {
        const row = companyRows.value.find((c) => c.id === companyId.value) as EvoluCompanyRow | undefined;
        if (!row) return null;
        return evoluCompanyToApi(row, (storeId) => {
            const store = storesStore.stores.find((s) => s.id === storeId);
            return store
                ? { id: store.id, name: store.name, default_currency: store.default_currency }
                : undefined;
        });
    }

    function contactsForCompany() {
        return contactRows.value
            .filter((c) => c.companyId === companyId.value)
            .map((c) => evoluContactToApi(c));
    }

    function profileApi(profileId: string): RecurringProfileApiRow | null {
        const row = profileRows.value.find((p) => p.id === profileId) as EvoluRecurringProfileRow | undefined;
        if (!row) return null;
        const contact = row.contactId
            ? contactsForCompany().find((c) => c.id === row.contactId) ?? null
            : null;
        return evoluRecurringProfileToApi(
            row,
            profileLineRows.value as EvoluRecurringProfileLineRow[],
            contact ? { id: contact.id, name: contact.name } : null,
        );
    }

    function saveProfile(
        payload: RecurringProfileSavePayload,
        options: {
            profileId?: RecurringProfileId;
            defaultVat: number;
            lineTaxApplies: (line: RecurringProfileSavePayload["lines"][0]) => boolean;
            lineTaxRate: (line: RecurringProfileSavePayload["lines"][0]) => number;
        },
    ) {
        return saveLocalRecurringProfile(evolu, companyId.value as CompanyId, payload, {
            profileId: options.profileId,
            defaultVat: options.defaultVat,
            lineTaxApplies: options.lineTaxApplies,
            lineTaxRate: options.lineTaxRate,
            existingLines: profileLineRows.value as EvoluRecurringProfileLineRow[],
        });
    }

    function deleteProfile(profileId: RecurringProfileId) {
        return deleteLocalRecurringProfile(
            evolu,
            profileId,
            profileLineRows.value as EvoluRecurringProfileLineRow[],
        );
    }

    async function generateNow(
        profileId: RecurringProfileId,
        options: {
            defaultVat: number;
            lineTaxApplies: (line: DocumentLinePayload) => boolean;
            lineTaxRate: (line: DocumentLinePayload) => number;
        },
    ) {
        const companyRow = companyRows.value.find((c) => c.id === companyId.value) as EvoluCompanyRow | undefined;
        if (!companyRow) return { ok: false as const, error: "company" };
        return generateLocalRecurringDocument(
            evolu,
            profileId,
            companyRow,
            profileRows.value as EvoluRecurringProfileRow[],
            profileLineRows.value as EvoluRecurringProfileLineRow[],
            documentRows.value as EvoluDocumentRow[],
            documentLineRows.value as EvoluDocumentLineRow[],
            seriesRows.value as EvoluNumberSeriesRow[],
            options,
        );
    }

    const linkedStores = computed(() => companyApi()?.stores ?? []);

    return {
        evolu,
        refreshAll,
        companyApi,
        contactsForCompany,
        profileApi,
        saveProfile,
        deleteProfile,
        generateNow,
        linkedStores,
    };
}
