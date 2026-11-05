import { bankPaymentCurrency, positiveBankAmount } from "./bankPaymentValues";
import type { EvoluExpenseRow } from "./expenseMap";

export type ExpenseDuplicateDraft = {
    external_number?: string | null;
    title?: string | null;
    issue_date?: string | null;
    total: number | string;
    currency?: string | null;
};

export type ExpenseCandidate = {
    id: string;
    internal_number: string;
    external_number: string | null;
    title: string | null;
    total: string;
    currency: string;
    issue_date: string | null;
    status: string;
    variable_symbol?: string | null;
};

export type ExpenseDuplicateSuggestion = {
    expense: ExpenseCandidate;
    reason: "external_number" | "title_date_amount";
};

/** Preserve punctuation in document numbers while ignoring letter case and edge whitespace. */
export function normalizedExpenseNumber(value: string | null | undefined): string {
    return value?.trim().toLowerCase() ?? "";
}

/** Collapse presentation whitespace only; a matching title is advisory, never supplier identity. */
export function normalizedExpenseTitle(value: string | null | undefined): string {
    return value?.trim().replace(/\s+/g, " ").toLowerCase() ?? "";
}

/** Return only display fields so callers never accidentally serialize a local owner or internal note. */
export function expenseCandidate(row: EvoluExpenseRow): ExpenseCandidate {
    return {
        id: row.id, internal_number: row.internalNumber, external_number: row.externalNumber,
        title: row.title, total: row.total || "0", currency: row.currency || "",
        issue_date: row.issueDate, status: row.status, variable_symbol: row.variableSymbol,
    };
}

/** Compare available evidence conservatively; a duplicate suggestion never prevents a save. */
export function expenseDuplicateReason(
    draft: ExpenseDuplicateDraft, row: Pick<EvoluExpenseRow, "externalNumber" | "title" | "issueDate" | "total" | "currency">,
): ExpenseDuplicateSuggestion["reason"] | null {
    const amount = positiveBankAmount(String(draft.total));
    const currency = bankPaymentCurrency(draft.currency);
    if (!amount || !currency || amount !== positiveBankAmount(row.total)
        || currency !== bankPaymentCurrency(row.currency)) return null;
    const number = normalizedExpenseNumber(draft.external_number);
    if (number && number === normalizedExpenseNumber(row.externalNumber)) return "external_number";
    const title = normalizedExpenseTitle(draft.title);
    return title && title === normalizedExpenseTitle(row.title) && draft.issue_date
        && draft.issue_date === row.issueDate ? "title_date_amount" : null;
}

/** Scope advisory reads to the selected company and exact owner partition, excluding the edited record. */
export function localExpenseDuplicateSuggestions(
    companyId: string, ownerId: string, draft: ExpenseDuplicateDraft,
    rows: readonly EvoluExpenseRow[], excludeId?: string, limit = 20,
): ExpenseDuplicateSuggestion[] {
    if (!companyId || !ownerId) return [];
    return rows.filter((row) => row.companyId === companyId && Reflect.get(row, "ownerId") === ownerId
        && row.id !== excludeId && row.status !== "cancelled" && !row.cancelledAt
        && Reflect.get(row, "isDeleted") !== 1)
        .flatMap((row) => {
            const reason = expenseDuplicateReason(draft, row);
            return reason ? [{ expense: expenseCandidate(row), reason }] : [];
        })
        .sort((a, b) => a.expense.internal_number.localeCompare(b.expense.internal_number)
            || a.expense.id.localeCompare(b.expense.id))
        .slice(0, limit);
}
