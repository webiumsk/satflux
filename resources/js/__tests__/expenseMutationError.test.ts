import { describe, expect, it } from 'vitest';
import { expenseMutationErrorKey } from '@/utils/expenseError';

describe('localized linked-expense recovery', () => {
    it('maps the exact server status code used by guarded unmark and cancellation', () => {
        const error = Object.assign(new Error('Request failed with status code 422'), {
            response: { data: { errors: { status: ['expense_linked_payment'] } } },
        });
        expect(expenseMutationErrorKey(error)).toBe('invoicing.expense_linked_payment');
    });
    it('retains guidance for the existing linked financial-edit response', () => {
        expect(expenseMutationErrorKey({ response: { data: { errors: {
            expense_linked_payment: ['Unmatch the linked bank movement before changing the expense total or currency.'],
        } } } })).toBe('invoicing.expense_linked_payment');
    });
    it.each([
        null, {}, new Error('unrelated'), { response: { data: { errors: [] } } },
        { response: { data: { errors: { status: ['unrelated'] } } } },
        { response: { data: { errors: { status: ['expense_linked_payment', 'unrelated'] } } } },
        { response: { data: { errors: { status: ['expense_linked_payment'], total: ['unrelated'] } } } },
    ])('leaves unrelated or malformed errors to existing handling (%j)', (error) => {
        expect(expenseMutationErrorKey(error)).toBeNull();
    });
    it.each([
        ['expense_linked_payment', 'invoicing.expense_linked_payment'],
        ['bank_expense_claim_changed', 'invoicing.bank_expense_match_failed'],
        ['bank_reconciliation_outcome_unknown', 'invoicing.expense_changes_unknown'],
        ['expense_reconciliation_owner_unavailable', 'invoicing.expense_owner_unavailable'],
    ])('preserves the existing local code %s', (code, key) => {
        expect(expenseMutationErrorKey(new Error(code))).toBe(key);
    });
});
