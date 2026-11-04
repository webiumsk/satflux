import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { reactive, ref, nextTick } from 'vue';
import type { BankTxApiRow } from '@/evolu/bankMap';

const mocks = vi.hoisted(() => ({ ensure: vi.fn(), inbound: vi.fn(), local: true,
  autoMatchBatch: vi.fn(), importFile: vi.fn(), ignore: vi.fn(), unmatch: vi.fn(), load: vi.fn(), flashError: vi.fn(),
}));
const route = reactive({ params: { companyId: 'local-a' } });
const hasBankAccount = ref(true);
const batches = ref<{ id: string; imported_count: number; row_count: number; can_auto_match?: boolean }[]>([]);
const transactions = ref<BankTxApiRow[]>([]);
vi.mock('vue-router', () => ({ useRoute: () => route }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }));
vi.mock('@/components/invoicing/InvoicingPageShell.vue', () => ({ default: { template: '<div><slot name="header"/><slot/></div>' } }));
vi.mock('@/components/invoicing/InvoicingAppHeader.vue', () => ({ default: { template: '<div><slot name="actions"/></div>' } }));
vi.mock('@/components/invoicing/InvoicingMobileCard.vue', () => ({ default: { template: '<div/>' } }));
vi.mock('@/components/invoicing/InvoicingRowActionsMenu.vue', () => ({ default: { template: '<div/>' } }));
vi.mock('@/components/invoicing/BankCreateExpenseModal.vue', () => ({ default: { template: '<div/>' } }));
vi.mock('@/composables/useInvoicingLayout', () => ({ useInvoicingLayout: () => ({ rememberCompany: vi.fn() }) }));
vi.mock('@/composables/useInvoicingCompanySummary', () => ({ useInvoicingCompanySummary: () => ({
  hasBankAccount, summaryLoaded: ref(true), bankAccountLabel: ref('Synthetic bank'), defaultCurrency: ref('EUR'),
}) }));
vi.mock('@/composables/useInvoicingBankPayments', () => ({ useInvoicingBankPayments: () => ({
  localFirst: mocks.local, loading: ref(false), transactions, batches,
  summary: ref({ credit_count: 0, credit_total: '0', debit_count: 0, debit_total: '0', balance: '0', currency: 'EUR', by_currency: [] }),
  wiseStatus: ref(null), wiseBridgeMissing: ref(false),
  load: mocks.load, loadBatches: vi.fn().mockResolvedValue(undefined), autoMatchBatch: mocks.autoMatchBatch, importFile: mocks.importFile,
  ignoreTransaction: mocks.ignore, unmatchTransaction: mocks.unmatch,
  fetchWiseStatus: vi.fn().mockResolvedValue(undefined),
}) }));
vi.mock('@/store/flash', () => ({ useFlashStore: () => ({ error: mocks.flashError, success: vi.fn() }) }));
vi.mock('@/services/api', () => ({ invoicingApi: { bankTransactions: { inboundEmail: mocks.inbound } } }));
vi.mock('@/evolu/bridgeCompanyEnsure', () => ({ ensureBridgeCompanyIdForLocalCompany: mocks.ensure }));

import CompanyPayments from '@/pages/invoicing/CompanyPayments.vue';

let wrapper: VueWrapper | undefined;
const addressA = 'payaaaaaaaaaaaa@payments.example';
const addressB = 'paybbbbbbbbbbbb@payments.example';
const response = (address: string) => ({ address, enabled: true, length: address.length, max_length: 50 });
function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason: Error) => void;
  const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
  return { promise, resolve, reject };
}
async function render() {
  wrapper = mount(CompanyPayments, { global: { stubs: { RouterLink: true } } });
  await wrapper.findAll('button').find((button) => button.text() === 'invoicing.bank_tab_import')!.trigger('click');
  await flushPromises();
}
beforeEach(() => {
  vi.resetAllMocks();
  route.params.companyId = 'local-a';
  hasBankAccount.value = true;
  mocks.local = true;
  batches.value = [];
  transactions.value = [];
  mocks.load.mockResolvedValue(undefined);
  mocks.autoMatchBatch.mockResolvedValue(undefined);
  mocks.ignore.mockResolvedValue(undefined);
  mocks.unmatch.mockResolvedValue(undefined);
  mocks.ensure.mockImplementation(async (company: string) => ({ ok: true, bridgeCompanyId: `bridge-${company}` }));
  mocks.inbound.mockImplementation(async (company: string) => response(company === 'bridge-local-a' ? addressA : addressB));
});
afterEach(() => { wrapper?.unmount(); wrapper = undefined; });

describe('batch matching errors', () => {
  it.each(['ignore', 'unmatch'] as const)('shows a localized rejected %s action without refreshing or retrying', async (action) => {
    transactions.value = [{ id: 'synthetic-tx', booked_at: '2026-11-04', amount: '19.00', currency: 'EUR',
      direction: 'debit', match_status: action === 'ignore' ? 'unmatched' : 'matched' }];
    mocks[action].mockRejectedValueOnce(new Error('bank_reconciliation_outcome_unknown'));
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_tab_transactions')!.trigger('click');
    mocks.load.mockClear();
    const label = action === 'ignore' ? 'invoicing.bank_ignore' : 'invoicing.bank_unmatch';
    await wrapper!.findAll('button').find((button) => button.text() === label)!.trigger('click');
    await flushPromises();
    expect(mocks[action]).toHaveBeenCalledExactlyOnceWith('synthetic-tx');
    expect(mocks.load).not.toHaveBeenCalled();
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('invoicing.expense_changes_unknown');
  });

  it('reports refresh failure separately after a saved Ignore action', async () => {
    transactions.value = [{ id: 'synthetic-tx', booked_at: '2026-11-04', amount: '19.00', currency: 'EUR', direction: 'debit', match_status: 'unmatched' }];
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_tab_transactions')!.trigger('click');
    mocks.load.mockClear();
    mocks.load.mockRejectedValueOnce(new Error('synthetic refresh failure'));
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_ignore')!.trigger('click');
    await flushPromises();
    expect(mocks.ignore).toHaveBeenCalledExactlyOnceWith('synthetic-tx');
    expect(mocks.load).toHaveBeenCalledOnce();
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('invoicing.bank_action_refresh_failed');
  });

  it.each([
    ['bank_reconciliation_wait_timed_out', 'invoicing.bank_matching_busy'],
    ['bank_reconciliation_outcome_unknown', 'invoicing.bank_matching_unknown'],
    ['synthetic unexpected failure', 'invoicing.bank_auto_match_failed'],
  ])('shows a localized error after %s without refreshing or retrying', async (message, key) => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 1, row_count: 1 }];
    mocks.autoMatchBatch.mockRejectedValueOnce(new Error(message));
    await render();
    mocks.load.mockClear();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_auto_match_batch')!.trigger('click');
    await flushPromises();
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith(key);
    expect(mocks.autoMatchBatch).toHaveBeenCalledExactlyOnceWith('synthetic-batch');
    expect(mocks.load).not.toHaveBeenCalled();
  });

  it('refreshes after successful batch matching', async () => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 1, row_count: 1 }];
    await render();
    mocks.load.mockClear();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_auto_match_batch')!.trigger('click');
    await flushPromises();
    expect(mocks.load).toHaveBeenCalledOnce();
    expect(mocks.flashError).not.toHaveBeenCalled();
  });

  it('reports a refresh failure separately after matching succeeds without retrying the match', async () => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 1, row_count: 1 }];
    await render();
    mocks.load.mockClear();
    mocks.load.mockRejectedValueOnce(new Error('synthetic refresh failure'));
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_auto_match_batch')!.trigger('click');
    await flushPromises();
    expect(mocks.autoMatchBatch).toHaveBeenCalledExactlyOnceWith('synthetic-batch');
    expect(mocks.load).toHaveBeenCalledOnce();
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('invoicing.bank_matching_refresh_failed');
  });

  it('keeps recovery matching available for persisted rows even when the verified count is zero', async () => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 0, row_count: 1, can_auto_match: true }];
    await render();
    const button = wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_auto_match_batch');
    expect(button).toBeDefined();
    await button!.trigger('click');
    await flushPromises();
    expect(mocks.autoMatchBatch).toHaveBeenCalledExactlyOnceWith('synthetic-batch');
  });

  it('hides local batches with no persisted unmatched rows despite an old positive count', async () => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 1, row_count: 1, can_auto_match: false }];
    await render();
    expect(wrapper!.findAll('button').some((button) => button.text() === 'invoicing.bank_auto_match_batch')).toBe(false);
  });

  it.each([
    ['bank_import_row_batch_rejected', 'invoicing.bank_import_failed'],
    ['bank_reconciliation_outcome_unknown', 'invoicing.bank_matching_unknown'],
  ])('localizes the import error %s without reporting success or retrying', async (message, key) => {
    const alert = vi.spyOn(window, 'alert').mockImplementation(() => {});
    try {
      mocks.importFile.mockRejectedValueOnce(new Error(message));
      await render();
      mocks.load.mockClear();
      const input = wrapper!.get('input[type="file"]');
      Object.defineProperty(input.element, 'files', { value: [new File(['synthetic'], 'bank.csv', { type: 'text/csv' })] });
      await input.trigger('change');
      await wrapper!.get('form').trigger('submit');
      await flushPromises();
      expect(alert).toHaveBeenCalledExactlyOnceWith(key);
      expect(mocks.importFile).toHaveBeenCalledOnce();
      expect(mocks.load).not.toHaveBeenCalled();
      expect(wrapper!.text()).not.toContain('invoicing.bank_import_result');
    } finally {
      alert.mockRestore();
    }
  });
});

describe('independent bank address context probes', () => {
  it('keeps B address when a deferred A bridge resolves after B', async () => {
    const pending = deferred<{ ok: true; bridgeCompanyId: string }>();
    mocks.ensure.mockImplementation((company: string) => company === 'local-a' ? pending.promise : Promise.resolve({ ok: true, bridgeCompanyId: 'bridge-local-b' }));
    await render();
    route.params.companyId = 'local-b';
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
    pending.resolve({ ok: true, bridgeCompanyId: 'bridge-local-a' });
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
    expect(mocks.inbound).not.toHaveBeenCalledWith('bridge-local-a');
  });
  it('keeps B address when a deferred A HTTP response resolves after B', async () => {
    const pending = deferred<ReturnType<typeof response>>();
    mocks.inbound.mockImplementation((company: string) => company === 'bridge-local-a' ? pending.promise : Promise.resolve(response(addressB)));
    await render();
    route.params.companyId = 'local-b';
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
    pending.resolve(response(addressA));
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
  });
  it('clears A immediately while B bridge is unresolved', async () => {
    await render();
    expect(wrapper!.get('code').text()).toBe(addressA);
    const pending = deferred<{ ok: true; bridgeCompanyId: string }>();
    mocks.ensure.mockReturnValue(pending.promise);
    route.params.companyId = 'local-b';
    await nextTick();
    expect(wrapper!.find('code').exists()).toBe(false);
    pending.resolve({ ok: true, bridgeCompanyId: 'bridge-local-b' });
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
  });
  it('does not send a delayed resolver result to the API after unmount', async () => {
    const pending = deferred<{ ok: true; bridgeCompanyId: string }>();
    mocks.ensure.mockReturnValue(pending.promise);
    await render();
    wrapper!.unmount(); wrapper = undefined;
    pending.resolve({ ok: true, bridgeCompanyId: 'bridge-local-a' });
    await flushPromises();
    expect(mocks.inbound).not.toHaveBeenCalled();
  });
  it('keeps B address when an old A HTTP request fails late', async () => {
    const pending = deferred<ReturnType<typeof response>>();
    mocks.inbound.mockImplementation((company: string) => company === 'bridge-local-a' ? pending.promise : Promise.resolve(response(addressB)));
    await render();
    route.params.companyId = 'local-b';
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
    pending.reject(new Error('synthetic network failure'));
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
  });
  it('keeps B state when an old A bridge reports a transient failure', async () => {
    const pending = deferred<{ ok: false }>();
    mocks.ensure.mockImplementation((company: string) => company === 'local-a' ? pending.promise : Promise.resolve({ ok: true, bridgeCompanyId: 'bridge-local-b' }));
    await render();
    route.params.companyId = 'local-b';
    await flushPromises();
    pending.resolve({ ok: false });
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressB);
    expect(wrapper!.text()).not.toContain('invoicing.bank_inbound_bridge_missing');
  });
  it('shows a missing-identity message without looking up another company', async () => {
    mocks.ensure.mockResolvedValue({ ok: true, bridgeCompanyId: null });
    await render();
    expect(mocks.ensure).toHaveBeenCalledWith('local-a');
    expect(mocks.inbound).not.toHaveBeenCalled();
    expect(wrapper!.find('code').exists()).toBe(false);
    expect(wrapper!.text()).toContain('invoicing.bank_inbound_bridge_missing');
  });
  it('keeps a transient failure retryable without presenting it as missing identity', async () => {
    mocks.ensure.mockResolvedValueOnce({ ok: false });
    await render();
    expect(wrapper!.find('code').exists()).toBe(false);
    expect(wrapper!.text()).not.toContain('invoicing.bank_inbound_bridge_missing');
    hasBankAccount.value = false;
    await nextTick();
    hasBankAccount.value = true;
    await flushPromises();
    expect(wrapper!.get('code').text()).toBe(addressA);
    expect(mocks.ensure).toHaveBeenCalledTimes(2);
  });
  it('keeps server mode on its selected company id without bridge creation', async () => {
    mocks.local = false;
    route.params.companyId = 'server-company-uuid';
    mocks.inbound.mockResolvedValue(response(addressA));
    await render();
    expect(mocks.ensure).not.toHaveBeenCalled();
    expect(mocks.inbound).toHaveBeenCalledWith('server-company-uuid');
    expect(wrapper!.get('code').text()).toBe(addressA);
  });
});
