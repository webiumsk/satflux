import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Evolu } from '@evolu/common/local-first';
import type { BankPaymentContext } from '@/evolu/bankCrud';
import type { InvoicingLocalSchema } from '@/evolu/schema';
import { bankTestQueryTable, installBankLocks } from './bankReconciliationTestSupport';
import { clearRowOwnerIndex } from '@/evolu/ownerScope';
import { clearCompanyShares } from '@/evolu/companyShareRegistry';

vi.mock('@/evolu/client', () => ({
  allBankImportBatchesQuery: 'batches', allBankTransactionsQuery: 'transactions', allBankTransactionMatchesQuery: 'matches',
  allDocumentsQuery: 'documents', allExpensesQuery: 'expenses', bankReconciliationBatchesQuery: 'fresh:batches',
  bankReconciliationTransactionsQuery: 'fresh:transactions', bankReconciliationMatchesQuery: 'fresh:matches',
  bankReconciliationDocumentsQuery: 'fresh:documents', bankReconciliationExpensesQuery: 'fresh:expenses',
}));
import { bankExpenseMatchSuggestions, matchLocalBankTransactionToExpense } from '@/evolu/bankExpenseManualMatching';

function fixture() {
  const transaction = { id: 'tx-a', companyId: 'company-a', ownerId: 'app-owner', bankImportBatchId: 'batch-a',
    bookedAt: '2026-11-05T12:00:00Z', amount: '19.00', currency: 'EUR', direction: 'debit', matchStatus: 'unmatched',
    businessExpenseId: null, variableSymbol: '00123', reference: 'INV-00123', source: 'csv' };
  const expense = { id: 'expense-a', companyId: 'company-a', ownerId: 'app-owner', status: 'recorded',
    internalNumber: 'EXP001', externalNumber: 'INV-00123', title: 'Synthetic expense', variableSymbol: '00123',
    total: '19.00', currency: 'EUR', paidAt: null, cancelledAt: null };
  const ctx = { batches: [], transactions: [transaction], expenses: [expense], matches: [], documents: [] } as unknown as BankPaymentContext;
  const tables = { bankImportBatch: ctx.batches, bankTransaction: ctx.transactions, expense: ctx.expenses,
    company: [{ id: 'company-a', legalName: 'Synthetic', ownerId: 'app-owner' }] } as unknown as Record<string, Record<string, unknown>[]>;
  const update = vi.fn((table: string, patch: Record<string, unknown>, options?: { onlyValidate?: boolean; onComplete?: () => void }) => {
    if (!options?.onlyValidate) {
      Object.assign(tables[table]?.find((row) => row.id === patch.id) ?? {}, patch);
      queueMicrotask(() => options?.onComplete?.());
    }
    return { ok: true, value: { id: patch.id } };
  });
  const evolu = {
    appOwner: Promise.resolve({ id: 'app-owner' }), update, subscribeError: () => () => {}, getError: () => null,
    upsert: (_table: string, patch: Record<string, unknown>, options?: { onComplete?: () => void }) => {
      queueMicrotask(() => options?.onComplete?.()); return { ok: true, value: { id: patch.id } };
    },
    loadQuery: async (query: string) => {
      const rows = ctx[query.replace('fresh:', '') as keyof BankPaymentContext] ?? tables[bankTestQueryTable(query) ?? ''] ?? [];
      return rows.map((row) => ({ ...row }));
    },
  } as unknown as Evolu<InvoicingLocalSchema>;
  return { transaction, expense, ctx, evolu, update };
}

beforeEach(() => { installBankLocks(); });
afterEach(() => { clearRowOwnerIndex(); clearCompanyShares(); vi.unstubAllGlobals(); });

describe('confirmed outgoing payment matching to an existing expense', () => {
  it('suggests without writes and marks paid only after explicit confirmation using the bank date', async () => {
    const { transaction, expense, ctx, evolu, update } = fixture();
    expect(bankExpenseMatchSuggestions(ctx.transactions[0]!, ctx, 'app-owner')).toHaveLength(1);
    expect(update).not.toHaveBeenCalled();
    await matchLocalBankTransactionToExpense(evolu, 'company-a', 'tx-a', 'expense-a');
    expect(transaction).toMatchObject({ businessExpenseId: 'expense-a', matchStatus: 'matched' });
    expect(expense).toMatchObject({ status: 'paid', paidAt: '2026-11-05' });
    const writes = update.mock.calls.filter((call) => !call[2]?.onlyValidate).length;
    await matchLocalBankTransactionToExpense(evolu, 'company-a', 'tx-a', 'expense-a');
    expect(update.mock.calls.filter((call) => !call[2]?.onlyValidate)).toHaveLength(writes);
  });
  it.each([
    { amount: '18.99' }, { amount: '19oops' }, { amount: '1.9e1' }, { amount: '19.001' },
    { currency: null }, { currency: 'USD' }, { direction: 'credit' }, { direction: 'unknown' },
    { variableSymbol: '123' }, { variableSymbol: 'invalid' }, { reference: 'Stav na účte' },
    { matchStatus: 'ignored' }, { ownerId: 'other-owner' },
    { source: 'inbound_email', recordedDirectionEvidence: 'unverified' },
  ])('declines unsafe candidate %j and performs no financial mutation', async (patch) => {
    const { transaction, ctx, evolu, update } = fixture();
    Object.assign(transaction, patch);
    expect(bankExpenseMatchSuggestions(ctx.transactions[0]!, ctx, 'app-owner')).toEqual([]);
    await expect(matchLocalBankTransactionToExpense(evolu, 'company-a', 'tx-a', 'expense-a')).rejects.toThrow();
    expect(update.mock.calls.filter((call) => !call[2]?.onlyValidate)).toHaveLength(0);
  });
  it('returns ambiguous candidates for explicit selection instead of auto-paying either', async () => {
    const { ctx, expense, update } = fixture();
    ctx.expenses.push({ ...expense, id: 'expense-b', internalNumber: 'EXP002' } as unknown as BankPaymentContext['expenses'][number]);
    expect(bankExpenseMatchSuggestions(ctx.transactions[0]!, ctx, 'app-owner')).toHaveLength(2);
    expect(update).not.toHaveBeenCalled();
  });
  it('uses a full external reference only when the bank VS is absent', () => {
    const { transaction, ctx } = fixture();
    transaction.variableSymbol = '';
    expect(bankExpenseMatchSuggestions(ctx.transactions[0]!, ctx, 'app-owner')[0]?.reason).toBe('payment_reference');
    transaction.variableSymbol = '999';
    expect(bankExpenseMatchSuggestions(ctx.transactions[0]!, ctx, 'app-owner')).toEqual([]);
  });
  it('revalidates a changed expense after displaying suggestions', async () => {
    const { expense, ctx, evolu } = fixture();
    expect(bankExpenseMatchSuggestions(ctx.transactions[0]!, ctx, 'app-owner')).toHaveLength(1);
    expense.total = '20.00';
    await expect(matchLocalBankTransactionToExpense(evolu, 'company-a', 'tx-a', 'expense-a')).rejects.toThrow('bank_expense_match_ineligible');
  });
  it('revalidates financial evidence even on a same-link retry', async () => {
    const { transaction, evolu } = fixture();
    await matchLocalBankTransactionToExpense(evolu, 'company-a', 'tx-a', 'expense-a');
    transaction.currency = 'USD';
    await expect(matchLocalBankTransactionToExpense(evolu, 'company-a', 'tx-a', 'expense-a')).rejects.toThrow('bank_expense_match_ineligible');
  });
});
