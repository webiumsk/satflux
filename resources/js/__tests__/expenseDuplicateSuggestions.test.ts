import { describe, expect, it } from 'vitest';
import { expenseDuplicateReason, localExpenseDuplicateSuggestions } from '@/evolu/expenseDuplicateSuggestions';
import type { EvoluExpenseRow } from '@/evolu/expenseMap';

const draft = { external_number: 'INV-001', title: 'Hosting fee', issue_date: '2026-11-05', total: '19.00', currency: 'EUR' };
function expense(patch: Record<string, unknown> = {}): EvoluExpenseRow {
  return { id: 'expense-a', companyId: 'company-a', ownerId: 'owner-a', internalNumber: 'EXP001',
    externalNumber: 'INV-001', title: 'Hosting fee', issueDate: '2026-11-05', total: '19.00', currency: 'EUR',
    status: 'recorded', cancelledAt: null, ...patch } as unknown as EvoluExpenseRow;
}

describe('advisory duplicate expense evidence', () => {
  it('normalizes edge whitespace/case without removing document-number punctuation or leading zeros', () => {
    expect(expenseDuplicateReason({ ...draft, external_number: ' inv-001 ' }, expense())).toBe('external_number');
    expect(expenseDuplicateReason({ ...draft, external_number: 'INV-1', title: 'Different' }, expense())).toBeNull();
    expect(expenseDuplicateReason({ ...draft, external_number: 'INV001', title: 'Different' }, expense())).toBeNull();
  });
  it('uses exact title/date/amount evidence when no document number matches', () => {
    expect(expenseDuplicateReason({ ...draft, external_number: '', title: ' Hosting\t fee ' }, expense())).toBe('title_date_amount');
    expect(expenseDuplicateReason({ ...draft, external_number: '', issue_date: '2026-11-06' }, expense())).toBeNull();
  });
  it.each(['19oops', '1.9e1', '-19.00', '0.00', '19.001', '18.99'])('declines invalid or different total %s', (total) => {
    expect(expenseDuplicateReason({ ...draft, total }, expense())).toBeNull();
  });
  it.each(['USD', '', 'EU', 'EURO'])('declines missing or different currency %s', (currency) => {
    expect(expenseDuplicateReason({ ...draft, currency }, expense())).toBeNull();
  });
  it('excludes self, other companies/owners, tombstones and cancelled expenses while retaining paid records', () => {
    const rows = [expense(), expense({ id: 'edited' }), expense({ id: 'other-company', companyId: 'company-b' }),
      expense({ id: 'other-owner', ownerId: 'owner-b' }), expense({ id: 'deleted', isDeleted: 1 }),
      expense({ id: 'cancelled', status: 'cancelled' }), expense({ id: 'paid', status: 'paid' })];
    expect(localExpenseDuplicateSuggestions('company-a', 'owner-a', draft, rows, 'edited').map((row) => row.expense.id)).toEqual(['expense-a', 'paid']);
    expect(localExpenseDuplicateSuggestions('company-a', '', draft, rows)).toEqual([]);
  });
  it('does not modify records or expose internal notes/owner IDs in display candidates', () => {
    const row = expense({ internalNote: 'Synthetic private note' });
    const before = JSON.stringify(row);
    const suggestions = localExpenseDuplicateSuggestions('company-a', 'owner-a', draft, [row]);
    expect(JSON.stringify(row)).toBe(before);
    expect(suggestions[0]?.expense).not.toHaveProperty('internalNote');
    expect(suggestions[0]?.expense).not.toHaveProperty('ownerId');
  });
});
