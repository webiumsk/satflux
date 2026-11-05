/** Canonical cents without floating-point coercion or amount tolerance. */
export function positiveBankAmount(value: string | null | undefined): string | null {
    const parts = /^(\d+)(?:\.(\d{1,2}))?$/.exec(value?.trim() ?? "");
    if (!parts) return null;
    const cents = `${parts[1]}${(parts[2] ?? "").padEnd(2, "0")}`.replace(/^0+/, "");
    return cents || null;
}

/**
 * Return an uppercase three-letter currency code, or null without inventing a debit default.
 */
export function bankPaymentCurrency(value: string | null | undefined): string | null {
    const code = value?.trim().toUpperCase() ?? "";
    return /^[A-Z]{3}$/.test(code) ? code : null;
}

/**
 * Validate the calendar date and timestamp fields, then return the ledger's literal booking day.
 * Do not shift the payment date through a timezone conversion.
 */
export function bankPaymentBookingDate(value: string | null): string | null {
    if (!value || !/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})?)?$/.test(value)) return null;
    const date = value.slice(0, 10);
    const midnight = new Date(`${date}T00:00:00Z`);
    if (!Number.isFinite(midnight.getTime()) || midnight.toISOString().slice(0, 10) !== date) return null;
    if (value.includes("T") && (Number(value.slice(11, 13)) > 23
        || Number(value.slice(14, 16)) > 59 || Number(value.slice(17, 19)) > 59)) return null;
    return Number.isFinite(Date.parse(value)) ? date : null;
}
