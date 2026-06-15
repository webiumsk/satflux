import type { Evolu } from "@evolu/common/local-first";
import type { DocumentAdvancedFilters } from "@/composables/useInvoicingDocumentListFilters";
import type { IssuePeriodState } from "@/composables/useInvoicingIssuePeriod";
import {
    cancelLocalDocument,
    deleteLocalDocument,
    markLocalDocumentPaid,
} from "./documentCrud";
import {
    filterLocalDocumentRows,
    type LocalDocumentFilterOptions,
} from "./documentListFilters";
import type { EvoluDocumentRow } from "./documentMap";
import type { CompanyId, InvoicingLocalSchema } from "./schema";

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
): boolean {
    return allDocuments.some(
        (other) =>
            other.sourceDocumentId === doc.id
            && other.status !== "cancelled",
    );
}

export function isLatestForCompanyType(
    doc: EvoluDocumentRow,
    allDocuments: EvoluDocumentRow[],
): boolean {
    const sameCompanyType = allDocuments.filter(
        (row) => row.companyId === doc.companyId && row.documentType === doc.documentType,
    );
    if (sameCompanyType.length === 0) {
        return true;
    }

    const sorted = [...sameCompanyType].sort((a, b) => {
        const aDate = a.issueDate || "";
        const bDate = b.issueDate || "";
        if (aDate !== bDate) return bDate.localeCompare(aDate);
        return String(b.id).localeCompare(String(a.id));
    });

    return sorted[0]?.id === doc.id;
}

export function canDeleteLocalDocument(
    doc: EvoluDocumentRow,
    allDocuments: EvoluDocumentRow[],
): boolean {
    if (hasBlockingRelations(doc, allDocuments)) {
        return false;
    }

    if (doc.status === "draft" || doc.status === "cancelled") {
        return true;
    }

    if (doc.status === "issued" || doc.status === "paid") {
        return isLatestForCompanyType(doc, allDocuments);
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

export function bulkDeleteLocal(
    evolu: Evolu<InvoicingLocalSchema>,
    rows: EvoluDocumentRow[],
    allDocuments: EvoluDocumentRow[],
): BulkResult {
    let processed = 0;
    let skipped = 0;

    for (const row of rows) {
        if (!canDeleteLocalDocument(row, allDocuments)) {
            skipped++;
            continue;
        }
        deleteLocalDocument(evolu, row.id);
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
