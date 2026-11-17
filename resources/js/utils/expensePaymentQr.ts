import type { ExpenseListRow } from '@/composables/useExpenseRowMeta';
import { encodePayBySquare } from './payBySquarePayload';
import { normalizePaymentIban } from './paymentIban';

export type ExpensePaymentQrDetails = {
    iban: string;
    variableSymbol: string;
    amount: string;
    currency: 'EUR';
    supplier: string;
};

/** Read only explicit supplier metadata written by expense importers, never a company's bank account. */
function noteValues(note: string, label: string): string[] {
    return note.split(/\r?\n/).filter(line => line.toLowerCase().startsWith(`${label.toLowerCase()}:`))
        .map(line => line.slice(label.length + 1).trim());
}

/** No partial-payment balance is inferred from imported notes or disputed matching evidence. */
export function expensePaymentQrDetails(row: ExpenseListRow): ExpensePaymentQrDetails | null {
    if (row.status !== 'recorded' || row.paid_at || row.bank_match_conflict || row.currency !== 'EUR') return null;
    const amount = /^(\d{1,12})(?:\.(\d{1,2}))?$/.exec(row.total);
    const vs = row.variable_symbol?.trim() ?? '';
    if (!amount || !/[1-9]/.test(row.total) || !/^\d{1,10}$/.test(vs)) return null;
    const note = row.internal_note ?? '';
    const accounts = noteValues(note, 'Supplier bank account');
    const suppliers = noteValues(note, 'Supplier');
    if (accounts.length !== 1 || suppliers.length !== 1) return null;
    const iban = normalizePaymentIban(accounts[0]);
    const supplier = suppliers[0];
    if (!iban || !supplier || Array.from(supplier).length > 70
        || /[\p{Cc}\p{Cs}\p{Zl}\p{Zp}]/u.test(supplier)) return null;
    // The import keeps the original total when only part of it was paid.
    // Until a remaining balance is authoritative, do not prepare another full payment.
    if (noteValues(note, 'Payment date').length || noteValues(note, 'Payment amount')
        .some(value => !/^0+(?:[.,]0{1,2})?$/.test(value))) return null;
    return { iban, variableSymbol: vs, amount: `${amount[1].replace(/^0+(?=\d)/, '')}.${(amount[2] ?? '').padEnd(2, '0')}`,
        currency: 'EUR', supplier };
}

/** PayMe AM is limited to nine characters; never round significant cents to make it fit. */
function payMeAmount(details: ExpensePaymentQrDetails): string | null {
    const match = /^(\d{1,12})\.(\d{2})$/.exec(details.amount);
    if (details.currency !== 'EUR' || !match || match[0] !== details.amount || !/[1-9]/.test(details.amount)) return null;
    if (details.amount.length <= 9) return details.amount;
    const integer = match[1].replace(/^0+(?=\d)/, '');
    const fraction = match[2].replace(/0+$/, '');
    const compact = `${integer}${fraction ? `.${fraction}` : ''}`;
    return compact.length <= 9 ? compact : null;
}

export function canCreateExpensePayMe(details: ExpensePaymentQrDetails): boolean {
    return payMeAmount(details) !== null;
}

/** SBA Payment Link Standard 2.0; editable QR instructions, without scheduling a payment. */
export function expensePayMePayload(details: ExpensePaymentQrDetails): string {
    const amount = payMeAmount(details);
    if (amount === null) throw new Error('Expense amount is not supported by PayMe.');
    const params = new URLSearchParams({ IBAN: details.iban, AM: amount, CC: details.currency,
        PI: `/VS${details.variableSymbol}`, CN: details.supplier });
    return `https://payme.sk/2/q/PME?${params}`;
}

export function expensePayBySquarePayload(details: ExpensePaymentQrDetails): string {
    return encodePayBySquare({ iban: details.iban, amount: details.amount, currency: details.currency,
        variableSymbol: details.variableSymbol, beneficiaryName: details.supplier });
}
