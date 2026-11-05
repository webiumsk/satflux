import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { enableAutoUnmount, flushPromises, mount, shallowMount } from '@vue/test-utils';
import { defineComponent, h, ref } from 'vue';
import api from '../services/api';
import AppScrollPane from '../components/layout/AppScrollPane.vue';
import InvoicingPageShell from '../components/invoicing/InvoicingPageShell.vue';
import InvoicingEvoluBootPlaceholder from '../components/invoicing/InvoicingEvoluBootPlaceholder.vue';
import AppShowLayout from '../components/stores/AppShowLayout.vue';

enableAutoUnmount(afterEach);

const mocks = vi.hoisted(() => ({
  fetchStore: vi.fn(), fetchDashboard: vi.fn(), fetchApps: vi.fn(),
}));

beforeEach(() => {
  vi.clearAllMocks();
  mocks.fetchStore.mockResolvedValue({ id: 'sample-store', name: 'Sample Store', logo_url: null, wallet_type: null });
  mocks.fetchDashboard.mockResolvedValue(undefined);
  mocks.fetchApps.mockResolvedValue([]);
});

vi.mock('vue-router', () => ({
  useRoute: () => ({ path: '/stores/sample-store', name: 'stores-show', params: { id: 'sample-store' }, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));
vi.mock('../store/apps', () => ({ useAppsStore: () => ({ apps: [], fetchApps: mocks.fetchApps }) }));
vi.mock('../store/stores', () => ({ useStoresStore: () => ({ dashboard: null, fetchStore: mocks.fetchStore, fetchDashboard: mocks.fetchDashboard }) }));
vi.mock('../store/flash', () => ({ useFlashStore: () => ({ error: vi.fn(), success: vi.fn(), clear: vi.fn() }) }));
vi.mock('../composables/useBtcPayUrl', () => ({
  useBtcPayUrl: () => ({ displayLightningDomain: ref('satflux.org'), load: vi.fn().mockResolvedValue(undefined) }),
}));
vi.mock('vue-i18n', async importOriginal => ({
  ...await importOriginal<typeof import('vue-i18n')>(),
  useI18n: () => ({ t: (key: string) => key }),
}));
vi.mock('../components/layout/AppFooter.vue', () => ({
  default: defineComponent({ name: 'AppFooter', setup: () => () => h('footer', 'footer links') }),
}));

const stubs = { InvoicingDocumentSubNav: true, InvoicingToolsSubNav: true, StoreSidebar: true, ArchivedStoreBanner: true };

describe('primary scroll footer ownership', () => {
  it('keeps nested panes footer-free unless their owning boundary opts in', () => {
    const NestedPanes = defineComponent({
      setup: () => () => h(AppScrollPane, { showFooter: true }, {
        default: () => [h('h1', 'Page'), h(AppScrollPane, {}, { default: () => h('p', 'Nested content') })],
      }),
    });
    const wrapper = mount(NestedPanes);
    expect(wrapper.findAll('footer')).toHaveLength(1);
    expect(wrapper.get('footer').element.parentElement).toBe(wrapper.element);
    expect(wrapper.element.lastElementChild?.tagName).toBe('FOOTER');
    expect(wrapper.findAllComponents(AppScrollPane)[1]!.find('footer').exists()).toBe(false);
  });

  it('moves the standalone invoicing footer with content and removes it when embedded', async () => {
    const wrapper = mount(InvoicingPageShell, { slots: { default: '<article>Invoice list</article>' }, global: { stubs } });
    expect(wrapper.findAll('footer')).toHaveLength(1);
    expect(wrapper.element.lastElementChild?.tagName).toBe('FOOTER');
    expect(wrapper.get('article').element.parentElement?.nextElementSibling).toBe(wrapper.get('footer').element);
    await wrapper.setProps({ embedded: true });
    expect(wrapper.find('footer').exists()).toBe(false);
    expect(wrapper.get('article').text()).toBe('Invoice list');
  });

  it('does not duplicate the dashboard footer while embedded invoicing is loading or fails', async () => {
    const wrapper = mount(InvoicingEvoluBootPlaceholder, { props: { embedded: true }, global: { stubs: { InvoicingLoadingState: true } } });
    expect(wrapper.find('footer').exists()).toBe(false);
    await wrapper.setProps({ error: true });
    expect(wrapper.find('footer').exists()).toBe(false);
    await wrapper.setProps({ embedded: false });
    expect(wrapper.findAll('footer')).toHaveLength(1);
    expect(wrapper.element.lastElementChild?.tagName).toBe('FOOTER');
  });

  it.each([false, true])('keeps store app toolbars outside the content scrollport (Inertia=%s)', (inertia) => {
    const wrapper = mount(AppShowLayout, {
      props: { store: { id: 'sample-store', name: 'Sample Store' } as never, app: { id: 'sample-app', name: 'Sample App' } as never },
      slots: { toolbar: '<nav data-testid="toolbar">Store tools</nav>', default: '<article>Long app content</article>' },
      global: { stubs, provide: { inertia } },
    });
    const pane = wrapper.getComponent(AppScrollPane);
    expect(pane.findAll('footer')).toHaveLength(1);
    expect(pane.get('article').text()).toBe('Long app content');
    expect(pane.find('[data-testid="toolbar"]').exists()).toBe(false);
    expect(wrapper.get('[data-testid="toolbar"]').element.parentElement?.nextElementSibling).toBe(pane.element);
    expect(pane.element.lastElementChild?.tagName).toBe('FOOTER');
  });

  it('keeps a store avatar without a logo inside its single primary pane', async () => {
    const { default: StoreShow } = await import('../pages/stores/Show.vue');
    const wrapper = shallowMount(StoreShow, { global: {
      stubs: { AppScrollPane: false, AppFooter: false, RouterLink: true },
      mocks: { $t: (key: string) => key },
    } });
    await flushPromises();
    const pane = wrapper.getComponent(AppScrollPane);
    const avatar = wrapper.get('.h-20.w-20');
    expect(avatar.element.tagName).toBe('DIV');
    expect(avatar.text()).toBe('S');
    expect(avatar.element.closest('.overflow-y-auto')).toBe(pane.element);
    expect(wrapper.findAllComponents(AppScrollPane)).toHaveLength(1);
    expect(wrapper.findAll('footer')).toHaveLength(1);
    expect(pane.element.lastElementChild?.tagName).toBe('FOOTER');
  });

  it('keeps pending lightning-address loading inside its single primary pane', async () => {
    let resolve!: (value: { data: { data: unknown[] } }) => void;
    const pending = new Promise<{ data: { data: unknown[] } }>(done => { resolve = done; });
    const get = vi.spyOn(api, 'get').mockReturnValue(pending as ReturnType<typeof api.get>);
    const { default: LightningAddresses } = await import('../pages/stores/LightningAddresses.vue');
    const wrapper = shallowMount(LightningAddresses, { global: { stubs: { AppScrollPane: false, AppFooter: false } } });
    try {
      await flushPromises();
      expect(get).toHaveBeenCalledWith('/stores/sample-store/lightning-addresses');
      const loading = wrapper.findAll('p').find(node => node.text() === 'stores.loading_lightning_addresses');
      expect(loading).toBeDefined();
      const pane = wrapper.getComponent(AppScrollPane);
      expect(loading!.element.closest('.overflow-y-auto')).toBe(pane.element);
      expect(wrapper.findAllComponents(AppScrollPane)).toHaveLength(1);
      expect(wrapper.findAll('footer')).toHaveLength(1);
      expect(pane.element.lastElementChild?.tagName).toBe('FOOTER');
    } finally {
      resolve({ data: { data: [] } });
      await flushPromises();
      get.mockRestore();
    }
    expect(wrapper.findAll('footer')).toHaveLength(1);
  });

});
