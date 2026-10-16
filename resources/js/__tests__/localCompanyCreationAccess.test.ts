import { flushPromises, shallowMount } from '@vue/test-utils';
import { computed, ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { User } from '../store/auth';
const mocks = vi.hoisted(() => ({
  auth: { user: null as User | null, fetchUser: vi.fn() }, loadQuery: vi.fn(), insert: vi.fn(), push: vi.fn(),
}));
vi.mock('../store/auth', () => ({ useAuthStore: () => mocks.auth }));
vi.mock('vue-router', () => ({ useRouter: () => ({ push: mocks.push }) }));
vi.mock('../store/stores', () => ({ useStoresStore: () => ({ stores: [{ id: 'synthetic-store' }], fetchStores: vi.fn() }) }));
vi.mock('../evolu/flags', () => ({ isInvoicingLocalFirst: () => true }));
vi.mock('../evolu/client', () => ({ useInvoicingEvolu: () => ({ loadQuery: mocks.loadQuery }), allCompaniesQuery: {} }));
vi.mock('../evolu/companyInsert', () => ({ insertLocalCompanyFromPayload: mocks.insert }));
vi.mock('../evolu/ephemeralBridge', () => ({ syncLinkedStoreToServerBridge: vi.fn() }));
vi.mock('../evolu/numberSeriesCrud', () => ({ seedDefaultNumberSeries: vi.fn(), localizedDefaultSeries: vi.fn() }));
vi.mock('../composables/useInvoicingRelaySync', () => ({ useInvoicingRelaySync: () => ({ isRelaySyncing: computed(() => false) }) }));
vi.mock('../composables/useCompanyRegistryLookup', () => ({ useCompanyRegistryLookup: () => ({
  suggestions: ref([]), registryLoading: ref(false), registryError: ref(''), showSuggestions: ref(false),
  registryOptions: ref([]), scheduleSearch: vi.fn(), clearSuggestions: vi.fn(), selectSuggestionForCompany: vi.fn(),
  registrySupportsAutocomplete: () => false,
}) }));
vi.mock('../services/api', () => ({ default: { post: vi.fn() } }));
import CompanyForm from '../pages/invoicing/CompanyForm.vue';
function account(id = 1): User { return {
  id, email: 'synthetic@example.test', role: 'pro', is_guest: false, email_verified_at: '2026-01-01',
  plan: { code: 'pro', name: 'Pro', features: ['business_invoicing'], max_companies: 2,
    max_stores: 3, max_api_keys: 3, max_ln_addresses: null },
}; }
async function submit() {
  const wrapper = shallowMount(CompanyForm, { global: { stubs: { InvoicingPageShell: { template: '<div><slot /></div>' } } } });
  await wrapper.get('form').trigger('submit'); await flushPromises();
  return wrapper;
}
describe('direct local company-create route', () => {
  beforeEach(() => {
    vi.clearAllMocks(); mocks.auth.user = account(); mocks.auth.fetchUser.mockResolvedValue(true);
    mocks.loadQuery.mockResolvedValue([]); mocks.insert.mockReturnValue({ ok: true, value: { id: 'synthetic-company' } });
    vi.spyOn(window, 'alert').mockImplementation(() => undefined);
  });
  it('requires fresh access and blocks an overlimit account without touching its data', async () => {
    mocks.loadQuery.mockResolvedValue([{ legalName: 'A' }, { legalName: 'B' }, { legalName: 'C' }]);
    const wrapper = await submit();
    expect(mocks.auth.fetchUser).toHaveBeenCalledTimes(1);
    expect(mocks.insert).not.toHaveBeenCalled(); expect(mocks.push).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('does not insert while offline or if the refreshed session switches accounts', async () => {
    mocks.auth.fetchUser.mockResolvedValueOnce(false);
    let wrapper = await submit(); expect(mocks.insert).not.toHaveBeenCalled(); wrapper.unmount();
    mocks.auth.fetchUser.mockImplementationOnce(async () => { mocks.auth.user = account(2); return true; });
    wrapper = await submit(); expect(mocks.insert).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('keeps manual PRO state while refusing a new company without verified email', async () => {
    mocks.auth.fetchUser.mockImplementationOnce(async () => { mocks.auth.user = { ...account(), email_verified_at: undefined }; return true; });
    const wrapper = await submit();
    expect(mocks.auth.user?.role).toBe('pro');
    expect(mocks.insert).not.toHaveBeenCalled(); expect(mocks.push).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('retains the existing insertion and navigation behavior when eligible and below quota', async () => {
    const wrapper = await submit();
    expect(mocks.insert).toHaveBeenCalledTimes(1);
    expect(mocks.push).toHaveBeenCalledWith({ name: 'invoicing' }); wrapper.unmount();
  });
});
