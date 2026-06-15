import type { ExpenseListRow } from "@/composables/useExpenseRowMeta";
import { expenseOverdueDays } from "@/composables/useExpenseRowMeta";
import type { CompanyId, ExpenseId } from "./schema";

export type EvoluExpenseRow = {
    id: ExpenseId;
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
    cancelled_at?: string | null;
    attachments?: unknown[];
    attachments_count?: number;
    attachment_path?: string | null;
    original_filename?: string | null;
};

export function evoluExpenseToApi(row: EvoluExpenseRow): ExpenseApiRow {
    const today = localDateString();
    const isOverdue =
        row.status === "recorded"
        && Boolean(row.dueDate)
        && row.dueDate! < today
        && expenseOverdueDays(row.dueDate) !== null;

    return {
        id: row.id,
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
        has_attachment: false,
        attachments: [],
        attachments_count: 0,
    };
}

export function evoluExpenseToListRow(row: EvoluExpenseRow): ExpenseListRow {
    const api = evoluExpenseToApi(row);
    return {
        id: api.id,
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

export type ExpenseListFilterOptions = {
    filter?: string;
    year?: number;
};

export function filterLocalExpenses(
    rows: EvoluExpenseRow[],
    options: ExpenseListFilterOptions,
): EvoluExpenseRow[] {
    const today = localDateString();
    let result = rows.filter((row) => row.status !== "cancelled");

    if (options.year) {
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
                && row.dueDate! < today,
        );
    }

    return result.sort((a, b) => {
        const aDate = a.issueDate || "";
        const bDate = b.issueDate || "";
        if (aDate !== bDate) return bDate.localeCompare(aDate);
        return b.internalNumber.localeCompare(a.internalNumber);
    });
}
