import { describe, expect, it } from 'vitest';
import { expensePaymentConfirmationTuple, expensePaymentReviewRequired, hasExpensePaymentEvidence } from '@/utils/expensePaymentReview';

describe('expense payment-review evidence', () => {
    it.each(['Payment amount: 5.00', 'Payment amount: unknown', 'Payment date: 2026-11-17',
        'Imported from ISDOC.\nSupplier: Sample', 'Dobropis prijatý z Peppol (SAPI-SK).'])('retains legacy safety evidence: %s', note => {
        expect(expensePaymentReviewRequired(null, note)).toBe(true);
    });
    it.each(['', 'Supplier: Sample\nSupplier bank account: ANYTHING', 'Payment amount: 0,00', 'Payment amount: 00.00'])('does not interpret legacy recipient prose as payment authority: %s', note => {
        expect(expensePaymentReviewRequired(null, note)).toBe(false);
    });
    it('uses durable structured evidence when notes change', () => {
        expect(expensePaymentReviewRequired(true, '')).toBe(true);
        expect(expensePaymentReviewRequired(1, '')).toBe(true);
        expect(expensePaymentReviewRequired(false, 'Payment amount: 5.00')).toBe(false);
        expect(expensePaymentReviewRequired(0, 'Imported from ISDOC.')).toBe(false);
    });
});


describe('explicit manual payment confirmation', () => {
    const fields = { supplier_name: ' Žluťoučký / supplier ', supplier_iban: 'sk68 0720 0002 8919 8742 6353',
        total: ' 0019.5 ', currency: 'EUR', variable_symbol: ' 00019 ' };
    it('binds exact normalized recipient instructions while retaining VS leading zeros', () => {
        expect(expensePaymentConfirmationTuple(fields)).toBe(JSON.stringify(['Žluťoučký / supplier', 'SK6807200002891987426353', '19.50', 'EUR', '00019']));
    });
    it.each([{ total: '0.00' }, { total: '-1' }, { total: '1.001' }, { total: '1e2' }, { total: '1000000000000' },
        { currency: 'USD' }, { supplier_name: 'x'.repeat(71) }, { supplier_name: 'bad\nname' },
        { supplier_iban: 'SK6807200002891987426354' }, { variable_symbol: '' }, { variable_symbol: '12345678901' }])('rejects ineligible confirmation data %j', patch => {
        expect(expensePaymentConfirmationTuple({ ...fields, ...patch })).toBeNull();
    });
    it('distinguishes unresolved historical XML proof from concrete payment evidence', () => {
        expect(hasExpensePaymentEvidence('Imported from ISDOC.')).toBe(false);
        expect(hasExpensePaymentEvidence('Payment amount: 0.00')).toBe(false);
        expect(hasExpensePaymentEvidence('Payment amount: 2.00')).toBe(true);
        expect(hasExpensePaymentEvidence('Payment amount: unknown')).toBe(true);
        expect(hasExpensePaymentEvidence('Payment date: 2026-11-17')).toBe(true);
    });
});
