export type ExpenseListFilterOptions = {
    filter?: string;
    year?: number;
    issue_from?: string;
    issue_to?: string;
    due?: string;
    due_from?: string;
    due_to?: string;
    amount_min?: string | number;
    amount_max?: string | number;
    search?: string;
};

export function expenseFilterDate(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value.startsWith("0000-")) return false;
    const date = new Date(`${value}T12:00:00Z`);
    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

/** Exact decimal comparison keeps filter boundaries independent of Number rounding. */
export function expenseFilterCents(value: string | number | null | undefined): bigint | null {
    if (value == null || !/^-?\d{1,20}(?:\.\d{1,2})?$/.test(String(value))) return null;
    const text = String(value);
    const negative = text.startsWith("-");
    const [whole, fraction = ""] = (negative ? text.slice(1) : text).split(".");
    return (negative ? -1n : 1n) * (BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0")));
}

/** Defensive validation is also used before local select-all, never expanding an invalid filter. */
export function validExpenseListFilters(options: ExpenseListFilterOptions): boolean {
    if (options.filter && !["all", "paid", "unpaid", "overdue"].includes(options.filter)) return false;
    if (options.year !== undefined && (!Number.isInteger(options.year) || options.year < 2000 || options.year > 2100)) return false;
    if (options.due && !["all", "overdue", "custom"].includes(options.due)) return false;
    if (options.due === "custom" && !options.due_from && !options.due_to) return false;
    for (const field of ["issue_from", "issue_to", "due_from", "due_to"] as const) {
        if (options[field] && !expenseFilterDate(options[field])) return false;
    }
    if (options.issue_from && options.issue_to && options.issue_from > options.issue_to) return false;
    if (options.due_from && options.due_to && options.due_from > options.due_to) return false;
    const min = options.amount_min === undefined || options.amount_min === "" ? undefined : expenseFilterCents(options.amount_min);
    const max = options.amount_max === undefined || options.amount_max === "" ? undefined : expenseFilterCents(options.amount_max);
    if (min === null || max === null || (min !== undefined && min < 0n) || (max !== undefined && max < 0n)) return false;
    if ((min !== undefined && min > 9007199254740991n) || (max !== undefined && max > 9007199254740991n)) return false;
    if (min !== undefined && max !== undefined && min > max) return false;
    return options.search === undefined || (typeof options.search === "string" && !options.search.includes("\u0000") && [...options.search].length <= 200);
}
