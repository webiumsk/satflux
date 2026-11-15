import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { invoicingRoutes } from '../router/invoicingRoutes';
import { useInvoicingLayout } from '../composables/useInvoicingLayout';
import InvoicingAppHeader from '../components/invoicing/InvoicingAppHeader.vue';
import InvoicingPageShell from '../components/invoicing/InvoicingPageShell.vue';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('../composables/useInvoicingCompanySummary', () => ({
  useInvoicingCompanySummary: () => ({ hasBankAccount: ref(false), companyName: ref('Synthetic company') }),
}));
vi.mock('../evolu/efakturaInboxLive', () => ({ initEfakturaInboxLive: vi.fn() }));
vi.mock('../evolu/integrationInboxLive', () => ({ initIntegrationInboxLive: vi.fn() }));
vi.mock('../components/invoicing/IntegrationInboxBadge.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingCompanySwitcher.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingMobileNavDrawer.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingMobileFilterDrawer.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/invoicing/InvoicingDocumentSubNav.vue', () => ({ default: { template: '<span />' } }));
vi.mock('../components/layout/AppFooter.vue', () => ({ default: { template: '<footer />' } }));

const applicationRoutes = [
  'invoicing-company-app', 'invoicing-company-app-emails',
  'invoicing-company-app-series', 'invoicing-company-app-woocommerce',
] as const;

async function routedShell(routeName: string) {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      ...invoicingRoutes.map((route) => ({
        path: `/invoicing/${route.path}`, name: route.name, meta: route.meta,
        component: { template: '<div />' },
      })),
      { path: '/account', name: 'account', component: { template: '<div />' } },
    ],
  });
  await router.push({ name: routeName, params: { companyId: 'synthetic-company-a' } });
  await router.isReady();
  let layout!: ReturnType<typeof useInvoicingLayout>;
  const host = defineComponent({
    setup() {
      layout = useInvoicingLayout();
      return () => h(InvoicingPageShell, {}, {
        header: () => h(InvoicingAppHeader, { showFilterBar: false }),
        default: () => h('p', 'Synthetic content'),
      });
    },
  });
  const wrapper = mount(host, { global: { plugins: [router] } });
  await flushPromises();
  return { wrapper, router, layout };
}

beforeEach(() => {
  HTMLElement.prototype.scrollIntoView = vi.fn();
  window.sessionStorage.clear();
});

describe('invoicing application navigation', () => {
  it.each(applicationRoutes)('keeps Tools and Application active for %s', async (routeName) => {
    const { wrapper, layout } = await routedShell(routeName);
    expect(layout.activeMainSection.value).toBe('tools');
    expect(layout.activeToolsSection.value).toBe('app');
    expect(wrapper.get('.invoicing-main-nav__item--active').text()).toBe('invoicing.main_nav_tools');
    const tools = wrapper.get('nav[aria-label="Company settings"]');
    expect(tools.findAll('.invoicing-sub-nav__item--active')).toHaveLength(1);
    expect(tools.get('.invoicing-sub-nav__item--active').text()).toBe('invoicing.settings_nav_application');
    expect(wrapper.get('.invoicing-mobile-header').text()).toContain('invoicing.settings_nav_application');
    wrapper.unmount();
  });

  it('keeps company-scoped Tools navigation available from WooCommerce', async () => {
    const { wrapper, router } = await routedShell('invoicing-company-app-woocommerce');
    const tools = wrapper.get('nav[aria-label="Company settings"]');
    const profile = tools.findAll('a').find((link) => link.text() === 'invoicing.settings_nav_profile')!;
    expect(profile.attributes('href')).toBe('/invoicing/companies/synthetic-company-a');
    await profile.trigger('click'); await flushPromises();
    expect(router.currentRoute.value.name).toBe('invoicing-company');
    expect(router.currentRoute.value.params.companyId).toBe('synthetic-company-a');
    expect(tools.get('.invoicing-sub-nav__item--active').text()).toBe('invoicing.settings_nav_profile');
    wrapper.unmount();
  });

  it('preserves the WooCommerce application tab when switching company', async () => {
    const { wrapper, router, layout } = await routedShell('invoicing-company-app-woocommerce');
    layout.switchCompany('synthetic-company-b'); await flushPromises();
    expect(router.currentRoute.value.name).toBe('invoicing-company-app-woocommerce');
    expect(router.currentRoute.value.params.companyId).toBe('synthetic-company-b');
    expect(layout.activeToolsSection.value).toBe('app');
    expect(wrapper.get('nav[aria-label="Company settings"]').get('.invoicing-sub-nav__item--active').text())
      .toBe('invoicing.settings_nav_application');
    wrapper.unmount();
  });

  it.each([
    ['invoicing-company', 'profile'], ['invoicing-company-import', 'import'],
    ['invoicing-vat-report', 'vat-report'], ['invoicing-audit', 'audit'],
    ['invoicing-accountant-export', 'accountant-export'],
  ])('preserves %s navigation outside the application tabs', async (routeName, section) => {
    const { wrapper, layout } = await routedShell(routeName);
    expect(layout.activeMainSection.value).toBe('tools');
    expect(layout.activeToolsSection.value).toBe(section);
    expect(wrapper.findAll('nav[aria-label="Company settings"] .invoicing-sub-nav__item--active')).toHaveLength(1);
    wrapper.unmount();
  });
});
