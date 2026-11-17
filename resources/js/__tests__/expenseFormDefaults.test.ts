import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DOMWrapper, flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { reactive, ref } from 'vue';
import type { CompanyContactRow } from '@/composables/useCompanyContact';
import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import { expensePaymentQrDetails } from '@/utils/expensePaymentQr';

const mocks = vi.hoisted(() => ({
  local: true,
  push: vi.fn(), replace: vi.fn(), refresh: vi.fn(), get: vi.fn(), company: vi.fn(), lookup: vi.fn(),
  create: vi.fn(), update: vi.fn(), insert: vi.fn(), updateLocal: vi.fn(),
  confirmExtract: vi.fn(), upload: vi.fn(), loadQuota: vi.fn(), skipExtract: vi.fn(),
}));
const supplierContacts = ref<CompanyContactRow[]>([]);
const contactsLoading = ref(false);
const companyId = ref('abcdefghijklmnopqrstuv');
const route = reactive({ name: 'invoicing-expense-new', params: {} as { expenseId?: string },
  query: {} as { duplicate?: string | string[] | null; credit_note?: string; source_expense?: string } });
const existingExpense = {
  id: 'synthetic-expense', internal_number: 'EXP20260001', title: 'Synthetic expense',
  external_number: 'DOC-0012', variable_symbol: '0099',
  issue_date: '2026-01-10', delivery_date: '2026-01-07' as string | null, due_date: null,
  total: '12.00', currency: 'EUR', constant_symbol: null, specific_symbol: null,
  internal_note: null as string | null, company_contact_id: null as string | null, supplier_name: null as string | null,
  supplier_iban: null as string | null, payment_review_required: false as boolean | null,
};

vi.mock('vue-router', () => ({ useRoute: () => route, useRouter: () => ({ push: mocks.push, replace: mocks.replace }) }));
vi.mock('@/composables/useExpenseSupplierContacts', () => ({ useExpenseSupplierContacts: () => ({
  contacts: supplierContacts, loading: contactsLoading, failed: ref(false), refresh: vi.fn(),
}) }));
vi.mock('@/evolu/companyShareRegistry', () => ({ ownerIdForCompany: () => undefined, companyShareRoutingKey: () => 'private', waitCompanyShareRegistryReady: async () => {} }));
vi.mock('@/evolu/flags', () => ({ isInvoicingLocalFirst: () => mocks.local }));
vi.mock('@/evolu/client', () => ({ expenseByIdIncludingDeletedQuery: vi.fn() }));
vi.mock('@/composables/useInvoicingLayout', () => ({ useInvoicingLayout: () => ({ companyId, rememberCompany: vi.fn() }) }));
vi.mock('@/composables/useInvoicingCompanySummary', () => ({ useInvoicingCompanySummary: () => ({ defaultCurrency: ref('EUR') }) }));
vi.mock('@/composables/useInvoicingExpenses', () => ({ useInvoicingExpense: () => ({
  evolu: { loadQuery: mocks.lookup, appOwner: Promise.resolve({ id: 'synthetic-owner' }) }, expense: ref(existingExpense), expenseRows: ref([]), refresh: mocks.refresh,
}) }));
vi.mock('@/evolu/expenseCrud', () => ({ insertLocalExpense: mocks.insert, updateLocalExpense: mocks.updateLocal }));
vi.mock('@/services/api', () => ({ invoicingApi: {
  companies: { get: mocks.company },
  expenses: { get: mocks.get, create: mocks.create, update: mocks.update },
} }));
vi.mock('@/composables/useExpenseIsdocAttachment', () => ({ useExpenseIsdocAttachment: () => ({
  quota: ref(null), pendingFiles: ref([]), pendingFileSummaries: ref([]), selectedPendingId: ref(null),
  pendingAttachmentName: ref(''), previewUrl: ref(null), previewKind: ref(null),
  lastDetectHasIsdoc: ref(false), detectError: ref(''), showExtractModal: ref(true),
  detecting: ref(false), extracting: ref(false), purchasing: ref(false),
  loadQuota: mocks.loadQuota, purchasePack: vi.fn(), onDocumentSelected: vi.fn(),
  onDocumentsSelected: vi.fn(), selectPendingFile: vi.fn(), removePendingFile: vi.fn(),
  confirmExtract: mocks.confirmExtract, skipExtract: mocks.skipExtract, uploadAllPendingAttachments: mocks.upload,
}) }));
vi.mock('@/components/invoicing/InvoicingPageShell.vue', () => ({ default: { template: '<main><slot /></main>' } }));
vi.mock('@/components/invoicing/InvoicingAppHeader.vue', () => ({ default: { template: '<header />' } }));
vi.mock('@/components/invoicing/ExpenseAttachmentPanel.vue', () => ({ default: { template: '<aside />' } }));
vi.mock('@/components/invoicing/ExpenseIsdocExtractModal.vue', () => ({ default: {
  name: 'ExpenseIsdocExtractModal', emits: ['confirm'], template: '<aside />',
} }));
import ExpenseForm from '@/pages/invoicing/ExpenseForm.vue';

type FieldKey = 'expense_col_external' | 'variable_symbol' | 'expense_col_issue' | 'expense_col_delivery';
let wrapper: VueWrapper | undefined;
function field(key: FieldKey) {
  const label = wrapper!.findAll('form label').find((entry) => entry.text().replace(' *', '') === en.invoicing[key]);
  if (!label) throw new Error(`Missing form field: ${key}`);
  return new DOMWrapper(label.element.nextElementSibling as HTMLInputElement);
}
/**
 * Mount ExpenseForm in the selected persistence/route mode with an optional initial copy marker.
 * Await mount-time hydration before assertions or user input.
 */
async function render(local = true, editing = false, duplicate?: string | string[] | null) {
  mocks.local = local;
  route.name = editing ? 'invoicing-expense-edit' : 'invoicing-expense-new';
  route.params = editing ? { expenseId: existingExpense.id } : {};
  route.query = duplicate === undefined ? {} : { duplicate };
  wrapper = mount(ExpenseForm, { global: {
    plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })],
    stubs: { RouterLink: { template: '<a><slot /></a>' } },
  } });
  await flushPromises();
}
async function extract(draft: Record<string, unknown>) {
  mocks.confirmExtract.mockResolvedValueOnce({ payment_review_required: false, ...draft });
  wrapper!.findComponent({ name: 'ExpenseIsdocExtractModal' }).vm.$emit('confirm');
  await flushPromises();
}

beforeEach(() => {
  vi.clearAllMocks();
  mocks.local = true;
  supplierContacts.value = [
    { id: 'contact-bank', name: 'Supplier A', iban: 'SK6807200002891987426353' },
    { id: 'contact-empty', name: 'Supplier A', iban: null },
    { id: 'contact-other', name: 'Other supplier', iban: null },
  ];
  contactsLoading.value = false;
  existingExpense.company_contact_id = null;
  existingExpense.supplier_name = null;
  existingExpense.supplier_iban = null;
  existingExpense.payment_review_required = false;
  existingExpense.internal_note = null;
  existingExpense.total = '12.00';
  existingExpense.delivery_date = '2026-01-07';
  companyId.value = 'abcdefghijklmnopqrstuv';
  route.name = 'invoicing-expense-new';
  route.params = {};
  route.query = {};
  mocks.push.mockResolvedValue(undefined);
  mocks.replace.mockResolvedValue(undefined);
  mocks.lookup.mockResolvedValue([{ id: existingExpense.id, companyId: companyId.value, isDeleted: null }]);
  mocks.refresh.mockResolvedValue(undefined);
  mocks.get.mockResolvedValue(existingExpense);
  mocks.company.mockResolvedValue({ default_currency: 'EUR' });
  mocks.create.mockResolvedValue({ id: 'synthetic-created' });
  mocks.insert.mockReturnValue({ ok: true, value: { id: 'synthetic-created' } });
  mocks.update.mockResolvedValue(undefined);
  mocks.updateLocal.mockResolvedValue({ ok: true });
  mocks.upload.mockResolvedValue(undefined);
  mocks.loadQuota.mockResolvedValue(undefined);
  mocks.confirmExtract.mockResolvedValue(null);
});
afterEach(() => {
  wrapper?.unmount();
  wrapper = undefined;
});

describe('new expense field defaults', () => {
  it.each([true, false])('clears the previous invoice reference when a replacement has no VS (local=%s)', async local => {
    await render(local);
    await extract({ external_number: 'A-2026', variable_symbol: '0001', total: 123.45, currency: 'EUR',
      supplier_name: 'Supplier A', supplier_iban: 'SK6807200002891987426353', payment_review_required: false,
      internal_note: 'Supplier: Supplier A\nSupplier bank account: SK6807200002891987426353' });
    expect(field('variable_symbol').element.value).toBe('0001');
    await extract({ external_number: 'B-2026', variable_symbol: null, total: 123.45, currency: 'EUR',
      supplier_name: 'Supplier B', supplier_iban: 'CZ6508000000192000145399', payment_review_required: false,
      internal_note: 'Supplier: Supplier B\nSupplier bank account: CZ6508000000192000145399' });
    expect(field('variable_symbol').element.value).toBe('');
    await field('expense_col_external').setValue('B-2027');
    expect(field('variable_symbol').element.value).toBe('');
    await wrapper!.get('form').trigger('submit');
    await flushPromises();
    const payload = local ? mocks.insert.mock.calls[0][2] : mocks.create.mock.calls[0][1];
    expect(payload).toMatchObject({ external_number: 'B-2027', variable_symbol: null, total: 123.45, currency: 'EUR' });
    expect(payload.internal_note).toContain('Supplier: Supplier B\nSupplier bank account: CZ6508000000192000145399');
    expect(expensePaymentQrDetails({ ...payload, id: 'synthetic-saved', internal_number: 'EXP-001',
      status: 'recorded', total: String(payload.total) })).toBeNull();
  });

  it.each([undefined, ''])('clears a manual reference when the extracted VS is %s', async variableSymbol => {
    await render();
    await field('variable_symbol').setValue('0099');
    await extract({ external_number: 'B-2026', variable_symbol: variableSymbol });
    expect(field('variable_symbol').element.value).toBe('');
  });

  it.each([true, false])('replaces the supplier and VS together while retaining explicit leading zeros (local=%s)', async local => {
    await render(local);
    await extract({ external_number: 'A-2026', variable_symbol: '0001', total: 123.45, currency: 'EUR',
      supplier_name: 'Supplier A', supplier_iban: 'SK6807200002891987426353', payment_review_required: false,
      internal_note: 'Supplier: Supplier A\nSupplier bank account: SK6807200002891987426353' });
    await extract({ external_number: 'B-2026', variable_symbol: '0000042', total: 123.45, currency: 'EUR',
      supplier_name: 'Supplier B', supplier_iban: 'CZ6508000000192000145399', payment_review_required: false,
      internal_note: 'Supplier: Supplier B\nSupplier bank account: CZ6508000000192000145399' });
    await wrapper!.get('form').trigger('submit');
    await flushPromises();
    const payload = local ? mocks.insert.mock.calls[0][2] : mocks.create.mock.calls[0][1];
    expect(expensePaymentQrDetails({ ...payload, id: 'synthetic-saved', internal_number: 'EXP-001',
      status: 'recorded', total: String(payload.total) })).toMatchObject({
      iban: 'CZ6508000000192000145399', variableSymbol: '0000042', supplier: 'Supplier B', amount: '123.45',
    });
  });

  it('preserves prose while replacing structured supplier payment fields', async () => {
    await render();
    const oldNote = 'Keep this user note\nSupplier: Previous supplier\nSupplier bank account: SK6807200002891987426353';
    await wrapper!.get('textarea').setValue(oldNote);
    await extract({ title: 'New supplier', supplier_name: 'New supplier', supplier_iban: 'CZ6508000000192000145399' });
    expect((wrapper!.get('textarea').element as HTMLTextAreaElement).value).toBe(oldNote);
    expect((wrapper!.get('#expense-supplier-iban').element as HTMLInputElement).value).toBe('CZ6508000000192000145399');
    await extract({ title: 'Supplier without payment details', supplier_name: 'Supplier without payment details', supplier_iban: null });
    expect((wrapper!.get('textarea').element as HTMLTextAreaElement).value).toBe(oldNote);
    await wrapper!.get('form').trigger('submit');
    await flushPromises();
    expect(mocks.insert.mock.calls[0][2]).toMatchObject({ internal_note: oldNote, supplier_name: 'Supplier without payment details', supplier_iban: null });
  });

  it('waits for the local expense write before navigating or saving attachments', async () => {
    let complete!: (value: { ok: true; value: { id: string } }) => void;
    mocks.insert.mockReturnValueOnce(new Promise((resolve) => { complete = resolve; }));
    await render();
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    expect(mocks.push).not.toHaveBeenCalled();
    expect(mocks.upload).not.toHaveBeenCalled();
    complete({ ok: true, value: { id: 'synthetic-created' } });
    await flushPromises();
    expect(mocks.upload).toHaveBeenCalledExactlyOnceWith('synthetic-created');
    expect(mocks.push).toHaveBeenCalledOnce();
  });

  it('does not upload attachments or navigate after the company changes during a local write', async () => {
    let complete!: (value: { ok: true; value: { id: string } }) => void;
    mocks.insert.mockReturnValueOnce(new Promise((resolve) => { complete = resolve; }));
    await render();
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    companyId.value = 'bcdefghijklmnopqrstuvw';
    await flushPromises();
    complete({ ok: true, value: { id: 'synthetic-old-company' } });
    await flushPromises();
    expect(mocks.upload).not.toHaveBeenCalled();
    expect(mocks.push).not.toHaveBeenCalled();
  });

  it.each([
    ['expense_reconciliation_lock_unavailable', en.invoicing.expense_changes_unavailable],
    ['bank_reconciliation_outcome_unknown', en.invoicing.expense_changes_unknown],
  ])('shows localized recovery for %s without retrying or navigating', async (message, expected) => {
    mocks.insert.mockRejectedValueOnce(new Error(message));
    await render();
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    expect(wrapper!.text()).toContain(expected);
    expect(mocks.insert).toHaveBeenCalledOnce();
    expect(mocks.push).not.toHaveBeenCalled();
    expect(mocks.upload).not.toHaveBeenCalled();
  });

  it.each([true, false])('updates VS as document number changes (local=%s)', async (local) => {
    await render(local);
    await field('expense_col_external').setValue('FA-00012/2026');
    expect(field('variable_symbol').element.value).toBe('000122026');
    await field('expense_col_external').setValue('FA-00013/2026');
    expect(field('variable_symbol').element.value).toBe('000132026');
    await field('expense_col_external').setValue('LETTERS');
    expect(field('variable_symbol').element.value).toBe('');
    await field('expense_col_external').setValue('007');
    await field('expense_col_external').setValue('');
    expect(field('variable_symbol').element.value).toBe('');
  });

  it.each([true, false])('leaves an overlong derived VS blank and resumes when the number is shortened (local=%s)', async (local) => {
    await render(local);
    for (const digits of [31, 32]) {
      const symbol = '0'.repeat(digits - 1) + '1';
      await field('expense_col_external').setValue(`DOC-${symbol}`);
      expect(field('variable_symbol').element.value).toBe(symbol);
    }
    await field('expense_col_external').setValue('1'.repeat(33));
    expect(field('variable_symbol').element.value).toBe('');
    await field('expense_col_external').setValue('DOC-0007');
    expect(field('variable_symbol').element.value).toBe('0007');
  });

  it.each([true, false])('saves a long document number with a null derived VS (local=%s)', async (local) => {
    await render(local);
    const number = '1'.repeat(33);
    await field('expense_col_external').setValue(number);
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    const write = local ? mocks.insert : mocks.create;
    expect(write).toHaveBeenCalledOnce();
    expect(write.mock.calls[0]?.[local ? 2 : 1]).toMatchObject({ external_number: number, variable_symbol: null });
    expect(mocks.push).toHaveBeenCalledOnce();
  });

  it('preserves a manually entered or cleared VS on later number changes', async () => {
    await render();
    await field('expense_col_external').setValue('DOC-001');
    await field('variable_symbol').setValue('0099');
    await field('expense_col_external').setValue('2'.repeat(33));
    expect(field('variable_symbol').element.value).toBe('0099');
    await field('variable_symbol').setValue('');
    await field('expense_col_external').setValue('3'.repeat(33));
    expect(field('variable_symbol').element.value).toBe('');
  });

  it.each([true, false])('keeps delivery date equal to received date by default (local=%s)', async (local) => {
    await render(local);
    expect(field('expense_col_delivery').element.value).toBe(field('expense_col_issue').element.value);
    await field('expense_col_issue').setValue('2026-07-17');
    expect(field('expense_col_delivery').element.value).toBe('2026-07-17');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('expense_col_delivery').element.value).toBe('2026-07-18');
  });

  it('preserves a manually edited or cleared delivery date on later receipt changes', async () => {
    await render();
    await field('expense_col_delivery').setValue('2026-07-15');
    await field('expense_col_issue').setValue('2026-07-17');
    expect(field('expense_col_delivery').element.value).toBe('2026-07-15');
    await field('expense_col_delivery').setValue('');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('expense_col_delivery').element.value).toBe('');
  });

  it.each([true, false])('saves a manually cleared delivery date as null (local=%s)', async (local) => {
    await render(local);
    await field('expense_col_delivery').setValue('');
    await field('expense_col_issue').setValue('2026-07-18');
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    const write = local ? mocks.insert : mocks.create;
    expect(write).toHaveBeenCalledOnce();
    expect(write.mock.calls[0]?.[local ? 2 : 1]).toMatchObject({ delivery_date: null });
  });

  it.each([true, false])('reopens a saved null delivery date as blank (local=%s)', async (local) => {
    existingExpense.delivery_date = null;
    await render(local, true);
    expect(field('expense_col_delivery').element.value).toBe('');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('expense_col_delivery').element.value).toBe('');
  });

  it.each([true, false])('persists the linked defaults in the create payload (local=%s)', async (local) => {
    await render(local);
    await field('expense_col_external').setValue('FA-00012/2026');
    await field('expense_col_issue').setValue('2026-07-17');
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    const payload = local ? mocks.insert.mock.calls[0][2] : mocks.create.mock.calls[0][1];
    expect(payload).toMatchObject({
      external_number: 'FA-00012/2026', variable_symbol: '000122026',
      issue_date: '2026-07-17', delivery_date: '2026-07-17',
    });
    expect(mocks.push).toHaveBeenCalledOnce();
    expect(local ? mocks.create : mocks.insert).not.toHaveBeenCalled();
  });

  it.each([true, false])('preserves existing expense hydration and later source edits (local=%s)', async (local) => {
    await render(local, true);
    expect(field('expense_col_external').element.value).toBe('DOC-0012');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_issue').element.value).toBe('2026-01-10');
    expect(field('expense_col_delivery').element.value).toBe('2026-01-07');
    await field('expense_col_external').setValue('DOC-0088');
    await field('expense_col_issue').setValue('2026-02-01');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_delivery').element.value).toBe('2026-01-07');
  });

  it.each([true, false])('persists deliberate overrides after later source edits (local=%s)', async (local) => {
    await render(local);
    await field('variable_symbol').setValue('0099');
    await field('expense_col_delivery').setValue('2026-07-15');
    await field('expense_col_external').setValue('DOC-0012');
    await field('expense_col_issue').setValue('2026-07-17');
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    const payload = local ? mocks.insert.mock.calls[0][2] : mocks.create.mock.calls[0][1];
    expect(payload).toMatchObject({
      external_number: 'DOC-0012', variable_symbol: '0099',
      issue_date: '2026-07-17', delivery_date: '2026-07-15',
    });
  });

  it('derives missing attachment delivery dates while clearing an absent payment reference', async () => {
    await render();
    await extract({ external_number: 'ATT-0004', issue_date: '2026-03-04' });
    expect(field('variable_symbol').element.value).toBe('');
    expect(field('expense_col_delivery').element.value).toBe('2026-03-04');
    await field('variable_symbol').setValue('0099');
    await field('expense_col_delivery').setValue('2026-03-01');
    await extract({ external_number: 'ATT-0005', issue_date: '2026-03-05' });
    expect(field('variable_symbol').element.value).toBe('');
    expect(field('expense_col_delivery').element.value).toBe('2026-03-01');
  });

  it('preserves explicit attachment values even when equal to previous defaults', async () => {
    await render();
    await field('expense_col_external').setValue('DOC-0012');
    await field('expense_col_issue').setValue('2026-03-04');
    await extract({
      external_number: 'ATT-0012', variable_symbol: '0012',
      issue_date: '2026-03-04', delivery_date: '2026-03-04',
    });
    await field('expense_col_external').setValue('DOC-0099');
    await field('expense_col_issue').setValue('2026-04-01');
    expect(field('variable_symbol').element.value).toBe('0012');
    expect(field('expense_col_delivery').element.value).toBe('2026-03-04');
  });

  it('keeps automatic defaults active after failed extraction', async () => {
    await render();
    mocks.confirmExtract.mockRejectedValueOnce(new Error('synthetic extraction failure'));
    wrapper!.findComponent({ name: 'ExpenseIsdocExtractModal' }).vm.$emit('confirm');
    await flushPromises();
    expect(mocks.skipExtract).toHaveBeenCalledOnce();
    await field('expense_col_external').setValue('DOC-0012');
    await field('expense_col_issue').setValue('2026-03-04');
    expect(field('variable_symbol').element.value).toBe('0012');
    expect(field('expense_col_delivery').element.value).toBe('2026-03-04');
  });

  it('preserves manual overrides across retained company context and resets them on remount', async () => {
    await render();
    await field('variable_symbol').setValue('0099');
    await field('expense_col_delivery').setValue('2026-03-01');
    companyId.value = 'bcdefghijklmnopqrstuvw';
    await flushPromises();
    await field('expense_col_external').setValue('DOC-0012');
    await field('expense_col_issue').setValue('2026-03-04');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_delivery').element.value).toBe('2026-03-01');
    wrapper!.unmount();
    await render();
    await field('expense_col_external').setValue('DOC-0013');
    await field('expense_col_issue').setValue('2026-03-05');
    expect(field('variable_symbol').element.value).toBe('0013');
    expect(field('expense_col_delivery').element.value).toBe('2026-03-05');
  });
});

describe('structured supplier snapshots and received credit notes', () => {
  const value = (selector: string) => (wrapper!.get(selector).element as HTMLInputElement).value;
  const submit = async (local: boolean) => {
    await wrapper!.get('form').trigger('submit');
    await flushPromises();
    return local ? mocks.insert.mock.calls.at(-1)?.[2] : mocks.create.mock.calls.at(-1)?.[1];
  };

  it.each([true, false])('takes the selected contact IBAN and never reuses a previous contact account (local=%s)', async local => {
    await render(local);
    expect(value('#expense-supplier-iban')).toBe('');
    await wrapper!.get('#expense-supplier-contact').setValue('contact-bank');
    expect(value('#expense-supplier-name')).toBe('Supplier A');
    expect(value('#expense-supplier-iban')).toBe('SK6807200002891987426353');
    await wrapper!.get('#expense-supplier-contact').setValue('contact-empty');
    expect(value('#expense-supplier-iban')).toBe('');
    expect(await submit(local)).toMatchObject({ company_contact_id: 'contact-empty', supplier_name: 'Supplier A', supplier_iban: null, payment_review_required: false });
  });

  it.each([true, false])('uses a matching extracted account only when the chosen contact has no IBAN (local=%s)', async local => {
    await render(local);
    await extract({ supplier_name: ' Supplier A ', supplier_iban: 'CZ6508000000192000145399', total: 19.5, variable_symbol: '0009' });
    await wrapper!.get('#expense-supplier-contact').setValue('contact-empty');
    expect(value('#expense-supplier-iban')).toBe('CZ6508000000192000145399');
    await wrapper!.get('#expense-supplier-contact').setValue('contact-bank');
    expect(value('#expense-supplier-iban')).toBe('SK6807200002891987426353');
    await wrapper!.get('#expense-supplier-contact').setValue('contact-other');
    expect(value('#expense-supplier-iban')).toBe('');
    const payload = await submit(local);
    expect(payload).toMatchObject({ company_contact_id: 'contact-other', supplier_name: 'Other supplier', supplier_iban: null });
    expect(expensePaymentQrDetails({ ...payload, id: 'test', internal_number: 'test', status: 'recorded', total: String(payload.total) })).toBeNull();
  });

  it.each([true, false])('keeps saved snapshots and explicit clears when contact choices hydrate later (local=%s)', async local => {
    existingExpense.company_contact_id = 'contact-bank';
    existingExpense.supplier_name = 'Saved supplier';
    existingExpense.supplier_iban = 'CZ6508000000192000145399';
    supplierContacts.value = [];
    await render(local, true);
    expect(value('#expense-supplier-name')).toBe('Saved supplier');
    expect(value('#expense-supplier-iban')).toBe('CZ6508000000192000145399');
    supplierContacts.value = [{ id: 'contact-bank', name: 'Changed contact', iban: 'SK6807200002891987426353' }];
    await flushPromises();
    expect(value('#expense-supplier-name')).toBe('Saved supplier');
    await wrapper!.get('#expense-supplier-iban').setValue('');
    await wrapper!.get('#expense-supplier-name').setValue('');
    await wrapper!.get('form').trigger('submit');
    await flushPromises();
    const payload = local ? mocks.updateLocal.mock.calls[0][2] : mocks.update.mock.calls[0][2];
    expect(payload).toMatchObject({ company_contact_id: 'contact-bank', supplier_iban: null, supplier_name: null });
  });

  it('does not derive payment instructions from copied notes or a generic title', async () => {
    await render();
    await wrapper!.get('textarea').setValue('Supplier: Copied supplier\nSupplier bank account: SK6807200002891987426353');
    const payload = await submit(true);
    expect(payload).toMatchObject({ company_contact_id: null, supplier_name: null, supplier_iban: null });
  });

  it('keeps extraction review sticky through another extraction and contact selection', async () => {
    await render();
    await extract({ supplier_name: 'Supplier A', supplier_iban: null, payment_review_required: true });
    await extract({ supplier_name: 'Supplier A', supplier_iban: 'CZ6508000000192000145399', payment_review_required: false });
    await wrapper!.get('#expense-supplier-contact').setValue('contact-bank');
    expect(wrapper!.text()).toContain(en.invoicing.expense_payment_review_required);
    expect(await submit(true)).toMatchObject({ payment_review_required: true });
  });

  it.each([undefined, null])('fails closed when an extraction review flag is %s', async payment_review_required => {
    await render();
    await extract({ supplier_name: 'Supplier A', supplier_iban: 'CZ6508000000192000145399', payment_review_required });
    expect(await submit(true)).toMatchObject({ payment_review_required: true });
  });

  it.each([true, false])('preserves manual accounts and explicit clears across extraction (contact=%s)', async contact => {
    await render();
    if (contact) await wrapper!.get('#expense-supplier-contact').setValue('contact-bank');
    for (const account of ['CZ6508000000192000145399', '']) {
      await wrapper!.get('#expense-supplier-iban').setValue(account);
      await extract({ supplier_name: 'Supplier A', supplier_iban: 'SK6807200002891987426353' });
      expect(value('#expense-supplier-iban')).toBe(account);
    }
    await wrapper!.get('#expense-supplier-name').setValue('Manually named supplier');
    await extract({ supplier_name: 'Supplier A', supplier_iban: 'SK6807200002891987426353' });
    expect(value('#expense-supplier-name')).toBe('Manually named supplier');
    await wrapper!.get('#expense-supplier-contact').setValue('contact-empty');
    await wrapper!.get('#expense-supplier-contact').setValue('contact-bank');
    expect(value('#expense-supplier-iban')).toBe('SK6807200002891987426353');
  });

  it.each([true, false])('preserves saved recipient snapshots on re-extraction after contact edits (local=%s)', async local => {
    existingExpense.company_contact_id = 'contact-bank';
    existingExpense.supplier_name = 'Saved supplier';
    existingExpense.supplier_iban = 'CZ6508000000192000145399';
    await render(local, true);
    await extract({ supplier_name: 'Supplier A', supplier_iban: 'SK6807200002891987426353', total: 15 });
    expect(value('#expense-supplier-name')).toBe('Saved supplier');
    expect(value('#expense-supplier-iban')).toBe('CZ6508000000192000145399');
  });

  it.each([true, false])('withholds an extracted account that conflicts with a manually retained supplier name (local=%s)', async local => {
    await render(local);
    await wrapper!.get('#expense-supplier-name').setValue('Different supplier');
    await extract({ supplier_name: 'Supplier A', supplier_iban: 'SK6807200002891987426353', payment_review_required: false });
    expect(value('#expense-supplier-name')).toBe('Different supplier');
    expect(value('#expense-supplier-iban')).toBe('');
    expect(await submit(local)).toMatchObject({ supplier_name: 'Different supplier', supplier_iban: null, payment_review_required: true });
  });

  it('requires correction of a nonempty invalid IBAN before saving', async () => {
    await render();
    await wrapper!.get('#expense-supplier-iban').setValue('SK6807200002891987426354');
    await submit(true);
    expect(mocks.insert).not.toHaveBeenCalled();
    expect(wrapper!.text()).toContain(en.invoicing.expense_supplier_iban_invalid);
  });

  it.each([true, false])('creates a received credit note with a negative total and preserves it on edit (local=%s)', async local => {
    await render(local);
    const amount = wrapper!.get('input[type="number"]');
    await amount.setValue('19.5');
    await wrapper!.get('#expense-document-kind').setValue('credit_note');
    expect(value('input[type="number"]')).toBe('-19.5');
    expect(amount.attributes('max')).toBe('-0.01');
    expect(await submit(local)).toMatchObject({ total: -19.5 });
    wrapper!.unmount();
    existingExpense.total = '-19.50';
    await render(local, true);
    expect(value('#expense-document-kind')).toBe('credit_note');
    await wrapper!.get('#expense-document-kind').setValue('expense');
    expect(value('input[type="number"]')).toBe('19.5');
  });

  it('rejects zero and positive received credit amounts even through the toolbar save path', async () => {
    await render();
    await wrapper!.get('#expense-document-kind').setValue('credit_note');
    for (const amount of ['0', '19.5']) {
      await wrapper!.get('input[type="number"]').setValue(amount);
      await submit(true);
      expect(mocks.insert).not.toHaveBeenCalled();
    }
    expect(wrapper!.text()).toContain(en.invoicing.expense_credit_note_amount_invalid);
  });

  it.each([true, false])('prefills a new received credit without copying original document references (local=%s)', async local => {
    existingExpense.supplier_name = 'Original supplier';
    existingExpense.supplier_iban = 'CZ6508000000192000145399';
    existingExpense.payment_review_required = null;
    existingExpense.internal_note = 'Payment amount: 2.00';
    mocks.lookup.mockResolvedValue([{ id: existingExpense.id, companyId: companyId.value, isDeleted: null,
      ownerId: 'synthetic-owner', internalNumber: 'EXP001', title: 'Original', total: '12.00', currency: 'EUR', supplierName: 'Original supplier', supplierIban: 'CZ6508000000192000145399', paymentReviewRequired: null, internalNote: 'Payment amount: 2.00' }]);
    await render(local);
    wrapper!.unmount();
    route.query = { credit_note: '1', source_expense: existingExpense.id };
    wrapper = mount(ExpenseForm, { global: {
      plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })],
      stubs: { RouterLink: { template: '<a><slot /></a>' } },
    } });
    await flushPromises();
    expect(value('input[type="number"]')).toBe('-12');
    expect(field('expense_col_external').element.value).toBe('');
    expect(field('variable_symbol').element.value).toBe('');
    expect(value('#expense-supplier-name')).toBe('Original supplier');
    await wrapper!.get('#expense-document-kind').setValue('expense');
    expect(await submit(local)).toMatchObject({ payment_review_required: true });
  });
});

describe('duplicated expense field defaults', () => {
  it.each([true, false])('preserves untouched copy hydration and saves only an update before replacing its edit entry (local=%s)', async (local) => {
    let finishUpload!: () => void;
    mocks.upload.mockReturnValueOnce(new Promise<void>((resolve) => { finishUpload = resolve; }));
    await render(local, true, existingExpense.id);
    expect(field('expense_col_external').element.value).toBe('DOC-0012');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_delivery').element.value).toBe('2026-01-07');
    expect(wrapper!.find('input[type="checkbox"]').exists()).toBe(false);
    expect(route.query.duplicate).toBe(existingExpense.id);
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    const write = local ? mocks.updateLocal : mocks.update;
    expect(write).toHaveBeenCalledOnce();
    expect(write.mock.calls[0][1]).toBe(existingExpense.id);
    expect(write.mock.calls[0][2]).toMatchObject({
      external_number: 'DOC-0012', variable_symbol: '0099',
      issue_date: '2026-01-10', delivery_date: '2026-01-07', mark_paid: undefined,
    });
    expect(mocks.insert).not.toHaveBeenCalled();
    expect(mocks.create).not.toHaveBeenCalled();
    expect(mocks.upload).toHaveBeenCalledExactlyOnceWith(existingExpense.id);
    expect(mocks.replace).not.toHaveBeenCalled();
    expect(mocks.push).not.toHaveBeenCalled();
    finishUpload();
    await flushPromises();
    expect(mocks.replace).toHaveBeenCalledExactlyOnceWith({ name: 'invoicing-expense-show',
      params: { companyId: companyId.value, expenseId: existingExpense.id } });
    expect(mocks.upload.mock.invocationCallOrder[0]).toBeLessThan(mocks.replace.mock.invocationCallOrder[0]);
    expect(mocks.push).not.toHaveBeenCalled();
  });

  it.each([true, false])('follows copy source inputs with leading zeros, the VS limit and null overflow payload (local=%s)', async (local) => {
    await render(local, true, existingExpense.id);
    await field('expense_col_external').setValue('COPY-00088');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('variable_symbol').element.value).toBe('00088');
    expect(field('expense_col_delivery').element.value).toBe('2026-07-18');
    await field('expense_col_external').setValue('DOC-' + '0'.repeat(31) + '1');
    expect(field('variable_symbol').element.value).toBe('0'.repeat(31) + '1');
    await field('expense_col_external').setValue('DOC-' + '1'.repeat(33));
    expect(field('variable_symbol').element.value).toBe('');
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    expect((local ? mocks.updateLocal : mocks.update).mock.calls[0][2]).toMatchObject({
      variable_symbol: null, delivery_date: '2026-07-18',
    });
  });

  it.each([true, false])('preserves equal-value manual copy targets and later clears (local=%s)', async (local) => {
    await render(local, true, existingExpense.id);
    await field('variable_symbol').setValue('0099');
    await field('expense_col_delivery').setValue('2026-01-07');
    await field('expense_col_external').setValue('COPY-00088');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_delivery').element.value).toBe('2026-01-07');
    await field('variable_symbol').setValue('');
    await field('expense_col_delivery').setValue('');
    await field('expense_col_external').setValue('COPY-00089');
    await field('expense_col_issue').setValue('2026-07-19');
    expect(field('variable_symbol').element.value).toBe('');
    expect(field('expense_col_delivery').element.value).toBe('');
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    expect((local ? mocks.updateLocal : mocks.update).mock.calls[0][2]).toMatchObject({ variable_symbol: null, delivery_date: null });
  });

  it.each([true, false])('clears missing copy import references and preserves explicit equal-value import overrides (local=%s)', async (local) => {
    await render(local, true, existingExpense.id);
    await extract({ external_number: 'COPY-00088', issue_date: '2026-07-18' });
    expect(field('variable_symbol').element.value).toBe('');
    expect(field('expense_col_delivery').element.value).toBe('2026-07-18');
    await extract({ external_number: 'COPY-00088', variable_symbol: '00088',
      issue_date: '2026-07-18', delivery_date: '2026-07-18' });
    await field('expense_col_external').setValue('COPY-00089');
    await field('expense_col_issue').setValue('2026-07-19');
    expect(field('variable_symbol').element.value).toBe('00088');
    expect(field('expense_col_delivery').element.value).toBe('2026-07-18');
  });

  it.each([undefined, 'another-expense', ['synthetic-expense'], null])('keeps ordinary edit semantics for marker %j', async (marker) => {
    await render(true, true, marker);
    await field('expense_col_external').setValue('COPY-00088');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_delivery').element.value).toBe('2026-01-07');
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    expect(mocks.push).toHaveBeenCalledOnce();
    expect(mocks.replace).not.toHaveBeenCalled();
  });

  it('does not enable copy defaults when a matching marker arrives after mounting', async () => {
    await render(true, true);
    route.query.duplicate = existingExpense.id;
    await field('expense_col_external').setValue('COPY-00088');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_delivery').element.value).toBe('2026-01-07');
  });

  it.each(['company', 'expense', 'route'] as const)('revokes copy defaults permanently after %s context ABA', async (context) => {
    await render(true, true, existingExpense.id);
    if (context === 'company') { companyId.value = 'another-company'; companyId.value = 'abcdefghijklmnopqrstuv'; }
    if (context === 'expense') { route.params.expenseId = 'another-expense'; route.params.expenseId = existingExpense.id; }
    if (context === 'route') { route.name = 'invoicing-expense-show'; route.name = 'invoicing-expense-edit'; }
    await field('expense_col_external').setValue('COPY-00088');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('variable_symbol').element.value).toBe('0099');
    expect(field('expense_col_delivery').element.value).toBe('2026-01-07');
  });

  it.each([true, false])('does not navigate or upload after the copy context exits during its update (local=%s)', async (local) => {
    let complete!: (value: { ok: true }) => void;
    (local ? mocks.updateLocal : mocks.update).mockReturnValueOnce(new Promise((resolve) => { complete = resolve; }));
    await render(local, true, existingExpense.id);
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    companyId.value = 'another-company'; companyId.value = 'abcdefghijklmnopqrstuv';
    complete({ ok: true });
    await flushPromises();
    expect(mocks.upload).not.toHaveBeenCalled();
    expect(mocks.push).not.toHaveBeenCalled();
    expect(mocks.replace).not.toHaveBeenCalled();
  });

  it.each([true, false])('retains the copy marker and update recovery after a failed save (local=%s)', async (local) => {
    (local ? mocks.updateLocal : mocks.update).mockRejectedValueOnce(new Error('synthetic save failure'));
    await render(local, true, existingExpense.id);
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    expect(mocks.replace).not.toHaveBeenCalled();
    expect(route.query.duplicate).toBe(existingExpense.id);
    await field('expense_col_external').setValue('COPY-00088');
    await field('expense_col_issue').setValue('2026-07-18');
    expect(field('variable_symbol').element.value).toBe('00088');
    expect(field('expense_col_delivery').element.value).toBe('2026-07-18');
    await wrapper!.find('form').trigger('submit');
    await flushPromises();
    expect(local ? mocks.updateLocal : mocks.update).toHaveBeenCalledTimes(2);
    expect(mocks.replace).toHaveBeenCalledOnce();
    expect(mocks.insert).not.toHaveBeenCalled();
    expect(mocks.create).not.toHaveBeenCalled();
  });
});
