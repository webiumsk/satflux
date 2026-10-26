import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { computed, effectScope, reactive, ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { User } from '../store/auth';

const mocks = vi.hoisted(() => ({
    auth: { user: null as User | null },
    storeId: null as ReturnType<typeof ref<string>> | null,
    get: vi.fn(), create: vi.fn(), push: vi.fn(), error: vi.fn(),
}));
vi.mock('../store/auth', () => ({ useAuthStore: () => mocks.auth }));
vi.mock('../services/api', () => ({ default: { get: mocks.get } }));
vi.mock('../store/raffles', () => ({ useRafflesStore: () => ({ createRaffle: mocks.create }) }));
vi.mock('../store/apps', () => ({ useAppsStore: () => ({ apps: [], fetchApps: async () => [] }) }));
vi.mock('../store/flash', () => ({ useFlashStore: () => ({ success: vi.fn(), error: mocks.error }) }));
vi.mock('vue-router', () => ({ useRouter: () => ({ push: mocks.push }) }));
vi.mock('../composables/useStorePageShell', () => ({ useStorePageShell: () => ({
    storeId: mocks.storeId,
    store: computed(() => ({ id: mocks.storeId!.value, name: 'Synthetic store', default_currency: 'EUR' })),
    error: ref(''), loadStore: vi.fn(), goSettings: vi.fn(), goSection: vi.fn(),
}) }));
vi.mock('../components/stores/RafflesPageLayout.vue', () => ({ default: { template: '<div><slot /></div>' } }));
vi.mock('../components/stores/AppShowLayout.vue', () => ({ default: { template: '<main><slot name="toolbar" /><slot /></main>' } }));
vi.mock('../components/stores/UpgradeModal.vue', () => ({ default: {
    props: ['show', 'message'], template: '<aside v-if="show" data-upgrade>{{ message }}</aside>',
} }));
vi.mock('../components/admin/RichTextEditor.vue', () => ({ default: { template: '<div />' } }));
vi.mock('../components/stores/RaffleTicketPricingFields.vue', () => ({ default: { template: '<div />' } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string, args?: Record<string, unknown>) => args?.max == null ? key : `${key}:${args.max}` }) }));

import RafflesIndex from '../pages/stores/RafflesIndex.vue';
import RafflesCreate from '../pages/stores/RafflesCreate.vue';
import { useAccountLimits } from '../composables/useAccountLimits';

function account(id = 1, role = 'admin'): User {
    return { id, email: 'synthetic@example.test', role, is_guest: false,
        purchase_availability: { pro_subscriptions: false, pro_credits: false, company_slots: false, isdoc_packs: false } };
}
function quota(max: number | null = null, allowed = true) {
    return { stores: { current: 1, max: 3, unlimited: false },
        api_keys: { current: 0, max: 3, unlimited: false }, ln_addresses: { current: 0, max: 3, unlimited: false },
        raffles: { current: 0, max, unlimited: max === null, allowed } };
}
const raffle = { id: 'raffle-a', name: 'Synthetic raffle', status: 'Draft', ticketsSold: 0,
    maxTickets: 100, createdAt: '2026-01-01', ticketCurrency: 'EUR', ticketPrice: 5 };
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (error: unknown) => void;
    const promise = new Promise<T>((yes, no) => { resolve = yes; reject = no; });
    return { promise, resolve, reject };
}
function createButton(wrapper: ReturnType<typeof mount>) {
    return wrapper.findAll('button').find(button => button.text() === 'raffles.create')!;
}
function apiWith(data = quota(), list: unknown = []) {
    mocks.get.mockImplementation((path: string) => Promise.resolve({ data: path === '/user/limits' ? data : { data: list } }));
}
enableAutoUnmount(afterEach);
beforeEach(() => {
    vi.clearAllMocks();
    mocks.auth = reactive({ user: account() });
    mocks.storeId = ref('store-a');
    const scope = effectScope();
    scope.run(() => useAccountLimits().clearCache());
    scope.stop();
    apiWith();
    mocks.create.mockResolvedValue({ id: 'new-raffle' });
});

describe('raffle creation checks through real scoped account limits', () => {
    it('loads the list page own limits before allowing an unlimited admin to open create', async () => {
        const pending = deferred<{ data: ReturnType<typeof quota> }>();
        mocks.get.mockImplementation((path: string) => path === '/user/limits' ? pending.promise : Promise.resolve({ data: { data: [raffle] } }));
        const wrapper = mount(RafflesIndex);
        expect(createButton(wrapper).attributes('disabled')).toBeDefined();
        await createButton(wrapper).trigger('click');
        expect(mocks.push).not.toHaveBeenCalled();
        expect(wrapper.find('[data-upgrade]').exists()).toBe(false);
        pending.resolve({ data: quota() });
        await flushPromises();
        expect(mocks.get).toHaveBeenCalledWith('/user/limits', { params: { store_id: 'store-a' } });
        expect(mocks.get.mock.calls.filter(([path]) => path === '/stores/store-a/raffles')).toHaveLength(1);
        await createButton(wrapper).trigger('click');
        expect(mocks.push).toHaveBeenCalledWith({ name: 'stores-raffles-create', params: { id: 'store-a' } });
    });

    it('keeps direct create pending without a fake upgrade and permits a free account below quota', async () => {
        mocks.auth.user = account(1, 'free');
        const pending = deferred<{ data: ReturnType<typeof quota> }>();
        mocks.get.mockImplementation((path: string) => path === '/user/limits' ? pending.promise : Promise.resolve({ data: { data: [] } }));
        const wrapper = mount(RafflesCreate);
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
        expect(wrapper.find('[data-upgrade]').exists()).toBe(false);
        pending.resolve({ data: quota(1) });
        await flushPromises();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
        await wrapper.get('input[type="text"]').setValue('New raffle');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(mocks.create).toHaveBeenCalledWith('store-a', expect.objectContaining({ name: 'New raffle' }));
    });

    it('uses the successful list count instead of the quota endpoint fallback zero and does not pitch paused purchases', async () => {
        apiWith(quota(1), [raffle]);
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.limit_reached:1');
        expect(wrapper.text()).not.toContain('raffles.limit_modal_message');
        expect(wrapper.text()).not.toContain('free_pro.purchases_paused');
        expect(wrapper.find('[data-upgrade]').exists()).toBe(false);
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
    });

    it('only opens a purchasable upgrade for a confirmed finite cap', async () => {
        mocks.auth.user!.purchase_availability!.pro_subscriptions = true;
        apiWith(quota(1), [raffle]);
        const wrapper = mount(RafflesIndex);
        await flushPromises();
        await createButton(wrapper).trigger('click');
        expect(wrapper.get('[data-upgrade]').text()).toBe('raffles.limit_reached:1');
        expect(mocks.push).not.toHaveBeenCalled();
    });

    it('shows the confirmed guest denial without calling a guest-restricted list or offering Pro', async () => {
        mocks.auth.user = { ...account(2, 'free'), is_guest: true };
        apiWith(quota(0, false));
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.creation_unavailable');
        expect(mocks.get.mock.calls.filter(([path]) => path !== '/user/limits')).toHaveLength(0);
        expect(wrapper.find('[data-upgrade]').exists()).toBe(false);
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
    });

    it.each(['quota', 'list'])('keeps failed %s verification blocked with an actionable retry', async failure => {
        mocks.get.mockImplementation((path: string) => {
            if ((failure === 'quota') === (path === '/user/limits')) return Promise.reject(new Error('offline'));
            return Promise.resolve({ data: path === '/user/limits' ? quota(1) : { data: [] } });
        });
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.creation_check_failed');
        expect(wrapper.find('[data-upgrade]').exists()).toBe(false);
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
        apiWith(quota(1));
        await wrapper.findAll('button').find(button => button.text() === 'common.retry')!.trigger('click');
        await flushPromises();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
    });

    it.each([null, {}, undefined])('never treats a malformed successful list (%s) as empty', async malformed => {
        mocks.get.mockImplementation((path: string) => Promise.resolve({ data: path === '/user/limits' ? quota(1) : { data: malformed } }));
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.creation_check_failed');
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
    });

    it('synchronously blocks a store switch and ignores the old list result', async () => {
        const oldList = deferred<{ data: { data: typeof raffle[] } }>();
        mocks.get.mockImplementation((path: string, options?: { params?: { store_id: string } }) => {
            if (path === '/user/limits') return Promise.resolve({ data: quota(options?.params?.store_id === 'store-a' ? null : 0, options?.params?.store_id === 'store-a') });
            return oldList.promise;
        });
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        mocks.storeId!.value = 'store-b';
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
        await flushPromises();
        oldList.resolve({ data: { data: [] } });
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.creation_unavailable');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();
    });

    it('invalidates account access immediately and can return to a cached prior account safely', async () => {
        apiWith(quota());
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
        apiWith(quota(0, false));
        mocks.auth.user = account(2, 'free');
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.creation_unavailable');
        mocks.auth.user = account();
        apiWith(quota());
        await flushPromises();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
    });

    it('invalidates a changed policy and discards stale quota failures', async () => {
        const oldQuota = deferred<{ data: ReturnType<typeof quota> }>();
        mocks.get.mockReturnValueOnce(oldQuota.promise);
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        mocks.auth.user!.effective_access = { policy_version: 'new' } as User['effective_access'];
        apiWith(quota(1));
        await flushPromises();
        oldQuota.reject(new Error('old account policy failed'));
        await flushPromises();
        expect(wrapper.text()).not.toContain('raffles.creation_check_failed');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
    });

    it('never reuses a full-account grant after a same-id guest transition', async () => {
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
        const requestCount = mocks.get.mock.calls.length;
        mocks.auth.user!.is_guest = true;
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.creation_unavailable');
        expect(mocks.get).toHaveBeenCalledTimes(requestCount);
        mocks.auth.user!.is_guest = false;
        await flushPromises();
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
    });

    it('discards a pending non-guest grant when the same account becomes a guest', async () => {
        const pending = deferred<{ data: ReturnType<typeof quota> }>();
        mocks.get.mockReturnValueOnce(pending.promise);
        const wrapper = mount(RafflesCreate);
        await flushPromises();
        mocks.auth.user!.is_guest = true;
        pending.resolve({ data: quota() });
        await flushPromises();
        expect(wrapper.text()).toContain('raffles.creation_unavailable');
        await wrapper.get('form').trigger('submit');
        expect(mocks.create).not.toHaveBeenCalled();
        expect(mocks.get.mock.calls.filter(([path]) => path !== '/user/limits')).toHaveLength(0);
    });
});
