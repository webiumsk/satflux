import { normalizePaymentIban } from "./paymentIban";

/** Legacy notes retain safety evidence only; they never supply a payment recipient. */
export function expensePaymentReviewRequired(value: boolean | number | null | undefined, note?: string | null): boolean {
    if (value != null) return value === true || value === 1;
    const text = note ?? "";
    // Old XML imports do not retain enough evidence to distinguish missing data from withheld unsafe instructions.
    if (/Imported from ISDOC\.|Peppol \(SAPI-SK\)\./i.test(text)) return true;
    return hasExpensePaymentEvidence(text);
}

/** Current saved payment evidence remains a veto during manual review. */
export function hasExpensePaymentEvidence(note?: string | null): boolean {
    return (note ?? "").split(/\r?\n/).some((line) => /^Payment date:/i.test(line)
        || (/^Payment amount:/i.test(line) && !/^0+(?:[.,]0{1,2})?$/.test(line.slice("Payment amount:".length).trim())));
}

export type ExpensePaymentConfirmationFields = {
    supplier_name?: string | null;
    supplier_iban?: string | null;
    total: string | number;
    currency?: string | null;
    variable_symbol?: string | null;
};

/** Bind deliberate manual review to the exact displayed payment instructions, never settlement. */
export function expensePaymentConfirmationTuple(fields: ExpensePaymentConfirmationFields): string | null {
    if (fields.currency !== "EUR") return null;
    const amount = /^(\d{1,12})(?:\.(\d{1,2}))?$/.exec(String(fields.total).trim());
    const name = fields.supplier_name?.trim() ?? "";
    const iban = normalizePaymentIban(fields.supplier_iban ?? "");
    const symbol = fields.variable_symbol?.trim() ?? "";
    if (!amount || !/[1-9]/.test(String(fields.total)) || !name || Array.from(name).length > 70
        || /[\p{Cc}\p{Cs}\p{Zl}\p{Zp}]/u.test(name) || !iban || !/^\d{1,10}$/.test(symbol)) return null;
    const canonicalAmount = `${amount[1].replace(/^0+(?=\d)/, "")}.${(amount[2] ?? "").padEnd(2, "0")}`;
    return JSON.stringify([name, iban, canonicalAmount, "EUR", symbol]);
}
