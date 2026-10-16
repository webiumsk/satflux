import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createRouter, createMemoryHistory } from 'vue-router';
import WalletConnectionForm from '../components/stores/WalletConnectionForm.vue';

const mocks = vi.hoisted(() => ({ request: vi.fn(), confirm: vi.fn(), resend: vi.fn(), create: vi.fn(), upgrade: vi.fn(), user: { can_use_password_login: false, is_guest: false } }));
vi.mock('../services/api', () => ({ walletApi: { connection: { create: mocks.create, change: { request: mocks.request, confirm: mocks.confirm, resend: mocks.resend } } } }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: mocks.user }) }));
vi.mock('../composables/useGuestUpgradeModal', () => ({ useGuestUpgradeModal: () => ({ openGuestUpgradeModal: mocks.upgrade }) }));
vi.mock('../composables/useSamRockPairing', () => ({ useSamRockPairing: () => ({ samrockOtp: ref(null), samrockExpiresAt: ref(null), samrockQrObjectUrl: ref(null), samrockBusy: ref(false), samrockPollStatus: ref(null), samrockErrorMessage: ref(''), cancelSamRockPairing: vi.fn(), startSamRockPairing: vi.fn() }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

const challenge = { purpose: 'wallet_change', email: 'owner@example.test', expires_at: '2099-01-01T00:00:00Z', resend_available_at: '2000-01-01T00:00:00Z', attempts_left: 5, sends_left: 3 };
const open = async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/stores/:id', component: { template: '<div />' } }] });
    await router.push('/stores/store-a'); await router.isReady();
    return mount(WalletConnectionForm, { props: { storeId: 'store-a', walletType: 'cashu', existingConnection: null }, global: { plugins: [router], stubs: {
        CashuConnectionSection: { template: '<button data-testid="switch" @click="$emit(\'switch-to-lightning\')">switch</button>' },
        WalletConnectionSmartPaste: true, LightningAddressQuickConnect: true, WalletConnectionTypeGuide: true,
    } } });
};

describe('Cashu to Lightning wallet change', () => {
    beforeEach(() => {
        vi.resetAllMocks(); mocks.user.can_use_password_login = false; mocks.user.is_guest = false;
        mocks.request.mockResolvedValue({ required: true, challenge });
        mocks.confirm.mockResolvedValue({ type: 'cashu', secret: null, granted_until: '2099-01-01T00:00:00Z' });
    });

    it('requires an email code for a pure Cashu store with no Lightning connection row', async () => {
        const wrapper = await open();
        await wrapper.get('[data-testid=switch]').trigger('click'); await flushPromises();
        expect(mocks.request).toHaveBeenCalledWith('store-a', {});
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(6);
        expect(wrapper.find('wallet-connection-smart-paste-stub').exists()).toBe(false);
        const inputs = wrapper.findAll('input[autocomplete=one-time-code]');
        for (let i = 0; i < 6; i++) await inputs[i]!.setValue(String(i + 1));
        await flushPromises();
        expect(mocks.confirm).toHaveBeenCalledWith('store-a', '123456');
        expect(wrapper.find('wallet-connection-smart-paste-stub').exists()).toBe(true); wrapper.unmount();
    });

    it('keeps the legacy password step before the code and can cancel back to Cashu', async () => {
        mocks.user.can_use_password_login = true;
        const wrapper = await open();
        await wrapper.get('[data-testid=switch]').trigger('click'); await flushPromises();
        expect(wrapper.find('#wc-password').exists()).toBe(true);
        await wrapper.get('#wc-password').setValue('synthetic-password');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.request).toHaveBeenCalledWith('store-a', { password: 'synthetic-password' });
        await wrapper.findAll('button').find(b => b.text() === 'common.cancel')!.trigger('click'); await flushPromises();
        expect(wrapper.find('[data-testid=switch]').exists()).toBe(true); wrapper.unmount();
    });

    it('opens account upgrade for guests without leaving the Cashu view', async () => {
        mocks.user.is_guest = true;
        const wrapper = await open();
        await wrapper.get('[data-testid=switch]').trigger('click'); await flushPromises();
        expect(mocks.upgrade).toHaveBeenCalledWith('stores.wallet_change_feature_label');
        expect(mocks.request).not.toHaveBeenCalled(); expect(wrapper.find('[data-testid=switch]').exists()).toBe(true); wrapper.unmount();
    });

    it('does not open an old store challenge after switching stores', async () => {
        let resolve!: (value: unknown) => void;
        mocks.request.mockImplementation(() => new Promise(r => { resolve = r; }));
        const wrapper = await open();
        await wrapper.get('[data-testid=switch]').trigger('click'); await flushPromises();
        await wrapper.setProps({ storeId: 'store-b' });
        resolve({ required: true, challenge }); await flushPromises();
        expect(wrapper.find('[data-testid=switch]').exists()).toBe(true);
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(0);
        expect(mocks.confirm).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it.each([
        ['cashu_outstanding_payments', 'stores.cashu_wallet_change_recovery_required'],
        ['cashu_safety_unavailable', 'stores.cashu_wallet_change_safety_unavailable'],
        [undefined, 'stores.cashu_wallet_change_recovery_required'],
    ])('shows a visible translated save refusal for %s and preserves the entered connection', async (code, message) => {
        mocks.create.mockRejectedValue({ response: { status: 422, data: { code, wallet_safety: code ? { status: code === 'cashu_safety_unavailable' ? 'unavailable' : 'blocked', unresolved_payments: 0, unresolved_change: 1 } : undefined, errors: { cashu: ['Outstanding Cashu settlements or change must be recovered before changing this wallet.'] } } } });
        const wrapper = await open();
        await wrapper.get('[data-testid=switch]').trigger('click'); await flushPromises();
        const inputs = wrapper.findAll('input[autocomplete=one-time-code]');
        for (let i = 0; i < 6; i++) await inputs[i]!.setValue(String(i + 1));
        await flushPromises();
        const paste = wrapper.getComponent({ name: 'WalletConnectionSmartPaste' });
        paste.vm.$emit('update:connectionType', 'lnaddress');
        paste.vm.$emit('update:modelValue', 'type=lnaddress;ln-address=replacement@wallet.example.test;');
        await flushPromises();
        await wrapper.findAll('button').find(b => b.text() === 'stores.save_connection')!.trigger('click');
        await flushPromises();
        expect(mocks.create).toHaveBeenCalledTimes(1);
        expect(mocks.create).toHaveBeenCalledWith('store-a', {
            type: 'lnaddress', secret: 'type=lnaddress;ln-address=replacement@wallet.example.test;', fallback_lightning_address: undefined,
        });
        expect(wrapper.get('[role=alert]').text()).toBe(message);
        expect(wrapper.get('[role=alert]').isVisible()).toBe(true);
        expect(wrapper.get('[data-testid=cashu-wallet-safety-status]').text()).toBe(code === 'cashu_safety_unavailable' ? 'stores.cashu_wallet_safety_unavailable' : 'stores.cashu_wallet_safety_blocked');
        if (code === 'cashu_outstanding_payments') expect(wrapper.get('[data-testid=cashu-safety-change-count]').text()).toBe('1');
        if (!code) expect(wrapper.get('[data-testid=cashu-safety-change-count]').text()).toBe('stores.cashu_wallet_safety_unknown');
        expect(paste.props('modelValue')).toBe('type=lnaddress;ln-address=replacement@wallet.example.test;');
        expect(wrapper.emitted('submitted')).toBeUndefined();
        expect(mocks.confirm).toHaveBeenCalledTimes(1); wrapper.unmount();
    });

    it('does not carry an old save refusal into a later editing session after switching away and back', async () => {
        let reject!: (reason: unknown) => void;
        mocks.create.mockImplementation(() => new Promise((_, rejectPromise) => { reject = rejectPromise; }));
        const wrapper = await open();
        const unlock = async () => {
            await wrapper.get('[data-testid=switch]').trigger('click'); await flushPromises();
            const inputs = wrapper.findAll('input[autocomplete=one-time-code]');
            for (let i = 0; i < 6; i++) await inputs[i]!.setValue(String(i + 1));
            await flushPromises();
        };
        await unlock();
        const paste = wrapper.getComponent({ name: 'WalletConnectionSmartPaste' });
        paste.vm.$emit('update:connectionType', 'lnaddress');
        paste.vm.$emit('update:modelValue', 'type=lnaddress;ln-address=replacement@wallet.example.test;');
        await flushPromises();
        await wrapper.findAll('button').find(b => b.text() === 'stores.save_connection')!.trigger('click');
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        await wrapper.setProps({ storeId: 'store-a' }); await flushPromises();
        await unlock();
        reject({ response: { status: 422, data: { code: 'cashu_outstanding_payments', wallet_safety: { status: 'blocked', unresolved_payments: 9, unresolved_change: 9 } } } });
        await flushPromises();
        expect(wrapper.find('[data-testid=cashu-wallet-safety]').exists()).toBe(false);
        expect(wrapper.find('[role=alert]').exists()).toBe(false);
        expect(mocks.create).toHaveBeenCalledTimes(1); expect(wrapper.emitted('submitted')).toBeUndefined(); wrapper.unmount();
    });
});
