import type { ExpenseListRow } from '@/composables/useExpenseRowMeta';
import { encodePayBySquare } from './payBySquarePayload';

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

function validIban(iban: string): boolean {
    if (!/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/.test(iban)) return false;
    let remainder = 0;
    for (const char of iban.slice(4) + iban.slice(0, 4)) {
        for (const digit of char >= 'A' ? String(char.charCodeAt(0) - 55) : char) {
            remainder = (remainder * 10 + Number(digit)) % 97;
        }
    }
    return remainder === 1;
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
    const iban = accounts[0].replace(/ /g, '').toUpperCase();
    const supplier = suppliers[0];
    if (!validIban(iban) || !supplier || Array.from(supplier).length > 70
        || /[\p{Cc}\p{Cs}\p{Zl}\p{Zp}]/u.test(supplier)) return null;
    // The import keeps the original total when only part of it was paid.
    // Until a remaining balance is authoritative, do not prepare another full payment.
    if (noteValues(note, 'Payment date').length || noteValues(note, 'Payment amount')
        .some(value => !/^0+(?:[.,]0{1,2})?$/.test(value))) return null;
    return { iban, variableSymbol: vs, amount: `${amount[1].replace(/^0+(?=\d)/, '')}.${(amount[2] ?? '').padEnd(2, '0')}`,
        currency: 'EUR', supplier };
}

/** SBA Payment Link Standard 2.0; editable QR instructions, without scheduling a payment. */
export function expensePayMePayload(details: ExpensePaymentQrDetails): string {
    const params = new URLSearchParams({ IBAN: details.iban, AM: details.amount, CC: details.currency,
        PI: `/VS${details.variableSymbol}`, CN: details.supplier });
    return `https://payme.sk/2/q/PME?${params}`;
}

export function expensePayBySquarePayload(details: ExpensePaymentQrDetails): string {
    return encodePayBySquare({ iban: details.iban, amount: details.amount, currency: details.currency,
        variableSymbol: details.variableSymbol, beneficiaryName: details.supplier });
}
