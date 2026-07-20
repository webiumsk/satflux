import { computed, ref, type Ref } from 'vue';
import { useQuery } from '@evolu/vue';
import {
    allDocumentLinesQuery,
    allDocumentsQuery,
    useInvoicingEvolu,
} from '@/evolu/client';
import { toAppRows } from '@/evolu/queryLoad';
import type { EvoluDocumentLineRow, EvoluDocumentRow } from '@/evolu/documentMap';
import type { CompanyId } from '@/evolu/schema';
import {
    type IssuePeriodState,
    resolveIssuePeriodRange,
} from '@/composables/useInvoicingIssuePeriod';
import { buildVatSummary, type VatSummary } from '@/evolu/vatReport';

/** Open-ended bounds so a missing range edge never excludes documents. */
const MIN_DATE = '0000-01-01';
const MAX_DATE = '9999-12-31';

/**
 * Local-first VAT summary for one company over a selectable issue period.
 * Loads documents + lines from Evolu once and recomputes the summary
 * reactively as the period changes (see evolu/vatReport for the maths).
 */
export function useVatReport(companyId: Ref<string>) {
    const evolu = useInvoicingEvolu();
    const loading = ref(true);

    const documentsPromise = evolu.loadQuery(allDocumentsQuery);
    const linesPromise = evolu.loadQuery(allDocumentLinesQuery);
    const documentRows = useQuery(allDocumentsQuery, { promise: documentsPromise });
    const lineRows = useQuery(allDocumentLinesQuery, { promise: linesPromise });

    void Promise.all([documentsPromise, linesPromise]).finally(() => {
        loading.value = false;
    });

    // VAT is filed per calendar period; default to the current year.
    const period = ref<IssuePeriodState>({
        preset: 'this_year',
        customFrom: '',
        customTo: '',
    });

    const summary = computed<VatSummary>(() => {
        const range = resolveIssuePeriodRange(period.value);
        const from = range.from ?? MIN_DATE;
        const to = range.to ?? MAX_DATE;

        const companyDocuments = toAppRows<EvoluDocumentRow>(
            documentRows.value.filter((row) => row.companyId === (companyId.value as CompanyId)),
        );
        const documentIds = new Set<string>(companyDocuments.map((doc) => doc.id));
        const companyLines = toAppRows<EvoluDocumentLineRow>(lineRows.value).filter((row) =>
            documentIds.has(row.documentId),
        );

        return buildVatSummary(companyDocuments, companyLines, { from, to });
    });

    return { loading, period, summary };
}
