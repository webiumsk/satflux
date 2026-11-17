import { describe, expect, it } from 'vitest';
import { guessDirectionFromAmountAndHints, resolvedBankDirection } from '@/evolu/bankDirectionGuesser';
import { buildBankSummary, evoluBankTxToApi, type EvoluBankTransactionRow } from '@/evolu/bankMap';
import type { BankTransactionDirection } from '@/evolu/schema';

function movement(direction: BankTransactionDirection, patch: Partial<EvoluBankTransactionRow> = {}): EvoluBankTransactionRow {
  return {
    id: `synthetic-${direction}`, companyId: 'synthetic-company', bankImportBatchId: null,
    bookedAt: '2026-11-16T12:00:00.000Z', amount: '42.50', currency: 'EUR', direction,
    matchStatus: 'unmatched', businessExpenseId: null, variableSymbol: '20260042',
    constantSymbol: null, specificSymbol: null,
    counterpartyName: 'Platba 0900/000000-1234567890', counterpartyIban: null,
    reference: 'Synthetic invoice 20260042', bankTransactionId: null, dedupeHash: null, source: 'csv',
    ...patch,
  } as EvoluBankTransactionRow;
}

describe('stored bank movement direction', () => {
  it.each(['debit', 'credit'] as const)('preserves recorded %s when non-empty payment text has no directional evidence', (direction) => {
    const row = movement(direction);
    expect(resolvedBankDirection(row)).toBe(direction);
    expect(evoluBankTxToApi(row, null, null, null)).toMatchObject({ direction, recorded_direction: direction });
    expect(row).toMatchObject({ direction, amount: '42.50' });
  });

  it.each([null, '', '   ', 'Synthetic supplier'])('keeps a debit with generic counterparty %s and no reference', (counterpartyName) => {
    expect(resolvedBankDirection(movement('debit', { counterpartyName, reference: null }))).toBe('debit');
  });

  it('counts generic outgoing and incoming movements on their recorded sides of the summary', () => {
    const summary = buildBankSummary([movement('debit'), movement('credit', { amount: '100.00' })]);
    expect(summary).toMatchObject({
      credit_count: 1, credit_total: '100.00', debit_count: 1, debit_total: '42.50', balance: '57.50',
      by_currency: [{ currency: 'EUR', credit_count: 1, credit_total: '100.00', debit_count: 1, debit_total: '42.50', balance: '57.50' }],
    });
  });

  it('retains legacy explicit debit text correction without changing recorded payment evidence', () => {
    const row = movement('credit', { reference: 'Debet na ucte - synthetic bank message' });
    expect(resolvedBankDirection(row)).toBe('debit');
    expect(evoluBankTxToApi(row, null, null, null)).toMatchObject({ direction: 'debit', recorded_direction: 'credit' });
  });

  it('retains explicit credit text correction', () => {
    expect(resolvedBankDirection(movement('debit', { reference: 'Kredit na ucte - synthetic bank message' }))).toBe('credit');
  });

  it('keeps conflicting inbound provenance unverified after correcting the display', () => {
    const row = movement('debit', { source: 'inbound_email', recordedDirectionEvidence: 'credit' });
    expect(evoluBankTxToApi(row, null, null, null)).toMatchObject({ direction: 'debit', recorded_direction: 'unverified' });
  });
});

describe('raw bank import direction', () => {
  it('still falls back to signed amounts when importing generic text', () => {
    expect(guessDirectionFromAmountAndHints(-42.50, 'Synthetic invoice')).toBe('debit');
    expect(guessDirectionFromAmountAndHints(42.50, 'Synthetic invoice')).toBe('credit');
  });

  it('preserves explicit Wise directions and legacy debit hints', () => {
    expect(guessDirectionFromAmountAndHints(42.50, 'OUT', 'Synthetic supplier')).toBe('debit');
    expect(guessDirectionFromAmountAndHints(42.50, 'IN', 'Synthetic customer')).toBe('credit');
    expect(guessDirectionFromAmountAndHints(42.50, 'EUR NAKUP POS')).toBe('debit');
  });
});
