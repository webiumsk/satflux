import { mount, flushPromises } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { beforeEach, expect, it, vi } from 'vitest';
import Login from '../pages/auth/Login.vue';
import PasswordReset from '../pages/auth/PasswordReset.vue';

const mocks = vi.hoisted(() => ({ login: vi.fn(), restore: vi.fn(), probe: vi.fn() }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ login: mocks.login, restoreGuestFromMnemonic: mocks.restore }) }));
vi.mock('../store/stores', () => ({ useStoresStore: () => ({ fetchStores: async () => [] }) }));
vi.mock('../store/flash', () => ({ useFlashStore: () => ({ error: vi.fn() }) }));
vi.mock('../composables/usePasskeyAccountLogin', () => ({ usePasskeyAccountLogin: () => ({ supported: false, loading: false, ownerSwitchImpact: null, probeSupport: mocks.probe, run: vi.fn() }) }));
vi.mock('../components/auth/GuestRestoreModal.vue', () => ({ default: { template: '<div />' } }));
vi.mock('../components/auth/AuthSeedGuestPanel.vue', () => ({ default: { template: '<button>Recovery phrase</button>' } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
beforeEach(() => vi.clearAllMocks());
const routerFor = async (path: string) => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/', component: { template: '<div />' } }, { path: '/register', component: { template: '<div />' } }, { path: '/login', component: Login }, { path: '/password/reset', component: PasswordReset }] });
    await router.push(path); await router.isReady(); return router;
};
it('keeps password login behind an explicit migration action, never offering reset', async () => {
    const router = await routerFor('/login?tab=email&legacy=1');
    const wrapper = mount(Login, { attachTo: document.body, global: { plugins: [router] } });
    expect(wrapper.get('form').isVisible()).toBe(false);
    expect(wrapper.text()).toContain('Recovery phrase');
    expect(wrapper.find('a[href="/password/reset"]').exists()).toBe(false);
    const migration = wrapper.findAll('button').find(b => b.text() === 'auth.migrate_older_account')!;
    await migration.trigger('click');
    expect(wrapper.get('form').isVisible()).toBe(true);
    expect(wrapper.text()).toContain('auth.migration_login_notice');
    expect(mocks.login).not.toHaveBeenCalled();
    wrapper.unmount();
});
it('drops reset token and email parameters when redirecting an obsolete reset page', async () => {
    const router = await routerFor('/password/reset?token=synthetic&email=synthetic@example.test');
    const wrapper = mount(PasswordReset, { global: { plugins: [router] } });
    await flushPromises();
    expect(router.currentRoute.value.path).toBe('/login');
    expect(router.currentRoute.value.query).toEqual({});
    expect(wrapper.find('form').exists()).toBe(false);
    wrapper.unmount();
});
