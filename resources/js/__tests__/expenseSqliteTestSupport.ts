import { Worker } from 'node:worker_threads';
import { createConsole, createEvolu, createId, createRandom, createRandomBytes, createTime, SimpleName } from '@evolu/common';
import { withCompanyOwnerScoping } from '@/evolu/ownerScope';
import { InvoicingLocalSchema } from '@/evolu/schema';

type Snapshot = { expense: Record<string, unknown>[]; bankTransaction: Record<string, unknown>[] };
type MutationMessage = { type: string; changes?: { table: string }[] };
let sequence = 0;

/** Connect the real Evolu client to its real worker/SQLite without a relay or disk database. */
export async function createExpenseSqliteProbe() {
  const worker = new Worker(new URL('./expenseSqliteWorker.mjs', import.meta.url));
  try {
    let receive: ((message: unknown) => void) | undefined;
    let snapshotResolve: ((snapshot: Snapshot) => void) | undefined;
    const mutations: MutationMessage[] = [];
    worker.on('message', (message: { response?: unknown; snapshot?: Snapshot }) => {
      if (message.snapshot) snapshotResolve?.(message.snapshot);
      else receive?.(message.response);
    });
    const deps = {
      console: createConsole({ enableLogging: false }), randomBytes: createRandomBytes(), random: createRandom(), time: createTime(), reloadApp: () => {},
      createDbWorker: () => ({
        postMessage: (message: MutationMessage) => { if (message.type === 'mutate') mutations.push(message); worker.postMessage(structuredClone(message)); },
        onMessage: (listener: (message: unknown) => void) => { receive = listener; },
      }),
    };
    const evolu = withCompanyOwnerScoping(createEvolu(deps as never)(InvoicingLocalSchema, {
      name: SimpleName.orThrow(`ExpenseSqlite${++sequence}`), transports: [], inMemory: true,
    }));
    await evolu.appOwner;
    const ids = { company: createId(deps), expense: createId(deps), transaction: createId(deps) };
    const mutate = (table: string, patch: Record<string, unknown>) => new Promise<void>((resolve, reject) => {
      const result = evolu.upsert(table as never, patch as never, { onComplete: resolve });
      if (!result.ok) reject(new Error('synthetic_fixture_validation_failed'));
    });
    await mutate('company', { id: ids.company, legalName: 'Synthetic company', jurisdiction: 'eu_sk', defaultCurrency: 'EUR', vatStatus: 'none' });
    await mutate('expense', { id: ids.expense, companyId: ids.company, internalNumber: 'EXP001', title: 'Synthetic hosting',
      externalNumber: 'INV-0019', variableSymbol: '0019', issueDate: '2026-11-01', total: '19.00', currency: 'EUR',
      status: 'recorded', paidAt: null, cancelledAt: null });
    await mutate('bankTransaction', { id: ids.transaction, companyId: ids.company, source: 'csv', direction: 'debit',
      amount: '19.00', currency: 'EUR', bookedAt: '2026-11-05T10:00:00Z', variableSymbol: '0019',
      reference: 'INV-0019', matchStatus: 'unmatched', businessExpenseId: null });
    return {
      evolu, ids, mutate, mutations,
      failNextCommit: () => worker.postMessage({ control: 'failNextCommit' }),
      driftNextCommit: (table: 'expense' | 'bankTransaction', field: string, value: string) => worker.postMessage({ control: 'driftNextCommit', table, field, value }),
      snapshot: () => new Promise<Snapshot>((resolve) => { snapshotResolve = resolve; worker.postMessage({ control: 'snapshot' }); }),
      close: () => worker.terminate(),
    };
  } catch (error) {
    try {
      await worker.terminate();
    } catch {
      // Keep the initialization failure even if cleanup itself fails.
    }
    throw error;
  }
}
