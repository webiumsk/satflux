import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { ref } from 'vue';
import PointOfSaleShow from '../pages/stores/PointOfSaleShow.vue';
import AppsShow from '../pages/stores/AppsShow.vue';
import AppShowLayout from '../components/stores/AppShowLayout.vue';
import PointOfSaleProductsEditor from '../components/stores/PointOfSaleProductsEditor.vue';
import type { AppRef, PosProduct } from '../types/btcpayApps';
import type { Store } from '../store/stores';

const mocks = vi.hoisted(() => ({
  updateApp: vi.fn(), fetchApp: vi.fn(), fetchApps: vi.fn(), fetchStore: vi.fn(), success: vi.fn(), error: vi.fn(),
  appsState: null as unknown as { apps: AppRef[] },
  storesState: null as unknown as { currentStore: Store | null },
  route: { params: { id: '00000000-0000-4000-8000-000000000001', appId: '00000000-0000-4000-8000-000000000002' } },
}));
vi.mock('../store/apps', async () => {
  const { reactive } = await import('vue');
  const state = reactive({ apps: [] as AppRef[], updateApp: mocks.updateApp, fetchApp: mocks.fetchApp, fetchApps: mocks.fetchApps });
  mocks.appsState = state;
  return { useAppsStore: () => state };
});
vi.mock('../store/stores', async () => {
  const { reactive } = await import('vue');
  const state = reactive({ currentStore: null as Store | null, fetchStore: mocks.fetchStore });
  mocks.storesState = state;
  return { useStoresStore: () => state };
});
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: null }) }));
vi.mock('../composables/useGuestUpgradeModal', () => ({ useGuestUpgradeModal: () => ({ openGuestUpgradeModal: vi.fn() }) }));
vi.mock('vue-router', async importOriginal => {
  const { reactive } = await import('vue');
  mocks.route = reactive(mocks.route);
  return {
    ...await importOriginal<typeof import('vue-router')>(),
    useRoute: () => mocks.route,
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
  };
});
vi.mock('../store/flash', () => ({ useFlashStore: () => ({ success: mocks.success, error: mocks.error }) }));
vi.mock('../composables/useEffectiveAccess', async importOriginal => ({
  ...await importOriginal<typeof import('../composables/useEffectiveAccess')>(),
  useEffectiveAccess: () => ({ hasFeature: () => true }),
}));
vi.mock('../composables/useBtcPayUrl', () => ({ useBtcPayUrl: () => ({ btcPayUrl: ref('https://btcpay.example'), load: vi.fn().mockResolvedValue(undefined) }) }));
vi.mock('vue-i18n', async importOriginal => ({
  ...await importOriginal<typeof import('vue-i18n')>(),
  useI18n: () => ({ t: (key: string) => key }),
}));

const store = { id: '00000000-0000-4000-8000-000000000001', name: 'Sample store', default_currency: 'EUR' } as Store;
const appId = '00000000-0000-4000-8000-000000000002';
const standardConfig = { defaultView: 'Light', showItems: false, showCustomAmount: false, showDiscount: false, showSearch: true, showCategories: true, enableTips: false, items: [] };
const products: PosProduct[] = [
  { id: 'coffee', title: 'Coffee', priceType: 'Fixed', price: 2.5, taxRate: 10, image: '', description: 'Synthetic coffee', categories: 'Drinks', inventory: 4, buyButtonText: null, disabled: false },
  { id: 'tea', title: 'Tea', priceType: 'Fixed', price: 1.5, taxRate: null, image: '', description: '', categories: null, inventory: null, buyButtonText: 'Tea', disabled: false },
];
let wrapper: VueWrapper | undefined;

/** Mount the actual PoS page and app-layout ref boundary with only unrelated UI shells stubbed. */
async function mountEditor(config: Record<string, unknown>, routeLoaded = false): Promise<VueWrapper> {
  const app = { id: appId, app_type: 'PointOfSale', name: 'Sample PoS', config: { ...standardConfig, ...config } } as AppRef;
  if (routeLoaded) {
    mocks.fetchStore.mockResolvedValue(store);
    mocks.fetchApps.mockResolvedValue([{ id: appId, app_type: 'PointOfSale', name: 'Sample PoS' }]);
    mocks.fetchApp.mockResolvedValue(app);
  }
  wrapper = shallowMount(PointOfSaleShow, {
    props: routeLoaded ? {} : { store, app },
    global: { provide: { inertia: !routeLoaded }, stubs: { AppShowLayout: false, AppScrollPane: false, PointOfSaleProductsEditor: false } },
  });
  await flushPromises();
  return wrapper;
}

/** Exercise the router parent while its sidebar receives metadata-only app records. */
async function mountSpaEditor(config: Record<string, unknown>): Promise<VueWrapper> {
  const metadata = { id: appId, name: 'Sample PoS', app_type: 'PointOfSale', config: { id: 'synthetic-pos', name: 'Sample PoS' } } as AppRef;
  mocks.fetchStore.mockImplementation(async () => {
    mocks.storesState.currentStore = store;
    return store;
  });
  mocks.fetchApps.mockImplementation(async () => {
    mocks.appsState.apps = [metadata];
    return [metadata];
  });
  mocks.fetchApp.mockResolvedValue({ ...metadata, config: { ...standardConfig, ...config } });
  wrapper = shallowMount(AppsShow, {
    global: { provide: { inertia: false }, stubs: { PointOfSaleShow: false, AppShowLayout: false, AppScrollPane: false, PointOfSaleProductsEditor: false } },
  });
  await flushPromises();
  return wrapper;
}

beforeEach(() => {
  vi.resetAllMocks();
  mocks.route.params.id = store.id;
  mocks.route.params.appId = appId;
  mocks.appsState.apps = [];
  mocks.storesState.currentStore = null;
});
afterEach(() => {
  wrapper?.unmount();
  wrapper = undefined;
  vi.useRealTimers();
});

describe('PoS editor settings and product round-trip', () => {
  it('saves a Light catalog created in Satflux and restores both items after a full remount', async () => {
    const page = await mountEditor({ defaultView: 'Light', showItems: false, items: [] });
    expect((page.get('#showItems').element as HTMLInputElement).checked).toBe(false);
    await page.get('#showItems').setValue(true);
    await page.get('#enableTips').setValue(true);
    await page.get('#title').setValue('Sample terminal');
    const drawer = page.findComponent({ name: 'ProductEditDrawer' });
    drawer.vm.$emit('save', products[0]);
    drawer.vm.$emit('save', products[1]);
    await flushPromises();
    expect(page.getComponent(PointOfSaleProductsEditor).props('products')).toHaveLength(2);
    let persisted: Record<string, unknown> = {};
    mocks.updateApp.mockImplementation(async (_store: string, _app: string, payload: { config: Record<string, unknown> }) => {
      persisted = structuredClone(payload.config);
      return { id: appId, name: 'Sample PoS', config: persisted };
    });
    vi.useFakeTimers();
    await page.get('#pos-settings-form').trigger('submit');
    await flushPromises();
    await vi.advanceTimersByTimeAsync(501);
    await flushPromises();
    expect(mocks.updateApp).toHaveBeenCalledOnce();
    expect(persisted.showItems).toBe(true);
    expect(persisted.enableTips).toBe(true);
    expect(persisted.title).toBe('Sample terminal');
    expect(persisted.template).toEqual(expect.arrayContaining([
      expect.objectContaining({ id: 'coffee', title: 'Coffee', price: '2.5', inventory: 4, taxRate: '10' }),
      expect.objectContaining({ id: 'tea', title: 'Tea', price: '1.5', inventory: null, taxRate: null }),
    ]));
    page.unmount();
    wrapper = undefined;
    vi.useRealTimers();
    const reopened = await mountEditor({ ...persisted, items: persisted.template });
    expect((reopened.get('#showItems').element as HTMLInputElement).checked).toBe(true);
    expect((reopened.get('#enableTips').element as HTMLInputElement).checked).toBe(true);
    expect((reopened.get('#title').element as HTMLInputElement).value).toBe('Sample terminal');
    expect(reopened.getComponent(PointOfSaleProductsEditor).props('products')).toEqual(expect.arrayContaining([
      expect.objectContaining({ id: 'coffee', title: 'Coffee', price: 2.5, inventory: 4, taxRate: 10 }),
      expect.objectContaining({ id: 'tea', title: 'Tea', price: 1.5, inventory: null, taxRate: null }),
    ]));
    expect(reopened.getComponent(AppShowLayout).props('app')?.id).toBe(appId);
  });

  it('hydrates the route-loaded editor from the detailed response after the metadata list finishes', async () => {
    const page = await mountEditor({ defaultView: 'Light', title: 'Sample terminal', showItems: true, enableTips: true, items: products }, true);
    expect(mocks.fetchStore).toHaveBeenCalledWith(store.id);
    expect(mocks.fetchApps).toHaveBeenCalledWith(store.id);
    expect(mocks.fetchApp).toHaveBeenCalledWith(store.id, appId);
    expect((page.get('#title').element as HTMLInputElement).value).toBe('Sample terminal');
    expect((page.get('#showItems').element as HTMLInputElement).checked).toBe(true);
    expect((page.get('#enableTips').element as HTMLInputElement).checked).toBe(true);
    expect(page.getComponent(PointOfSaleProductsEditor).props('products')).toHaveLength(2);
  });

  it('preserves explicit false settings and an authoritative empty catalog', async () => {
    const page = await mountEditor({ defaultView: 'Light', showItems: false, enableTips: false, items: [], template: products });
    expect((page.get('#showItems').element as HTMLInputElement).checked).toBe(false);
    expect((page.get('#enableTips').element as HTMLInputElement).checked).toBe(false);
    expect(page.findComponent(PointOfSaleProductsEditor).exists()).toBe(false);
    await page.get('#showItems').setValue(true);
    expect(page.getComponent(PointOfSaleProductsEditor).props('products')).toEqual([]);
  });


  it('loads the detailed PoS configuration through the actual SPA parent instead of editing list metadata', async () => {
    const metadata = { id: appId, name: 'Sample PoS', app_type: 'PointOfSale', config: { id: 'synthetic-pos', name: 'Sample PoS' } } as AppRef;
    const detail = { ...metadata, config: { ...standardConfig, defaultView: 'Light', title: 'Sample terminal', showItems: true, enableTips: true, items: products } } as AppRef;
    mocks.fetchStore.mockImplementation(async () => {
      mocks.storesState.currentStore = store;
      return store;
    });
    mocks.fetchApps.mockImplementation(async () => {
      mocks.appsState.apps = [metadata];
      return [metadata];
    });
    mocks.fetchApp.mockImplementation(async () => {
      return detail;
    });
    wrapper = shallowMount(AppsShow, {
      global: { provide: { inertia: false }, stubs: { PointOfSaleShow: false, AppShowLayout: false, AppScrollPane: false, PointOfSaleProductsEditor: false } },
    });
    await flushPromises();
    expect(mocks.fetchApp).toHaveBeenCalledWith(store.id, appId);
    expect((wrapper.get('#title').element as HTMLInputElement).value).toBe('Sample terminal');
    expect((wrapper.get('#showItems').element as HTMLInputElement).checked).toBe(true);
    expect((wrapper.get('#enableTips').element as HTMLInputElement).checked).toBe(true);
    expect(wrapper.getComponent(PointOfSaleProductsEditor).props('products')).toHaveLength(2);
  });


  it('persists items and tips created in the actual SPA parent after saving and reopening', async () => {
    const page = await mountSpaEditor({ defaultView: 'Light', showItems: false, enableTips: false, items: [] });
    await page.get('#showItems').setValue(true);
    await page.get('#enableTips').setValue(true);
    await page.get('#title').setValue('Sample terminal');
    const drawer = page.findComponent({ name: 'ProductEditDrawer' });
    drawer.vm.$emit('save', products[0]);
    drawer.vm.$emit('save', products[1]);
    await flushPromises();
    let persisted: Record<string, unknown> = {};
    mocks.updateApp.mockImplementation(async (_store: string, _app: string, payload: { config: Record<string, unknown> }) => {
      persisted = structuredClone(payload.config);
      return { id: appId, name: 'Sample PoS', config: persisted };
    });
    vi.useFakeTimers();
    await page.get('#pos-settings-form').trigger('submit');
    await flushPromises();
    await vi.advanceTimersByTimeAsync(501);
    await flushPromises();
    expect(mocks.updateApp).toHaveBeenCalledWith(store.id, appId, expect.anything());
    page.unmount();
    wrapper = undefined;
    vi.useRealTimers();
    const reopened = await mountSpaEditor({ ...persisted, items: persisted.template });
    expect((reopened.get('#showItems').element as HTMLInputElement).checked).toBe(true);
    expect((reopened.get('#enableTips').element as HTMLInputElement).checked).toBe(true);
    expect((reopened.get('#title').element as HTMLInputElement).value).toBe('Sample terminal');
    expect(reopened.getComponent(PointOfSaleProductsEditor).props('products')).toEqual(expect.arrayContaining([
      expect.objectContaining({ id: 'coffee', title: 'Coffee', price: 2.5, inventory: 4, taxRate: 10 }),
      expect.objectContaining({ id: 'tea', title: 'Tea', price: 1.5, inventory: null, taxRate: null }),
    ]));
    expect(mocks.appsState.apps[0]?.config).toEqual({ id: 'synthetic-pos', name: 'Sample PoS' });
  });

  it('discards an older detail response after navigating to another app', async () => {
    let resolveFirst!: (value: AppRef) => void;
    const firstRequest = new Promise<AppRef>(resolve => { resolveFirst = resolve; });
    const page = await mountSpaEditor({ defaultView: 'Light', showItems: true, enableTips: true, items: products });
    page.unmount();
    mocks.fetchApp.mockReset();
    mocks.fetchApp.mockReturnValueOnce(firstRequest);
    const nextId = '00000000-0000-4000-8000-000000000003';
    mocks.fetchApp.mockResolvedValueOnce({ id: nextId, name: 'Another PoS', app_type: 'PointOfSale', config: { ...standardConfig, defaultView: 'Light', title: 'Another terminal', showItems: false, enableTips: false, items: [] } });
    wrapper = shallowMount(AppsShow, {
      global: { provide: { inertia: false }, stubs: { PointOfSaleShow: false, AppShowLayout: false, AppScrollPane: false, PointOfSaleProductsEditor: false } },
    });
    await flushPromises();
    expect(wrapper.findComponent(PointOfSaleShow).exists()).toBe(false);
    mocks.route.params.appId = nextId;
    await flushPromises();
    expect((wrapper.get('#title').element as HTMLInputElement).value).toBe('Another terminal');
    resolveFirst({ id: appId, name: 'Sample PoS', app_type: 'PointOfSale', config: { ...standardConfig, defaultView: 'Light', title: 'Stale terminal', showItems: true, enableTips: true, items: products } });
    await flushPromises();
    expect(wrapper.getComponent(PointOfSaleShow).props('app')?.id).toBe(nextId);
    expect((wrapper.get('#title').element as HTMLInputElement).value).toBe('Another terminal');
  });

  it('does not open an editor when the detailed read fails', async () => {
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => undefined);
    const page = await mountSpaEditor({ defaultView: 'Light', showItems: true, enableTips: true, items: products });
    page.unmount();
    mocks.fetchApp.mockRejectedValueOnce(new Error('Synthetic unavailable detail'));
    wrapper = shallowMount(AppsShow, {
      global: { provide: { inertia: false }, stubs: { PointOfSaleShow: false, AppShowLayout: false, AppScrollPane: false, PointOfSaleProductsEditor: false } },
    });
    await flushPromises();
    expect(wrapper.findComponent(PointOfSaleShow).exists()).toBe(false);
    expect(wrapper.find('#pos-settings-form').exists()).toBe(false);
    consoleError.mockRestore();
  });


  it.each([
    ['metadata only', { defaultView: undefined, showItems: undefined, enableTips: undefined, items: undefined }],
    ['missing boolean', { showDiscount: undefined }],
    ['null boolean', { enableTips: null }],
    ['unreadable catalog', { showItems: true, items: '{invalid' }],
    ['null catalog', { showItems: true, items: null }],
  ])('prevents saving guessed defaults after a %s read', async (_label, config) => {
    const page = await mountEditor(config);
    expect(page.get('[role="alert"]').text()).toBe('stores.settings_load_failed');
    expect(page.find('#pos-settings-form').exists()).toBe(false);
    expect(mocks.updateApp).not.toHaveBeenCalled();
  });

  it('blocks stale cached products when the server reports an incomplete catalog read', async () => {
    const page = await mountEditor({ showItems: true, items: products });
    expect(page.getComponent(PointOfSaleProductsEditor).props('products')).toHaveLength(2);
    const existing = page.getComponent(AppShowLayout).props('app') as AppRef;
    await page.setProps({ app: { ...existing, config_read_errors: ['products'] } });
    expect(page.get('[role="alert"]').text()).toBe('stores.settings_load_failed');
    expect(page.find('#pos-settings-form').exists()).toBe(false);
    expect(mocks.updateApp).not.toHaveBeenCalled();
  });

  it('preserves unsupported item fields and omits absent tax settings on an unrelated edit', async () => {
    const item = { ...products[0], customTaxData: { rate: '10', label: 'Synthetic tax' }, customFlag: false };
    const page = await mountEditor({ showItems: true, items: [item] });
    await page.get('#appName').setValue('Renamed PoS');
    mocks.updateApp.mockResolvedValue({ id: appId });
    vi.useFakeTimers();
    await page.get('#pos-settings-form').trigger('submit');
    await flushPromises();
    await vi.advanceTimersByTimeAsync(501);
    const payload = mocks.updateApp.mock.calls[0]?.[2];
    expect(payload.config.template[0]).toMatchObject({ customTaxData: item.customTaxData, customFlag: false, taxRate: '10', inventory: 4 });
    expect(payload.config).not.toHaveProperty('taxIncludedInPrice');
    expect(payload.config).not.toHaveProperty('defaultTaxRate');
    expect(payload.config).not.toHaveProperty('tipTaxRate');
  });

  it.each(['Static', 'Cart', 'Print'])('keeps the %s catalog editable without Light item selection', async defaultView => {
    const page = await mountEditor({ defaultView, showItems: false, items: products });
    expect(page.getComponent(PointOfSaleProductsEditor).props('products')).toHaveLength(2);
  });


  it.each(['A custom thank-you', '', null])('preserves canonical tip text %j through an actual SPA save and full remount', async tipText => {
    const page = await mountSpaEditor({ showItems: true, enableTips: true, tipText, tipsMessage: 'Stale cached text', items: products });
    let persisted: Record<string, unknown> = {};
    mocks.updateApp.mockImplementation(async (_store: string, _app: string, payload: { config: Record<string, unknown> }) => {
      persisted = structuredClone(payload.config);
      return { id: appId, config: persisted };
    });
    vi.useFakeTimers();
    await page.get('#appName').setValue('Renamed PoS');
    await page.get('#pos-settings-form').trigger('submit');
    await flushPromises();
    await vi.advanceTimersByTimeAsync(501);
    expect(persisted.tipsMessage).toBe(tipText);
    expect(page.get('#tipsMessage').attributes('required')).toBeUndefined();
    page.unmount();
    wrapper = undefined;
    vi.useRealTimers();
    const reopened = await mountSpaEditor({ ...persisted, tipText: persisted.tipsMessage, tipsMessage: 'Stale cached text', items: persisted.template });
    expect((reopened.get('#tipsMessage').element as HTMLInputElement).value).toBe(tipText ?? '');
  });

  it('keeps known zero, false and null tax settings in the save payload', async () => {
    const page = await mountEditor({ showItems: true, defaultTaxRate: 0, taxIncludedInPrice: false, tipTaxRate: null, items: products });
    mocks.updateApp.mockResolvedValue({ id: appId });
    vi.useFakeTimers();
    await page.get('#pos-settings-form').trigger('submit');
    await flushPromises();
    await vi.advanceTimersByTimeAsync(501);
    expect(mocks.updateApp.mock.calls[0]?.[2].config).toMatchObject({ defaultTaxRate: '0', taxIncludedInPrice: false, tipTaxRate: null });
  });

  it('uses the returned Crowdfund detail while preserving sidebar metadata', async () => {
    const page = await mountSpaEditor({});
    page.unmount();
    const detail = { id: appId, name: 'Sample fundraiser', app_type: 'Crowdfund', config: { title: 'Detailed fundraiser', targetAmount: 100 } } as AppRef;
    mocks.fetchApp.mockResolvedValue(detail);
    wrapper = shallowMount(AppsShow, {
      global: { provide: { inertia: false } },
    });
    await flushPromises();
    expect(wrapper.getComponent({ name: 'CrowdfundShow' }).props('app')).toEqual(detail);
    expect(mocks.appsState.apps[0]?.config).toEqual({ id: 'synthetic-pos', name: 'Sample PoS' });
  });


  it('hydrates replacement detail props even when the shared layout still exposes its initial app', async () => {
    const page = await mountEditor({ showItems: false, enableTips: false, items: [] });
    const initial = page.getComponent(AppShowLayout).props('app') as AppRef;
    await page.setProps({ app: { ...initial, config: { ...standardConfig, title: 'Refreshed terminal', showItems: true, enableTips: true, items: products } } });
    expect((page.get('#title').element as HTMLInputElement).value).toBe('Refreshed terminal');
    expect((page.get('#showItems').element as HTMLInputElement).checked).toBe(true);
    expect((page.get('#enableTips').element as HTMLInputElement).checked).toBe(true);
    expect(page.getComponent(PointOfSaleProductsEditor).props('products')).toHaveLength(2);
  });

  it('blocks subsequent saves after an update response reports a failed configuration read', async () => {
    const page = await mountEditor({ showItems: true, items: products });
    mocks.updateApp.mockResolvedValue({ id: appId, config: { ...standardConfig, items: products }, config_read_errors: ['settings'] });
    await page.get('#pos-settings-form').trigger('submit');
    await flushPromises();
    expect(page.get('[role="alert"]').text()).toBe('stores.settings_load_failed');
    expect(page.find('#pos-settings-form').exists()).toBe(false);
    expect(mocks.success).not.toHaveBeenCalled();
    expect(mocks.updateApp).toHaveBeenCalledOnce();
    const initial = page.getComponent(AppShowLayout).props('app') as AppRef;
    await page.setProps({ app: { ...initial } });
    expect(page.find('#pos-settings-form').exists()).toBe(true);
  });

  it('saves an explicit empty catalog after removing products and hiding Light item selection', async () => {
    const page = await mountEditor({ showItems: true, items: products });
    page.getComponent(PointOfSaleProductsEditor).vm.$emit('update:products', []);
    await page.get('#showItems').setValue(false);
    mocks.updateApp.mockResolvedValue({ id: appId });
    vi.useFakeTimers();
    await page.get('#pos-settings-form').trigger('submit');
    await flushPromises();
    await vi.advanceTimersByTimeAsync(501);
    expect(mocks.updateApp.mock.calls[0]?.[2].config).toMatchObject({ showItems: false, template: [] });
  });

});
