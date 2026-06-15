import type { DocumentAdvancedFilters } from "@/composables/useInvoicingDocumentListFilters";
import {
    resolveIssuePeriodRange,
    type IssuePeriodState,
} from "@/composables/useInvoicingIssuePeriod";
import type { EvoluDocumentRow } from "./documentMap";

export type LocalDocumentFilterOptions = {
    apiType?: string | undefined;
    statusFilter?: string;
    issuePeriod?: IssuePeriodState;
    advanced?: DocumentAdvancedFilters;
    contactNameById?: Map<string, string>;
    contactId?: string;
};

function resolveQuoteStatus(doc: EvoluDocumentRow): string | null {
    if (doc.documentType !== "quote") return null;
    const base = doc.quoteStatus || "pending";
    if (base !== "pending" || !doc.dueDate) return base;
    const due = new Date(`${doc.dueDate}T23:59:59`);
    if (!Number.isNaN(due.getTime()) && due < new Date()) {
        return "expired";
    }
    return base;
}

function applyQuoteFilter(rows: EvoluDocumentRow[], filter: string): EvoluDocumentRow[] {
    const today = new Date().toISOString().slice(0, 10);

    return rows.filter((row) => {
        if (row.status === "draft") return filter === "all";
        const qs = resolveQuoteStatus(row);
        switch (filter) {
            case "approved":
                return row.status === "issued" && row.quoteStatus === "approved";
            case "pending":
                return row.status === "issued"
                    && qs === "pending"
                    && (!row.dueDate || row.dueDate >= today);
            case "rejected":
                return row.status === "issued" && row.quoteStatus === "rejected";
            case "expired":
                return row.status === "issued" && qs === "expired";
            default:
                return true;
        }
    });
}

function applyStatusFilter(rows: EvoluDocumentRow[], filter: string | undefined): EvoluDocumentRow[] {
    if (!filter || filter === "all") return rows;
    if (filter === "draft") return rows.filter((row) => row.status === "draft");
    if (filter === "paid") return rows.filter((row) => row.status === "paid");
    if (filter === "unpaid") {
        return rows.filter((row) => row.status === "draft" || row.status === "issued");
    }
    if (filter === "overdue") {
        const now = new Date();
        return rows.filter(
            (row) =>
                row.status === "issued"
                && row.dueDate
                && new Date(`${row.dueDate}T23:59:59`) < now,
        );
    }
    return rows;
}

function applyIssuePeriod(rows: EvoluDocumentRow[], issuePeriod?: IssuePeriodState): EvoluDocumentRow[] {
    if (!issuePeriod) return rows;
    const range = resolveIssuePeriodRange(issuePeriod);
    if (!range.from && !range.to) return rows;

    return rows.filter((row) => {
        if (!row.issueDate) return false;
        if (range.from && row.issueDate < range.from) return false;
        if (range.to && row.issueDate > range.to) return false;
        return true;
    });
}

function applyAdvancedFilters(
    rows: EvoluDocumentRow[],
    advanced?: DocumentAdvancedFilters,
    contactNameById?: Map<string, string>,
): EvoluDocumentRow[] {
    if (!advanced) return rows;

    let filtered = rows;

    if (advanced.status !== "all") {
        filtered = filtered.filter((row) => row.status === advanced.status);
    }

    if (advanced.paid === "yes") {
        filtered = filtered.filter((row) => row.status === "paid");
    } else if (advanced.paid === "no") {
        filtered = filtered.filter((row) => row.status === "draft" || row.status === "issued");
    }

    if (advanced.due === "overdue") {
        const now = new Date();
        filtered = filtered.filter(
            (row) =>
                row.status === "issued"
                && row.dueDate
                && new Date(`${row.dueDate}T23:59:59`) < now,
        );
    }

    if (advanced.due === "custom") {
        if (advanced.dueFrom) {
            filtered = filtered.filter((row) => row.dueDate && row.dueDate >= advanced.dueFrom);
        }
        if (advanced.dueTo) {
            filtered = filtered.filter((row) => row.dueDate && row.dueDate <= advanced.dueTo);
        }
    }

    if (advanced.amountMin !== "") {
        const min = Number(advanced.amountMin);
        if (!Number.isNaN(min)) {
            filtered = filtered.filter((row) => Number(row.total ?? 0) >= min);
        }
    }

    if (advanced.amountMax !== "") {
        const max = Number(advanced.amountMax);
        if (!Number.isNaN(max)) {
            filtered = filtered.filter((row) => Number(row.total ?? 0) <= max);
        }
    }

    const term = advanced.search.trim().toLowerCase();
    if (term) {
        filtered = filtered.filter((row) => {
            const haystack = [
                row.number,
                row.title,
                row.variableSymbol,
                row.contactId ? contactNameById?.get(row.contactId) : null,
            ]
                .filter(Boolean)
                .join(" ")
                .toLowerCase();
            return haystack.includes(term);
        });
    }

    return filtered;
}

export function filterLocalDocumentRows(
    rows: EvoluDocumentRow[],
    options: LocalDocumentFilterOptions,
): EvoluDocumentRow[] {
    let filtered = rows;

    if (options.apiType) {
        filtered = filtered.filter((row) => row.documentType === options.apiType);
    }

    if (options.statusFilter === "draft") {
        filtered = filtered.filter((row) => row.status === "draft");
    } else if (options.apiType === "quote") {
        filtered = applyQuoteFilter(filtered, options.statusFilter || "all");
    } else {
        filtered = applyStatusFilter(filtered, options.statusFilter);
    }

    filtered = applyIssuePeriod(filtered, options.issuePeriod);
    filtered = applyAdvancedFilters(filtered, options.advanced, options.contactNameById);

    if (options.contactId) {
        filtered = filtered.filter((row) => row.contactId === options.contactId);
    }

    return filtered.sort((a, b) => {
        const aDate = a.issueDate || "";
        const bDate = b.issueDate || "";
        if (aDate !== bDate) return bDate.localeCompare(aDate);
        return String(b.number || "").localeCompare(String(a.number || ""));
    });
}
