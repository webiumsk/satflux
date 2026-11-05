// @vitest-environment node
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createExpenseSqliteProbe } from './expenseSqliteTestSupport';
import { effectiveExpensePayment } from '@/evolu/bankExpenseClaims';
import type { EvoluExpenseRow } from '@/evolu/expenseMap';
import type { EvoluBankTransactionRow } from '@/evolu/bankMap';
import { clearRowOwnerIndex } from '@/evolu/ownerScope';
import { clearCompanyShares } from '@/evolu/companyShareRegistry';
import { installBankLocks } from './bankReconciliationTestSupport';

vi.mock('@/evolu/documentCrud', () => ({ markLocalDocumentPaidWithAmount: vi.fn(), unmarkLocalDocumentPaid: vi.fn() }));
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
import { createLocalExpenseFromBankTransaction, loadBankPaymentContext } from '@/evolu/bankCrud';
import { cancelLocalExpense, markLocalExpensePaid, unmarkLocalExpensePaid, updateLocalExpense } from '@/evolu/expenseCrud';
import { bulkCancelLocalExpenses, bulkMarkPaidLocalExpenses } from '@/evolu/expenseBulkLocal';

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
      expect(state.expense[0]).toMatchObject({ status: 'recorded', paidAt: null });
      expect(effectiveExpensePayment(state.expense[0] as unknown as EvoluExpenseRow, state.bankTransaction as unknown as EvoluBankTransactionRow[])).toMatchObject({ status: 'paid', paidAt: '2026-11-05' });
      const financial = probe.mutations.slice(before).filter((message) => message.changes?.some((change) => change.table !== '_expenseReconciliation'));
      expect(financial.map((message) => message.changes?.map((change) => change.table))).toEqual([['bankTransaction', 'expense']]);
      await matchLocalBankTransactionToExpense(probe.evolu, probe.ids.company, probe.ids.transaction, probe.ids.expense);
      expect((await probe.snapshot()).expense[0]?.paidAt).toBeNull();
    } finally { await probe.close(); }
  });

  it('rejects a real schema failure without committing either half of the financial batch', async () => {
    const probe = await createExpenseSqliteProbe();
    const client = new Proxy(probe.evolu, { /** Inject an invalid expense field at the real write boundary to prove whole-batch schema rejection. */ get(target, property) {
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
    const client = new Proxy(probe.evolu, { /** Arm a real SQLite COMMIT failure when the first expense write reaches the worker boundary. */ get(target, property) {
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
    const client = new Proxy(probe.evolu, { /** Schedule post-COMMIT evidence drift while forwarding the actual guarded mutation unchanged. */ get(target, property) {
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
      expect(state.expense[0]).toMatchObject({ status: 'recorded', paidAt: null });
      expect(effectiveExpensePayment(state.expense[0] as unknown as EvoluExpenseRow, state.bankTransaction as unknown as EvoluBankTransactionRow[])).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
    } finally { await probe.close(); }
  });

  it('rejects linked amount/currency/status changes while preserving canonical equal edits and nonfinancial fields', async () => {
    const probe = await createExpenseSqliteProbe();
    try {
      await matchLocalBankTransactionToExpense(probe.evolu, probe.ids.company, probe.ids.transaction, probe.ids.expense);
      const payload = { issue_date: '2026-11-01', external_number: 'INV-0019', variable_symbol: '0019', total: '19.004', currency: 'eur', title: 'Updated note title' };
      expect((await updateLocalExpense(probe.evolu, probe.ids.expense as never, payload)).ok).toBe(true);
      expect((await probe.snapshot()).expense[0]).toMatchObject({ total: '19.00', currency: 'EUR', title: 'Updated note title' });
      await expect(updateLocalExpense(probe.evolu, probe.ids.expense as never, { ...payload, total: '20.00' })).rejects.toThrow('expense_linked_payment');
      await expect(updateLocalExpense(probe.evolu, probe.ids.expense as never, { ...payload, currency: 'USD' })).rejects.toThrow('expense_linked_payment');
      await expect(unmarkLocalExpensePaid(probe.evolu, probe.ids.expense as never)).rejects.toThrow('expense_linked_payment');
      await expect(cancelLocalExpense(probe.evolu, probe.ids.expense as never)).rejects.toThrow('expense_linked_payment');
      const state = await probe.snapshot();
      expect(effectiveExpensePayment(state.expense[0] as unknown as EvoluExpenseRow, state.bankTransaction as unknown as EvoluBankTransactionRow[]).status).toBe('paid');
      expect(state.expense[0]).toMatchObject({ status: 'recorded', paidAt: null, total: '19.00', currency: 'EUR' });
    } finally { await probe.close(); }
  });

  it('bulk actions skip a fresh effective-paid active claim without manufacturing independent payment history', async () => {
    const probe = await createExpenseSqliteProbe();
    try {
      const staleTargets = (await loadBankPaymentContext(probe.evolu)).expenses;
      await matchLocalBankTransactionToExpense(probe.evolu, probe.ids.company, probe.ids.transaction, probe.ids.expense);
      expect(await bulkMarkPaidLocalExpenses(probe.evolu, staleTargets)).toEqual({ processed: 0, skipped: 1 });
      expect(await bulkCancelLocalExpenses(probe.evolu, staleTargets)).toEqual({ processed: 0, skipped: 1 });
      expect((await markLocalExpensePaid(probe.evolu, probe.ids.expense as never)).ok).toBe(true);
      expect((await probe.snapshot()).expense[0]).toMatchObject({ status: 'recorded', paidAt: null, cancelledAt: null });
    } finally { await probe.close(); }
  });

  it.each(['0.00', '-19.00'])('permits unchanged signed/zero CREATE bookkeeping %s title edits and rejects value/sign changes', async (total) => {
    const probe = await createExpenseSqliteProbe();
    try {
      await new Promise<void>((onComplete) => probe.evolu.update('bankTransaction', { id: probe.ids.transaction as never, reference: 'platba kartou' }, { onComplete }));
      const created = await createLocalExpenseFromBankTransaction(probe.evolu, probe.ids.transaction as never,
        { issue_date: '2026-11-05', total, currency: 'USD', mark_paid: true }, await loadBankPaymentContext(probe.evolu));
      const payload = { issue_date: '2026-11-05', total, currency: 'usd', variable_symbol: '0019', title: 'Changed bookkeeping title' };
      expect((await updateLocalExpense(probe.evolu, created.id, payload)).ok).toBe(true);
      await expect(updateLocalExpense(probe.evolu, created.id, { ...payload, total: '19.00' })).rejects.toThrow('expense_linked_payment');
      const state = await probe.snapshot();
      const row = state.expense.find((candidate) => candidate.id === created.id) as unknown as EvoluExpenseRow;
      expect(row).toMatchObject({ total, currency: 'USD', title: 'Changed bookkeeping title', status: 'recorded', paidAt: null });
      expect(effectiveExpensePayment(row, state.bankTransaction as unknown as EvoluBankTransactionRow[])).toMatchObject({ status: 'paid', bankMatchConflict: 0 });
    } finally { await probe.close(); }
  });

  it('persists maximum schema-accepted escaped evidence in reciprocal claim cells without truncation', async () => {
    const probe = await createExpenseSqliteProbe();
    try {
      const reference = '\u0001'.repeat(4000);
      const counterpartyName = '\u0002'.repeat(255);
      await new Promise<void>((onComplete) => probe.evolu.update('bankTransaction', {
        id: probe.ids.transaction as never, reference, counterpartyName,
      }, { onComplete }));
      await matchLocalBankTransactionToExpense(probe.evolu, probe.ids.company, probe.ids.transaction, probe.ids.expense);
      const state = await probe.snapshot();
      const expense = state.expense[0] as unknown as EvoluExpenseRow;
      const transaction = state.bankTransaction[0] as unknown as EvoluBankTransactionRow;
      expect(expense.bankPaymentClaimJson).toBe(transaction.bankPaymentClaimJson);
      expect(JSON.parse(expense.bankPaymentClaimJson!)).toMatchObject({ reference, counterpartyName });
      expect(effectiveExpensePayment(expense, [transaction]).status).toBe('paid');
      expect(probe.evolu.update('expense', { id: probe.ids.expense as never,
        bankPaymentClaimJson: 'x'.repeat(32769),
      }, { onlyValidate: true }).ok).toBe(false);
    } finally { await probe.close(); }
  });

  it('CREATE reports post-handler source drift as changed, retaining recorded conflict evidence without compensation', async () => {
    const probe = await createExpenseSqliteProbe();
    let injected = false;
    const client = new Proxy(probe.evolu, { /** Arm source drift at the new expense upsert boundary to test CREATE post-handler verification. */ get(target, property) {
      if (property !== 'upsert') return Reflect.get(target, property);
      return (table: string, patch: Record<string, unknown>, options?: { onlyValidate?: boolean }) => {
        if (table === 'expense' && !options?.onlyValidate && !injected) {
          injected = true; probe.driftNextCommit('bankTransaction', 'amount', '20.00');
        }
        return target.upsert(table as never, patch as never, options);
      };
    } });
    try {
      // Preserve the existing explicit-create debit guesser eligibility in this control.
      await new Promise<void>((onComplete) => probe.evolu.update('bankTransaction', { id: probe.ids.transaction as never, reference: 'platba kartou' }, { onComplete }));
      const context = await loadBankPaymentContext(probe.evolu);
      await expect(createLocalExpenseFromBankTransaction(client, probe.ids.transaction as never,
        { issue_date: '2026-11-05', total: '19.00', currency: 'EUR', mark_paid: true }, context))
        .rejects.toThrow('bank_reconciliation_state_changed');
      expect(context.expenses).toHaveLength(1);
      const state = await probe.snapshot();
      const transaction = state.bankTransaction[0] as unknown as EvoluBankTransactionRow;
      const created = state.expense.find((row) => row.id === transaction.businessExpenseId) as unknown as EvoluExpenseRow;
      expect(injected).toBe(true);
      expect(transaction).toMatchObject({ amount: '20.00', matchStatus: 'matched' });
      expect(created).toMatchObject({ status: 'recorded', paidAt: null });
      expect(effectiveExpensePayment(created, [transaction])).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
    } finally { await probe.close(); }
  });

});
