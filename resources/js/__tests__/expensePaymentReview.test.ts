import { describe, expect, it } from 'vitest';
import { expensePaymentReviewRequired } from '@/utils/expensePaymentReview';

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
