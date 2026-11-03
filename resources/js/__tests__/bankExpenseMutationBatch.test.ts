import { afterEach, describe, expect, it, vi } from 'vitest';
import { createEvolu, createId, SimpleName } from '@evolu/common';
import { InvoicingLocalSchema } from '@/evolu/schema';
import { clearRowOwnerIndex, withCompanyOwnerScoping } from '@/evolu/ownerScope';
import { clearCompanyShares, setCompanyShare } from '@/evolu/companyShareRegistry';
import type { OwnerId } from '@evolu/common/local-first';
import { tryAutoMatchBankEmailExpense } from '@/evolu/bankExpensePaymentMatcher';

type Message = { type: string; changes?: Array<{ table: string; ownerId?: string; values: Record<string, unknown> }> };
let sequence = 0;
function createProbe(shared = false) {
  const messages: Message[] = [];
  const deps = {
    console: { ...console, log: vi.fn() },
    createDbWorker: () => ({ postMessage: (message: Message) => messages.push(message), onMessage: vi.fn() }),
    randomBytes: { create: (size: number) => crypto.getRandomValues(new Uint8Array(size)) },
    time: { now: () => Date.now(), nowIso: () => new Date().toISOString() },
    reloadApp: vi.fn(),
  };
  const raw = createEvolu(deps as never)(InvoicingLocalSchema, { name: SimpleName.orThrow(`qaBatch${++sequence}`) });
  const evolu = withCompanyOwnerScoping(raw);
  const companyId = createId(deps as never);
  const ownerId = createId(deps as never) as OwnerId;
  if (shared) setCompanyShare({ companyId, ownerId, role: 'owner', status: 'active', bridgeCompanyId: null });
  const expenseId = createId(deps as never);
  const bank = { id: createId(deps as never), companyId, businessExpenseId: expenseId, matchStatus: 'matched' };
  const expense = { id: expenseId, companyId, status: 'paid', paidAt: '2026-11-03' };
  return { evolu, messages, ownerId, bank, expense };
}
afterEach(() => { clearRowOwnerIndex(); clearCompanyShares(); });

describe('independent real Evolu mutation queue probe', () => {
  it.each([false, true])('runs the actual matcher through the real owner proxy and one mutation queue (shared=%s)', async (shared) => {
    const { evolu, messages, ownerId, bank, expense } = createProbe(shared);
    const transaction = { ...bank, businessExpenseId: null, matchStatus: 'unmatched',
      direction: 'debit', source: 'inbound_email', amount: '19.00', currency: 'EUR',
      bookedAt: '2026-11-03T10:15:30Z', variableSymbol: '00019', counterpartyName: null, reference: null };
    const candidate = { ...expense, status: 'recorded', paidAt: null, cancelledAt: null,
      total: '19.00', currency: 'EUR', variableSymbol: '00019', externalNumber: 'INV-00019' };
    const ctx = { batches: [], transactions: [transaction], matches: [], documents: [], expenses: [candidate] };
    expect(tryAutoMatchBankEmailExpense(evolu, transaction as never, ctx as never)).toBe(true);
    expect(transaction).toMatchObject({ matchStatus: 'matched', businessExpenseId: candidate.id });
    expect(candidate).toMatchObject({ status: 'paid', paidAt: '2026-11-03' });
    await Promise.resolve();
    const mutations = messages.filter((message) => message.type === 'mutate');
    expect(mutations).toHaveLength(1);
    expect(mutations[0].changes?.map((change) => change.table)).toEqual(['bankTransaction', 'expense']);
    expect(mutations[0].changes?.map((change) => change.ownerId)).toEqual(shared ? [ownerId, ownerId] : [undefined, undefined]);
    expect(mutations[0].changes?.[0].values).toMatchObject({ matchStatus: 'matched', businessExpenseId: candidate.id, companyId: transaction.companyId });
    expect(mutations[0].changes?.[1].values).toMatchObject({ status: 'paid', paidAt: '2026-11-03', companyId: transaction.companyId });
  });
  it.each([false, true])('queues both valid writes in one worker transaction (shared=%s)', async (shared) => {
    const { evolu, messages, ownerId, bank, expense } = createProbe(shared);
    expect(evolu.update('bankTransaction', bank as never, { onlyValidate: true }).ok).toBe(true);
    expect(evolu.update('expense', expense as never, { onlyValidate: true }).ok).toBe(true);
    await Promise.resolve();
    expect(messages.filter((message) => message.type === 'mutate')).toHaveLength(0);
    expect(evolu.update('bankTransaction', bank as never).ok).toBe(true);
    expect(evolu.update('expense', expense as never).ok).toBe(true);
    expect(messages.filter((message) => message.type === 'mutate')).toHaveLength(0);
    await Promise.resolve();
    const mutations = messages.filter((message) => message.type === 'mutate');
    expect(mutations).toHaveLength(1);
    expect(mutations[0].changes?.map((change) => change.table)).toEqual(['bankTransaction', 'expense']);
    expect(mutations[0].changes?.map((change) => change.ownerId)).toEqual(shared ? [ownerId, ownerId] : [undefined, undefined]);
  });
  it('rejects invalid prevalidation without sending any mutation', async () => {
    const { evolu, messages, bank, expense } = createProbe(true);
    expect(evolu.update('bankTransaction', bank as never, { onlyValidate: true }).ok).toBe(true);
    const invalidExpense = { ...expense, paidAt: 'x'.repeat(33) };
    expect(evolu.update('expense', invalidExpense as never, { onlyValidate: true }).ok).toBe(false);
    await Promise.resolve();
    expect(messages.filter((message) => message.type === 'mutate')).toHaveLength(0);
  });
  it('aborts the entire queued transaction if the second actual update fails validation', async () => {
    const { evolu, messages, bank, expense } = createProbe(true);
    expect(evolu.update('bankTransaction', bank as never).ok).toBe(true);
    expect(evolu.update('expense', { ...expense, paidAt: 'x'.repeat(33) } as never).ok).toBe(false);
    await Promise.resolve();
    expect(messages.filter((message) => message.type === 'mutate')).toHaveLength(0);
  });
});
