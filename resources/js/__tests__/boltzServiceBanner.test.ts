import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import BoltzServiceBanner from '../components/stores/BoltzServiceBanner.vue';
import { hasBoltzServiceAlert } from '../utils/boltzAvailability';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

describe('Boltz service warning', () => {
    it('warns legacy Boltz stores without relying on readiness network calls', async () => {
        const router = createRouter({ history: createMemoryHistory(), routes: [
            { path: '/stores/:id/wallet-connection', name: 'stores-wallet-connection', component: { template: '<div />' } },
        ] });
        await router.push('/stores/local-store-uuid/wallet-connection');
        await router.isReady();
        const wrapper = mount(BoltzServiceBanner, {
            props: { store: { id: 'local-store-uuid', wallet_type: 'aqua_boltz' } },
            global: { plugins: [router] },
        });
        expect(wrapper.get('[role=status]').text()).toContain('stores.boltz_service_alert_body');
        expect(wrapper.get('a').attributes('href')).toBe('/stores/local-store-uuid/wallet-connection');
        await wrapper.setProps({ store: { id: 'local-store-uuid', wallet_type: 'lnaddress' } });
        expect(wrapper.find('[role=status]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('respects server provider status and does not flag unrelated stores', () => {
        expect(hasBoltzServiceAlert({ wallet_type: 'cashu' })).toBe(false);
        expect(hasBoltzServiceAlert({ wallet_type: 'blink' })).toBe(false);
        expect(hasBoltzServiceAlert({ wallet_type: 'aqua_boltz', boltz_service_alert: {
            active: false, code: 'boltz_receiving_unavailable', new_connections_available: true,
        } })).toBe(false);
    });
});
