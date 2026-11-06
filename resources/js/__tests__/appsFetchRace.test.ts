import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import { flushPromises, shallowMount, type VueWrapper } from '@vue/test-utils';
import { useAppsStore, type App } from '../store/apps';
import AppsShow from '../pages/stores/AppsShow.vue';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  fetchStore: vi.fn(),
  route: { params: { id: 'store-a', appId: 'app-a' } },
  stores: { currentStore: null as { id: string; name: string } | null, fetchStore: vi.fn() },
}));
vi.mock('../services/api', () => ({ default: { get: mocks.get } }));
vi.mock('vue-router', () => ({ useRoute: () => mocks.route, useRouter: () => ({ push: vi.fn(), replace: vi.fn() }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('../store/stores', () => ({ useStoresStore: () => mocks.stores }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: { is_guest: false } }) }));
vi.mock('../composables/useGuestUpgradeModal', () => ({ useGuestUpgradeModal: () => ({ openGuestUpgradeModal: vi.fn() }) }));
vi.mock('../components/layout/AppScrollPane.vue', () => ({ default: { template: '<div><slot /></div>' } }));
vi.mock('../components/stores/StoreSidebar.vue', () => ({ default: { name: 'StoreSidebar', props: ['store', 'apps'], template: '<div />' } }));
vi.mock('../pages/stores/PointOfSaleShow.vue', () => ({ default: { name: 'PointOfSaleShow', props: ['store', 'app'], template: '<div />' } }));
vi.mock('../pages/stores/PayButtonShow.vue', () => ({ default: { template: '<div />' } }));
vi.mock('../pages/stores/TicketsShow.vue', () => ({ default: { template: '<div />' } }));
vi.mock('../pages/stores/CrowdfundShow.vue', () => ({ default: { template: '<div />' } }));

type ListResponse = { data: { data: App[] } };
let store: ReturnType<typeof useAppsStore>;
let wrapper: VueWrapper | undefined;

/** Hold a synthetic transport response until a newer route or request supersedes it. */
function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (error: Error) => void;
  const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
  return { promise, resolve, reject };
}

/** Return only synthetic local application identifiers for shared-list assertions. */
function apps(id: string): App[] {
  return [{ id, name: id, app_type: 'PointOfSale' }];
}

beforeEach(() => {
  vi.resetAllMocks();
  setActivePinia(createPinia());
  store = useAppsStore();
  mocks.route = reactive({ params: { id: 'store-a', appId: 'app-a' } });
  mocks.stores = reactive({ currentStore: null, fetchStore: mocks.fetchStore });
  mocks.fetchStore.mockImplementation(async (id: string) => {
    mocks.stores.currentStore = { id, name: id };
    return mocks.stores.currentStore;
  });
});

afterEach(() => {
  wrapper?.unmount();
  wrapper = undefined;
  store.$dispose();
});

describe('guarded shared app list requests', () => {
  it('retains the existing success contract when no caller guard is provided', async () => {
    mocks.get.mockResolvedValue({ data: { data: apps('app-a') } });
    expect(await store.fetchApps('store-a')).toEqual(apps('app-a'));
    expect(mocks.get).toHaveBeenCalledExactlyOnceWith('/stores/store-a/apps');
    expect(store.apps).toEqual(apps('app-a'));
    expect(store.loading).toBe(false);
  });

  it('retains current-request failure clearing and error propagation', async () => {
    const error = new Error('Synthetic list failure');
    store.apps = apps('previous');
    mocks.get.mockRejectedValue(error);
    await expect(store.fetchApps('store-a', () => true)).rejects.toBe(error);
    expect(store.apps).toEqual([]);
    expect(store.loading).toBe(false);
  });

  it('does not begin an already obsolete request or change shared loading state', async () => {
    store.apps = apps('app-b');
    store.loading = true;
    mocks.get.mockResolvedValue({ data: { data: apps('app-a') } });
    expect(await store.fetchApps('store-a', () => false)).toEqual(apps('app-b'));
    expect(mocks.get).not.toHaveBeenCalled();
    expect(store.apps).toEqual(apps('app-b'));
    expect(store.loading).toBe(true);
  });

  it.each(['success', 'failure'])('ignores stale %s after the current list has loaded', async outcome => {
    let generation = 1;
    const oldResponse = deferred<ListResponse>();
    const currentResponse = deferred<ListResponse>();
    mocks.get.mockReturnValueOnce(oldResponse.promise).mockReturnValueOnce(currentResponse.promise);
    const oldRequest = store.fetchApps('store-a', () => generation === 1).catch(error => error);
    generation = 2;
    const currentRequest = store.fetchApps('store-b', () => generation === 2);
    currentResponse.resolve({ data: { data: apps('app-b') } });
    await currentRequest;

    const error = new Error('Synthetic stale list failure');
    if (outcome === 'success') oldResponse.resolve({ data: { data: apps('app-a') } });
    else oldResponse.reject(error);
    const oldResult = await oldRequest;
    if (outcome === 'failure') expect(oldResult).toBe(error);
    expect(store.apps).toEqual(apps('app-b'));
    expect(store.loading).toBe(false);
  });

  it.each(['success', 'failure'])('does not let stale %s clear a newer pending loading state', async outcome => {
    let generation = 1;
    const oldResponse = deferred<ListResponse>();
    const currentResponse = deferred<ListResponse>();
    store.apps = apps('retained');
    mocks.get.mockReturnValueOnce(oldResponse.promise).mockReturnValueOnce(currentResponse.promise);
    const oldRequest = store.fetchApps('store-a', () => generation === 1).catch(error => error);
    generation = 2;
    const currentRequest = store.fetchApps('store-b', () => generation === 2);

    if (outcome === 'success') oldResponse.resolve({ data: { data: apps('app-a') } });
    else oldResponse.reject(new Error('Synthetic stale list failure'));
    await oldRequest;
    expect(store.apps).toEqual(apps('retained'));
    expect(store.loading).toBe(true);
    currentResponse.resolve({ data: { data: apps('app-b') } });
    await currentRequest;
    expect(store.apps).toEqual(apps('app-b'));
    expect(store.loading).toBe(false);
  });
});

describe('AppsShow request guard at the real store boundary', () => {
  it.each(['success', 'failure'])('keeps the newer sidebar when an old route list ends in %s', async outcome => {
    const oldResponse = deferred<ListResponse>();
    const currentResponse = deferred<ListResponse>();
    mocks.get.mockImplementation((url: string) => {
      if (url === '/stores/store-a/apps') return oldResponse.promise;
      if (url === '/stores/store-b/apps') return currentResponse.promise;
      if (url === '/stores/store-b/apps/app-b') return Promise.resolve({ data: { data: apps('app-b')[0] } });
      throw new Error('Unexpected synthetic request');
    });
    wrapper = shallowMount(AppsShow);
    await flushPromises();
    mocks.route.params = { id: 'store-b', appId: 'app-b' };
    await flushPromises();
    currentResponse.resolve({ data: { data: apps('app-b') } });
    await flushPromises();
    expect(wrapper.getComponent({ name: 'PointOfSaleShow' }).props('app').id).toBe('app-b');

    if (outcome === 'success') oldResponse.resolve({ data: { data: apps('app-a') } });
    else oldResponse.reject(new Error('Synthetic stale route failure'));
    await flushPromises();
    expect(store.apps).toEqual(apps('app-b'));
    expect(wrapper.getComponent({ name: 'StoreSidebar' }).props('apps')).toEqual(apps('app-b'));
    expect(store.currentApp?.id).toBe('app-b');
    expect(store.loading).toBe(false);
  });

  it('does not publish an old list after its AppsShow owner unmounts', async () => {
    const response = deferred<ListResponse>();
    store.apps = apps('retained');
    mocks.get.mockReturnValue(response.promise);
    wrapper = shallowMount(AppsShow);
    await flushPromises();
    wrapper.unmount();
    wrapper = undefined;
    response.resolve({ data: { data: apps('app-a') } });
    await flushPromises();
    expect(store.apps).toEqual(apps('retained'));
    expect(store.currentApp).toBeNull();
    expect(mocks.get).toHaveBeenCalledExactlyOnceWith('/stores/store-a/apps');
  });
});
