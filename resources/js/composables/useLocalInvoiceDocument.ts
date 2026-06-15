import { computed } from "vue";
import { useQuery } from "@evolu/vue";
import {
    allCompaniesDetailQuery,
    allContactsQuery,
    allDocumentEventsQuery,
    allDocumentLinesQuery,
    allDocumentsQuery,
    allNumberSeriesQuery,
    useInvoicingEvolu,
} from "@/evolu/client";
import { evoluCompanyToApi, type EvoluCompanyRow } from "@/evolu/companyMap";
import { evoluContactToApi } from "@/evolu/contactMap";
import {
    approveLocalQuote,
    cancelLocalDocument,
    createLocalCreditNoteFromInvoice,
    createLocalFinalInvoiceFromProforma,
    createLocalInvoiceFromQuote,
    deleteLocalDocument,
    getLocalDocumentApi,
    issueLocalDocument,
    markLocalDocumentPaid,
    markLocalDocumentEmailSent,
    payloadFromApiDocument,
    rejectLocalQuote,
    saveLocalDocument,
    unmarkLocalDocumentPaid,
    type DocumentLinePayload,
    type DocumentSavePayload,
} from "@/evolu/documentCrud";
import { previewNextDocumentNumber } from "@/evolu/documentNumber";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import type { EvoluDocumentEventRow } from "@/evolu/documentEventLog";
import type { CompanyId, DocumentId } from "@/evolu/schema";
import { useStoresStore } from "@/store/stores";

/** Evolu-backed document operations (requires EvoluProvider). */
export function useLocalInvoiceDocumentSupport() {
    const evolu = useInvoicingEvolu();
    const storesStore = useStoresStore();

    const companyRows = useQuery(allCompaniesDetailQuery);
    const contactRows = useQuery(allContactsQuery);
    const documentRows = useQuery(allDocumentsQuery);
    const lineRows = useQuery(allDocumentLinesQuery);
    const seriesRows = useQuery(allNumberSeriesQuery);
    const documentEventRows = useQuery(allDocumentEventsQuery) as { value: EvoluDocumentEventRow[] };

    async function refreshAll(): Promise<void> {
        await Promise.all([
            evolu.loadQuery(allCompaniesDetailQuery),
            evolu.loadQuery(allContactsQuery),
            evolu.loadQuery(allDocumentsQuery),
            evolu.loadQuery(allDocumentLinesQuery),
            evolu.loadQuery(allNumberSeriesQuery),
            evolu.loadQuery(allDocumentEventsQuery),
        ]);
    }

    function companyApi(companyId: string) {
        const row = companyRows.value.find((c) => c.id === companyId);
        if (!row) return null;
        return evoluCompanyToApi(row as EvoluCompanyRow, (storeId) => {
            const store = storesStore.stores.find((s) => s.id === storeId);
            return store
                ? { id: store.id, name: store.name, default_currency: store.default_currency }
                : undefined;
        });
    }

    function contactsForCompany(companyId: string) {
        return contactRows.value
            .filter((c) => c.companyId === companyId)
            .map((c) => evoluContactToApi(c));
    }

    function previewNumber(companyId: CompanyId, documentType: string) {
        return previewNextDocumentNumber(
            documentRows.value as Parameters<typeof previewNextDocumentNumber>[0],
            companyId,
            documentType as Parameters<typeof previewNextDocumentNumber>[2],
            null,
            seriesRows.value as EvoluNumberSeriesRow[],
        );
    }

    function issueLocalDocumentWrapped(
        evoluInst: typeof evolu,
        documentId: DocumentId,
        company: EvoluCompanyRow,
        allDocuments: Parameters<typeof issueLocalDocument>[3],
    ) {
        return issueLocalDocument(
            evoluInst,
            documentId,
            company,
            allDocuments,
            seriesRows.value as EvoluNumberSeriesRow[],
        );
    }

    function documentApi(documentId: DocumentId) {
        return getLocalDocumentApi(
            documentId,
            documentRows.value as Parameters<typeof getLocalDocumentApi>[1],
            lineRows.value as Parameters<typeof getLocalDocumentApi>[2],
        );
    }

    function saveOptions(
        defaultVat: number,
        lineTaxApplies: (line: DocumentLinePayload) => boolean,
        lineTaxRate: (line: DocumentLinePayload) => number,
    ) {
        return {
            defaultVat,
            lineTaxApplies,
            lineTaxRate,
            existingLines: lineRows.value as Parameters<typeof saveLocalDocument>[3]["existingLines"],
        };
    }

    const documentsForCompany = computed(() => documentRows.value);

    return {
        evolu,
        companyRows,
        contactRows,
        documentRows,
        lineRows,
        seriesRows,
        documentEventRows,
        documentsForCompany,
        refreshAll,
        companyApi,
        contactsForCompany,
        previewNumber,
        documentApi,
        saveOptions,
        saveLocalDocument,
        issueLocalDocument: issueLocalDocumentWrapped,
        deleteLocalDocument,
        cancelLocalDocument,
        markLocalDocumentPaid,
        unmarkLocalDocumentPaid,
        markLocalDocumentEmailSent,
        approveLocalQuote,
        rejectLocalQuote,
        createLocalInvoiceFromQuote,
        createLocalFinalInvoiceFromProforma,
        createLocalCreditNoteFromInvoice,
        payloadFromApiDocument,
    };
}
