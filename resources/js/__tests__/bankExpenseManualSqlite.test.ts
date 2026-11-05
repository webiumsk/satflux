// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createExpenseSqliteProbe } from './expenseSqliteTestSupport';
import { clearRowOwnerIndex } from '@/evolu/ownerScope';
import { clearCompanyShares } from '@/evolu/companyShareRegistry';
import { installBankLocks } from './bankReconciliationTestSupport';

vi.mock('@/evolu/client', async () => {
  const { createQuery } = await import('@evolu/common/local-first');
  const { sqliteTrue } = await import('@evolu/common');
  const tables = { Batches: 'bankImportBatch', Transactions: 'bankTransaction', Matches: 'bankTransactionMatch', Documents: 'document', Expenses: 'expense' } as const;
  const queries: Record<string, unknown> = {};
  for (const [suffix, table] of Object.entries(tables)) {
    queries[`bankReconciliation${suffix}Query`] = createQuery((db) => db.selectFrom(table).selectAll().where('isDeleted', 'is not', sqliteTrue).where('id', 'is not', null));
    const name = suffix === 'Batches' ? 'BankImportBatches' : suffix === 'Transactions' ? 'BankTransactions' : suffix === 'Matches' ? 'BankTransactionMatches' : suffix;
    queries[`all${name}Query`] = createQuery((db) => db.selectFrom(table).selectAll().where('isDeleted', 'is not', sqliteTrue));
  }
  return queries;
});
import { matchLocalBankTransactionToExpense } from '@/evolu/bankExpenseManualMatching';

beforeEach(() => { installBankLocks(); });
afterEach(() => { clearCompanyShares(); clearRowOwnerIndex(); vi.unstubAllGlobals(); });

describe('manual expense matching with the real Evolu worker and SQLite', () => {
  it('persists both linked/paid records in one worker transaction and survives fresh database reads', async () => {
    const probe = await createExpenseSqliteProbe();
    try {
      const before = probe.mutations.length;
      await matchLocalBankTransactionToExpense(probe.evolu, probe.ids.company, probe.ids.transaction, probe.ids.expense);
      const state = await probe.snapshot();
      expect(state.bankTransaction[0]).toMatchObject({ businessExpenseId: probe.ids.expense, matchStatus: 'matched' });
      expect(state.expense[0]).toMatchObject({ status: 'paid', paidAt: '2026-11-05' });
      const financial = probe.mutations.slice(before).filter((message) => message.changes?.some((change) => change.table !== '_expenseReconciliation'));
      expect(financial.map((message) => message.changes?.map((change) => change.table))).toEqual([['bankTransaction', 'expense']]);
      await matchLocalBankTransactionToExpense(probe.evolu, probe.ids.company, probe.ids.transaction, probe.ids.expense);
      expect((await probe.snapshot()).expense[0]?.paidAt).toBe('2026-11-05');
    } finally { await probe.close(); }
  });

  it('rejects a real schema failure without committing either half of the financial batch', async () => {
    const probe = await createExpenseSqliteProbe();
    const client = new Proxy(probe.evolu, { get(target, property) {
      if (property !== 'update') return Reflect.get(target, property);
      return (table: string, patch: Record<string, unknown>, options?: { onlyValidate?: boolean }) => target.update(table as never,
        (table === 'expense' && !options?.onlyValidate ? { ...patch, paidAt: 'x'.repeat(33) } : patch) as never, options);
    } });
    try {
      await expect(matchLocalBankTransactionToExpense(client, probe.ids.company, probe.ids.transaction, probe.ids.expense)).rejects.toThrow('expense_mutation_batch_rejected');
      const state = await probe.snapshot();
      expect(state.bankTransaction[0]).toMatchObject({ businessExpenseId: null, matchStatus: 'unmatched' });
      expect(state.expense[0]).toMatchObject({ status: 'recorded', paidAt: null });
    } finally { await probe.close(); }
  });

  it('reports an actual SQLite COMMIT failure as unknown and never claims paid success', async () => {
    const probe = await createExpenseSqliteProbe();
    let failed = false;
    const client = new Proxy(probe.evolu, { get(target, property) {
      if (property !== 'update') return Reflect.get(target, property);
      return (table: string, patch: Record<string, unknown>, options?: { onlyValidate?: boolean }) => {
        if (table === 'expense' && !options?.onlyValidate && !failed) { failed = true; probe.failNextCommit(); }
        return target.update(table as never, patch as never, options);
      };
    } });
    try {
      await expect(matchLocalBankTransactionToExpense(client, probe.ids.company, probe.ids.transaction, probe.ids.expense)).rejects.toThrow('bank_reconciliation_outcome_unknown');
      const state = await probe.snapshot();
      expect(state.bankTransaction[0]).toMatchObject({ businessExpenseId: null, matchStatus: 'unmatched' });
      expect(state.expense[0]).toMatchObject({ status: 'recorded', paidAt: null });
    } finally { await probe.close(); }
  });
  it.each([
    ['expense', 'total', '20.00'], ['expense', 'currency', 'USD'], ['expense', 'variableSymbol', '999'],
    ['bankTransaction', 'amount', '20.00'], ['bankTransaction', 'currency', 'USD'],
    ['bankTransaction', 'variableSymbol', '999'], ['bankTransaction', 'bookedAt', '2026-11-06T10:00:00Z'],
    ['bankTransaction', 'businessExpenseId', 'AAAAAAAAAAAAAAAAAAAAAA'],
  ] as const)('rejects post-COMMIT evidence drift in %s.%s without compensating committed data', async (table, field, value) => {
    const probe = await createExpenseSqliteProbe();
    let injected = false;
    const client = new Proxy(probe.evolu, { get(target, property) {
      if (property !== 'update') return Reflect.get(target, property);
      return (targetTable: string, patch: Record<string, unknown>, options?: { onlyValidate?: boolean }) => {
        if (targetTable === 'expense' && !options?.onlyValidate && !injected) {
          injected = true; probe.driftNextCommit(table, field, value);
        }
        return target.update(targetTable as never, patch as never, options);
      };
    } });
    try {
      await expect(matchLocalBankTransactionToExpense(client, probe.ids.company, probe.ids.transaction, probe.ids.expense)).rejects.toThrow('bank_reconciliation_state_changed');
      const state = await probe.snapshot();
      expect(state[table][0]?.[field]).toBe(value);
      // The financial transaction committed; recovery must not silently undo either record.
      expect(state.expense[0]?.status).toBe('paid');
    } finally { await probe.close(); }
  });

});
