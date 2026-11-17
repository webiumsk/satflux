/** Legacy notes retain safety evidence only; they never supply a payment recipient. */
export function expensePaymentReviewRequired(value: boolean | number | null | undefined, note?: string | null): boolean {
    if (value != null) return value === true || value === 1;
    const text = note ?? "";
    // Old XML imports do not retain enough evidence to distinguish missing data from withheld unsafe instructions.
    if (/Imported from ISDOC\.|Peppol \(SAPI-SK\)\./i.test(text)) return true;
    return text.split(/\r?\n/).some((line) => /^Payment date:/i.test(line)
        || (/^Payment amount:/i.test(line) && !/^0+(?:[.,]0{1,2})?$/.test(line.slice("Payment amount:".length).trim())));
}
