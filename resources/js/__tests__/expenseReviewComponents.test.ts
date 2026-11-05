import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import type { EvoluExpenseRow } from '@/evolu/expenseMap';
import type { BankTxApiRow } from '@/evolu/bankMap';

const mocks = vi.hoisted(() => ({ duplicates: vi.fn() }));
vi.mock('@/services/api', () => ({ invoicingApi: { expenses: { duplicateSuggestions: mocks.duplicates } } }));
vi.mock('@/evolu/companyShareRegistry', () => ({ companyShareRoutingKey: () => 'private', waitCompanyShareRegistryReady: async () => {} }));
vi.mock('@/evolu/ownerScope', () => ({ resolveMutationOwner: () => undefined }));
import ExpenseDuplicateWarning from '@/components/invoicing/ExpenseDuplicateWarning.vue';
import BankExpenseMatchModal from '@/components/invoicing/BankExpenseMatchModal.vue';

const draft = { external_number: 'INV-001', title: 'Hosting', issue_date: '2026-11-05', total: '19.00', currency: 'EUR' };
const expense = { id: 'expense-a', companyId: 'company-a', ownerId: 'owner-a', internalNumber: 'EXP001', externalNumber: 'INV-001',
  title: 'Hosting', issueDate: '2026-11-05', total: '19.00', currency: 'EUR', status: 'recorded', cancelledAt: null } as unknown as EvoluExpenseRow;
const suggestion = { expense: { id: 'expense-a', internal_number: 'EXP001', external_number: 'INV-001', title: 'Hosting',
  total: '19.00', currency: 'EUR', issue_date: '2026-11-05', status: 'recorded' }, reason: 'external_number' };
const global = () => ({ plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })],
  stubs: { RouterLink: { template: '<a><slot /></a>' } } });
let wrapper: VueWrapper | undefined;
beforeEach(() => { vi.useFakeTimers(); mocks.duplicates.mockReset(); mocks.duplicates.mockResolvedValue([]); });
afterEach(() => { wrapper?.unmount(); wrapper = undefined; vi.useRealTimers(); });

describe('expense review components', () => {
  it('keeps local duplicate checks private and advisory', async () => {
    wrapper = mount(ExpenseDuplicateWarning, { props: { companyId: 'company-a', draft, localFirst: true,
      localRows: [expense], evolu: { appOwner: Promise.resolve({ id: 'owner-a' }) } as never }, global: global() });
    await vi.advanceTimersByTimeAsync(1); await flushPromises();
    expect(wrapper.text()).toContain(en.invoicing.expense_duplicate_warning);
    expect(wrapper.text()).toContain(en.invoicing.expense_duplicate_advisory);
    expect(wrapper.findAll('button')).toHaveLength(0);
    expect(mocks.duplicates).not.toHaveBeenCalled();
    await wrapper.setProps({ excludeId: 'expense-a' });
    await vi.advanceTimersByTimeAsync(1); await flushPromises();
    expect(wrapper.find('aside').exists()).toBe(false);
  });
  it('ignores an old company server response even when transport cancellation is ignored', async () => {
    let resolve!: (rows: unknown[]) => void;
    mocks.duplicates.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
    wrapper = mount(ExpenseDuplicateWarning, { props: { companyId: 'company-a', draft, localFirst: false, localRows: [], evolu: null }, global: global() });
    await vi.advanceTimersByTimeAsync(301);
    await wrapper.setProps({ companyId: 'company-b' });
    resolve([suggestion]); await flushPromises();
    expect(wrapper.text()).not.toContain('EXP001');
    await vi.advanceTimersByTimeAsync(301); await flushPromises();
    expect(wrapper.text()).not.toContain('EXP001');
  });
  it('shows an advisory check failure without a save-blocking action', async () => {
    mocks.duplicates.mockRejectedValueOnce(new Error('synthetic unavailable'));
    wrapper = mount(ExpenseDuplicateWarning, { props: { companyId: 'company-a', draft, localFirst: false, localRows: [], evolu: null }, global: global() });
    await vi.advanceTimersByTimeAsync(301); await flushPromises();
    expect(wrapper.text()).toContain(en.invoicing.expense_duplicate_check_failed);
    expect(wrapper.findAll('button')).toHaveLength(0);
  });
  it('requires an explicit confirmation and keeps financial writes out of mounting/suggestion rendering', async () => {
    wrapper = mount(BankExpenseMatchModal, { props: {
      transaction: { id: 'tx-a', amount: '19.00', currency: 'EUR', variable_symbol: '001' } as BankTxApiRow,
      suggestions: [{ ...suggestion, reason: 'variable_symbol' } as never], loading: false, saving: false, error: '',
    }, global: global(), attachTo: document.body });
    await flushPromises();
    expect(wrapper.find('[role="dialog"]').attributes('aria-modal')).toBe('true');
    expect(wrapper.emitted('confirm')).toBeUndefined();
    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted('confirm')).toEqual([['expense-a']]);
    wrapper.find('button').element.focus();
    expect(document.activeElement).toBe(wrapper.find('button').element);
    await wrapper.setProps({ saving: true });
    const panel = wrapper.find<HTMLElement>('[tabindex="-1"]').element;
    expect(document.activeElement).toBe(panel);
    const tab = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true });
    panel.dispatchEvent(tab);
    expect(tab.defaultPrevented).toBe(true);
    expect(document.activeElement).toBe(panel);
    await wrapper.find('[role="dialog"]').trigger('keydown', { key: 'Escape' });
    expect(wrapper.emitted('close')).toBeUndefined();
    expect(wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined)).toBe(true);
  });
});
