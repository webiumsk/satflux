import type { ExpenseSummary } from "../types/expenses";
export type { ExpenseSummary, ExpenseCurrencySummary } from "../types/expenses";

function cents(value: string): bigint {
    const match = /^(-?)(\d+)(?:\.(\d{1,2}))?$/.exec(value.trim());
    if (!match) throw new Error("invalid_expense_total");
    const amount = BigInt(match[2]) * 100n + BigInt((match[3] ?? "").padEnd(2, "0"));
    return match[1] ? -amount : amount;
}

function decimal(amount: bigint): string {
    const absolute = amount < 0n ? -amount : amount;
    return `${amount < 0n ? "-" : ""}${absolute / 100n}.${String(absolute % 100n).padStart(2, "0")}`;
}

/** Accepts the full filtered collection, before pagination or selection. */
export function summarizeExpenses(
    rows: ReadonlyArray<{ status: string; total: string; currency: string }>,
): ExpenseSummary {
    const groups = new Map<string, { count: number; total: bigint; unpaid: bigint }>();
    let count = 0;
    for (const row of rows) {
        if (row.status === "cancelled") continue;
        const currency = row.currency.trim().toUpperCase() || "EUR";
        const group = groups.get(currency) ?? { count: 0, total: 0n, unpaid: 0n };
        const amount = cents(row.total);
        group.count += 1;
        group.total += amount;
        if (row.status === "recorded") group.unpaid += amount;
        groups.set(currency, group);
        count += 1;
    }
    return {
        count,
        currencies: [...groups.keys()].sort().map((currency) => {
            const group = groups.get(currency)!;
            return { currency, count: group.count, total: decimal(group.total), unpaid_total: decimal(group.unpaid) };
        }),
    };
}

/** Format the integer and fractional parts without converting an amount to Number. */
export function formatExpenseSummaryAmount(value: string, currency: string, locale: string): string {
    const amount = cents(value);
    const negative = amount < 0n;
    const absolute = negative ? -amount : amount;
    const whole = absolute / 100n;
    const fraction = String(absolute % 100n).padStart(2, "0");
    const integer = negative ? (whole === 0n ? -0 : -whole) : whole;
    const hasCurrencyCode = /^[a-z]{3}$/i.test(currency);
    const formatter = new Intl.NumberFormat(locale, {
        ...(hasCurrencyCode ? { style: "currency" as const, currency } : { style: "decimal" as const }),
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    const formatted = formatter.formatToParts(integer).map((part) => part.type === "fraction" ? fraction : part.value).join("");
    // Historical records can contain non-ISO codes accepted by older forms.
    // Keep their exact amount and literal code visible without currency conversion.
    return hasCurrencyCode ? formatted : `${formatted} ${currency}`;
}
