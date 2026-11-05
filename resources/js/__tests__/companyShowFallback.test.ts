import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { ref, type Component } from 'vue';

const mocks = vi.hoisted(() => ({
  getCompany: vi.fn(),
  getPublicVersion: vi.fn(),
  rememberCompany: vi.fn(),
  companyId: 'sample-company',
}));

vi.mock('../services/api', () => ({
  default: { get: mocks.getPublicVersion },
  invoicingApi: { companies: { get: mocks.getCompany } },
}));
vi.mock('../evolu/flags', () => ({ isInvoicingLocalFirst: () => false }));
vi.mock('../evolu/client', () => ({ allCompaniesDetailQuery: {}, useInvoicingEvolu: vi.fn() }));
vi.mock('@evolu/vue', () => ({ useQuery: vi.fn() }));
vi.mock('../store/stores', () => ({ useStoresStore: () => ({}) }));
vi.mock('../evolu/autoIssueProfileSync', () => ({ resyncAutoIssueProfileIfEnabled: vi.fn() }));
vi.mock('vue-router', () => ({ useRoute: () => ({ name: 'invoicing-company', path: '/invoicing/companies/sample-company' }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('../composables/useInvoicingLayout', () => ({
  INVOICING_CONTAINER_CLASS: 'max-w-7xl mx-auto',
  useInvoicingLayout: () => ({ companyId: ref(mocks.companyId), rememberCompany: mocks.rememberCompany }),
}));
vi.mock('../components/invoicing/InvoicingDocumentSubNav.vue', () => ({ default: { template: '<nav />' } }));
vi.mock('../components/invoicing/InvoicingToolsSubNav.vue', () => ({ default: { template: '<nav />' } }));

let CompanyShow: Component;
let wrapper: VueWrapper | undefined;

beforeAll(async () => {
  // The real company lookup and primary shell are exercised; unrelated settings forms are inert fixtures.
  for (const name of [
    'CompanyAppSettingsForm', 'CompanyAppTabsNav', 'CompanyAutoIssueCard',
    'CompanyEmailSettingsForm', 'CompanyNumberSeriesPanel', 'CompanySettingsForm',
    'CompanyShareCard', 'EfakturaReadinessCard', 'InvoicingGettingStartedCard', 'InvoicingAppHeader',
  ]) {
    vi.doMock(`../components/invoicing/${name}.vue`, () => ({ default: { template: '<section />' } }));
  }
  CompanyShow = (await import('../pages/invoicing/CompanyShow.vue')).default;
});

beforeEach(() => {
  mocks.companyId = 'sample-company';
  mocks.getCompany.mockReset();
  mocks.rememberCompany.mockReset();
  mocks.getPublicVersion.mockResolvedValue({ data: { version: '2.0.0-test' } });
});

afterEach(() => {
  wrapper?.unmount();
  wrapper = undefined;
});

/** Mount the actual company page and shared footer with an inert server lookup and ordinary local links. */
function render(): VueWrapper {
  wrapper = mount(CompanyShow, {
    global: { stubs: { RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' } } },
  });
  return wrapper;
}

/** Verify a single footer lives after fallback content inside the original primary invoicing scroll host. */
function expectFallbackWithFooter(page: VueWrapper): void {
  expect(page.get('[role="status"]').text()).toBe('invoicing.company_share_error_company_not_found');
  expect(page.get('a[href="/invoicing"]').text()).toBe('common.back');
  expect(page.findAll('footer')).toHaveLength(1);
  expect(page.element.lastElementChild?.tagName).toBe('FOOTER');
  expect(page.get('footer a[href="/legal/privacy"]').text()).toBeTruthy();
}

describe('company lookup fallback footer', () => {
  it('retains footer links when no company is selected without starting a company request', async () => {
    mocks.companyId = '';
    const page = render();
    await flushPromises();
    expect(mocks.getCompany).not.toHaveBeenCalled();
    expectFallbackWithFooter(page);
  });

  it('retains footer links when the selected company does not exist', async () => {
    mocks.getCompany.mockResolvedValue(null);
    const page = render();
    await flushPromises();
    expect(mocks.getCompany).toHaveBeenCalledWith('sample-company');
    expectFallbackWithFooter(page);
  });

  it('replaces the loading state with a footer-bearing fallback after a rejected company lookup', async () => {
    let reject!: (reason: Error) => void;
    mocks.getCompany.mockReturnValue(new Promise((_resolve, rejectLookup) => { reject = rejectLookup; }));
    const page = render();
    expect(page.text()).toContain('common.loading');
    expect(page.findAll('footer')).toHaveLength(1);
    reject(new Error('synthetic company lookup failure'));
    await flushPromises();
    expect(page.text()).not.toContain('common.loading');
    expectFallbackWithFooter(page);
  });

  it('preserves the existing successful company shell and its one footer', async () => {
    mocks.getCompany.mockResolvedValue({ id: 'sample-company', legal_name: 'Sample Company', trade_name: null });
    const page = render();
    await flushPromises();
    expect(page.get('h1').text()).toBe('invoicing.company_settings_title');
    expect(page.find('[role="status"]').exists()).toBe(false);
    expect(page.findAll('footer')).toHaveLength(1);
    expect(page.element.lastElementChild?.tagName).toBe('FOOTER');
  });
});
