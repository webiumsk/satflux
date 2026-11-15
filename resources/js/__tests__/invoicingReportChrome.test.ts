import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { h, ref } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { invoicingRoutes } from '../router/invoicingRoutes';
import InvoicingAppHeader from '../components/invoicing/InvoicingAppHeader.vue';
import VatReport from '../pages/invoicing/VatReport.vue';
import GobdAudit from '../pages/invoicing/GobdAudit.vue';
import AccountantExport from '../pages/invoicing/AccountantExport.vue';
import type { IssuePeriodState } from '../composables/useInvoicingIssuePeriod';

const mocks = vi.hoisted(() => ({ download: vi.fn(), downloadBlob: vi.fn(), buildAuditBlob: vi.fn() }));
const period = ref<IssuePeriodState>({ preset: 'this_year', customFrom: '', customTo: '' });
const options = ref({ formats: ['csv'], includeIsdoc: false, includePdf: false, includeUbl: false, includeExpenseAttachments: false });

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }));
vi.mock('../composables/useInvoicingCompanySummary', () => ({
  useInvoicingCompanySummary: () => ({ hasBankAccount: ref(false), companyName: ref('Synthetic company') }),
}));
vi.mock('../composables/useInvoicingCompany', () => ({
  useInvoicingCompany: () => ({ company: ref({ id: 'synthetic-company', legal_name: 'Synthetic company', jurisdiction: 'eu_sk' }) }),
}));
vi.mock('../composables/useVatReport', () => ({ useVatReport: () => ({
  loading: ref(false), loadError: ref(false), period, retry: vi.fn(),
  summary: ref({ from: '2026-01-01', to: '2026-12-31', byCurrency: [
    { currency: 'EUR', documentCount: 1, turnover: 123, base: 100, vat: 23,
      byRate: [{ rate: 23, base: 100, vat: 23, gross: 123 }] },
  ] }),
}) }));
vi.mock('../composables/useGobdAudit', () => ({ useGobdAudit: () => ({
  loading: ref(false), loadError: ref(false), period, gapReports: ref([]), buildExportBlob: mocks.buildAuditBlob, retry: vi.fn(),
}) }));
vi.mock('../composables/useAccountantExport', () => ({ useAccountantExport: () => ({
  period, options, range: ref({ from: '2026-01-01', to: '2026-12-31' }), plan: ref(null),
  nothingToExport: ref(false), wantsAnything: ref(true), loading: ref(false), loadError: ref(false),
  downloading: ref(false), progress: ref(null), error: ref(null), download: mocks.download, cancel: vi.fn(), retry: vi.fn(),
}) }));
vi.mock('../evolu/documentBulkLocal', () => ({ downloadCsvBlob: mocks.downloadBlob }));
vi.mock('../evolu/efakturaInboxLive', () => ({ initEfakturaInboxLive: vi.fn() }));
vi.mock('../evolu/integrationInboxLive', () => ({ initIntegrationInboxLive: vi.fn() }));
vi.mock('../components/invoicing/IntegrationInboxBadge.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingCompanySwitcher.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingMobileNavDrawer.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingMobileFilterDrawer.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingDocumentSubNav.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/layout/AppFooter.vue', () => ({ default: { template: '<footer />' } }));

async function reportRouter(name: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      ...invoicingRoutes.map((route) => ({ path: `/invoicing/${route.path}`, name: route.name, meta: route.meta, component: { template: '<div />' } })),
      { path: '/account', name: 'account', component: { template: '<div />' } },
    ],
  });
  await router.push({ name, params: { companyId: 'synthetic-company' } });
  await router.isReady();
  return router;
}

beforeEach(() => {
  HTMLElement.prototype.scrollIntoView = vi.fn();
  period.value = { preset: 'this_year', customFrom: '', customTo: '' };
  options.value = { formats: ['csv'], includeIsdoc: false, includePdf: false, includeUbl: false, includeExpenseAttachments: false };
  vi.clearAllMocks();
  mocks.buildAuditBlob.mockReturnValue({ blob: new Blob(['synthetic audit']), range: { from: '2026-01-01', to: '2026-12-31' } });
});

describe('report page chrome', () => {
  it.each([
    { route: 'invoicing-vat-report', component: VatReport, action: 'invoicing.vat_report_export_csv' },
    { route: 'invoicing-audit', component: GobdAudit, action: 'invoicing.audit_export_button' },
    { route: 'invoicing-accountant-export', component: AccountantExport, action: 'invoicing.accountant_export_download' },
  ])('omits the empty desktop filter strip on $route and retains page controls', async ({ route, component, action }) => {
    const router = await reportRouter(route);
    const wrapper = mount(component, { global: { plugins: [router] } }); await flushPromises();
    expect(wrapper.find('.invoicing-filter-bar').exists()).toBe(false);
    expect(wrapper.find('nav[aria-label="Company settings"]').exists()).toBe(true);
    const select = wrapper.get('select');
    await select.setValue('this_month');
    expect(period.value.preset).toBe('this_month');
    const exportButton = wrapper.findAll('button').find((button) => button.text() === action)!;
    expect(exportButton.attributes('disabled')).toBeUndefined();
    await exportButton.trigger('click');
    if (route === 'invoicing-accountant-export') expect(mocks.download).toHaveBeenCalledOnce();
    else expect(mocks.downloadBlob).toHaveBeenCalledOnce();
    wrapper.unmount();
  });

  it('keeps actual filter and action slots usable on filter-bearing headers', async () => {
    const router = await reportRouter('invoicing-contacts');
    const query = ref('');
    const action = vi.fn();
    const wrapper = mount(InvoicingAppHeader, { global: { plugins: [router] }, slots: {
      filters: () => h('input', { type: 'search', value: query.value, onInput: (event: Event) => { query.value = (event.target as HTMLInputElement).value; } }),
      actions: () => h('button', { onClick: action }, 'Synthetic action'),
    } });
    expect(wrapper.find('.invoicing-filter-bar').exists()).toBe(true);
    await wrapper.get('.invoicing-filter-bar input').setValue('Synthetic buyer');
    await wrapper.get('.invoicing-filter-bar button').trigger('click');
    expect(query.value).toBe('Synthetic buyer');
    expect(action).toHaveBeenCalledOnce();
    wrapper.unmount();
  });
});
