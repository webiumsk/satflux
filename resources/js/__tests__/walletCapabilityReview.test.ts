import { flushPromises, mount } from '@vue/test-utils';
import { ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import WalletConnectionForm from '../components/stores/WalletConnectionForm.vue';

const api = vi.hoisted(() => ({ create: vi.fn(), probe: vi.fn(), update: vi.fn() }));
vi.mock('../services/api', () => ({ walletApi: {
    connection: { create: api.create, lnaddressProbe: api.probe },
    cashu: { updateSettings: api.update },
} }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: { can_use_password_login: false, is_guest: false } }) }));
vi.mock('../composables/useGuestUpgradeModal', () => ({ useGuestUpgradeModal: () => ({ openGuestUpgradeModal: vi.fn() }) }));
vi.mock('../composables/useSamRockPairing', () => ({ useSamRockPairing: () => ({
    samrockOtp: ref(null), samrockExpiresAt: ref(null), samrockQrObjectUrl: ref(null),
    samrockBusy: ref(false), samrockPollStatus: ref(null), samrockErrorMessage: ref(''),
    cancelSamRockPairing: vi.fn(), startSamRockPairing: vi.fn(),
}) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

const unsupported = {
    lud21: false, reason: 'no_verify', outcome: 'unsupported', code: 'lud21_unsupported',
    alternatives: [{ type: 'cashu', available: true, blocked_reason: null,
        mint_url: 'https://merchant-mint.example/Bitcoin', requires_consent: true }],
};

describe('Advanced address capability review', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        api.create.mockRejectedValue({ response: { status: 422, data: unsupported } });
        api.probe.mockResolvedValue(unsupported);
        api.update.mockResolvedValue({});
    });

    it.each([
        ['migrated@coinos.io', 'migrated@coinos.io'],
        ['type=blink;ln-address=merchant;', 'merchant@blink.sv'],
        ['type=blitz;ln-address=merchant;', 'merchant@blitzwalletapp.com'],
        ['type=flash;username=merchant;', 'merchant@flashapp.me'],
    ])('reviews the current type on Save without blur for %s and requires fresh consent', async (secret, address) => {
        const router = createRouter({ history: createMemoryHistory(), routes: [
            { path: '/stores/:id', component: { template: '<div />' } },
        ] });
        await router.push('/stores/store-a');
        await router.isReady();
        const wrapper = mount(WalletConnectionForm, {
            props: { storeId: 'store-a', walletType: 'blink', existingConnection: null },
            global: { plugins: [router], stubs: { WalletTypeIcon: true, WalletConnectionTypeGuide: true } },
        });
        try {
            await flushPromises();
            await wrapper.get('#wallet-setup-tab-advanced').trigger('click');
            await wrapper.get('#wallet-connection-smart-paste').setValue(secret);
            // Deliberately invoke Save without triggering blur first.
            await wrapper.findAll('button').find(button => button.text() === 'stores.save_connection')!.trigger('click');
            await flushPromises();
            expect(api.create).toHaveBeenCalledTimes(1);
            expect(wrapper.get<HTMLInputElement>('#ln-quick-address').element.value).toBe(address);
            expect(wrapper.get('#wallet-setup-panel-ln').isVisible()).toBe(true);
            const connect = () => wrapper.findAll('button').find(button => button.text() === 'stores.ln_quick_connect_button')!;
            await connect().trigger('click');
            await flushPromises();
            expect(api.probe).toHaveBeenCalledWith('store-a', address);
            expect(api.update).not.toHaveBeenCalled();
            const consent = wrapper.get('#wallet-setup-panel-ln input[type=checkbox]');
            await consent.setValue(true);
            await connect().trigger('click');
            await flushPromises();
            expect(api.update).toHaveBeenCalledExactlyOnceWith('store-a', {
                mint_url: 'https://merchant-mint.example/Bitcoin', lightning_address: address, enabled: true,
            });
        } finally {
            wrapper.unmount();
        }
    });
});
