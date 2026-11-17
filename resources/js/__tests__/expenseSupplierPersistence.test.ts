import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createIdFromString } from '@evolu/common';
import type { Evolu } from '@evolu/common/local-first';
import { insertLocalExpense, updateLocalExpense, updateLocalExpenseNote, duplicateLocalExpense } from '@/evolu/expenseCrud';
import { evoluExpenseToApi, type EvoluExpenseRow } from '@/evolu/expenseMap';
import { ContactId, type CompanyId, type ExpenseId, type InvoicingLocalSchema } from '@/evolu/schema';
import { expensePaymentConfirmationTuple } from '@/utils/expensePaymentReview';
import { expensePaymentQrDetails } from '@/utils/expensePaymentQr';
import { installBankLocks } from './bankReconciliationTestSupport';
import { createExpenseWriterTestAdapter } from './expenseReconciliationTestSupport';
vi.mock('@/evolu/client', async () => ({ ...(await import('./expenseReconciliationTestSupport')).expenseTestQueryExports(), allContactsQuery: 'ui:contact' }));

const companyId = 'company-a' as CompanyId;
const expenseId = 'expense-1' as ExpenseId;
const contactId = ContactId.orThrow(createIdFromString('synthetic expense supplier'));
const iban = 'SK6807200002891987426353';
const base = { issue_date: '2026-11-17', total: '19.50', currency: 'EUR', variable_symbol: '00019',
    company_contact_id: contactId, supplier_name: 'Synthetic supplier', supplier_iban: iban, payment_review_required: false };
function adapter() {
    const data = createExpenseWriterTestAdapter();
    data.state.contact = [{ id: contactId, companyId, ownerId: data.appOwnerId, name: 'Synthetic supplier' }];
    return { ...data, evolu: data.evolu as unknown as Evolu<InvoicingLocalSchema>, saved: () => data.state.expense[0] as unknown as EvoluExpenseRow };
}
beforeEach(() => installBankLocks());
afterEach(() => vi.unstubAllGlobals());

describe('structured expense supplier persistence', () => {
    it('saves recipient snapshots independently of contact edits and arbitrary notes', async () => {
        const data = adapter();
        expect((await insertLocalExpense(data.evolu, companyId, { ...base, internal_note: 'Supplier bank account: another account' }, [])).ok).toBe(true);
        data.state.contact[0].name = 'Renamed contact';
        data.state.contact[0].iban = 'DE89370400440532013000';
        expect(evoluExpenseToApi(data.saved())).toMatchObject(base);
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))?.iban).toBe(iban);
    });
    it('preserves omitted values and persists explicit cleared contact/account/name', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, base, []);
        await updateLocalExpense(data.evolu, expenseId, { issue_date: base.issue_date, total: base.total });
        expect(evoluExpenseToApi(data.saved())).toMatchObject({ company_contact_id: contactId, supplier_iban: iban });
        await updateLocalExpense(data.evolu, expenseId, { ...base, company_contact_id: null, supplier_name: null, supplier_iban: null });
        expect(evoluExpenseToApi(data.saved())).toMatchObject({ company_contact_id: null, supplier_name: null, supplier_iban: null });
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))).toBeNull();
    });
    it.each(['company', 'owner'])('rejects a contact from another %s before writing an expense', async (scope) => {
        const data = adapter();
        data.state.contact[0][scope === 'company' ? 'companyId' : 'ownerId'] = 'foreign';
        await expect(insertLocalExpense(data.evolu, companyId, base, [])).rejects.toThrow('expense_contact_unavailable');
        expect(data.state.expense).toHaveLength(0);
    });
    it('keeps an imported safety blocker through contact selection, note edits and duplication', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true }, []);
        await updateLocalExpense(data.evolu, expenseId, { ...base, internal_note: '' });
        expect(evoluExpenseToApi(data.saved()).payment_review_required).toBe(true);
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))).toBeNull();
        await duplicateLocalExpense(data.evolu, data.saved(), []);
        expect(data.state.expense[1]).toMatchObject({ supplierIban: iban, supplierName: base.supplier_name, paymentReviewRequired: 1 });
    });
    it('captures original legacy payment evidence before replacing the note', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, base, []);
        Object.assign(data.state.expense[0], { paymentReviewRequired: null, internalNote: 'Payment amount: 5.00' });
        await updateLocalExpense(data.evolu, expenseId, { ...base, internal_note: null });
        expect(evoluExpenseToApi(data.saved()).payment_review_required).toBe(true);
    });
    it.each(['Payment amount: 5.00', 'Imported from ISDOC.'])('retains original legacy review evidence through detail note autosave: %s', async (note) => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, base, []);
        Object.assign(data.state.expense[0], { paymentReviewRequired: null, internalNote: note });
        await updateLocalExpenseNote(data.evolu, expenseId, null, companyId);
        await updateLocalExpense(data.evolu, expenseId, { ...base, internal_note: null });
        expect(evoluExpenseToApi(data.saved()).payment_review_required).toBe(true);
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))).toBeNull();
    });
    it.each(['missing', 'deleted', 'inactive', 'incomplete'])('duplicates with supplier snapshots and a null link when its contact is %s', async (kind) => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true }, []);
        if (kind === 'missing') data.state.contact = [];
        else Object.assign(data.state.contact[0], kind === 'deleted' ? { isDeleted: 1 }
            : kind === 'inactive' ? { isActive: 0 } : { name: null });
        expect((await duplicateLocalExpense(data.evolu, data.saved(), [])).ok).toBe(true);
        expect(data.state.expense[1]).toMatchObject({ companyContactId: null, supplierName: base.supplier_name,
            supplierIban: iban, paymentReviewRequired: 1 });
        expect(data.saved().companyContactId).toBe(contactId);
    });
    it('still rejects a live duplicate contact from another encrypted owner', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, base, []);
        data.state.contact[0].ownerId = 'foreign-owner';
        await expect(duplicateLocalExpense(data.evolu, data.saved(), [])).rejects.toThrow('expense_contact_unavailable');
        expect(data.state.expense).toHaveLength(1);
    });
    it('does not copy a source expense from a stale encrypted owner partition', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, base, []);
        Object.assign(data.state.expense[0], { ownerId: 'stale-owner', companyContactId: null });
        await expect(duplicateLocalExpense(data.evolu, data.saved(), [])).rejects.toThrow('expense_reconciliation_owner_unavailable');
        expect(data.state.expense).toHaveLength(1);
    });
    it('rejects duplication when its contact has moved out of the company', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, base, []);
        data.state.contact[0].companyId = 'foreign-company';
        await expect(duplicateLocalExpense(data.evolu, data.saved(), [])).rejects.toThrow('expense_contact_unavailable');
        expect(data.state.expense).toHaveLength(1);
    });
    it('round-trips a manually added negative credit without preparing an outgoing payment', async () => {
        const data = adapter();
        expect((await insertLocalExpense(data.evolu, companyId, { ...base, total: '-19.50' }, [])).ok).toBe(true);
        expect(evoluExpenseToApi(data.saved()).total).toBe('-19.50');
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))).toBeNull();
        await updateLocalExpense(data.evolu, expenseId, { ...base, total: '-9.50' });
        expect(evoluExpenseToApi(data.saved()).total).toBe('-9.50');
    });
});


describe('legacy expense payment review recovery', () => {
    it('makes edited legacy payment QR available only after exact explicit manual verification', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true, internal_note: 'Imported from ISDOC.' }, []);
        await updateLocalExpense(data.evolu, expenseId, base);
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))).toBeNull();
        const confirmation = expensePaymentConfirmationTuple(base)!;
        expect((await updateLocalExpense(data.evolu, expenseId, { ...base, payment_details_confirmation: confirmation })).ok).toBe(true);
        expect(evoluExpenseToApi(data.saved())).toMatchObject({ payment_review_required: false, status: 'recorded', paid_at: null });
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))?.iban).toBe(iban);
        expect(data.saved()).not.toHaveProperty('payment_details_confirmation');
    });
    it.each([{ supplier_name: 'Other recipient' }, { supplier_iban: 'CZ6508000000192000145399' },
        { total: '20.00' }, { currency: 'USD' }, { variable_symbol: '00020' }])('rejects a confirmation after instructions change %j', async patch => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true }, []);
        await expect(updateLocalExpense(data.evolu, expenseId, { ...base, ...patch,
            payment_details_confirmation: expensePaymentConfirmationTuple(base) })).rejects.toThrow('expense_payment_details_confirmation');
        expect(data.saved().paymentReviewRequired).toBe(1);
    });
    it.each(['Payment amount: 2.00', 'Payment amount: unknown', 'Payment date: 2026-11-17'])('preserves original payment evidence even when the edited note clears it: %s', async note => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, { ...base, internal_note: note, payment_review_required: true }, []);
        await expect(updateLocalExpense(data.evolu, expenseId, { ...base, internal_note: null,
            payment_details_confirmation: expensePaymentConfirmationTuple(base) })).rejects.toThrow('expense_payment_details_confirmation');
        expect(data.saved().internalNote).toBe(note);
        expect(data.saved().paymentReviewRequired).toBe(1);
    });
    it.each([{ status: 'paid' }, { paidAt: '2026-11-17' }, { cancelledAt: '2026-11-17' }, { bankMatchConflict: true }])('does not manually approve incompatible saved payment state %j', async patch => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true }, []);
        Object.assign(data.state.expense[0], patch);
        await expect(updateLocalExpense(data.evolu, expenseId, { ...base,
            payment_details_confirmation: expensePaymentConfirmationTuple(base) })).rejects.toThrow('expense_payment_details_confirmation');
        expect(data.saved().paymentReviewRequired).toBe(1);
    });
    it('rejects a fresh bank association before changing review disposition', async () => {
        const data = adapter();
        await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true }, []);
        data.state.bankTransaction.push({ id: 'linked-bank', companyId, ownerId: data.appOwnerId, businessExpenseId: expenseId });
        await expect(updateLocalExpense(data.evolu, expenseId, { ...base,
            payment_details_confirmation: expensePaymentConfirmationTuple(base) })).rejects.toThrow('expense_payment_details_confirmation');
        expect(data.saved().paymentReviewRequired).toBe(1);
    });
    it.each(['create', 'update'].flatMap(operation => [
        ' Payment amount: 2.00', '\n\tPayment amount: unknown  ', '\u00a0Payment date: 2026-11-17 ',
    ].map(note => ({ operation, note }))))('rejects normalized payment evidence on $operation: $note', async ({ operation, note }) => {
        const data = adapter();
        if (operation === 'update') await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true }, []);
        const before = operation === 'update' ? { ...data.saved() } : null;
        const payload = { ...base, internal_note: note, payment_details_confirmation: expensePaymentConfirmationTuple(base) };
        await expect(operation === 'create' ? insertLocalExpense(data.evolu, companyId, payload, [])
            : updateLocalExpense(data.evolu, expenseId, payload)).rejects.toThrow('expense_payment_details_confirmation');
        expect(data.state.expense).toHaveLength(operation === 'create' ? 0 : 1);
        if (before) expect(data.saved()).toEqual(before);
    });
    it.each(['create', 'update'].flatMap(operation => ['Imported from ISDOC.', 'Payment amount: 0.00']
        .map(note => ({ operation, note }))))('allows normalized non-payment notes on $operation: $note', async ({ operation, note }) => {
        const data = adapter();
        if (operation === 'update') await insertLocalExpense(data.evolu, companyId, { ...base, payment_review_required: true }, []);
        const payload = { ...base, payment_review_required: true, internal_note: ` \n${note} \t`,
            payment_details_confirmation: expensePaymentConfirmationTuple(base) };
        const result = operation === 'create' ? await insertLocalExpense(data.evolu, companyId, payload, [])
            : await updateLocalExpense(data.evolu, expenseId, payload);
        expect(result.ok).toBe(true);
        expect(data.saved()).toMatchObject({ internalNote: note, paymentReviewRequired: 0 });
        expect(expensePaymentQrDetails(evoluExpenseToApi(data.saved()))?.iban).toBe(iban);
    });
    it('allows reviewed manual creation but never combines confirmation with mark-paid intent', async () => {
        const data = adapter();
        const payload = { ...base, payment_review_required: true, payment_details_confirmation: expensePaymentConfirmationTuple(base) };
        expect((await insertLocalExpense(data.evolu, companyId, payload, [])).ok).toBe(true);
        expect(data.saved().paymentReviewRequired).toBe(0);
        await expect(insertLocalExpense(data.evolu, companyId, { ...payload, mark_paid: true }, [])).rejects.toThrow('expense_payment_details_confirmation');
        expect(data.state.expense).toHaveLength(1);
    });
});
