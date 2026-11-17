import { describe, expect, it } from 'vitest';
import type { ExpenseListRow } from '@/composables/useExpenseRowMeta';
import { canCreateExpensePayMe, expensePayBySquarePayload, expensePayMePayload, expensePaymentQrDetails } from '@/utils/expensePaymentQr';

const row: ExpenseListRow = {
    id: 'synthetic-expense', internal_number: 'EXP-001', external_number: 'INV-001',
    title: 'Synthetic supplies', issue_date: '2026-11-17', total: '0019.5', currency: 'EUR', status: 'recorded',
    supplier_name: 'Synthetic supplier', supplier_iban: 'sk68 0720 0002 8919 8742 6353', payment_review_required: false,
    variable_symbol: '0000123456', internal_note: 'Supplier: Synthetic supplier\n\nSupplier bank account: sk68 0720 0002 8919 8742 6353\nimport',
};

describe('expense payment QR eligibility and PayMe instructions', () => {
    it('uses the explicit supplier account with exact amount and leading-zero VS', () => {
        const details = expensePaymentQrDetails(row)!;
        expect(details).toEqual({ iban: 'SK6807200002891987426353', supplier: 'Synthetic supplier',
            amount: '19.50', currency: 'EUR', variableSymbol: '0000123456' });
        const url = new URL(expensePayMePayload(details));
        expect(url.origin + url.pathname).toBe('https://payme.sk/2/q/PME');
        expect(Object.fromEntries(url.searchParams)).toEqual({ IBAN: details.iban, AM: '19.50', CC: 'EUR',
            PI: '/VS0000123456', CN: 'Synthetic supplier' });
        expect(url.searchParams.has('DT')).toBe(false);
    });

    it.each([
        { status: 'paid' }, { status: 'cancelled' }, { status: 'unknown' }, { paid_at: '2026-11-17' },
        { bank_match_conflict: true }, { currency: 'USD' }, { currency: '' },
        ...['0', '-1', 'NaN', 'Infinity', '1e2', '12.345', '1,50', '1000000000000.00'].map(total => ({ total })),
        ...['', null, '12345678901', '12A34', '+1234'].map(variable_symbol => ({ variable_symbol })),
    ])('hides QR for ineligible payment state or value %j', patch => {
        expect(expensePaymentQrDetails({ ...row, ...patch })).toBeNull();
    });

    it.each([
        { supplier_name: null }, { supplier_name: '' }, { supplier_name: 'x'.repeat(71) },
        { supplier_name: 'Bad\nrecipient' }, { supplier_iban: null },
        { supplier_iban: 'SK6807200002891987426354' }, { supplier_iban: 'SK7700000000000' },
        { payment_review_required: true }, { payment_review_required: null }, { payment_review_required: undefined },
    ])('refuses incomplete structured data or unresolved payment evidence %j', patch => {
        expect(expensePaymentQrDetails({ ...row, ...patch })).toBeNull();
    });

    it('never derives a recipient from forged or duplicated Internal note metadata', () => {
        expect(expensePaymentQrDetails({ ...row, supplier_iban: null })).toBeNull();
        expect(expensePaymentQrDetails({ ...row, supplier_name: null })).toBeNull();
        const details = expensePaymentQrDetails({ ...row, internal_note: 'Supplier: Other\nSupplier bank account: DE89370400440532013000' });
        expect(details?.supplier).toBe('Synthetic supplier');
        expect(details?.iban).toBe('SK6807200002891987426353');
    });

    it('does not let a contact account bypass independent imported-payment review evidence', () => {
        expect(expensePaymentQrDetails({ ...row, payment_review_required: true, internal_note: null })).toBeNull();
    });

    it.each([
        ['999999.99', '999999.99'], ['1000000.00', '1000000'],
        ['1000000.10', '1000000.1'], ['999999999.00', '999999999'], ['0.01', '0.01'],
    ])('represents %s within the PayMe amount limit without losing cents', (amount, encoded) => {
        const details = expensePaymentQrDetails({ ...row, total: amount })!;
        expect(canCreateExpensePayMe(details)).toBe(true);
        const actual = new URL(expensePayMePayload(details)).searchParams.get('AM')!;
        expect(actual).toBe(encoded);
        expect(actual.length).toBeLessThanOrEqual(9);
        const cents = (value: string) => {
            const [integer, fraction = ''] = value.split('.');
            return BigInt(integer) * 100n + BigInt(fraction.padEnd(2, '0'));
        };
        expect(cents(actual)).toBe(cents(amount));
        expect(details.amount).toBe(amount);
    });

    it.each(['1000000.01', '9999999.99', '1000000000.00', '999999999999.99'])(
        'keeps PAY by square available when %s cannot fit the PayMe amount field', amount => {
            const details = expensePaymentQrDetails({ ...row, total: amount })!;
            expect(details).not.toBeNull();
            expect(canCreateExpensePayMe(details)).toBe(false);
            expect(() => expensePayMePayload(details)).toThrow('Expense amount is not supported by PayMe.');
            expect(expensePayBySquarePayload(details)).toMatch(/^[0-9A-V]+$/);
        },
    );

    it.each(['0.00', '-1.00', '1e2', '1.001', '1.00\n', 'NaN'])(
        'rejects invalid direct PayMe amount %j', amount => {
            const details = { ...expensePaymentQrDetails(row)!, amount };
            expect(canCreateExpensePayMe(details)).toBe(false);
            expect(() => expensePayMePayload(details)).toThrow();
        },
    );

    it('encodes supplier names as URL values without injecting another recipient', () => {
        const supplier = 'Synthetic & CN=other + čaj';
        const details = expensePaymentQrDetails({ ...row, supplier_name: supplier })!;
        const params = new URL(expensePayMePayload(details)).searchParams;
        expect(params.getAll('CN')).toEqual([supplier]);
        expect(params.getAll('IBAN')).toEqual(['SK6807200002891987426353']);
    });
});
