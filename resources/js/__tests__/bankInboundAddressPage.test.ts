import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { computed, reactive, ref, nextTick } from 'vue';
import type { BankTxApiRow } from '@/evolu/bankMap';

const mocks = vi.hoisted(() => ({ ensure: vi.fn(), inbound: vi.fn(), local: true,
  autoMatchBatch: vi.fn(), importFile: vi.fn(), ignore: vi.fn(), unmatch: vi.fn(), load: vi.fn(), loadBatches: vi.fn(), flashError: vi.fn(),
  fetchWiseStatus: vi.fn(),
  connectWise: vi.fn(), flashSuccess: vi.fn(),
  syncWise: vi.fn(),
}));
const route = reactive({ params: { companyId: 'local-a' } });
const hasBankAccount = ref(true);
const batches = ref<{ id: string; imported_count: number; row_count: number; can_auto_match?: boolean }[]>([]);
const transactions = ref<BankTxApiRow[]>([]);
const bridgeLookupFailed = ref(false);
const wiseBridgeMissing = ref(false);
const wiseStatus = ref<{ connected: boolean } | null>(null);
const wiseStatusFailed = ref(false);
const selectedRouting = ref('private');
const bridgeRoutingKey = computed(() => JSON.stringify([route.params.companyId, selectedRouting.value]));
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
  wiseStatus, wiseStatusFailed, wiseBridgeMissing, bridgeLookupFailed, bridgeRoutingKey,
  load: mocks.load, loadBatches: mocks.loadBatches, autoMatchBatch: mocks.autoMatchBatch, importFile: mocks.importFile,
  ignoreTransaction: mocks.ignore, unmatchTransaction: mocks.unmatch,
  fetchWiseStatus: mocks.fetchWiseStatus,
  connectWise: mocks.connectWise,
  syncWise: mocks.syncWise,
}) }));
vi.mock('@/store/flash', () => ({ useFlashStore: () => ({ error: mocks.flashError, success: mocks.flashSuccess }) }));
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
  bridgeLookupFailed.value = false;
  wiseBridgeMissing.value = false;
  wiseStatus.value = null;
  wiseStatusFailed.value = false;
  selectedRouting.value = 'private';
  mocks.fetchWiseStatus.mockResolvedValue(undefined);
  mocks.connectWise.mockResolvedValue(undefined);
  mocks.syncWise.mockResolvedValue({ imported: 0, auto_matched: 0, skipped_duplicates: 0 });
  mocks.load.mockResolvedValue(undefined);
  mocks.loadBatches.mockResolvedValue(undefined);
  mocks.autoMatchBatch.mockResolvedValue(undefined);
  mocks.ignore.mockResolvedValue(undefined);
  mocks.unmatch.mockResolvedValue(undefined);
  mocks.ensure.mockImplementation(async (company: string) => ({ ok: true, bridgeCompanyId: `bridge-${company}` }));
  mocks.inbound.mockImplementation(async (company: string) => response(company === 'bridge-local-a' ? addressA : addressB));
});
afterEach(() => { wrapper?.unmount(); wrapper = undefined; });

describe('recorded bridge address context', () => {
  it.each(['switch', 'aba', 'unmount'] as const)('cancels stale address-helper reads after %s', async (change) => {
    const pending = deferred<{ ok: true; bridgeCompanyId: string }>();
    mocks.ensure.mockReturnValueOnce(pending.promise);
    await render();
    const options = mocks.ensure.mock.calls[0][1] as () => { signal: AbortSignal };
    const signal = options().signal;
    expect(signal.aborted).toBe(false);
    if (change === 'unmount') {
      wrapper!.unmount();
      wrapper = undefined;
    } else {
      route.params.companyId = 'local-b';
      if (change === 'aba') route.params.companyId = 'local-a';
    }
    await flushPromises();
    expect(signal.aborted).toBe(true);
    expect(options).toThrow('bank_inbound_address_stale');
    const currentReads = mocks.inbound.mock.calls.length;
    pending.resolve({ ok: true, bridgeCompanyId: 'stale-bridge' });
    await flushPromises();
    expect(mocks.inbound).toHaveBeenCalledTimes(currentReads);
    expect(mocks.flashError).not.toHaveBeenCalled();
  });

  it('reloads transactions once for a company change and preserves the batch refresh', async () => {
    await render();
    route.params.companyId = 'local-b';
    await flushPromises();
    expect(mocks.load).toHaveBeenCalledTimes(2);
    expect(mocks.loadBatches).toHaveBeenCalledTimes(2);
  });

  it('reports a current same-company routing refresh failure without retrying', async () => {
    await render();
    mocks.load.mockRejectedValueOnce(new Error('bank_reconciliation_outcome_unknown'));
    selectedRouting.value = 'shared-new';
    await flushPromises();
    expect(mocks.load).toHaveBeenCalledTimes(2);
    expect(mocks.loadBatches).toHaveBeenCalledTimes(1);
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('invoicing.bank_matching_unknown');
  });

  it('discards a failed superseded routing refresh while the current refresh succeeds', async () => {
    await render();
    const old = deferred<void>();
    mocks.load.mockReturnValueOnce(old.promise);
    selectedRouting.value = 'shared-old';
    await flushPromises();
    selectedRouting.value = 'shared-new';
    await flushPromises();
    old.reject(new Error('bank_reconciliation_outcome_unknown'));
    await flushPromises();
    expect(mocks.load).toHaveBeenCalledTimes(3);
    expect(mocks.flashError).not.toHaveBeenCalled();
  });

  it('loads the new recorded route and discards the previous mapping response', async () => {
    selectedRouting.value = 'shared-a';
    mocks.ensure.mockImplementation(async () => ({ ok: true, bridgeCompanyId: selectedRouting.value }));
    const first = deferred<ReturnType<typeof response>>();
    mocks.inbound.mockReturnValueOnce(first.promise).mockResolvedValueOnce(response(addressB));
    await render();
    expect(mocks.ensure).toHaveBeenCalledWith('local-a', expect.any(Function), { preferRecordedBridgeId: true });
    expect(mocks.inbound).toHaveBeenCalledWith('shared-a');
    selectedRouting.value = 'shared-b';
    await flushPromises();
    expect(mocks.inbound).toHaveBeenLastCalledWith('shared-b');
    expect(wrapper!.text()).toContain(addressB);
    first.resolve(response(addressA));
    await flushPromises();
    expect(wrapper!.text()).toContain(addressB);
    expect(wrapper!.text()).not.toContain(addressA);
    expect(mocks.load).toHaveBeenCalledTimes(2);
    expect(mocks.loadBatches).toHaveBeenCalledTimes(1);
  });

  it('rejects an old address across same-company routing A-B-A', async () => {
    selectedRouting.value = 'shared-a';
    mocks.ensure.mockImplementation(async () => ({ ok: true, bridgeCompanyId: selectedRouting.value }));
    const first = deferred<ReturnType<typeof response>>();
    mocks.inbound.mockReturnValueOnce(first.promise).mockResolvedValue(response(addressB));
    await render();
    selectedRouting.value = 'shared-b';
    selectedRouting.value = 'shared-a';
    await flushPromises();
    expect(wrapper!.text()).toContain(addressB);
    first.resolve(response(addressA));
    await flushPromises();
    expect(wrapper!.text()).toContain(addressB);
    expect(wrapper!.text()).not.toContain(addressA);
    expect(mocks.load).toHaveBeenCalledTimes(3);
  });

  it('discards a stale lookup before requesting its address after routing changes', async () => {
    const first = deferred<{ ok: true; bridgeCompanyId: string }>();
    mocks.ensure.mockReturnValueOnce(first.promise).mockResolvedValue({ ok: true, bridgeCompanyId: 'shared-new' });
    await render();
    selectedRouting.value = 'shared-new';
    await flushPromises();
    first.resolve({ ok: true, bridgeCompanyId: 'shared-old' });
    await flushPromises();
    expect(mocks.inbound).toHaveBeenCalledExactlyOnceWith('shared-new');
  });
});

describe('Wise sync result scope', () => {
  it.each(['success', 'failure'] as const)('discards a stale sync %s after a company change', async (outcome) => {
    wiseStatus.value = { connected: true };
    const pending = deferred<{ imported: number; auto_matched: number; skipped_duplicates: number }>();
    mocks.syncWise.mockReturnValueOnce(pending.promise);
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_wise_sync')!.trigger('click');
    route.params.companyId = 'local-b';
    await flushPromises();
    const loads = mocks.load.mock.calls.length;
    if (outcome === 'success') pending.resolve({ imported: 4, auto_matched: 1, skipped_duplicates: 0 });
    else pending.reject(new Error('synthetic stale sync failure'));
    await flushPromises();
    expect(wrapper!.text()).not.toContain('invoicing.bank_import_result');
    expect(mocks.flashError).not.toHaveBeenCalled();
    expect(mocks.load).toHaveBeenCalledTimes(loads);
  });

  it('discards an old sync result across same-company context A-B-A', async () => {
    wiseStatus.value = { connected: true };
    const pending = deferred<{ imported: number; auto_matched: number; skipped_duplicates: number }>();
    mocks.syncWise.mockReturnValueOnce(pending.promise);
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_wise_sync')!.trigger('click');
    selectedRouting.value = 'other';
    selectedRouting.value = 'private';
    await flushPromises();
    const loads = mocks.load.mock.calls.length;
    pending.resolve({ imported: 4, auto_matched: 1, skipped_duplicates: 0 });
    await flushPromises();
    expect(wrapper!.text()).not.toContain('invoicing.bank_import_result');
    expect(mocks.load).toHaveBeenCalledTimes(loads);
  });

  it('discards a disposed sync failure without an error flash', async () => {
    wiseStatus.value = { connected: true };
    const pending = deferred<{ imported: number; auto_matched: number; skipped_duplicates: number }>();
    mocks.syncWise.mockReturnValueOnce(pending.promise);
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_wise_sync')!.trigger('click');
    wrapper!.unmount();
    wrapper = undefined;
    pending.reject(new Error('synthetic disposed sync failure'));
    await flushPromises();
    expect(mocks.flashError).not.toHaveBeenCalled();
    expect(mocks.load).toHaveBeenCalledTimes(1);
  });

  it('keeps a successful current sync result and refreshes its rows and batches', async () => {
    wiseStatus.value = { connected: true };
    mocks.syncWise.mockResolvedValueOnce({ imported: 2, auto_matched: 1, skipped_duplicates: 0 });
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_wise_sync')!.trigger('click');
    await flushPromises();
    expect(wrapper!.text()).toContain('invoicing.bank_import_result');
    expect(mocks.load).toHaveBeenCalledTimes(2);
    expect(mocks.loadBatches).toHaveBeenCalledTimes(2);
    expect(mocks.flashError).not.toHaveBeenCalled();
  });
});

describe('Wise connection lookup recovery', () => {
  it.each([null, { connected: true }])('shows a status retry without guessing connection state: %s', async (lastStatus) => {
    wiseStatus.value = lastStatus;
    wiseStatusFailed.value = true;
    await render();
    expect(wrapper!.text()).toContain('invoicing.bank_wise_connection_check_failed');
    expect(wrapper!.findAll('button').some((button) => button.text() === 'common.retry')).toBe(true);
    expect(wrapper!.find('#wise-api-token').exists()).toBe(false);
    expect(wrapper!.text()).not.toContain('invoicing.bank_wise_connected');
    expect(wrapper!.text()).not.toContain('invoicing.bank_wise_sync');
    expect(wrapper!.text()).not.toContain('invoicing.bank_wise_bridge_missing');
  });

  it.each([true, false])('keeps the failed-status retry visible while pending, then shows confirmed connected=%s', async (connected) => {
    wiseStatusFailed.value = true;
    await render();
    const pending = deferred<void>();
    mocks.fetchWiseStatus.mockReturnValueOnce(pending.promise);
    const retry = wrapper!.findAll('button').find((button) => button.text() === 'common.retry')!;
    await retry.trigger('click');
    expect(retry.attributes('disabled')).toBeDefined();
    expect(wrapper!.find('#wise-api-token').exists()).toBe(false);
    wiseStatusFailed.value = false;
    wiseStatus.value = { connected };
    pending.resolve();
    await flushPromises();
    expect(wrapper!.text()).not.toContain('invoicing.bank_wise_connection_check_failed');
    expect(wrapper!.find('#wise-api-token').exists()).toBe(!connected);
    expect(wrapper!.text().includes('invoicing.bank_wise_connected')).toBe(connected);
    expect(mocks.fetchWiseStatus).toHaveBeenCalledTimes(2);
    expect(mocks.load).toHaveBeenCalledTimes(1);
    expect(mocks.flashSuccess).not.toHaveBeenCalled();
  });

  it('keeps the token and reports failure when no current bridge was connected', async () => {
    bridgeLookupFailed.value = true;
    mocks.connectWise.mockRejectedValueOnce(new Error('bank_bridge_lookup_failed'));
    await render();
    const input = wrapper!.find('#wise-api-token');
    await input.setValue('synthetic-wise-token');
    input.element.closest('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();
    expect((input.element as HTMLInputElement).value).toBe('synthetic-wise-token');
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('invoicing.bank_wise_connect_failed');
    expect(mocks.flashSuccess).not.toHaveBeenCalled();
    expect(mocks.connectWise).toHaveBeenCalledExactlyOnceWith('synthetic-wise-token');
  });

  it.each(['success', 'failure'] as const)('ignores an old connection %s while a new context is connecting', async (outcome) => {
    const old = deferred<void>();
    const current = deferred<void>();
    mocks.connectWise.mockReturnValueOnce(old.promise).mockReturnValueOnce(current.promise);
    await render();
    await wrapper!.find('#wise-api-token').setValue('synthetic-old-token');
    wrapper!.find('#wise-api-token').element.closest('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    selectedRouting.value = 'shared-new';
    await flushPromises();
    await wrapper!.find('#wise-api-token').setValue('synthetic-new-token');
    wrapper!.find('#wise-api-token').element.closest('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    await flushPromises();
    if (outcome === 'success') old.resolve();
    else old.reject(new Error('synthetic old failure'));
    await flushPromises();
    expect((wrapper!.find('#wise-api-token').element as HTMLInputElement).value).toBe('synthetic-new-token');
    expect(wrapper!.find('#wise-api-token').element.closest('form')!.querySelector('button')!.disabled).toBe(true);
    expect(mocks.flashError).not.toHaveBeenCalled();
    expect(mocks.flashSuccess).not.toHaveBeenCalled();
    current.resolve();
    await flushPromises();
    expect(mocks.flashSuccess).toHaveBeenCalledExactlyOnceWith('invoicing.bank_wise_connect_success');
    expect((wrapper!.find('#wise-api-token').element as HTMLInputElement).value).toBe('');
  });

  it('preserves a new token after routing A-B-A before an old connection completes', async () => {
    const old = deferred<void>();
    mocks.connectWise.mockReturnValueOnce(old.promise);
    await render();
    await wrapper!.find('#wise-api-token').setValue('synthetic-old-token');
    wrapper!.find('#wise-api-token').element.closest('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    selectedRouting.value = 'other';
    selectedRouting.value = 'private';
    await flushPromises();
    await wrapper!.find('#wise-api-token').setValue('synthetic-new-token');
    old.resolve();
    await flushPromises();
    expect((wrapper!.find('#wise-api-token').element as HTMLInputElement).value).toBe('synthetic-new-token');
    expect(mocks.flashSuccess).not.toHaveBeenCalled();
  });

  it('discards an old connection failure after unmount', async () => {
    const old = deferred<void>();
    mocks.connectWise.mockReturnValueOnce(old.promise);
    await render();
    await wrapper!.find('#wise-api-token').setValue('synthetic-old-token');
    wrapper!.find('#wise-api-token').element.closest('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    wrapper!.unmount();
    wrapper = undefined;
    old.reject(new Error('synthetic old failure'));
    await flushPromises();
    expect(mocks.flashError).not.toHaveBeenCalled();
    expect(mocks.flashSuccess).not.toHaveBeenCalled();
  });

  it('keeps connect controls visible after a failed lookup and explicitly retries status', async () => {
    bridgeLookupFailed.value = true;
    await render();
    expect(wrapper!.text()).toContain('invoicing.bank_wise_connection_check_failed');
    expect(wrapper!.text()).not.toContain('invoicing.bank_wise_bridge_missing');
    expect(wrapper!.find('#wise-api-token').exists()).toBe(true);

    const pending = deferred<void>();
    mocks.fetchWiseStatus.mockReturnValueOnce(pending.promise);
    const retry = wrapper!.findAll('button').find((button) => button.text() === 'common.retry')!;
    await retry.trigger('click');
    expect(mocks.fetchWiseStatus).toHaveBeenCalledTimes(2);
    expect(retry.attributes('disabled')).toBeDefined();
    bridgeLookupFailed.value = false;
    wiseStatus.value = { connected: true };
    pending.resolve();
    await flushPromises();
    expect(wrapper!.text()).not.toContain('invoicing.bank_wise_connection_check_failed');
    expect(wrapper!.text()).toContain('invoicing.bank_wise_connected');
    expect(wrapper!.text()).toContain('invoicing.bank_wise_sync');
    expect(mocks.load).toHaveBeenCalledTimes(1);
  });

  it('shows only the missing-bridge guidance after a confirmed absence', async () => {
    wiseBridgeMissing.value = true;
    await render();
    expect(wrapper!.text()).toContain('invoicing.bank_wise_bridge_missing');
    expect(wrapper!.text()).not.toContain('invoicing.bank_wise_connection_check_failed');
    expect(wrapper!.find('#wise-api-token').exists()).toBe(false);
  });

  it('reports an unexpected retry failure locally and enables another explicit attempt', async () => {
    bridgeLookupFailed.value = true;
    await render();
    mocks.fetchWiseStatus.mockRejectedValueOnce(new Error('synthetic lookup failure'));
    await wrapper!.findAll('button').find((button) => button.text() === 'common.retry')!.trigger('click');
    await flushPromises();
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('invoicing.bank_wise_connection_check_failed');
    const retry = wrapper!.findAll('button').find((button) => button.text() === 'common.retry')!;
    expect(retry.attributes('disabled')).toBeUndefined();
    await retry.trigger('click');
    await flushPromises();
    expect(mocks.fetchWiseStatus).toHaveBeenCalledTimes(3);
  });

  it('discards a pending retry failure after a company change', async () => {
    bridgeLookupFailed.value = true;
    await render();
    const pending = deferred<void>();
    mocks.fetchWiseStatus.mockReturnValueOnce(pending.promise);
    await wrapper!.findAll('button').find((button) => button.text() === 'common.retry')!.trigger('click');
    route.params.companyId = 'local-b';
    await flushPromises();
    pending.reject(new Error('synthetic old lookup failure'));
    await flushPromises();
    expect(mocks.flashError).not.toHaveBeenCalled();
    expect(mocks.fetchWiseStatus).toHaveBeenCalledTimes(3);
  });

  it('discards a pending retry failure after unmount', async () => {
    bridgeLookupFailed.value = true;
    await render();
    const pending = deferred<void>();
    mocks.fetchWiseStatus.mockReturnValueOnce(pending.promise);
    await wrapper!.findAll('button').find((button) => button.text() === 'common.retry')!.trigger('click');
    wrapper!.unmount();
    wrapper = undefined;
    pending.reject(new Error('synthetic disposed lookup failure'));
    await flushPromises();
    expect(mocks.flashError).not.toHaveBeenCalled();
  });
});

describe('batch matching errors', () => {
  it.each([
    ['bank_import_row_batch_rejected', 'invoicing.bank_import_failed'],
    ['bank_reconciliation_outcome_unknown', 'invoicing.bank_matching_unknown'],
    ['bank_reconciliation_wait_timed_out', 'invoicing.bank_matching_busy'],
    ['expense_reconciliation_owner_unavailable', 'invoicing.expense_owner_unavailable'],
    ['synthetic failure', 'common.error'],
  ])('reports a mounted page load failure %s without retrying or rejecting the lifecycle', async (message, key) => {
    mocks.load.mockRejectedValueOnce(new Error(message));
    await render();
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith(key);
    expect(mocks.load).toHaveBeenCalledExactlyOnceWith('all', { skipErrorFlash: true });
  });

  it.each(['load', 'loadBatches'] as const)('discards a stale %s failure after switching companies', async (action) => {
    const pending = deferred<void>();
    mocks[action].mockReturnValueOnce(pending.promise);
    await render();
    route.params.companyId = 'local-b';
    await flushPromises();
    pending.reject(new Error('bank_reconciliation_outcome_unknown'));
    await flushPromises();
    expect(mocks[action]).toHaveBeenCalledTimes(2);
    expect(mocks.flashError).not.toHaveBeenCalled();
  });

  it('reports a current batch-list failure through the page without a global error flash', async () => {
    mocks.loadBatches.mockRejectedValueOnce(new Error('synthetic batch failure'));
    await render();
    expect(mocks.loadBatches).toHaveBeenCalledExactlyOnceWith({ skipErrorFlash: true });
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('common.error');
  });

  it.each(['load', 'loadBatches'] as const)('discards a superseded %s failure across A-B-A', async (action) => {
    const pending = deferred<void>();
    mocks[action].mockReturnValueOnce(pending.promise);
    await render();
    route.params.companyId = 'local-b';
    await flushPromises();
    route.params.companyId = 'local-a';
    await flushPromises();
    pending.reject(new Error('bank_reconciliation_outcome_unknown'));
    await flushPromises();
    expect(mocks[action]).toHaveBeenCalledTimes(3);
    expect(mocks.flashError).not.toHaveBeenCalled();
  });

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

  it('discards a failed saved-action refresh after switching companies', async () => {
    transactions.value = [{ id: 'synthetic-tx', booked_at: '2026-11-04', amount: '19.00', currency: 'EUR', direction: 'debit', match_status: 'unmatched' }];
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_tab_transactions')!.trigger('click');
    const pending = deferred<void>();
    mocks.load.mockClear();
    mocks.load.mockReturnValueOnce(pending.promise);
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_ignore')!.trigger('click');
    await flushPromises();
    expect(mocks.load).toHaveBeenCalledOnce();
    route.params.companyId = 'local-b';
    await flushPromises();
    pending.reject(new Error('synthetic refresh failure'));
    await flushPromises();
    expect(mocks.ignore).toHaveBeenCalledExactlyOnceWith('synthetic-tx');
    expect(mocks.flashError).not.toHaveBeenCalled();
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
    expect(mocks.load).toHaveBeenCalledWith('all', { skipErrorFlash: true });
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

  it('reports an unknown inbound import during post-match refresh with recovery guidance', async () => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 1, row_count: 1 }];
    await render();
    mocks.load.mockClear();
    mocks.load.mockRejectedValueOnce(new Error('bank_reconciliation_outcome_unknown'));
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_auto_match_batch')!.trigger('click');
    await flushPromises();
    expect(mocks.autoMatchBatch).toHaveBeenCalledExactlyOnceWith('synthetic-batch');
    expect(mocks.load).toHaveBeenCalledOnce();
    expect(mocks.flashError).toHaveBeenCalledExactlyOnceWith('invoicing.bank_matching_unknown');
  });

  it.each(['resolve', 'reject'] as const)('discards a pending batch %s after switching companies', async (outcome) => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 1, row_count: 1 }];
    const pending = deferred<void>();
    mocks.autoMatchBatch.mockReturnValueOnce(pending.promise);
    await render();
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_auto_match_batch')!.trigger('click');
    route.params.companyId = 'local-b';
    await flushPromises();
    mocks.load.mockClear();
    if (outcome === 'resolve') pending.resolve();
    else pending.reject(new Error('bank_reconciliation_outcome_unknown'));
    await flushPromises();
    expect(mocks.autoMatchBatch).toHaveBeenCalledExactlyOnceWith('synthetic-batch');
    expect(mocks.load).not.toHaveBeenCalled();
    expect(mocks.flashError).not.toHaveBeenCalled();
  });

  it('discards a failed post-match refresh after switching companies', async () => {
    batches.value = [{ id: 'synthetic-batch', imported_count: 1, row_count: 1 }];
    await render();
    const pending = deferred<void>();
    mocks.load.mockClear();
    mocks.load.mockReturnValueOnce(pending.promise);
    await wrapper!.findAll('button').find((button) => button.text() === 'invoicing.bank_auto_match_batch')!.trigger('click');
    await flushPromises();
    expect(mocks.load).toHaveBeenCalledOnce();
    route.params.companyId = 'local-b';
    await flushPromises();
    pending.reject(new Error('synthetic refresh failure'));
    await flushPromises();
    expect(mocks.autoMatchBatch).toHaveBeenCalledExactlyOnceWith('synthetic-batch');
    expect(mocks.flashError).not.toHaveBeenCalled();
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
    expect(mocks.ensure).toHaveBeenCalledWith('local-a', expect.any(Function), { preferRecordedBridgeId: true });
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
