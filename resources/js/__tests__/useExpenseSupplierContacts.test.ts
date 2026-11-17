import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { deserializeQuery } from '@evolu/common/local-first';

const mocks = vi.hoisted(() => ({ local: false, list: vi.fn(), load: vi.fn(), ready: vi.fn(), sharedOwner: null as string | null }));
const company = ref('company-a');
const routing = ref('private');
vi.mock('@/evolu/flags', () => ({ isInvoicingLocalFirst: () => mocks.local }));
vi.mock('@/evolu/client', () => ({ useInvoicingEvolu: () => ({ loadQuery: mocks.load, appOwner: Promise.resolve({ id: 'owner-private' }) }) }));
vi.mock('@/services/api', () => ({ invoicingApi: { contacts: { list: mocks.list } } }));
vi.mock('@/evolu/companyShareRegistry', () => ({
  companyShareRoutingKey: () => routing.value,
  ownerIdForCompany: () => mocks.sharedOwner,
  waitCompanyShareRegistryReady: mocks.ready,
}));
import { useExpenseSupplierContacts } from '@/composables/useExpenseSupplierContacts';

let resource: ReturnType<typeof useExpenseSupplierContacts>;
let wrapper: VueWrapper | undefined;
function render(local = false) {
  mocks.local = local;
  wrapper = mount(defineComponent({ setup() {
    resource = useExpenseSupplierContacts(company);
    return () => h('div', resource.contacts.value.map(contact => contact.name).join(','));
  } }));
}
function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>(done => { resolve = done; });
  return { promise, resolve };
}
beforeEach(() => {
  vi.resetAllMocks();
  mocks.local = false;
  mocks.sharedOwner = null;
  mocks.ready.mockResolvedValue(undefined);
  mocks.list.mockResolvedValue({ data: [], meta: { last_page: 1 } });
  mocks.load.mockResolvedValue([]);
  company.value = 'company-a';
  routing.value = 'private';
});
afterEach(() => { wrapper?.unmount(); wrapper = undefined; });

describe('expense supplier contact loading', () => {
  it('loads all server pages and keeps inactive contacts out of the picker', async () => {
    mocks.list.mockResolvedValueOnce({ data: [{ id: 'a', name: 'First' }], meta: { last_page: 2 } })
      .mockResolvedValueOnce({ data: [{ id: 'b', name: 'Second' }, { id: 'c', name: 'Inactive', is_active: false }], meta: { last_page: 2 } });
    render(); await flushPromises();
    expect(mocks.list.mock.calls.map(call => call.slice(0, 2))).toEqual([
      ['company-a', { page: 1, per_page: 100 }], ['company-a', { page: 2, per_page: 100 }],
    ]);
    expect(resource.contacts.value.map(contact => contact.id)).toEqual(['a', 'b']);
    expect(resource.failed.value).toBe(false);
  });

  it('clears choices on company changes and rejects a late old-company response', async () => {
    const pending = deferred<{ data: unknown[]; meta: { last_page: number } }>();
    mocks.list.mockReturnValueOnce(pending.promise);
    render();
    company.value = 'company-b'; await flushPromises();
    pending.resolve({ data: [{ id: 'foreign', name: 'Old company contact' }], meta: { last_page: 2 } });
    await flushPromises();
    expect(resource.contacts.value).toEqual([]);
    expect(mocks.list).toHaveBeenCalledTimes(2);
    expect(mocks.list.mock.calls[1][0]).toBe('company-b');
  });

  it('reports failures for manual entry and supports a deliberate retry', async () => {
    mocks.list.mockRejectedValueOnce(new Error('synthetic unavailable'));
    render(); await flushPromises();
    expect(resource.failed.value).toBe(true);
    expect(resource.loading.value).toBe(false);
    mocks.list.mockResolvedValueOnce({ data: [{ id: 'a', name: 'Recovered' }], meta: {} });
    await resource.refresh();
    expect(resource.contacts.value).toMatchObject([{ id: 'a', name: 'Recovered' }]);
    expect(resource.failed.value).toBe(false);
  });

  it.each([null, 'owner-shared'])('binds local reads to company and resolved owner %s', async sharedOwner => {
    mocks.sharedOwner = sharedOwner;
    mocks.load.mockResolvedValueOnce([{ id: 'a', companyId: 'company-a', name: 'Local supplier', iban: 'SK6807200002891987426353' }]);
    render(true); await flushPromises();
    const query = deserializeQuery(mocks.load.mock.calls[0][0]);
    expect(query.sql).toContain('"companyId" = ?');
    expect(query.sql).toContain('"ownerId" = ?');
    expect(query.parameters).toEqual(['company-a', sharedOwner ?? 'owner-private', 1]);
    expect(resource.contacts.value[0]).toMatchObject({ name: 'Local supplier', iban: 'SK6807200002891987426353' });
  });

  it('ignores a local query that resolves after owner routing changes', async () => {
    const pending = deferred<unknown[]>();
    mocks.load.mockReturnValueOnce(pending.promise);
    render(true); await flushPromises();
    mocks.sharedOwner = 'owner-shared'; routing.value = 'shared'; await flushPromises();
    pending.resolve([{ id: 'old', companyId: 'company-a', name: 'Previous owner contact' }]);
    await flushPromises();
    expect(resource.contacts.value).toEqual([]);
    expect(mocks.load).toHaveBeenCalledTimes(2);
  });

  it('keeps pending server responses out after disposal', async () => {
    const pending = deferred<{ data: unknown[]; meta: object }>();
    mocks.list.mockReturnValueOnce(pending.promise);
    render(); wrapper!.unmount(); wrapper = undefined;
    pending.resolve({ data: [{ id: 'a', name: 'Too late' }], meta: {} }); await flushPromises();
    expect(resource.contacts.value).toEqual([]);
  });
});
