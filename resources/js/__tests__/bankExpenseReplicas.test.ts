// @vitest-environment node
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createExpenseReplicaPair } from './expenseReplicaTestSupport';
import { installBankLocks } from './bankReconciliationTestSupport';
import { clearCompanyShares } from '@/evolu/companyShareRegistry';
import { clearRowOwnerIndex } from '@/evolu/ownerScope';
import { effectiveExpensePayment } from '@/evolu/bankExpenseClaims';
import type { EvoluExpenseRow } from '@/evolu/expenseMap';
import type { EvoluBankTransactionRow } from '@/evolu/bankMap';
import { BankTransactionId, CompanyId, ExpenseId } from '@/evolu/schema';

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
import { activeBankExpenseClaim } from '@/evolu/bankExpenseClaims';
import { loadBankPaymentContext, createLocalExpenseFromBankTransaction, unmatchLocalBankTransaction, releaseLocalExpenseBankClaim } from '@/evolu/bankCrud';
import { tryAutoMatchBankEmailExpense } from '@/evolu/bankExpensePaymentMatcher';
import { markLocalExpensePaid, unmarkLocalExpensePaid, updateLocalExpense } from '@/evolu/expenseCrud';
import { matchLocalBankTransactionToExpense } from '@/evolu/bankExpenseManualMatching';

afterEach(() => { clearCompanyShares(); clearRowOwnerIndex(); vi.unstubAllGlobals(); });

type Pair = Awaited<ReturnType<typeof createExpenseReplicaPair>>;
/** Seed synthetic records, synchronize both independent databases, and disconnect them before conflicting decisions. */
async function seed(pair: Pair, expenses = 2, movements = 1, source = 'csv') {
  const companyId = CompanyId.orThrow(pair.newId());
  const expenseIds = Array.from({ length: expenses }, () => ExpenseId.orThrow(pair.newId()));
  const transactionIds = Array.from({ length: movements }, () => BankTransactionId.orThrow(pair.newId()));
  await pair.mutate(0, 'company', { id: companyId, legalName: 'Synthetic replica company', jurisdiction: 'eu_sk', defaultCurrency: 'EUR', vatStatus: 'none' });
  for (const [index, id] of expenseIds.entries()) await pair.mutate(0, 'expense', {
    id, companyId, internalNumber: `E00${index + 1}`, status: 'recorded', paidAt: null, cancelledAt: null,
    total: '19.00', currency: 'EUR', variableSymbol: '0019', externalNumber: 'INV-0019',
  });
  for (const [index, id] of transactionIds.entries()) await pair.mutate(0, 'bankTransaction', {
    id, companyId, source, recordedDirectionEvidence: source === 'inbound_email' ? 'debit' : null,
    direction: 'debit', amount: '19.00', currency: 'EUR', bookedAt: `2026-11-0${index + 3}T10:00:00Z`,
    variableSymbol: '0019', reference: 'platba kartou', matchStatus: 'unmatched', businessExpenseId: null,
  });
  await pair.synchronize(); pair.disconnect();
  return { companyId, expenseIds, transactionIds };
}
async function raw(pair: Pair, index = 0) { return loadBankPaymentContext(pair.clients[index]!); }
function view(ctx: Awaited<ReturnType<typeof raw>>) {
  return ctx.expenses.map((row) => effectiveExpensePayment(row, ctx.transactions, ctx.matches));
}

describe('independent offline SQLite replicas exchanging real encrypted Evolu protocol messages', () => {
  it.each([[0, 1], [1, 0]])('converges two movements claiming one expense (delivery %j)', async (...order) => {
    const pair = await createExpenseReplicaPair();
    const companyId = pair.newId();
    const expenseId = pair.newId();
    const txIds = [pair.newId(), pair.newId()];
    const rawExpenses = pair.clients[0]!.createQuery((db) => db.selectFrom('expense').selectAll());
    const rawTransactions = pair.clients[0]!.createQuery((db) => db.selectFrom('bankTransaction').selectAll());
    try {
      await pair.mutate(0, 'company', { id: companyId, legalName: 'Synthetic replica company', jurisdiction: 'eu_sk', defaultCurrency: 'EUR', vatStatus: 'none' });
      await pair.mutate(0, 'expense', { id: expenseId, companyId, internalNumber: 'E001', status: 'recorded', paidAt: null,
        total: '19.00', currency: 'EUR', variableSymbol: '0019', externalNumber: 'INV-0019' });
      for (const [index, id] of txIds.entries()) await pair.mutate(0, 'bankTransaction', {
        id, companyId, source: 'csv', direction: 'debit', amount: '19.00', currency: 'EUR', bookedAt: `2026-11-0${index + 3}T10:00:00Z`,
        variableSymbol: '0019', reference: 'INV-0019', matchStatus: 'unmatched', businessExpenseId: null,
      });
      await pair.synchronize();
      expect((await pair.clients[1]!.loadQuery(rawTransactions)).length).toBe(2);
      pair.disconnect();
      // Separate origin managers cannot serialize or see one another's offline decisions.
      installBankLocks();
      await matchLocalBankTransactionToExpense(pair.clients[0]!, companyId, txIds[0]!, expenseId);
      installBankLocks();
      await matchLocalBankTransactionToExpense(pair.clients[1]!, companyId, txIds[1]!, expenseId);
      await pair.synchronize(order as number[]);
      await pair.synchronize([...order].reverse() as number[]);
      const views = [];
      for (const client of pair.clients) {
        const expenses = await client.loadQuery(rawExpenses) as unknown as EvoluExpenseRow[];
        const transactions = await client.loadQuery(rawTransactions) as unknown as EvoluBankTransactionRow[];
        expect(expenses[0]).toMatchObject({ status: 'recorded', paidAt: null });
        const view = effectiveExpensePayment(expenses[0]!, transactions);
        expect(view.status).toBe('paid');
        const { evoluBankTxToApi } = await import('@/evolu/bankMap');
        const txViews = transactions.map((row) => evoluBankTxToApi(row, null, null, expenses[0]!));
        expect(txViews.filter((row) => row.match_status === 'matched')).toHaveLength(1);
        expect(txViews.filter((row) => row.bank_match_conflict && row.match_status === 'unmatched')).toHaveLength(1);
        views.push(txViews.map((row) => [row.id, row.match_status, Boolean(row.bank_match_conflict)]).sort());
      }
      expect(views[0]).toEqual(views[1]);
    } finally { await pair.close(); }
  }, 30_000);
});


describe('reciprocal claims resolve both offline race directions and explicit recovery', () => {
  it.each([[0, 1], [1, 0]])('converges one movement claimed by two expenses, permits orphan recovery, rejects stale unlink (delivery %j)', async (...order) => {
    const pair = await createExpenseReplicaPair();
    try {
      const { companyId, expenseIds, transactionIds } = await seed(pair);
      installBankLocks(); await matchLocalBankTransactionToExpense(pair.clients[0]!, companyId, transactionIds[0]!, expenseIds[0]!);
      const oldA = (await raw(pair, 0)).transactions[0]!.bankPaymentClaimJson!;
      installBankLocks(); await matchLocalBankTransactionToExpense(pair.clients[1]!, companyId, transactionIds[0]!, expenseIds[1]!);
      const oldB = (await raw(pair, 1)).transactions[0]!.bankPaymentClaimJson!;
      await pair.synchronize(order); await pair.synchronize([...order].reverse());
      const merged = await raw(pair);
      expect(view(merged).filter((row) => row.status === 'paid')).toHaveLength(1);
      const winner = merged.transactions[0]!;
      const winnerExpense = merged.expenses.find((row) => row.id === winner.businessExpenseId)!;
      const loser = merged.expenses.find((row) => row.id !== winner.businessExpenseId)!;
      expect(effectiveExpensePayment(loser, merged.transactions)).toMatchObject({ status: 'recorded', bankMatchConflict: 1 });
      const oldClaim = winner.bankPaymentClaimJson === oldA ? oldB : oldA;
      installBankLocks();
      await expect(unmatchLocalBankTransaction(pair.clients[0]!, winner.id, merged, { companyId, claim: oldClaim })).rejects.toThrow('bank_expense_claim_changed');
      expect((await raw(pair)).transactions[0]!.bankPaymentClaimJson).toBe(winner.bankPaymentClaimJson);
      await releaseLocalExpenseBankClaim(pair.clients[0]!, companyId as never, loser.id, loser.bankPaymentClaimJson!);
      const recovered = await raw(pair);
      expect(recovered.expenses.find((row) => row.id === loser.id)?.bankPaymentClaimJson).toBeNull();
      expect(activeBankExpenseClaim(recovered.transactions[0]!, recovered.expenses.find((row) => row.id === winnerExpense.id)!)).not.toBeNull();
      await unmatchLocalBankTransaction(pair.clients[0]!, winner.id, recovered, { companyId, claim: winner.bankPaymentClaimJson! });
      const detached = await raw(pair);
      expect(detached.expenses.find((row) => row.id === winnerExpense.id)).toMatchObject({ status: 'recorded', paidAt: null });
      expect(view(detached).find((row) => row.id === winnerExpense.id)).toMatchObject({ status: 'paid', paidAt: '2026-11-03', bankMatchConflict: 0 });
      await pair.synchronize();
      expect(view(await raw(pair, 1)).find((row) => row.id === winnerExpense.id)?.status).toBe('paid');
      await unmarkLocalExpensePaid(pair.clients[0]!, winnerExpense.id);
      expect(view(await raw(pair)).find((row) => row.id === winnerExpense.id)).toMatchObject({ status: 'recorded', paidAt: null });
    } finally { await pair.close(); }
  }, 30_000);

  it('automatic and manual offline matching use the same single-cell selection invariant', async () => {
    const pair = await createExpenseReplicaPair();
    try {
      const { companyId, expenseIds, transactionIds } = await seed(pair, 1, 2, 'inbound_email');
      const a = await raw(pair, 0);
      installBankLocks(); expect(await tryAutoMatchBankEmailExpense(pair.clients[0]!, a.transactions.find((row) => row.id === transactionIds[0])!, a)).toBe(true);
      installBankLocks(); await matchLocalBankTransactionToExpense(pair.clients[1]!, companyId, transactionIds[1]!, expenseIds[0]!);
      await pair.synchronize();
      const merged = await raw(pair);
      expect(merged.expenses[0]).toMatchObject({ status: 'recorded', paidAt: null });
      expect(merged.transactions.filter((tx) => activeBankExpenseClaim(tx, merged.expenses[0]!))).toHaveLength(1);
      expect(view(merged)[0]?.status).toBe('paid');
    } finally { await pair.close(); }
  }, 30_000);

  it.each(['create', 'manual'] as const)('a concurrent CREATE/%s decision cannot materialize two bank-paid expenses', async (other) => {
    const pair = await createExpenseReplicaPair();
    try {
      const { companyId, expenseIds, transactionIds } = await seed(pair, other === 'manual' ? 1 : 0);
      installBankLocks();
      await createLocalExpenseFromBankTransaction(pair.clients[0]!, transactionIds[0]!, {
        issue_date: '2026-11-05', total: '25.00', currency: 'USD', title: 'Explicit override', mark_paid: true,
      }, await raw(pair, 0));
      installBankLocks();
      if (other === 'create') await createLocalExpenseFromBankTransaction(pair.clients[1]!, transactionIds[0]!, {
        issue_date: '2026-11-05', total: '30.00', currency: 'EUR', title: 'Other explicit override', mark_paid: true,
      }, await raw(pair, 1));
      else await matchLocalBankTransactionToExpense(pair.clients[1]!, companyId, transactionIds[0]!, expenseIds[0]!);
      await pair.synchronize([1, 0]); await pair.synchronize([0, 1]);
      for (const index of [0, 1]) {
        const merged = await raw(pair, index);
        expect(merged.expenses.every((row) => row.status === 'recorded' && row.paidAt === null)).toBe(true);
        expect(view(merged).filter((row) => row.status === 'paid')).toHaveLength(1);
        expect(view(merged).filter((row) => row.bankMatchConflict === 1)).toHaveLength(1);
      }
    } finally { await pair.close(); }
  }, 30_000);

  it('unpaid CREATE stays unpaid after unlink and permits subsequent financial edits', async () => {
    const pair = await createExpenseReplicaPair();
    try {
      const { companyId, transactionIds } = await seed(pair, 0);
      installBankLocks();
      const created = await createLocalExpenseFromBankTransaction(pair.clients[0]!, transactionIds[0]!, {
        issue_date: '2026-11-05', total: '25.00', currency: 'USD', title: 'Unpaid override', mark_paid: false,
      }, await raw(pair));
      const linked = await raw(pair);
      expect(view(linked)[0]).toMatchObject({ status: 'recorded', paidAt: null, bankMatchConflict: 0 });
      await unmatchLocalBankTransaction(pair.clients[0]!, transactionIds[0]!, linked, { companyId, claim: linked.transactions[0]!.bankPaymentClaimJson! });
      const detached = await raw(pair);
      expect(view(detached)[0]).toMatchObject({ status: 'recorded', paidAt: null });
      expect(detached.expenses[0]?.bankPaymentClaimJson).toBeNull();
      expect((await updateLocalExpense(pair.clients[0]!, created.id, { issue_date: '2026-11-05', total: '26.00', currency: 'EUR' })).ok).toBe(true);
    } finally { await pair.close(); }
  }, 30_000);

  it('winning unlink never overwrites independently marked raw paid status/date after sync', async () => {
    const pair = await createExpenseReplicaPair();
    try {
      const { companyId, transactionIds, expenseIds } = await seed(pair, 1);
      installBankLocks(); await matchLocalBankTransactionToExpense(pair.clients[0]!, companyId, transactionIds[0]!, expenseIds[0]!);
      installBankLocks(); await markLocalExpensePaid(pair.clients[1]!, expenseIds[0]! as never);
      await pair.synchronize();
      const before = await raw(pair);
      const independentDate = before.expenses[0]!.paidAt;
      expect(before.expenses[0]?.status).toBe('paid');
      await unmatchLocalBankTransaction(pair.clients[0]!, transactionIds[0]!, before,
        { companyId, claim: before.transactions[0]!.bankPaymentClaimJson! });
      await pair.synchronize();
      for (const index of [0, 1]) expect((await raw(pair, index)).expenses[0]).toMatchObject({ status: 'paid', paidAt: independentDate });
    } finally { await pair.close(); }
  }, 30_000);
});
