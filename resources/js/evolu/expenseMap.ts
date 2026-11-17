import type { ExpenseListRow } from "@/composables/useExpenseRowMeta";
import { expenseOverdueDays } from "@/composables/useExpenseRowMeta";
import {
    attachmentRowToApi,
    type EvoluExpenseAttachmentRow,
} from "./expenseAttachmentCrud";
import type { CompanyId, ExpenseId } from "./schema";
import { expenseFilterCents, validExpenseListFilters, type ExpenseListFilterOptions } from "./expenseListFilters";
export type { ExpenseListFilterOptions } from "./expenseListFilters";

export type EvoluExpenseRow = {
    id: ExpenseId;
    bankPaymentClaimJson?: string | null;
    bankMatchConflict?: number | boolean;
    companyId: CompanyId;
    status: string;
    internalNumber: string;
    externalNumber: string | null;
    title: string | null;
    variableSymbol: string | null;
    constantSymbol: string | null;
    specificSymbol: string | null;
    issueDate: string | null;
    deliveryDate: string | null;
    dueDate: string | null;
    paidAt: string | null;
    cancelledAt: string | null;
    total: string | null;
    currency: string | null;
    internalNote: string | null;
};

export type ExpenseApiRow = ExpenseListRow & {
    bank_match_claim?: string | null;
    cancelled_at?: string | null;
    attachments?: unknown[];
    attachments_count?: number;
    attachment_path?: string | null;
    original_filename?: string | null;
};

/** Map an expense detail and attachments while retaining the opaque local claim revision for explicit recovery. */
export function evoluExpenseToApi(
    row: EvoluExpenseRow,
    attachments: EvoluExpenseAttachmentRow[] = [],
): ExpenseApiRow {
    const today = localDateString();
    const isOverdue =
        row.status === "recorded"
        && Boolean(row.dueDate)
        && row.dueDate! < today
        && expenseOverdueDays(row.dueDate) !== null;

    const apiAttachments = attachments.map(attachmentRowToApi);

    return {
        id: row.id,
        bank_match_conflict: Boolean(row.bankMatchConflict),
        bank_match_claim: row.bankPaymentClaimJson ?? null,
        internal_number: row.internalNumber,
        external_number: row.externalNumber,
        title: row.title,
        variable_symbol: row.variableSymbol,
        constant_symbol: row.constantSymbol,
        specific_symbol: row.specificSymbol,
        issue_date: row.issueDate || "",
        delivery_date: row.deliveryDate,
        due_date: row.dueDate,
        paid_at: row.paidAt,
        total: row.total || "0",
        currency: row.currency || "EUR",
        status: row.status,
        internal_note: row.internalNote,
        cancelled_at: row.cancelledAt,
        is_overdue: isOverdue,
        has_attachment: apiAttachments.length > 0,
        attachments: apiAttachments,
        attachments_count: apiAttachments.length,
    };
}

export function evoluExpenseToListRow(
    row: EvoluExpenseRow,
    attachments: EvoluExpenseAttachmentRow[] = [],
): ExpenseListRow {
    const api = evoluExpenseToApi(row, attachments);
    return {
        id: api.id,
        bank_match_conflict: Boolean(api.bank_match_conflict),
        internal_number: api.internal_number,
        external_number: api.external_number,
        variable_symbol: api.variable_symbol,
        title: api.title,
        issue_date: api.issue_date,
        delivery_date: api.delivery_date,
        due_date: api.due_date,
        paid_at: api.paid_at,
        total: api.total,
        currency: api.currency,
        status: api.status,
        internal_note: api.internal_note,
        is_overdue: api.is_overdue,
        has_attachment: api.has_attachment,
    };
}

function localDateString(date = new Date()): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

export function filterLocalExpenses(
    rows: EvoluExpenseRow[],
    options: ExpenseListFilterOptions,
): EvoluExpenseRow[] {
    const overdueBefore = options.overdue_before ?? localDateString();
    if (!validExpenseListFilters(options)) return [];
    let result = rows.filter((row) => row.status !== "cancelled");

    if (options.issue_from || options.issue_to) {
        result = result.filter((row) => Boolean(row.issueDate)
            && (!options.issue_from || row.issueDate! >= options.issue_from)
            && (!options.issue_to || row.issueDate! <= options.issue_to));
    } else if (options.year) {
        const yearPrefix = String(options.year);
        result = result.filter((row) => row.issueDate?.startsWith(yearPrefix));
    }

    const filter = options.filter || "all";
    if (filter === "paid") {
        result = result.filter((row) => row.status === "paid");
    } else if (filter === "unpaid") {
        result = result.filter((row) => row.status === "recorded");
    } else if (filter === "overdue") {
        result = result.filter(
            (row) =>
                row.status === "recorded"
                && Boolean(row.dueDate)
                && row.dueDate! < overdueBefore,
        );
    }

    if (options.due === "overdue") {
        result = result.filter((row) => row.status === "recorded" && Boolean(row.dueDate) && row.dueDate! < overdueBefore);
    } else if (options.due === "custom") {
        result = result.filter((row) => Boolean(row.dueDate)
            && (!options.due_from || row.dueDate! >= options.due_from)
            && (!options.due_to || row.dueDate! <= options.due_to));
    }
    const min = options.amount_min === undefined || options.amount_min === "" ? null : expenseFilterCents(options.amount_min);
    const max = options.amount_max === undefined || options.amount_max === "" ? null : expenseFilterCents(options.amount_max);
    if (min !== null || max !== null) {
        result = result.filter((row) => {
            const total = expenseFilterCents(row.total);
            return total !== null && (min === null || total >= min) && (max === null || total <= max);
        });
    }
    const search = options.search?.trim().toLowerCase();
    if (search) {
        result = result.filter((row) => [row.internalNumber, row.externalNumber, row.title, row.variableSymbol, row.internalNote]
            .some((value) => value?.toLowerCase().includes(search)));
    }

    return result.sort((a, b) => {
        const aDate = a.issueDate || "";
        const bDate = b.issueDate || "";
        if (aDate !== bDate) return bDate.localeCompare(aDate);
        return b.internalNumber.localeCompare(a.internalNumber);
    });
}
