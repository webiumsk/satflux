import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises, type VueWrapper } from '@vue/test-utils';
import { reactive, ref, nextTick } from 'vue';

const mocks = vi.hoisted(() => ({ ensure: vi.fn(), inbound: vi.fn(), local: true }));
const route = reactive({ params: { companyId: 'local-a' } });
const hasBankAccount = ref(true);
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
  localFirst: mocks.local, loading: ref(false), transactions: ref([]), batches: ref([]),
  summary: ref({ credit_count: 0, credit_total: '0', debit_count: 0, debit_total: '0', balance: '0', currency: 'EUR', by_currency: [] }),
  wiseStatus: ref(null), wiseBridgeMissing: ref(false),
  load: vi.fn().mockResolvedValue(undefined), loadBatches: vi.fn().mockResolvedValue(undefined),
  fetchWiseStatus: vi.fn().mockResolvedValue(undefined),
}) }));
vi.mock('@/store/flash', () => ({ useFlashStore: () => ({ error: vi.fn(), success: vi.fn() }) }));
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
  mocks.ensure.mockImplementation(async (company: string) => ({ ok: true, bridgeCompanyId: `bridge-${company}` }));
  mocks.inbound.mockImplementation(async (company: string) => response(company === 'bridge-local-a' ? addressA : addressB));
});
afterEach(() => { wrapper?.unmount(); wrapper = undefined; });

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
