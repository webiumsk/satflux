import { describe, expect, it } from 'vitest';
import { activeBankExpenseClaim, createBankExpenseClaim, effectiveExpensePayment, hasExpenseBankAssociation, parseBankExpenseClaim } from '@/evolu/bankExpenseClaims';
import type { EvoluExpenseRow } from '@/evolu/expenseMap';
import type { EvoluBankTransactionRow } from '@/evolu/bankMap';

function fixture() {
  const expense = { id: 'expense-a', ownerId: 'owner-a', companyId: 'company-a', status: 'recorded', paidAt: null, cancelledAt: null,
    internalNumber: 'EXP001', total: '19.00', currency: 'EUR', variableSymbol: '0019', externalNumber: 'INV-0019' } as unknown as EvoluExpenseRow;
  const transaction = { id: 'tx-a', ownerId: 'owner-a', companyId: 'company-a', direction: 'debit', source: 'csv',
    amount: '19.00', currency: 'EUR', variableSymbol: '0019', reference: 'INV-0019', counterpartyName: null,
    bookedAt: '2026-11-05T10:00:00Z', businessExpenseId: expense.id, matchStatus: 'matched' } as unknown as EvoluBankTransactionRow;
  const claim = createBankExpenseClaim(transaction, expense, 'manual');
  transaction.bankPaymentClaimJson = claim; expense.bankPaymentClaimJson = claim;
  return { expense, transaction, claim };
}

describe('one canonical reciprocal bank expense claim policy', () => {
  it('projects paid/date without changing raw payment history or serializable raw rows', () => {
    const { expense, transaction } = fixture();
    const raw = JSON.stringify(expense);
    expect(effectiveExpensePayment(expense, [transaction])).toMatchObject({ status: 'paid', paidAt: '2026-11-05', bankMatchConflict: 0 });
    expect(JSON.stringify(expense)).toBe(raw);
    expect(expense).toMatchObject({ status: 'recorded', paidAt: null });
  });
  it.each(['{', '{}', '{"v":99}', 'null', '[]', 'x'.repeat(4001)])('quarantines malformed/unknown claim cells', (claim) => {
    const { expense, transaction } = fixture();
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = claim;
    expect(activeBankExpenseClaim(transaction, expense)).toBeNull();
    expect(effectiveExpensePayment(expense, [transaction])).toMatchObject({ status: 'recorded', paidAt: null, bankMatchConflict: 1 });
  });
  it.each(['2026-11-05 10:00:00', '2026-11-05T10:00Z', '2026-02-30T10:00:00Z', '2026-11-05T24:00:00Z'])('rejects invalid booking grammar %s with aligned snapshots', (bookedAt) => {
    const { expense, transaction, claim } = fixture();
    transaction.bookedAt = bookedAt;
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = JSON.stringify({ ...JSON.parse(claim), bookedAt });
    expect(activeBankExpenseClaim(transaction, expense)).toBeNull();
  });
  it.each(['STAV NA ÚČTE', 'Stav na ÚČte'])('rejects Unicode account-balance hints %s', (counterpartyName) => {
    const { expense, transaction, claim } = fixture();
    transaction.counterpartyName = counterpartyName;
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = JSON.stringify({ ...JSON.parse(claim), counterpartyName });
    expect(activeBankExpenseClaim(transaction, expense)).toBeNull();
  });
  it('uses the same Unicode whitespace/currency semantics as strict matching', () => {
    const { expense, transaction } = fixture();
    expense.currency = transaction.currency = '\u00a0EUR\u00a0';
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = createBankExpenseClaim(transaction, expense, 'manual');
    expect(effectiveExpensePayment(expense, [transaction]).status).toBe('paid');
  });
  it('preserves maximum accepted escaped reference/name evidence without truncation', () => {
    const { expense, transaction } = fixture();
    transaction.reference = '\u0001'.repeat(4000);
    transaction.counterpartyName = '\u0002'.repeat(255);
    const claim = createBankExpenseClaim(transaction, expense, 'manual');
    expect(claim.length).toBeGreaterThan(24000);
    expect(claim.length).toBeLessThanOrEqual(32768);
    expect(parseBankExpenseClaim(claim)).toMatchObject({ reference: transaction.reference, counterpartyName: transaction.counterpartyName });
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = claim;
    expect(effectiveExpensePayment(expense, [transaction]).status).toBe('paid');
  });
  it('rejects valid JSON above the shared cell cap and unbounded claim tokens', () => {
    const { claim } = fixture();
    const base = JSON.parse(claim);
    const empty = JSON.stringify({ ...base, padding: '' });
    const limit = JSON.stringify({ ...base, padding: 'x'.repeat(32768 - empty.length) });
    expect(limit.length).toBe(32768);
    expect(parseBankExpenseClaim(limit)).not.toBeNull();
    expect(parseBankExpenseClaim(limit.slice(0, -1) + ' ' + '}')).toBeNull();
    expect(parseBankExpenseClaim(JSON.stringify({ ...base, token: 'x'.repeat(4100) }))).toBeNull();
  });
  it('requires exact actual owner/company and both selected pair IDs', () => {
    const { expense, transaction } = fixture();
    expect(activeBankExpenseClaim({ ...transaction, ownerId: 'other-owner' } as unknown as EvoluBankTransactionRow, expense)).toBeNull();
    expect(activeBankExpenseClaim({ ...transaction, companyId: 'other-company' } as unknown as EvoluBankTransactionRow, expense)).toBeNull();
    expect(activeBankExpenseClaim({ ...transaction, businessExpenseId: 'other-expense' } as unknown as EvoluBankTransactionRow, expense)).toBeNull();
    expect(activeBankExpenseClaim({ ...transaction, isDeleted: 1 } as unknown as EvoluBankTransactionRow, expense)).toBeNull();
  });
  it('retains legacy claimless values and independent explicit manual payment fields', () => {
    const { expense, transaction } = fixture();
    expense.status = 'paid'; expense.paidAt = '2026-10-01';
    expect(effectiveExpensePayment(expense, [transaction])).toMatchObject({ status: 'paid', paidAt: '2026-10-01' });
    expense.bankPaymentClaimJson = null;
    expect(effectiveExpensePayment(expense, [])).toBe(expense);
  });
  it('preserves an explicitly released paid receipt without reserving an active bank association', () => {
    const { expense, transaction, claim } = fixture();
    expense.bankPaymentClaimJson = JSON.stringify({ ...JSON.parse(claim), state: 'released' });
    transaction.bankPaymentClaimJson = null; transaction.businessExpenseId = null; transaction.matchStatus = 'unmatched';
    expect(effectiveExpensePayment(expense, [transaction])).toMatchObject({ status: 'paid', paidAt: '2026-11-05', bankMatchConflict: 0 });
    expect(hasExpenseBankAssociation(expense, { transactions: [transaction] } as never)).toBe(false);
  });
  it('keeps CREATE bookkeeping overrides separate from strict matching and honors unpaid intent', () => {
    const { expense, transaction } = fixture();
    expense.total = '25.00'; expense.currency = 'USD'; expense.externalNumber = null; expense.variableSymbol = null;
    transaction.reference = 'platba kartou';
    expect(() => createBankExpenseClaim(transaction, expense, 'manual')).toThrow('bank_expense_match_ineligible');
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = createBankExpenseClaim(transaction, expense, 'create', { paid: false, paidAt: null });
    expect(parseBankExpenseClaim(expense.bankPaymentClaimJson)?.mode).toBe('create');
    expect(effectiveExpensePayment(expense, [transaction])).toMatchObject({ status: 'recorded', paidAt: null, bankMatchConflict: 0 });
    expense.bankPaymentClaimJson = transaction.bankPaymentClaimJson = createBankExpenseClaim(transaction, expense, 'create', { paid: true, paidAt: '2026-11-06' });
    expect(effectiveExpensePayment(expense, [transaction])).toMatchObject({ status: 'paid', paidAt: '2026-11-06' });
  });
});
