import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import WalletConnectionForm from '../components/stores/WalletConnectionForm.vue';
import WalletConnectionSmartPaste from '../components/stores/WalletConnectionSmartPaste.vue';

const api = vi.hoisted(() => ({ test: vi.fn(), create: vi.fn(), request: vi.fn(), confirm: vi.fn(), reveal: vi.fn() }));
vi.mock('../services/api', () => ({ walletApi: { connection: { test: api.test, create: api.create, reveal: api.reveal, change: { request: api.request, confirm: api.confirm } } } }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: { can_use_password_login: false, is_guest: false } }) }));
vi.mock('../composables/useGuestUpgradeModal', () => ({ useGuestUpgradeModal: () => ({ openGuestUpgradeModal: vi.fn() }) }));
vi.mock('../composables/useSamRockPairing', () => ({ useSamRockPairing: () => ({ samrockOtp: ref(null), samrockExpiresAt: ref(null), samrockQrObjectUrl: ref(null), samrockBusy: ref(false), samrockPollStatus: ref(null), samrockErrorMessage: ref(''), cancelSamRockPairing: vi.fn(), startSamRockPairing: vi.fn() }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

const blink = 'type=blink;server=https://api.example.test;api-key=synthetic-key;wallet-id=synthetic-wallet;';
const blitz = 'type=blitz;ln-address=merchant@blitzwalletapp.com;';
const open = async (props = {}) => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/stores/:id', component: { template: '<div />' } }] });
    await router.push('/stores/store-a'); await router.isReady();
    const wrapper = mount(WalletConnectionForm, { props: { storeId: 'store-a', walletType: 'blink', existingConnection: null, ...props }, global: { plugins: [router], stubs: {
        WalletTypeIcon: true, LightningAddressQuickConnect: true, WalletConnectionTypeGuide: true,
        CashuConnectionSection: { template: '<div data-testid="cashu-section" />' },
    } } });
    await flushPromises();
    const advanced = wrapper.findAll('button').find(b => b.text() === 'create_store.tab_advanced');
    if (advanced) await advanced.trigger('click');
    return wrapper;
};
const button = (wrapper: Awaited<ReturnType<typeof open>>, key: string) => wrapper.findAll('button').find(b => b.text() === key)!;
const input = (wrapper: Awaited<ReturnType<typeof open>>) => wrapper.get('#wallet-connection-smart-paste');

beforeEach(() => { vi.resetAllMocks(); api.create.mockResolvedValue(undefined); api.test.mockResolvedValue({ success: true, message: 'synthetic-test-success' }); });

describe('Wallet connection detection boundaries', () => {
    it('keeps typing stable without detection, validation, fallback prompts or network calls until blur', async () => {
        const wrapper = await open();
        for (const value of ['t', 'type=', 'type=blitz;', blitz]) {
            await input(wrapper).setValue(value);
            expect(wrapper.getComponent(WalletConnectionSmartPaste).props('connectionType')).toBe('blink');
            expect(wrapper.find('#wallet-advanced-fallback').exists()).toBe(false);
            expect(wrapper.find('[data-testid=cashu-section]').exists()).toBe(false);
            expect(wrapper.find('[role=alert]').exists()).toBe(false);
        }
        expect(api.test).not.toHaveBeenCalled(); expect(api.create).not.toHaveBeenCalled(); expect(api.request).not.toHaveBeenCalled();
        await input(wrapper).trigger('blur'); await flushPromises();
        expect(wrapper.getComponent(WalletConnectionSmartPaste).props('connectionType')).toBe('blitz'); wrapper.unmount();
    });

    it.each([
        [blink, 'blink'],
    ])('shows fallback only after committing %s and saves its configured value', async (secret, type) => {
        const wrapper = await open(); await input(wrapper).setValue(secret);
        expect(wrapper.find('#wallet-advanced-fallback').exists()).toBe(false);
        await input(wrapper).trigger('blur'); await flushPromises();
        expect(wrapper.find('#wallet-advanced-fallback').exists()).toBe(true);
        await wrapper.get('#wallet-advanced-fallback').setValue('owner@wallet.example.test');
        expect(api.test).not.toHaveBeenCalled(); expect(api.create).not.toHaveBeenCalled();
        await button(wrapper, 'stores.save_connection').trigger('click'); await flushPromises();
        expect(api.create).toHaveBeenCalledExactlyOnceWith('store-a', { type, secret, fallback_lightning_address: 'owner@wallet.example.test' }); wrapper.unmount();
    });

    it.each([
        'ct(slip77(synthetic-test-only),elwpkh(xpubSyntheticTestOnly))',
        'ct(slip77(synthetic-test-only),elsh(wpkh(xpubSyntheticTestOnly)))',
    ])('refuses a paused Boltz descriptor on Test and Save without a prior blur: %s', async (secret) => {
        const wrapper = await open();
        await input(wrapper).setValue(secret);
        await button(wrapper, 'stores.test_connection').trigger('click');
        await flushPromises();
        expect(api.test).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('stores.boltz_setup_paused');
        await button(wrapper, 'stores.save_connection').trigger('click');
        await flushPromises();
        expect(api.create).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('stores.boltz_setup_paused');
        wrapper.unmount();
    });

    it('does not offer or auto-open SamRock even for a stale setup link', async () => {
        const wrapper = await open({ walletType: 'aqua_boltz', autoSamrock: true });
        expect(wrapper.find('#wallet-setup-tab-samrock').exists()).toBe(false);
        expect(wrapper.find('#wallet-setup-panel-samrock').exists()).toBe(false);
        expect(wrapper.find('#wallet-setup-tab-ln').exists()).toBe(true);
        expect(wrapper.find('#wallet-setup-tab-advanced').exists()).toBe(true);
        wrapper.unmount();
    });

    it('does not route partial mint URLs to Cashu before the user leaves the field', async () => {
        const wrapper = await open();
        await input(wrapper).setValue('https://mint.example.test');
        expect(wrapper.find('[data-testid=cashu-section]').exists()).toBe(false);
        await input(wrapper).trigger('blur'); await flushPromises();
        expect(wrapper.find('[data-testid=cashu-section]').exists()).toBe(true);
        expect(api.test).not.toHaveBeenCalled(); expect(api.create).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('validates and detects the latest candidate when Save is invoked without a blur', async () => {
        const wrapper = await open();
        await input(wrapper).setValue(blitz);
        await button(wrapper, 'stores.save_connection').trigger('click'); await flushPromises();
        expect(api.create).toHaveBeenCalledExactlyOnceWith('store-a', { type: 'blitz', secret: blitz, fallback_lightning_address: undefined }); wrapper.unmount();
    });

    it('never saves an invalid edited secret using an earlier detected type', async () => {
        const wrapper = await open();
        await input(wrapper).setValue(blitz); await input(wrapper).trigger('blur');
        await input(wrapper).setValue('invalid-edited-candidate');
        await button(wrapper, 'stores.save_connection').trigger('click'); await flushPromises();
        expect(api.create).not.toHaveBeenCalled(); expect(wrapper.get('[role=alert]').text()).toBe('stores.blitz_invalid_connection'); wrapper.unmount();
    });

    it('detects a changed complete candidate on Save rather than retaining the previous detected type', async () => {
        const wrapper = await open();
        await input(wrapper).setValue(blitz); await input(wrapper).trigger('blur');
        await input(wrapper).setValue('type=lnaddress;ln-address=merchant@wallet.example.test;');
        await button(wrapper, 'stores.save_connection').trigger('click'); await flushPromises();
        expect(api.create).toHaveBeenCalledExactlyOnceWith('store-a', { type: 'lnaddress', secret: 'type=lnaddress;ln-address=merchant@wallet.example.test;', fallback_lightning_address: undefined }); wrapper.unmount();
    });

    it('ignores a test result after the candidate is edited and allows a fresh explicit test', async () => {
        let resolve!: (value: unknown) => void;
        api.test.mockImplementationOnce(() => new Promise(r => { resolve = r; }));
        const wrapper = await open(); await input(wrapper).setValue(blitz);
        await button(wrapper, 'stores.test_connection').trigger('click');
        expect(api.test).toHaveBeenCalledTimes(1);
        await input(wrapper).setValue('type=flash;ln-address=merchant@flashapp.me;');
        resolve({ success: true, message: 'old-candidate-result' }); await flushPromises();
        expect(wrapper.text()).not.toContain('old-candidate-result');
        await button(wrapper, 'stores.test_connection').trigger('click'); await flushPromises();
        expect(api.test).toHaveBeenLastCalledWith('store-a', { connection_string: 'type=flash;ln-address=merchant@flashapp.me;', crypto_code: 'BTC' }); wrapper.unmount();
    });

    it('ignores an old test after A-B-A store changes', async () => {
        let resolve!: (value: unknown) => void;
        api.test.mockImplementationOnce(() => new Promise(r => { resolve = r; }));
        const wrapper = await open(); await input(wrapper).setValue(blitz);
        await button(wrapper, 'stores.test_connection').trigger('click');
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        await wrapper.setProps({ storeId: 'store-a' }); await flushPromises();
        resolve({ success: true, message: 'old-store-result' }); await flushPromises();
        expect(wrapper.text()).not.toContain('old-store-result'); expect(api.create).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('retains configured credentials after reveal without triggering Cashu switching or testing', async () => {
        api.request.mockResolvedValue({ required: false }); api.reveal.mockResolvedValue({ type: 'blink', secret: blink });
        const wrapper = await open({ existingConnection: { type: 'blink', status: 'connected' } });
        await button(wrapper, 'stores.change_connection').trigger('click'); await flushPromises();
        expect((input(wrapper).element as HTMLTextAreaElement).value).toBe(blink);
        expect(wrapper.getComponent(WalletConnectionSmartPaste).props('connectionType')).toBe('blink');
        expect(wrapper.find('[data-testid=cashu-section]').exists()).toBe(false); expect(api.test).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('keeps a newer test result when the old candidate request finishes later', async () => {
        let reject!: (value: unknown) => void;
        api.test.mockImplementationOnce(() => new Promise((_, r) => { reject = r; }));
        const wrapper = await open(); await input(wrapper).setValue(blitz);
        await button(wrapper, 'stores.test_connection').trigger('click');
        await input(wrapper).setValue('type=flash;ln-address=merchant@flashapp.me;');
        await button(wrapper, 'stores.test_connection').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('synthetic-test-success');
        reject({ response: { data: { message: 'old-candidate-error' } } }); await flushPromises();
        expect(wrapper.text()).toContain('synthetic-test-success'); expect(wrapper.text()).not.toContain('old-candidate-error'); wrapper.unmount();
    });

    it('does not show stale success for a saved candidate that was edited while pending', async () => {
        let resolve!: () => void;
        api.create.mockImplementationOnce(() => new Promise<void>(r => { resolve = r; }));
        const wrapper = await open(); await input(wrapper).setValue(blitz);
        await button(wrapper, 'stores.save_connection').trigger('click');
        await input(wrapper).setValue('new-incomplete-candidate');
        resolve(); await flushPromises();
        expect(api.create).toHaveBeenCalledTimes(1); expect(wrapper.emitted('submitted')).toBeUndefined();
        expect(wrapper.get('[role=status]').text()).toBe('stores.wallet_connection_details_changed');
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        expect(wrapper.find('[role=status]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('does not show a retry notice for a pending save from a previous store', async () => {
        let reject!: (value: unknown) => void;
        api.create.mockImplementationOnce(() => new Promise((_, fail) => { reject = fail; }));
        const wrapper = await open(); await input(wrapper).setValue(blitz);
        await button(wrapper, 'stores.save_connection').trigger('click');
        await input(wrapper).setValue('type=flash;ln-address=merchant@flashapp.me;');
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises();
        reject({ response: { status: 422, data: { code: 'lud21_unsupported' } } }); await flushPromises();
        expect(wrapper.find('[role=status]').exists()).toBe(false);
        expect(wrapper.emitted('submitted')).toBeUndefined();
        wrapper.unmount();
    });

    it('routes a latest Cashu candidate on Save without blur and never submits the old connection', async () => {
        const wrapper = await open(); await input(wrapper).setValue(blitz); await input(wrapper).trigger('blur');
        await input(wrapper).setValue('https://mint.example.test');
        await button(wrapper, 'stores.save_connection').trigger('click'); await flushPromises();
        expect(wrapper.find('[data-testid=cashu-section]').exists()).toBe(true);
        expect(api.create).not.toHaveBeenCalled(); expect(api.test).not.toHaveBeenCalled(); expect(api.request).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('ignores an edited pending Save error, then allows an explicit Save of the latest candidate', async () => {
        let reject!: (value: unknown) => void;
        api.create.mockImplementationOnce(() => new Promise((_, r) => { reject = r; }));
        const wrapper = await open(); await input(wrapper).setValue(blitz);
        await button(wrapper, 'stores.save_connection').trigger('click');
        await input(wrapper).setValue('type=flash;ln-address=merchant@flashapp.me;');
        reject({ response: { status: 422, data: { errors: { secret: ['old-candidate-save-error'] } } } }); await flushPromises();
        expect(wrapper.text()).not.toContain('old-candidate-save-error'); expect(wrapper.emitted('submitted')).toBeUndefined();
        await button(wrapper, 'stores.save_connection').trigger('click'); await flushPromises();
        expect(api.create).toHaveBeenCalledTimes(2);
        expect(api.create).toHaveBeenLastCalledWith('store-a', { type: 'flash', secret: 'type=flash;ln-address=merchant@flashapp.me;', fallback_lightning_address: undefined });
        expect(wrapper.emitted('submitted')).toEqual([['flash']]); wrapper.unmount();
    });

    it('retains the existing six-digit authorization step before exposing configured values', async () => {
        const challenge = { purpose: 'wallet_change', email: 'owner@example.test', expires_at: '2099-01-01T00:00:00Z', resend_available_at: '2000-01-01T00:00:00Z', attempts_left: 5, sends_left: 3 };
        api.request.mockResolvedValue({ required: true, challenge }); api.confirm.mockResolvedValue({ type: 'blink', secret: blink });
        const wrapper = await open({ existingConnection: { type: 'blink', status: 'connected', change_confirmation: { required: true } } });
        await button(wrapper, 'stores.change_connection').trigger('click'); await flushPromises();
        expect(wrapper.find('#wallet-connection-smart-paste').exists()).toBe(false);
        const digits = wrapper.findAll('input[autocomplete=one-time-code]');
        for (let i = 0; i < 5; i++) await digits[i]!.setValue(String(i + 1));
        expect(api.confirm).not.toHaveBeenCalled();
        await digits[5]!.setValue('6'); await flushPromises();
        expect(api.confirm).toHaveBeenCalledExactlyOnceWith('store-a', '123456');
        expect((input(wrapper).element as HTMLTextAreaElement).value).toBe(blink);
        expect(api.create).not.toHaveBeenCalled(); expect(api.test).not.toHaveBeenCalled(); wrapper.unmount();
    });

});


describe('SmartPaste blur mode and legacy consumers', () => {
    it('keeps initial configured input intact and allows manual type selection after blur', async () => {
        const wrapper = mount(WalletConnectionSmartPaste, { props: { modelValue: 'unrecognized-candidate', connectionType: 'blink', detectionTrigger: 'blur' }, global: { stubs: { WalletTypeIcon: true } } });
        expect((wrapper.get('textarea').element as HTMLTextAreaElement).value).toBe('unrecognized-candidate');
        expect(wrapper.emitted('update:connectionType')).toBeUndefined();
        await wrapper.get('textarea').trigger('blur');
        await wrapper.findAll('button').find(b => b.text() === 'stores.wallet_detect_blitz')!.trigger('click');
        expect(wrapper.emitted('update:connectionType')).toEqual([['blitz']]);
        await wrapper.setProps({ modelValue: 'edited-candidate' });
        expect(wrapper.text()).not.toContain('stores.wallet_detected_label'); wrapper.unmount();
    });

    it('preserves input-trigger detection for existing creation-wizard consumers', async () => {
        const wrapper = mount(WalletConnectionSmartPaste, { props: { modelValue: '', connectionType: 'blink' }, global: { stubs: { WalletTypeIcon: true } } });
        await wrapper.setProps({ modelValue: blitz });
        expect(wrapper.emitted('update:connectionType')).toEqual([['blitz']]); wrapper.unmount();
    });
});
