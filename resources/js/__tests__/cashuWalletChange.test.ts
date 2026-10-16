import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import CashuConnectionSection from '../components/stores/wallet-connection/CashuConnectionSection.vue';

const mocks = vi.hoisted(() => ({
    getSettings: vi.fn(), updateSettings: vi.fn(), confirmEdit: vi.fn(),
    request: vi.fn(), confirm: vi.fn(), resend: vi.fn(), upgrade: vi.fn(),
    user: { can_use_password_login: false, is_guest: false },
}));
vi.mock('../services/api', () => ({ walletApi: {
    cashu: { getSettings: mocks.getSettings, updateSettings: mocks.updateSettings, confirmEdit: mocks.confirmEdit },
    connection: { change: { request: mocks.request, confirm: mocks.confirm, resend: mocks.resend } },
} }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: mocks.user }) }));
vi.mock('../composables/useGuestUpgradeModal', () => ({ useGuestUpgradeModal: () => ({ openGuestUpgradeModal: mocks.upgrade }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

const challenge = { purpose: 'wallet_change', email: 'owner@example.test', expires_at: '2099-01-01T00:00:00Z', resend_available_at: '2000-01-01T00:00:00Z', attempts_left: 5, sends_left: 3 };
const settings = { mint_url: 'https://mint.example.test', lightning_address: 'owner@example.test', enabled: true, unit: 'sat', change_confirmation: { required: true, guest_upgrade_required: false, pending: null, granted_until: null } };
const open = async (props = {}) => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ name: 'stores-show', path: '/stores/:id', component: { template: '<div />' } }] });
    await router.push('/stores/store-a'); await router.isReady();
    return mount(CashuConnectionSection, { props: { storeId: 'store-a', walletType: 'cashu', switchIntent: false, ...props }, global: { plugins: [router] } });
};
const button = (wrapper: Awaited<ReturnType<typeof open>>, key: string) => wrapper.findAll('button').find(b => b.text() === key)!;
const enterCode = async (wrapper: Awaited<ReturnType<typeof open>>, code = '123456') => {
    const inputs = wrapper.findAll('input[autocomplete=one-time-code]');
    for (let i = 0; i < code.length; i++) await inputs[i]!.setValue(code[i]);
    await flushPromises();
};
const edit = async (wrapper: Awaited<ReturnType<typeof open>>) => {
    await flushPromises(); await button(wrapper, 'stores.change_connection').trigger('click'); await flushPromises();
};
const acceptBeta = async (wrapper: Awaited<ReturnType<typeof open>>) => {
    await wrapper.findAll('input[type=checkbox]')[0]!.setValue(true); await flushPromises();
};

describe('Cashu wallet change confirmation', () => {
    beforeEach(() => {
        vi.resetAllMocks(); mocks.user.can_use_password_login = false; mocks.user.is_guest = false;
        mocks.getSettings.mockResolvedValue(settings);
        mocks.request.mockResolvedValue({ required: true, challenge });
        mocks.confirm.mockResolvedValue({ type: 'cashu', secret: null, granted_until: '2099-01-01T00:00:00Z' });
        mocks.resend.mockResolvedValue({ ...challenge, sends_left: 2 });
        mocks.updateSettings.mockResolvedValue(settings);
    });

    it('requires six digits before editing a pure Cashu wallet without a Lightning connection', async () => {
        const wrapper = await open(); await edit(wrapper);
        expect(mocks.request).toHaveBeenCalledWith('store-a', {});
        expect(wrapper.find('#cashu-mint-url').exists()).toBe(false);
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(6);
        expect(mocks.confirmEdit).not.toHaveBeenCalled();
        await enterCode(wrapper, '12345'); expect(mocks.confirm).not.toHaveBeenCalled();
        await wrapper.findAll('input[autocomplete=one-time-code]')[5]!.setValue('6'); await flushPromises();
        expect(mocks.confirm).toHaveBeenCalledWith('store-a', '123456');
        expect(wrapper.find('#cashu-mint-url').exists()).toBe(false);
        await acceptBeta(wrapper); expect(wrapper.find('#cashu-mint-url').exists()).toBe(true);
        expect(mocks.updateSettings).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('retains password authentication and then asks for the email code', async () => {
        mocks.user.can_use_password_login = true;
        const wrapper = await open(); await edit(wrapper);
        expect(mocks.request).not.toHaveBeenCalled();
        await wrapper.get('#cashu-wc-password').setValue('synthetic-password');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.request).toHaveBeenCalledWith('store-a', { password: 'synthetic-password' });
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(6); wrapper.unmount();
    });

    it('resumes a pending challenge and resends without requesting a new change', async () => {
        mocks.getSettings.mockResolvedValue({ ...settings, change_confirmation: { ...settings.change_confirmation, pending: challenge } });
        const wrapper = await open(); await edit(wrapper);
        expect(mocks.request).not.toHaveBeenCalled();
        await button(wrapper, 'account.email_code_resend').trigger('click'); await flushPromises();
        expect(mocks.resend).toHaveBeenCalledWith('store-a');
        await enterCode(wrapper); expect(mocks.confirm).toHaveBeenCalledTimes(1); wrapper.unmount();
    });

    it('keeps the code step when confirmation fails and permits a corrected code', async () => {
        mocks.confirm.mockRejectedValueOnce({ response: { status: 422, data: { message: 'Wrong code' } } });
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper);
        expect(wrapper.get('[role=alert]').text()).toBe('Wrong code');
        expect(wrapper.find('#cashu-mint-url').exists()).toBe(false);
        await wrapper.findAll('input[autocomplete=one-time-code]')[0]!.setValue('2'); await flushPromises();
        expect(mocks.confirm).toHaveBeenCalledTimes(2); wrapper.unmount();
    });

    it('sends guests to account upgrade without requesting a code', async () => {
        mocks.user.is_guest = true;
        const wrapper = await open(); await edit(wrapper);
        expect(mocks.upgrade).toHaveBeenCalledWith('stores.wallet_change_feature_label');
        expect(mocks.request).not.toHaveBeenCalled(); expect(mocks.confirmEdit).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('preserves typed settings and consent when the grant expires, requiring an explicit save again', async () => {
        mocks.updateSettings.mockRejectedValueOnce({ response: { status: 409, data: { code: 'wallet_change_confirmation_required' } } });
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://new-mint.example.test');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.request).toHaveBeenCalledTimes(2);
        await enterCode(wrapper, '654321');
        expect((wrapper.get('#cashu-mint-url').element as HTMLInputElement).value).toBe('https://new-mint.example.test');
        expect(mocks.updateSettings).toHaveBeenCalledTimes(1);
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.updateSettings).toHaveBeenCalledTimes(2); wrapper.unmount();
    });

    it('translates the outstanding settlement guard and links to existing recovery without bypassing it', async () => {
        mocks.updateSettings.mockRejectedValue({ response: { status: 422, data: { errors: { cashu: ['Outstanding Cashu settlements or change must be recovered before changing this wallet.'] } } } });
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://replacement.example.test');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.get('[role=alert]').text()).toContain('stores.cashu_wallet_change_recovery_required');
        expect(wrapper.get('[role=alert] a').attributes('href')).toBe('/stores/store-a?section=cashu');
        expect(wrapper.find('#cashu-mint-url').exists()).toBe(true);
        expect(wrapper.emitted('submitted')).toBeUndefined(); expect(mocks.updateSettings).toHaveBeenCalledTimes(1); wrapper.unmount();
    });

    it('ignores a completed grant from a previously selected store', async () => {
        let resolve!: (value: unknown) => void;
        mocks.confirm.mockImplementation(() => new Promise(r => { resolve = r; }));
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper);
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        resolve({ type: 'cashu', secret: null }); await flushPromises();
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(0);
        expect(wrapper.find('#cashu-mint-url').exists()).toBe(false);
        expect(mocks.updateSettings).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('accepts an authoritative unchanged-settings response and returns to the connected summary', async () => {
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.updateSettings).toHaveBeenCalledWith('store-a', {
            mint_url: settings.mint_url, lightning_address: settings.lightning_address,
            enabled: true, unit: 'sat', trusted_mint_urls: null,
            max_melt_fee_reserve_sats: null, max_melt_fee_reserve_percent_of_minted: null,
        });
        expect(wrapper.find('#cashu-mint-url').exists()).toBe(false);
        expect(wrapper.emitted('submitted')).toHaveLength(1);
        expect(wrapper.text()).not.toContain('stores.cashu_wallet_change_recovery_required');
        expect(mocks.confirm).toHaveBeenCalledTimes(1); wrapper.unmount();
    });

    it.each([
        ['cashu_outstanding_payments', 'stores.cashu_wallet_change_recovery_required', true],
        ['cashu_safety_unavailable', 'stores.cashu_wallet_change_safety_unavailable', false],
    ])('explains %s without exposing upstream details or reporting a successful save', async (code, message, recovery) => {
        mocks.updateSettings.mockRejectedValue({ response: { status: 422, data: { code, errors: { cashu: ['Synthetic upstream detail'] } } } });
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://replacement.example.test');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.get('[role=alert]').text()).toContain(message);
        expect(wrapper.text()).not.toContain('Synthetic upstream detail');
        expect(wrapper.find('[role=alert] a').exists()).toBe(recovery);
        expect(wrapper.emitted('submitted')).toBeUndefined(); wrapper.unmount();
    });

    it('shows blocked-save record counts and keeps the edited settings', async () => {
        mocks.updateSettings.mockRejectedValue({ response: { status: 422, data: { code: 'cashu_outstanding_payments', wallet_safety: { status: 'blocked', unresolved_payments: 0, unresolved_change: 1 } } } });
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://replacement.example.test');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.get('[role=alert]').get('[data-testid=cashu-safety-change-count]').text()).toBe('1');
        expect(wrapper.get('[role=alert]').get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_blocked');
        expect((wrapper.get('#cashu-mint-url').element as HTMLInputElement).value).toBe('https://replacement.example.test');
        expect(mocks.updateSettings).toHaveBeenCalledTimes(1); expect(wrapper.emitted('submitted')).toBeUndefined(); wrapper.unmount();
    });

    it('keeps legacy refusal evidence blocked with unknown counts', async () => {
        mocks.updateSettings.mockRejectedValue({ response: { status: 422, data: { code: 'cashu_outstanding_payments' } } });
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://replacement.example.test');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.get('[role=alert]').get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_blocked');
        expect(wrapper.get('[role=alert]').get('[data-testid=cashu-safety-change-count]').text()).toBe('stores.cashu_wallet_safety_unknown'); wrapper.unmount();
    });

    it('ignores blocked-save diagnostics after switching stores and returning to the original store', async () => {
        let reject!: (reason: unknown) => void;
        mocks.updateSettings.mockImplementation(() => new Promise((_, rejectPromise) => { reject = rejectPromise; }));
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://replacement.example.test');
        await wrapper.get('form').trigger('submit');
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        await wrapper.setProps({ storeId: 'store-a' }); await flushPromises();
        await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        reject({ response: { status: 422, data: { code: 'cashu_outstanding_payments', wallet_safety: { status: 'blocked', unresolved_payments: 9, unresolved_change: 9 } } } });
        await flushPromises();
        expect(wrapper.find('[role=alert] [data-testid=cashu-wallet-safety]').exists()).toBe(false);
        expect(wrapper.emitted('submitted')).toBeUndefined(); wrapper.unmount();
    });
});


describe('Cashu connection safety preflight', () => {
    const blocked = { status: 'blocked', unresolved_payments: 0, unresolved_change: 1 };
    const safe = { status: 'safe', unresolved_payments: 0, unresolved_change: 0 };
    const preflight = (wrapper: Awaited<ReturnType<typeof open>>) => wrapper.get('[data-testid=cashu-safety-preflight]');
    beforeEach(() => {
        vi.resetAllMocks(); mocks.user.can_use_password_login = false; mocks.user.is_guest = false;
        mocks.getSettings.mockResolvedValue({ ...settings, wallet_safety: blocked });
        mocks.request.mockResolvedValue({ required: true, challenge });
        mocks.confirm.mockResolvedValue({ type: 'cashu', secret: null });
    });

    it('shows a loading state then the block and recovery route before any edit or mutation', async () => {
        let resolve!: (value: unknown) => void;
        mocks.getSettings.mockImplementationOnce(() => new Promise(r => { resolve = r; }));
        const wrapper = await open();
        expect(preflight(wrapper).text()).toContain('stores.cashu_wallet_safety_loading');
        expect(preflight(wrapper).find('[data-testid=cashu-wallet-safety]').exists()).toBe(false);
        resolve({ ...settings, wallet_safety: blocked }); await flushPromises();
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_blocked');
        expect(preflight(wrapper).get('[data-testid=cashu-safety-change-count]').text()).toBe('1');
        expect(preflight(wrapper).get('a').attributes('href')).toBe('/stores/store-a?section=cashu');
        expect(preflight(wrapper).text()).toContain('stores.cashu_wallet_safety_recovery_steps');
        expect(button(wrapper, 'stores.change_connection').attributes('disabled')).toBeUndefined();
        expect(mocks.getSettings).toHaveBeenCalledTimes(1);
        expect(mocks.updateSettings).not.toHaveBeenCalled(); expect(mocks.request).not.toHaveBeenCalled();
        expect(mocks.confirm).not.toHaveBeenCalled(); expect(mocks.confirmEdit).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it.each([
        [safe, 'safe'],
        [{ status: 'unavailable', unresolved_payments: null, unresolved_change: null }, 'unavailable'],
        [undefined, 'unavailable'], [null, 'unavailable'],
        [{ status: 'unknown', unresolved_payments: 0, unresolved_change: 0 }, 'unavailable'],
        ['safe', 'unavailable'],
    ])('honestly presents snapshot %j as %s without recovery or automatic authorization', async (wallet_safety, status) => {
        mocks.getSettings.mockResolvedValue({ ...settings, wallet_safety });
        const wrapper = await open(); await flushPromises();
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe(`stores.cashu_wallet_safety_${status}`);
        expect(preflight(wrapper).find('a').exists()).toBe(false);
        expect(mocks.request).not.toHaveBeenCalled(); expect(mocks.updateSettings).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('preserves typed settings, consent and confirmation state on safety-only refresh', async () => {
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://typed.example.test');
        mocks.getSettings.mockResolvedValue({ ...settings, mint_url: 'https://ignored.example.test', wallet_safety: safe, change_confirmation: { ...settings.change_confirmation, pending: challenge } });
        await preflight(wrapper).get('button').trigger('click'); await flushPromises();
        expect((wrapper.get('#cashu-mint-url').element as HTMLInputElement).value).toBe('https://typed.example.test');
        expect((wrapper.findAll('input[type=checkbox]')[0]!.element as HTMLInputElement).checked).toBe(true);
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_safe');
        expect(mocks.request).toHaveBeenCalledTimes(1); expect(mocks.confirm).toHaveBeenCalledTimes(1);
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(0);
        expect(mocks.updateSettings).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('keeps password entry and email-code steps visible while refreshing only safety', async () => {
        mocks.user.can_use_password_login = true;
        const wrapper = await open(); await edit(wrapper);
        await wrapper.get('#cashu-wc-password').setValue('synthetic-password');
        await preflight(wrapper).get('button').trigger('click'); await flushPromises();
        expect((wrapper.get('#cashu-wc-password').element as HTMLInputElement).value).toBe('synthetic-password');
        expect(mocks.request).not.toHaveBeenCalled();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        await wrapper.findAll('input[autocomplete=one-time-code]')[0]!.setValue('1');
        await preflight(wrapper).get('button').trigger('click'); await flushPromises();
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(6);
        expect((wrapper.findAll('input[autocomplete=one-time-code]')[0]!.element as HTMLInputElement).value).toBe('1');
        expect(mocks.request).toHaveBeenCalledTimes(1); expect(mocks.confirm).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('replaces old safe evidence with loading and then unavailable on refresh failure', async () => {
        mocks.getSettings.mockResolvedValueOnce({ ...settings, wallet_safety: safe });
        const wrapper = await open(); await flushPromises();
        let reject!: (value: unknown) => void;
        mocks.getSettings.mockImplementationOnce(() => new Promise((_, r) => { reject = r; }));
        await preflight(wrapper).get('button').trigger('click');
        expect(preflight(wrapper).text()).toContain('stores.cashu_wallet_safety_loading');
        expect(preflight(wrapper).text()).not.toContain('stores.cashu_wallet_safety_safe');
        reject(new Error('synthetic private upstream detail')); await flushPromises();
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_unavailable');
        expect(wrapper.text()).not.toContain('synthetic private upstream detail');
        expect(mocks.updateSettings).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('ignores an old refresh after switching A to B and back to A', async () => {
        const wrapper = await open(); await flushPromises();
        let resolve!: (value: unknown) => void;
        mocks.getSettings.mockImplementationOnce(() => new Promise(r => { resolve = r; }));
        await preflight(wrapper).get('button').trigger('click');
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        mocks.getSettings.mockResolvedValue({ ...settings, wallet_safety: safe });
        await wrapper.setProps({ storeId: 'store-a' }); await flushPromises();
        resolve({ ...settings, wallet_safety: { ...blocked, unresolved_change: 99 } }); await flushPromises();
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_safe');
        expect(preflight(wrapper).text()).not.toContain('99'); expect(mocks.request).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('ignores old settings hydration and safety after A to B to A and after unmount', async () => {
        let resolve!: (value: unknown) => void;
        mocks.getSettings.mockImplementationOnce(() => new Promise(r => { resolve = r; }));
        const wrapper = await open();
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        mocks.getSettings.mockResolvedValue({ ...settings, wallet_safety: safe });
        await wrapper.setProps({ storeId: 'store-a' }); await flushPromises();
        await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://typed.example.test');
        resolve({ ...settings, mint_url: 'https://stale.example.test', wallet_safety: blocked }); await flushPromises();
        expect((wrapper.get('#cashu-mint-url').element as HTMLInputElement).value).toBe('https://typed.example.test');
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_safe');
        let finish!: (value: unknown) => void;
        mocks.getSettings.mockImplementationOnce(() => new Promise(r => { finish = r; }));
        await preflight(wrapper).get('button').trigger('click'); wrapper.unmount();
        finish({ ...settings, wallet_safety: blocked }); await flushPromises();
        expect(mocks.updateSettings).not.toHaveBeenCalled(); expect(wrapper.emitted('submitted')).toBeUndefined();
    });

    it.each([
        ['cashu_outstanding_payments', 'blocked'], ['cashu_safety_unavailable', 'unavailable'],
    ])('gives fresh %s refusal precedence over safe metadata and an in-flight refresh', async (code, status) => {
        mocks.getSettings.mockResolvedValue({ ...settings, wallet_safety: safe });
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        await wrapper.get('#cashu-mint-url').setValue('https://typed.example.test');
        let resolve!: (value: unknown) => void;
        mocks.getSettings.mockImplementationOnce(() => new Promise(r => { resolve = r; }));
        await preflight(wrapper).get('button').trigger('click');
        mocks.updateSettings.mockRejectedValueOnce({ response: { status: 422, data: { code, wallet_safety: safe } } });
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe(`stores.cashu_wallet_safety_${status}`);
        resolve({ ...settings, wallet_safety: safe }); await flushPromises();
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe(`stores.cashu_wallet_safety_${status}`);
        expect((wrapper.get('#cashu-mint-url').element as HTMLInputElement).value).toBe('https://typed.example.test');
        expect(wrapper.emitted('submitted')).toBeUndefined(); wrapper.unmount();
    });

    it('refreshes old blocked evidence after an authoritative successful save without another code', async () => {
        mocks.updateSettings.mockResolvedValue(settings);
        const wrapper = await open(); await edit(wrapper); await enterCode(wrapper); await acceptBeta(wrapper);
        mocks.getSettings.mockResolvedValue({ ...settings, wallet_safety: safe });
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(preflight(wrapper).get('[data-testid=cashu-wallet-safety-status]').text()).toBe('stores.cashu_wallet_safety_safe');
        expect(mocks.getSettings).toHaveBeenCalledTimes(2);
        expect(mocks.request).toHaveBeenCalledTimes(1); expect(mocks.confirm).toHaveBeenCalledTimes(1);
        expect(mocks.updateSettings).toHaveBeenCalledTimes(1); expect(wrapper.emitted('submitted')).toHaveLength(1); wrapper.unmount();
    });

});
