import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { createI18n } from 'vue-i18n';
import ExpensesTable from '@/components/invoicing/ExpensesTable.vue';
import type { ExpenseListRow } from '@/composables/useExpenseRowMeta';

vi.mock('@/utils/expensePaymentQr', () => ({
  expensePaymentQrDetails: (row: ExpenseListRow) => row.status === 'recorded' && Number(row.total) > 0 && !row.bank_match_conflict && row.supplier_iban === 'SK3112000000198742637541' && row.payment_review_required === false
    ? { iban: 'SK3112000000198742637541', variableSymbol: row.variable_symbol, amount: row.total, currency: 'EUR', supplier: row.title }
    : null,
  expensePayMePayload: () => '', expensePayBySquarePayload: () => '',
}));

const row = (changes: Partial<ExpenseListRow> = {}): ExpenseListRow => ({
  id: 'synthetic-expense', internal_number: 'EXP001', external_number: 'SUP001',
  title: 'Synthetic supplier', issue_date: '2026-11-01', status: 'recorded',
  total: '12.34', currency: 'EUR', variable_symbol: '000123', supplier_name: 'Synthetic supplier', supplier_iban: 'SK3112000000198742637541', payment_review_required: false, ...changes,
});
let wrapper: VueWrapper | undefined;
function render(rows = [row()]) {
  wrapper = mount(ExpensesTable, {
    props: { rows, companyId: 'company-one', selectedIds: [], selectAllMode: false, selectionCount: 0, totalCount: rows.length, showHeaderMenu: false, actionId: null },
    global: {
      plugins: [createI18n({ legacy: false, locale: 'en', missingWarn: false, fallbackWarn: false, messages: { en: { invoicing: { expense_received_credit_note: 'Received credit note', expense_payment_qr_open: 'Payment QR', expense_payment_qr_open_for: 'Payment QR for {number}' } } } })],
      stubs: {
        RouterLink: { props: ['to'], template: '<a><slot /></a>' },
        ExpensePaymentQrDialog: { props: ['details'], template: '<div data-test="qr-dialog">{{ details ? details.amount : "closed" }}</div>' },
      },
    },
  });
  return wrapper;
}
const triggers = () => wrapper!.findAll('button[aria-label^="Payment QR for"]');
afterEach(() => { wrapper?.unmount(); wrapper = undefined; });

describe('expense-row payment QR entry points', () => {
  it('labels received credits in desktop and mobile rows and offers no payment QR', () => {
    render([row({ total: '-12.34' })]);
    expect(wrapper!.findAll('span,p').filter(entry => entry.text() === 'Received credit note')).toHaveLength(2);
    expect(triggers()).toHaveLength(0);
  });
  it('offers visible desktop and mobile QR controls only for eligible expense rows', () => {
    render([row(), row({ id: 'paid', status: 'paid' }), row({ id: 'cancelled', status: 'cancelled' }), row({ id: 'missing', supplier_iban: null })]);
    expect(triggers()).toHaveLength(2);
    expect(triggers().every(button => button.text() === 'Payment QR')).toBe(true);
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('closed');
  });

  it('opens one dialog without opening the row, selecting it, or marking it paid', async () => {
    render();
    await triggers()[0].trigger('click');
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('12.34');
    expect(wrapper!.findAll('[data-test="qr-dialog"]')).toHaveLength(1);
    expect(wrapper!.emitted('open')).toBeUndefined();
    expect(wrapper!.emitted('toggle-row')).toBeUndefined();
    expect(wrapper!.emitted('mark-paid')).toBeUndefined();
  });

  it('keeps mobile Enter and Space on the QR button from opening the expense card', async () => {
    render(); const mobile = triggers()[1];
    await mobile.trigger('keydown', { key: 'Enter' });
    await mobile.trigger('keydown', { key: ' ' });
    expect(wrapper!.emitted('open')).toBeUndefined();
    await mobile.trigger('click');
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('12.34');
    expect(wrapper!.emitted('open')).toBeUndefined();
  });

  it('closes on company changes and requires a new explicit open when the company returns', async () => {
    render(); await triggers()[0].trigger('click');
    await wrapper!.setProps({ companyId: 'company-two' });
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('closed');
    await wrapper!.setProps({ companyId: 'company-one' });
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('closed');
  });

  it('closes after payment-data changes and lets the user reopen the updated row', async () => {
    render(); await triggers()[0].trigger('click');
    await wrapper!.setProps({ rows: [row({ total: '25.00' })] });
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('closed');
    await triggers()[0].trigger('click');
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('25.00');
    await wrapper!.setProps({ rows: [row({ status: 'paid' })] });
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('closed');
    expect(triggers()).toHaveLength(0);
  });

  it('closes when the selected row leaves the current list and does not reopen if it returns', async () => {
    render(); await triggers()[0].trigger('click');
    await wrapper!.setProps({ rows: [] });
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('closed');
    await wrapper!.setProps({ rows: [row()] });
    expect(wrapper!.get('[data-test="qr-dialog"]').text()).toBe('closed');
  });
});
