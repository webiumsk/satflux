import type { BankSummary, BankTxApiRow } from "./bankMap";

export type BankListFilterOptions = {
    from?: string;
    to?: string;
    direction?: "credit" | "debit";
    amount_min?: string;
    amount_max?: string;
    search?: string;
};

/** Exact nonnegative cents, constrained to the browser's safe integer range. */
export function bankFilterMinorUnits(value: string): number | null {
    const match = /^([0-9]+)(?:\.([0-9]{1,2}))?$/.exec(value);
    if (!match) return null;
    const whole = match[1].replace(/^0+/, "");
    if (whole.length > 14) return null;
    const minor = Number(whole || "0") * 100 + Number((match[2] || "").padEnd(2, "0"));
    return Number.isSafeInteger(minor) && minor >= 0 ? minor : null;
}

function validDate(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || value.startsWith("0000-")) return false;
    const date = new Date(`${value}T12:00:00.000Z`);
    return Number.isFinite(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

/** Copy only ordinary list fields. Never spread these into synchronization requests. */
export function normalizeBankListFilters(filters: BankListFilterOptions = {}): BankListFilterOptions {
    const result: BankListFilterOptions = {};
    for (const key of ["from", "to"] as const) {
        const value = filters[key];
        if (value !== undefined && value !== "") {
            if (typeof value !== "string" || !validDate(value)) throw new Error("bank_filters_invalid");
            result[key] = value;
        }
    }
    if (result.from && result.to && result.from > result.to) throw new Error("bank_filters_invalid");
    if (filters.direction !== undefined) {
        if (filters.direction !== "credit" && filters.direction !== "debit") throw new Error("bank_filters_invalid");
        result.direction = filters.direction;
    }
    for (const key of ["amount_min", "amount_max"] as const) {
        const value = filters[key];
        if (value !== undefined && value !== "") {
            if (typeof value !== "string" || value.length > 64 || bankFilterMinorUnits(value) === null) throw new Error("bank_filters_invalid");
            result[key] = value;
        }
    }
    if (result.amount_min !== undefined && result.amount_max !== undefined
        && bankFilterMinorUnits(result.amount_min)! > bankFilterMinorUnits(result.amount_max)!) throw new Error("bank_filters_invalid");
    if (filters.search !== undefined) {
        if (typeof filters.search !== "string" || [...filters.search].length > 200 || filters.search.includes("\0")
            || /[\uD800-\uDFFF]/u.test(filters.search)) throw new Error("bank_filters_invalid");
        if (filters.search.trim()) result.search = filters.search.trim();
    }
    return result;
}

/** Use owner/company-scoped projected metadata, including the effective pairing status. */
export function matchesBankListFilters(row: BankTxApiRow, filters: BankListFilterOptions): boolean {
    const booked = row.booked_at.slice(0, 10);
    if ((filters.from || filters.to) && !validDate(booked)) return false;
    if (filters.from && booked < filters.from) return false;
    if (filters.to && booked > filters.to) return false;
    if (filters.direction && row.direction !== filters.direction) return false;
    if (filters.amount_min !== undefined || filters.amount_max !== undefined) {
        const match = /^-?([0-9]+)(?:\.([0-9]{1,2}))?$/.exec(row.amount);
        if (!match) return false;
        // Historical stored values may exceed the safe UI input ceiling.
        const minor = BigInt(match[1]) * 100n + BigInt((match[2] || "").padEnd(2, "0"));
        if (filters.amount_min !== undefined && minor < BigInt(bankFilterMinorUnits(filters.amount_min)!)) return false;
        if (filters.amount_max !== undefined && minor > BigInt(bankFilterMinorUnits(filters.amount_max)!)) return false;
    }
    const search = filters.search?.toLowerCase();
    if (!search) return true;
    return [row.variable_symbol, row.counterparty_name, row.counterparty_iban, row.reference, row.match?.document?.number, row.match?.document?.title,
        row.expense?.internal_number, row.expense?.title]
        .some(value => typeof value === "string" && value.toLowerCase().includes(search));
}

/** Streaming accumulator: list caps do not change totals and mixed currencies are never summed. */
export function createBankListSummary() {
    const currencies = new Map<string, { credit_count: number; debit_count: number; credit_total: number; debit_total: number }>();
    const money = (amount: number) => amount.toFixed(2);
    return {
        add(row: BankTxApiRow) {
            const amount = Math.abs(parseFloat(row.amount || "0"));
            const currency = row.currency || "EUR";
            const entry = currencies.get(currency) ?? { credit_count: 0, debit_count: 0, credit_total: 0, debit_total: 0 };
            if (row.direction === "credit") { entry.credit_count++; entry.credit_total += amount; }
            else { entry.debit_count++; entry.debit_total += amount; }
            currencies.set(currency, entry);
        },
        finish(accountBalance: NonNullable<BankSummary["account_balance"]> | null = null): BankSummary {
            const byCurrency = [...currencies].map(([currency, entry]) => ({ currency,
                credit_count: entry.credit_count, credit_total: money(entry.credit_total),
                debit_count: entry.debit_count, debit_total: money(entry.debit_total),
                balance: money(entry.credit_total - entry.debit_total) }));
            const single = byCurrency.length === 1 ? byCurrency[0] : null;
            return { credit_count: byCurrency.reduce((sum, row) => sum + row.credit_count, 0), credit_total: single?.credit_total ?? null,
                debit_count: byCurrency.reduce((sum, row) => sum + row.debit_count, 0), debit_total: single?.debit_total ?? null,
                balance: single?.balance ?? null, currency: single?.currency ?? null, by_currency: byCurrency, account_balance: accountBalance };
        },
    };
}
