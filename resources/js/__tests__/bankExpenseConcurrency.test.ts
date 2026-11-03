import { expect, it, vi } from 'vitest';
vi.mock('@/evolu/client', () => ({
  allBankImportBatchesQuery: 'batches', allBankTransactionsQuery: 'transactions',
  allBankTransactionMatchesQuery: 'matches', allDocumentsQuery: 'documents', allExpensesQuery: 'expenses',
}));
vi.mock('@/evolu/documentCrud', () => ({ markLocalDocumentPaidWithAmount: vi.fn(), unmarkLocalDocumentPaid: vi.fn() }));
import { autoMatchLocalBatchAsync, importInboundServerTransactionsAsync } from '@/evolu/bankCrud';

function fixture(existing = false) {
  const expense = { id: 'expense-a', companyId: 'company-a', status: 'recorded', paidAt: null, cancelledAt: null,
    total: '19.00', currency: 'EUR', variableSymbol: '0019', externalNumber: 'INV-0019' };
  const state: Record<string, Array<Record<string, unknown>>> = { batches: [], transactions: [], matches: [], documents: [], expenses: [expense] };
  if (existing) state.transactions.push({ id: 'existing-a', companyId: 'company-a', bankImportBatchId: 'existing-batch',
    bookedAt: '2026-11-03', amount: '19.00', currency: 'EUR', direction: 'debit', matchStatus: 'unmatched',
    businessExpenseId: null, variableSymbol: '0019', reference: null, counterpartyName: null, dedupeHash: 'oldhash', source: 'inbound_email' });
  const tables = { bankImportBatch: state.batches, bankTransaction: state.transactions, expense: state.expenses };
  let sequence = 0;
  const evolu = {
    loadQuery: vi.fn(async (key: string) => state[key].map((row) => ({ ...row }))),
    insert: vi.fn((table: keyof typeof tables, props: Record<string, unknown>) => {
      const id = `insert-${++sequence}`;
      tables[table].push({ ...props, id });
      return { ok: true, value: { id } };
    }),
    update: vi.fn((table: keyof typeof tables, patch: Record<string, unknown>, options?: { onlyValidate?: boolean }) => {
      if (!options?.onlyValidate) Object.assign(tables[table].find((row) => row.id === patch.id) ?? {}, patch);
      return { ok: true, value: { id: patch.id } };
    }),
  };
  const row = { booked_at: '2026-11-03', amount: '19.00', currency: 'EUR', direction: 'debit', recorded_direction: 'debit', variable_symbol: '0019' };
  return { evolu, row, state, expense };
}
it('does not link two concurrently imported debit rows to one local expense', async () => {
  const { evolu, row, state, expense } = fixture();
  await Promise.all([
    importInboundServerTransactionsAsync(evolu as never, 'company-a' as never, [{ ...row, dedupe_hash: 'hash-a' }]),
    importInboundServerTransactionsAsync(evolu as never, 'company-a' as never, [{ ...row, dedupe_hash: 'hash-b' }]),
  ]);
  expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
});
it('does not link a new import and an explicitly matched old batch to one expense concurrently', async () => {
  const { evolu, row, state, expense } = fixture(true);
  await Promise.all([
    importInboundServerTransactionsAsync(evolu as never, 'company-a' as never, [{ ...row, dedupe_hash: 'newhash' }]),
    autoMatchLocalBatchAsync(evolu as never, 'existing-batch' as never),
  ]);
  expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
});
it('releases a failed operation so the next queued import can reconcile', async () => {
  const { evolu, row, state, expense } = fixture();
  evolu.loadQuery.mockRejectedValueOnce(new Error('synthetic query failure'));
  const results = await Promise.allSettled([
    importInboundServerTransactionsAsync(evolu as never, 'company-a' as never, [{ ...row, dedupe_hash: 'failedhash' }]),
    importInboundServerTransactionsAsync(evolu as never, 'company-a' as never, [{ ...row, dedupe_hash: 'nexthash' }]),
  ]);
  expect(results[0].status).toBe('rejected');
  expect(results[1]).toMatchObject({ status: 'fulfilled', value: { imported: 1, auto_matched: 1 } });
  expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
  expect(state.transactions.some((transaction) => transaction.dedupeHash === 'failedhash')).toBe(false);
});
