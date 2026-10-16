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
const response = (safety: unknown = blocked, items: Array<Record<string, unknown>> = terminalRows) => ({ data: { data: { total: items.length, items, wallet_safety: safety } } });
const open = () => mount(StoreCashuPayments, { props: { store: { id: 'store-a' } }, global: { stubs: { Select: true, CashuChangeRecovery: true } } });

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


const paymentHolds = { status: 'blocked', unresolved_payments: 3, unresolved_change: 0, failed_payments: 2, other_non_terminal_payments: 0, terminal_recovery_payments: 1 };

describe('Cashu payment hold inventory', () => {
    it('distinguishes two failed payments from three holds including a completed recovery record', async () => {
        const failed = { settlement_state: 'FAILED', blocks_wallet_change: true, wallet_change_block_reasons: ['non_terminal_settlement'], recovery_phase: 'funding_verification', failure_reason_code: 'mint_poll_error' };
        api.get.mockResolvedValue(response(paymentHolds, [
            { ...failed, quote_id: 'failed-a' }, { ...failed, quote_id: 'failed-b' },
            { quote_id: 'completed-c', settlement_state: 'SETTLED', reported_settlement_state: 'SETTLED', blocks_wallet_change: true, wallet_change_block_reasons: ['retained_minted_proofs'], recovery_phase: 'terminal_recovery_review' },
        ]));
        const wrapper = open(); await flushPromises();
        expect(wrapper.get('[data-testid=cashu-safety-payment-count]').text()).toBe('3');
        expect(wrapper.get('[data-testid=cashu-safety-failed-count]').text()).toBe('2');
        expect(wrapper.get('[data-testid=cashu-safety-other-count]').text()).toBe('0');
        expect(wrapper.get('[data-testid=cashu-safety-terminal-count]').text()).toBe('1');
        expect(wrapper.text()).toContain('Total payment holds are not a count of failed payments.');
        const rows = wrapper.findAll('tbody tr');
        expect(rows.filter(row => row.text().includes('Failed'))).toHaveLength(2);
        expect(rows[2]!.get('[data-testid=cashu-payment-hold]').text()).toBe('Blocks wallet changes');
        expect(rows[2]!.get('details').attributes('open')).toBeUndefined();
        expect(rows[2]!.get('summary').text()).toBe('Recovery details');
        expect(rows[2]!.text()).toContain('Completed record requires recovery review');
        expect(rows[2]!.text()).toContain('Minted-token recovery material retained');
        expect(rows[2]!.findAll('button')).toHaveLength(0);
        expect(rows[0]!.text()).toContain('including HTTP 400');
        expect(rows[0]!.text()).toContain('Repeated retry may remain refused');
        expect(rows[0]!.text()).toContain('original mint');
        expect(wrapper.get('tbody').findAll('button')).toHaveLength(2);
        expect(api.post).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it.each([
        {}, { failed_payments: null }, { failed_payments: '2' }, { other_non_terminal_payments: -1 },
        { terminal_recovery_payments: 1.5 }, { failed_payments: 2147483648 },
        { unresolved_payments: 4 }, { unresolved_payments: null },
    ])('shows an entirely unknown breakdown for incomplete or malformed partition %j', change => {
        const safety = Object.keys(change).length ? { ...paymentHolds, ...change } : blocked;
        const wrapper = mount(CashuWalletSafetySummary, { props: { safety } });
        expect(wrapper.find('[data-testid=cashu-safety-payment-breakdown]').exists()).toBe(false);
        expect(wrapper.get('[data-testid=cashu-safety-breakdown-unknown]').text()).toBe('The payment-hold breakdown is unknown.');
        expect(wrapper.get('[data-testid=cashu-wallet-safety-status]').text()).toBe('Cashu is blocking wallet changes.');
        expect(wrapper.findAll('button')).toHaveLength(0); wrapper.unmount();
    });

    it('accepts an explicit valid zero partition without fabricating unknown categories', () => {
        const wrapper = mount(CashuWalletSafetySummary, { props: { safety: { ...paymentHolds, unresolved_payments: 0, failed_payments: 0, terminal_recovery_payments: 0 } } });
        expect(wrapper.get('[data-testid=cashu-safety-failed-count]').text()).toBe('0');
        expect(wrapper.get('[data-testid=cashu-safety-terminal-count]').text()).toBe('0');
        expect(wrapper.get('[data-testid=cashu-wallet-safety-status]').text()).toBe('Cashu is blocking wallet changes.'); wrapper.unmount();
    });

    it.each([
        {}, { blocks_wallet_change: 'false', recovery_phase: 'private-phase-marker', wallet_change_block_reasons: ['private-reason-marker'] },
        { wallet_change_block_reasons: ['non_terminal_settlement', 'non_terminal_settlement'] },
        { wallet_change_block_reasons: 'non_terminal_settlement' },
        { wallet_change_block_reasons: ['non_terminal_settlement', 'retained_minted_proofs', 'retained_issuance_intent', 'retained_change_outputs', 'retained_payout_quote', 'non_terminal_settlement'] },
    ])('treats missing or malformed row diagnostics as unknown and does not echo metadata %j', metadata => {
        return (async () => {
            const diagnostics: Record<string, unknown> = { blocks_wallet_change: true, ...metadata };
            api.get.mockResolvedValue(response(paymentHolds, [{ quote_id: 'legacy', settlement_state: 'SETTLED', ...diagnostics }]));
            const wrapper = open(); await flushPromises();
            const details = wrapper.get('[data-testid=cashu-payment-diagnostics]');
            if (diagnostics.blocks_wallet_change === true) {
                expect(details.get('[data-testid=cashu-payment-hold]').text()).toBe('Blocks wallet changes');
                expect(details.text()).toContain('Recovery stage: Unknown');
                expect(details.text()).toContain('Hold reasons are unknown.');
            } else {
                expect(details.get('[data-testid=cashu-payment-hold]').text()).toBe('Wallet-change hold unknown');
                expect(details.find('details').exists()).toBe(false);
            }
            expect(details.text()).not.toContain('private-phase-marker'); expect(details.text()).not.toContain('private-reason-marker');
            expect(wrapper.get('tbody').findAll('button')).toHaveLength(0); expect(api.post).not.toHaveBeenCalled(); wrapper.unmount();
        })();
    });

    it.each([
        ['SETTLED', true, false], ['EXPIRED_UNPAID', true, false], ['FAILED', false, true], ['PENDING', null, true],
    ] as const)('keeps existing %s actions independent of diagnostic flag %j', async (settlement_state, blocks_wallet_change, hasRetry) => {
        api.get.mockResolvedValue(response(paymentHolds, [{ quote_id: 'original', settlement_state, blocks_wallet_change, wallet_change_block_reasons: [], recovery_phase: 'complete' }]));
        api.post.mockResolvedValue({ data: { data: { settled: false } } });
        const wrapper = open(); await flushPromises();
        const row = wrapper.get('tbody');
        expect(row.findAll('button')).toHaveLength(hasRetry ? 1 : 0);
        expect(row.get('[data-testid=cashu-payment-hold]').text()).toBe(blocks_wallet_change === true ? 'Blocks wallet changes' : blocks_wallet_change === false ? 'No wallet-change hold reported for this record' : 'Wallet-change hold unknown');
        expect(api.post).not.toHaveBeenCalled();
        if (hasRetry) {
            await row.get('button').trigger('click'); await flushPromises();
            expect(api.post.mock.calls).toEqual([['/stores/store-a/cashu/payments/original/retry']]);
        }
        wrapper.unmount();
    });

    it.each([false, undefined])('keeps clear or legacy unknown rows compact without recovery disclosures (%j)', async blocks_wallet_change => {
        api.get.mockResolvedValue(response(paymentHolds, [{ quote_id: 'compact', settlement_state: 'SETTLED', blocks_wallet_change, wallet_change_block_reasons: ['retained_minted_proofs'], recovery_phase: 'funding_verification' }]));
        const wrapper = open(); await flushPromises();
        const diagnostics = wrapper.get('[data-testid=cashu-payment-diagnostics]');
        expect(diagnostics.find('details').exists()).toBe(false);
        expect(diagnostics.findAll('p, ul')).toHaveLength(0);
        expect(diagnostics.get('[data-testid=cashu-payment-hold]').text()).toBe(blocks_wallet_change === false ? 'No wallet-change hold reported for this record' : 'Wallet-change hold unknown');
        expect(wrapper.get('[data-testid=cashu-wallet-safety]').text()).toContain('not proof of funds or permission');
        expect(api.post).not.toHaveBeenCalled(); wrapper.unmount();
    });

});
