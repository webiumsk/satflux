import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CashuWalletSafetySummary from '../components/stores/wallet-connection/CashuWalletSafetySummary.vue';
import StoreCashuPayments from '../components/stores/StoreCashuPayments.vue';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('../services/api', () => ({ default: api }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: { can_use_password_login: false } }) }));
vi.mock('vue-i18n', async () => {
    const { default: en } = await import('../locales/en.json');
    return { useI18n: () => ({ t: (key: string, params: Record<string, unknown> = {}) => {
        const value = (en.stores as unknown as Record<string, string>)[key.replace(/^stores\./, '')] ?? key;
        return Object.entries(params).reduce((text, [name, replacement]) => text.split(`{${name}}`).join(String(replacement)), value);
    } }) };
});
const blocked = { status: 'blocked', unresolved_payments: 0, unresolved_change: 1 };
const terminalRows = [
    { quote_id: 'quote-a', settlement_state: 'SETTLED', reported_settlement_state: 'SETTLED', amount_sats: 100 },
    { quote_id: 'quote-b', settlement_state: 'SETTLED', reported_settlement_state: 'SETTLED', amount_sats: 200 },
    { quote_id: 'quote-c', settlement_state: 'EXPIRED_UNPAID', reported_settlement_state: 'EXPIRED_UNPAID', amount_sats: 300 },
];
const response = (safety: unknown = blocked, items = terminalRows) => ({ data: { data: { total: items.length, items, wallet_safety: safety } } });
const open = () => mount(StoreCashuPayments, { props: { store: { id: 'store-a' } }, global: { stubs: { Select: true } } });

beforeEach(() => { vi.resetAllMocks(); api.get.mockResolvedValue(response()); });

describe('Cashu safety summary', () => {
    it('describes records independently from payments without offering any mutation', () => {
        const wrapper = mount(CashuWalletSafetySummary, { props: { safety: blocked } });
        expect(wrapper.get('[data-testid=cashu-safety-change-count]').text()).toBe('1');
        expect(wrapper.text()).toContain('Counts represent records, not monetary amounts.');
        expect(wrapper.text()).toContain('Change records are separate from payment history.');
        expect(wrapper.text()).toContain('Every save checks again.');
        expect(wrapper.findAll('button, a, input')).toHaveLength(0); wrapper.unmount();
    });

    it.each([
        { status: 'blocked', unresolved_payments: 0, unresolved_change: 0 },
        { status: 'blocked', unresolved_payments: null, unresolved_change: null },
        { status: 'blocked', unresolved_payments: '0', unresolved_change: -1 },
        { status: 'blocked', unresolved_payments: false, unresolved_change: 1.5 },
        { status: 'blocked', unresolved_payments: 2147483648, unresolved_change: Infinity },
    ])('keeps a refusal with zero or unknown counts blocked and unexplained: %j', safety => {
        const wrapper = mount(CashuWalletSafetySummary, { props: { safety } });
        expect(wrapper.get('[data-testid=cashu-wallet-safety-status]').text()).toBe('Cashu is blocking wallet changes.');
        expect(wrapper.get('[data-testid=cashu-safety-unexplained]').text()).toContain('contact support');
        expect(wrapper.text()).not.toContain('no wallet-change restriction');
        if (safety.unresolved_payments !== 0) expect(wrapper.get('[data-testid=cashu-safety-payment-count]').text()).toBe('Unknown');
        if (safety.unresolved_change !== 0) expect(wrapper.get('[data-testid=cashu-safety-change-count]').text()).toBe('Unknown');
        wrapper.unmount();
    });

    it.each([null, undefined, { status: 'unavailable' }, { status: 'unexpected', unresolved_change: 1 }])('shows unavailable evidence without treating it as outstanding money: %j', safety => {
        const wrapper = mount(CashuWalletSafetySummary, { props: { safety } });
        expect(wrapper.text()).toContain('The safety check is unavailable.');
        expect(wrapper.find('dl').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('funds'); wrapper.unmount();
    });

    it('shows safe evidence as a past observation without authorizing any action', () => {
        const wrapper = mount(CashuWalletSafetySummary, { props: { safety: { status: 'safe', unresolved_payments: 0, unresolved_change: 0 } } });
        expect(wrapper.text()).toContain('At the last check');
        expect(wrapper.text()).toContain('Every save checks again.');
        expect(wrapper.findAll('button, a, input')).toHaveLength(0); wrapper.unmount();
    });

    it.each(['blocked', 'unavailable'] as const)('does not let contradictory safe metadata override a %s save refusal', refusalStatus => {
        const wrapper = mount(CashuWalletSafetySummary, { props: { refusalStatus, safety: { status: 'safe', unresolved_payments: 0, unresolved_change: 0, private_extra: 'do-not-display' } } });
        expect(wrapper.text()).not.toContain('no wallet-change restriction');
        expect(wrapper.text()).not.toContain('do-not-display'); wrapper.unmount();
    });
});

describe('Cashu payment diagnostics', () => {
    it('shows blocked change beside all three terminal payments without inventing a recovery action', async () => {
        const wrapper = open(); await flushPromises();
        expect(wrapper.findAll('tbody tr')).toHaveLength(3);
        expect(wrapper.get('[data-testid=cashu-safety-change-count]').text()).toBe('1');
        expect(wrapper.get('tbody').findAll('button')).toHaveLength(0);
        expect(wrapper.get('[data-testid=cashu-wallet-safety]').text()).toContain('If Cashu settlements offer no recovery action, contact support.');
        expect(api.post).not.toHaveBeenCalled();
        await wrapper.findAll('button').find(b => b.text() === 'Refresh')!.trigger('click'); await flushPromises();
        expect(api.get).toHaveBeenCalledTimes(2); expect(api.post).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('makes original PENDING versus displayed SETTLED explicit without enabling terminal retries', async () => {
        api.get.mockResolvedValue(response(blocked, [{ ...terminalRows[0]!, reported_settlement_state: 'PENDING' }]));
        const wrapper = open(); await flushPromises();
        expect(wrapper.get('tbody').text()).toContain('Plugin status: Pending');
        expect(wrapper.get('tbody').text()).toContain('differs from the plugin status');
        expect(wrapper.get('tbody').findAll('button')).toHaveLength(0);
        expect(api.post).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('labels unknown original state honestly even when completion is displayed', async () => {
        api.get.mockResolvedValue(response(blocked, [{ ...terminalRows[0]!, reported_settlement_state: undefined } as unknown as typeof terminalRows[number]]));
        const wrapper = open(); await flushPromises();
        expect(wrapper.get('tbody').text()).toContain('The original plugin status is unknown.');
        expect(wrapper.get('tbody').findAll('button')).toHaveLength(0); wrapper.unmount();
    });

    it('keeps payment history available when the safety snapshot is unavailable', async () => {
        api.get.mockResolvedValue(response({ status: 'unavailable', unresolved_payments: null, unresolved_change: null }));
        const wrapper = open(); await flushPromises();
        expect(wrapper.findAll('tbody tr')).toHaveLength(3);
        expect(wrapper.text()).toContain('The safety check is unavailable.'); expect(api.post).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('does not restore stale blocked diagnostics after the selected store changes', async () => {
        let resolve!: (value: unknown) => void;
        api.get.mockImplementationOnce(() => new Promise(r => { resolve = r; })).mockResolvedValue(response({ status: 'safe', unresolved_payments: 0, unresolved_change: 0 }));
        const wrapper = open();
        await wrapper.setProps({ store: { id: 'store-b' } }); await flushPromises();
        resolve(response(blocked)); await flushPromises();
        expect(wrapper.get('[data-testid=cashu-wallet-safety-status]').text()).toContain('no wallet-change restriction');
        expect(wrapper.get('[data-testid=cashu-safety-change-count]').text()).toBe('0'); expect(api.post).not.toHaveBeenCalled(); wrapper.unmount();
    });
});
