import { describe, expect, it } from 'vitest';
import type { InvoicingDataSnapshot } from '@/evolu/invoicingSnapshot';
const emptySnapshot: InvoicingDataSnapshot = {
  company: [], contact: [], numberSeries: [], document: [], documentLine: [], documentEvent: [], documentSnapshot: [],
  invoiceTemplate: [], expense: [], expenseAttachment: [], recurringProfile: [], recurringProfileLine: [], companyWarehouse: [],
  companyStockItem: [], companyStockBalance: [], companyStockMovement: [], bankImportBatch: [], bankTransaction: [], bankTransactionMatch: [],
};
import { prepareServerSnapshotForEvolu } from '@/evolu/serverSnapshotPrepare';

describe('server snapshot payment evidence boundary', () => {
  it('remaps an expense contact reference while preserving snapshots and review state', () => {
    const companyUuid = '019ec83b-9368-7183-ab36-693391827ee6';
    const contactUuid = '019ec83b-9368-7183-ab36-693391827ee7';
    const input = { ...emptySnapshot, company: [{ id: companyUuid, legalName: 'Synthetic company' }],
      contact: [{ id: contactUuid, companyId: companyUuid, name: 'Synthetic supplier' }],
      expense: [{ id: '019ec83b-9368-7183-ab36-693391827ee8', companyId: companyUuid, companyContactId: contactUuid,
        internalNumber: 'EXP-1', supplierName: 'Frozen supplier', supplierIban: 'SK6807200002891987426353', paymentReviewRequired: 1, total: '-19.50' }] };
    const prepared = prepareServerSnapshotForEvolu(input);
    expect(prepared.expense[0]).toMatchObject({ companyContactId: prepared.contact[0].id, companyId: prepared.company[0].id,
      supplierName: 'Frozen supplier', supplierIban: 'SK6807200002891987426353', paymentReviewRequired: 1, total: '-19.50' });
    expect(prepared.expense[0].companyContactId).not.toBe(contactUuid);
  });
  it.each([undefined, null, '{"v":1,"token":"untrusted-server-value"}'])('omits server claim %s rather than overwriting local evidence', (claim) => {
    const expense = { id: 'synthetic-expense', internalNumber: 'EXP-1', companyId: 'synthetic-company', total: '19.00', status: 'recorded', bankPaymentClaimJson: claim };
    const transaction = { id: 'synthetic-tx', companyId: 'synthetic-company', amount: '19.00', businessExpenseId: 'synthetic-expense', bankPaymentClaimJson: claim };
    const input = { ...emptySnapshot, expense: [expense], bankTransaction: [transaction] };
    const prepared = prepareServerSnapshotForEvolu(input);
    expect(prepared.expense[0]).not.toHaveProperty('bankPaymentClaimJson');
    expect(prepared.bankTransaction[0]).not.toHaveProperty('bankPaymentClaimJson');
    expect(prepared.expense[0]).toMatchObject({ total: '19.00', status: 'recorded' });
    expect(prepared.bankTransaction[0]).toMatchObject({ amount: '19.00', businessExpenseId: 'synthetic-expense' });
    // Preparation must not mutate a raw local backup or the caller's input.
    expect(expense).toHaveProperty('bankPaymentClaimJson', claim);
    expect(transaction).toHaveProperty('bankPaymentClaimJson', claim);
    const localRow = { bankPaymentClaimJson: 'existing-encrypted-local-evidence', ...prepared.expense[0] };
    expect(localRow.bankPaymentClaimJson).toBe('existing-encrypted-local-evidence');
  });
});
