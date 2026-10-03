import type { Evolu } from "@evolu/common/local-first";
import type { DocumentAdvancedFilters } from "@/composables/useInvoicingDocumentListFilters";
import type { IssuePeriodState } from "@/composables/useInvoicingIssuePeriod";
import {
    cancelLocalDocument,
    cancelLocalDocumentAsync,
    deleteLocalDocument,
    markLocalDocumentPaid,
} from "./documentCrud";
import { resolveDefaultSeries, syncNumberSeriesCounterFromDocuments } from "./numberSeriesCrud";
import { documentNumberSortKey } from "./numberSeriesFormat";
import { reverseDocumentStockOnCancelAsync } from "./documentStockMovement";
import {
    filterLocalDocumentRows,
    type LocalDocumentFilterOptions,
} from "./documentListFilters";
import type { EvoluDocumentRow } from "./documentMap";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import type { CompanyId, DocumentId, DocumentType, InvoicingLocalSchema } from "./schema";

export type BulkResult = {
    processed: number;
    skipped: number;
};

export type ResolveBulkTargetsOptions = LocalDocumentFilterOptions & {
    companyId: CompanyId;
    selectAll: boolean;
    selectedIds: Iterable<string>;
    allDocuments: EvoluDocumentRow[];
};

export type LocalDocumentDeletionPolicy = {
    jurisdictionByCompanyId?: ReadonlyMap<string, string | null | undefined>;
    /** Documents with a bank transaction match - deleting orphans the match. */
    bankMatchedDocumentIds?: ReadonlySet<string>;
    /** Number series - "latest" is decided by the number, not the date. */
    allSeries?: readonly EvoluNumberSeriesRow[];
};

/** One builder for every deletion guard (list, detail, bulk, single delete). */
export function buildLocalDeletionPolicy(
    companies: ReadonlyArray<{ id: unknown; jurisdiction?: unknown }>,
    allSeries: readonly EvoluNumberSeriesRow[] = [],
    bankMatches: ReadonlyArray<{ businessDocumentId?: unknown }> = [],
): LocalDocumentDeletionPolicy {
    return {
        jurisdictionByCompanyId: new Map(
            companies.map((row) => [String(row.id), String(row.jurisdiction ?? "")]),
        ),
        bankMatchedDocumentIds: new Set(
            bankMatches
                .map((row) => (row.businessDocumentId ? String(row.businessDocumentId) : ""))
                .filter(Boolean),
        ),
        allSeries,
    };
}

/** Issued, paid or cancelled with a number - it occupies a slot of the sequence. */
export function holdsSequenceNumber(doc: EvoluDocumentRow): boolean {
    return doc.status !== "draft" && Boolean(doc.number);
}

export function resolveBulkTargets(options: ResolveBulkTargetsOptions): EvoluDocumentRow[] {
    const { companyId, selectAll, selectedIds, allDocuments, ...filterOpts } = options;

    const companyDocs = allDocuments.filter((row) => row.companyId === companyId);

    if (selectAll) {
        return filterLocalDocumentRows(companyDocs, filterOpts);
    }

    const idSet = new Set(selectedIds);
    return companyDocs.filter((row) => idSet.has(row.id));
}

export function hasBlockingRelations(
    doc: EvoluDocumentRow,
    allDocuments: EvoluDocumentRow[],
    policy: LocalDocumentDeletionPolicy = {},
): boolean {
    // A bank-matched document is evidence of a received payment: deleting
    // it would leave the transaction "matched" to nothing and release the
    // number for reuse (the server refuses the same).
    if (policy.bankMatchedDocumentIds?.has(String(doc.id))) {
        return true;
    }
    return allDocuments.some(
        (other) =>
            other.sourceDocumentId === doc.id
            && other.status !== "cancelled",
    );
}

/**
 * True when `doc` holds the top number of its company + type - the only
 * numbered document that can be deleted without leaving a hole. Decided by
 * the number parsed with the series format, not by the issue date and a
 * random id: same-day invoices made the real last one undeletable half of
 * the time, a draft dated today blocked it, and an edited issue date let an
 * older number pass.
 */
export function isLatestForCompanyType(
    doc: EvoluDocumentRow,
    allDocuments: EvoluDocumentRow[],
    allSeries: readonly EvoluNumberSeriesRow[] = [],
): boolean {
    const numbered = allDocuments.filter(
        (row) =>
            row.companyId === doc.companyId
            && row.documentType === doc.documentType
            && holdsSequenceNumber(row),
    );
    if (numbered.length === 0) {
        return true;
    }

    const format = resolveDefaultSeries([...allSeries], doc.companyId, doc.documentType as DocumentType)?.format ?? null;
    const keyOf = (row: EvoluDocumentRow) => documentNumberSortKey(format, row.number, row.issueDate);
    const sorted = [...numbered].sort((a, b) => {
        const cmp = keyOf(b).localeCompare(keyOf(a));
        return cmp !== 0 ? cmp : String(b.id).localeCompare(String(a.id));
    });

    return sorted[0]?.id === doc.id;
}

export function canDeleteLocalDocument(
    doc: EvoluDocumentRow,
    allDocuments: EvoluDocumentRow[],
    policy: LocalDocumentDeletionPolicy = {},
): boolean {
    if (hasBlockingRelations(doc, allDocuments, policy)) {
        return false;
    }

    if (
        doc.status !== "draft"
        && policy.jurisdictionByCompanyId?.get(String(doc.companyId)) === "eu_de"
    ) {
        return false;
    }

    if (doc.status === "draft") {
        return true;
    }

    // A cancelled document without a number never took a slot. With one it
    // is treated like an issued one: deleting it frees the number, so only
    // the top of the sequence may go - otherwise a hole remains (and a
    // cancelled top number used to block deleting the issued one below it
    // forever, because its reservation was never released).
    if (doc.status === "cancelled" && !doc.number) {
        return true;
    }

    if (doc.status === "issued" || doc.status === "paid" || doc.status === "cancelled") {
        return isLatestForCompanyType(doc, allDocuments, policy.allSeries ?? []);
    }

    return false;
}

export function canCancelLocalDocument(doc: EvoluDocumentRow): boolean {
    return doc.status === "issued" || doc.status === "paid";
}

export function bulkMarkPaidLocal(
    evolu: Evolu<InvoicingLocalSchema>,
    rows: EvoluDocumentRow[],
    allDocuments: EvoluDocumentRow[],
): BulkResult {
    let processed = 0;
    let skipped = 0;

    for (const row of rows) {
        if (row.status !== "issued") {
            skipped++;
            continue;
        }
        markLocalDocumentPaid(evolu, row.id, allDocuments);
        processed++;
    }

    return { processed, skipped };
}

/**
 * Newest-number-first order (per company + type) so a selected contiguous
 * tail of the sequence can be deleted in one bulk run: after the newest
 * falls, the next one becomes the latest and passes the only-latest guard.
 */
export function sortRowsForSequentialDelete(
    rows: EvoluDocumentRow[],
    allSeries: readonly EvoluNumberSeriesRow[] = [],
): EvoluDocumentRow[] {
    const keyOf = (row: EvoluDocumentRow) => {
        const format = resolveDefaultSeries([...allSeries], row.companyId, row.documentType as DocumentType)?.format ?? null;
        return documentNumberSortKey(format, row.number, row.issueDate);
    };
    return [...rows].sort((a, b) => {
        const cmp = keyOf(b).localeCompare(keyOf(a));
        return cmp !== 0 ? cmp : String(b.id).localeCompare(String(a.id));
    });
}

export async function bulkDeleteLocal(
    evolu: Evolu<InvoicingLocalSchema>,
    rows: EvoluDocumentRow[],
    allDocuments: EvoluDocumentRow[],
    allSeries?: EvoluNumberSeriesRow[],
    policy: LocalDocumentDeletionPolicy = {},
): Promise<BulkResult> {
    let processed = 0;
    let skipped = 0;
    const deletedIds = new Set<DocumentId>();
    const numberedDeletes: EvoluDocumentRow[] = [];
    // The guard evaluates "is latest" against the documents that still
    // exist - shrink the working set as rows fall so a chain of the newest
    // invoices deletes in one pass (gapless numbering, P3).
    let remainingDocs = allDocuments;
    const effectivePolicy: LocalDocumentDeletionPolicy = {
        ...policy,
        allSeries: policy.allSeries ?? allSeries ?? [],
    };
    const { releaseIssuedNumber } = await import("./numberReleaseBridge");

    for (const row of sortRowsForSequentialDelete(rows, effectivePolicy.allSeries)) {
        if (!canDeleteLocalDocument(row, remainingDocs, effectivePolicy)) {
            skipped++;
            continue;
        }

        if (holdsSequenceNumber(row)) {
            const release = await releaseIssuedNumber(
                row.companyId,
                String(row.documentType),
                String(row.number),
                String(row.id),
            );
            if (!release.ok) {
                skipped++;
                continue;
            }
        }

        // Return the stock first - the reversal reads the live document.
        // Idempotent: a cancelled document was already reversed.
        if (row.status === "issued" || row.status === "paid") {
            await reverseDocumentStockOnCancelAsync(evolu, row.id);
        }

        const result = deleteLocalDocument(evolu, row.id);
        if (!result.ok) {
            skipped++;
            continue;
        }
        deletedIds.add(row.id);
        remainingDocs = remainingDocs.filter((docRow) => docRow.id !== row.id);
        if (holdsSequenceNumber(row)) {
            numberedDeletes.push(row);
        }
        processed++;
    }

    if (allSeries && numberedDeletes.length > 0) {
        const remaining = allDocuments.filter((row) => !deletedIds.has(row.id));
        const releasedByKey = new Map<string, EvoluDocumentRow[]>();
        for (const row of numberedDeletes) {
            const key = `${row.companyId}:${row.documentType}`;
            releasedByKey.set(key, [...(releasedByKey.get(key) ?? []), row]);
        }
        for (const released of releasedByKey.values()) {
            const first = released[0];
            syncNumberSeriesCounterFromDocuments(
                evolu,
                first.companyId,
                first.documentType as DocumentType,
                remaining,
                allSeries,
                { releasedNumbers: released.map((row) => String(row.number)) },
            );
        }
    }

    return { processed, skipped };
}

export async function bulkCancelLocalAsync(
    evolu: Evolu<InvoicingLocalSchema>,
    rows: EvoluDocumentRow[],
    allDocuments: EvoluDocumentRow[],
): Promise<BulkResult> {
    let processed = 0;
    let skipped = 0;

    for (const row of rows) {
        if (!canCancelLocalDocument(row)) {
            skipped++;
            continue;
        }
        await cancelLocalDocumentAsync(evolu, row.id, allDocuments);
        processed++;
    }

    return { processed, skipped };
}

export function bulkCancelLocal(
    evolu: Evolu<InvoicingLocalSchema>,
    rows: EvoluDocumentRow[],
): BulkResult {
    let processed = 0;
    let skipped = 0;

    for (const row of rows) {
        if (!canCancelLocalDocument(row)) {
            skipped++;
            continue;
        }
        cancelLocalDocument(evolu, row.id);
        processed++;
    }

    return { processed, skipped };
}

export type CsvExportRow = {
    number: string;
    status: string;
    client: string;
    total: string;
    currency: string;
    issueDate: string;
    dueDate: string;
    variableSymbol: string;
};

export function buildDocumentsCsvBlob(rows: CsvExportRow[]): Blob {
    const header = [
        "Number",
        "Status",
        "Client",
        "Total",
        "Currency",
        "Issue date",
        "Due date",
        "Variable symbol",
    ];

    const escape = (value: string) => {
        const v = value.replace(/"/g, '""');
        return `"${v}"`;
    };

    const lines = [
        header.map(escape).join(","),
        ...rows.map((row) =>
            [
                row.number,
                row.status,
                row.client,
                row.total,
                row.currency,
                row.issueDate,
                row.dueDate,
                row.variableSymbol,
            ]
                .map((cell) => escape(String(cell ?? "")))
                .join(","),
        ),
    ];

    const bom = "\uFEFF";
    return new Blob([bom + lines.join("\r\n")], { type: "text/csv;charset=utf-8" });
}

export function downloadCsvBlob(blob: Blob, filename: string): void {
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
}

export function localBulkFilterOptions(
    nav: { kind: string; apiType?: string },
    statusFilter: string,
    issuePeriod: IssuePeriodState,
    advanced: DocumentAdvancedFilters,
    contactNameById?: Map<string, string>,
): LocalDocumentFilterOptions {
    return {
        apiType: nav.kind === "drafts" ? undefined : nav.apiType,
        statusFilter: nav.kind === "drafts" ? "draft" : statusFilter,
        issuePeriod,
        advanced,
        contactNameById,
    };
}
