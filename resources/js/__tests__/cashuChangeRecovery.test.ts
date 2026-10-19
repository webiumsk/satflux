import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CashuChangeRecovery from '../components/stores/wallet-connection/CashuChangeRecovery.vue';
import StoreCashuPayments from '../components/stores/StoreCashuPayments.vue';

const mocks = vi.hoisted(() => ({ summary: vi.fn(), request: vi.fn(), resend: vi.fn(), export: vi.fn(), verify: vi.fn(), payments: vi.fn(), paymentPost: vi.fn(), createUrl: vi.fn(), revokeUrl: vi.fn(), user: { can_use_password_login: false } }));
vi.mock('../services/api', () => ({ default: { get: mocks.payments, post: mocks.paymentPost }, walletApi: { cashu: { change: { getSummary: mocks.summary, requestConfirmation: mocks.request, resendConfirmation: mocks.resend, exportTokens: mocks.export, verify: mocks.verify } } } }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: mocks.user }) }));
vi.mock('vue-i18n', async () => {
    const { default: en } = await import('../locales/en.json');
    return { useI18n: () => ({ t: (key: string, params: Record<string, unknown> = {}) => {
        const text = (en.stores as unknown as Record<string, string>)[key.replace(/^stores\./, '')] ?? key;
        return Object.entries(params).reduce((value, [name, replacement]) => value.split(`{${name}}`).join(String(replacement)), text);
    } }) };
});
const summary = { version: 1, status: 'available', can_recover: true, pending_sat: '5', swept_sat: '0', minimum_sweep_sat: '100', unresolved_records: 1, available_records: 1, exported_records: 0, sweeping_records: 0, manual_review_records: 0, unsupported_unit_records: 0, other_unresolved_records: 0 };
const challenge = { purpose: 'cashu_change_recovery', email: 'owner@example.test', expires_at: '2099-01-01T00:00:00Z', resend_available_at: '2000-01-01T00:00:00Z', attempts_left: 5, sends_left: 3 };
const global = { stubs: { RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' }, Select: true } };
const originalUrl = URL;
const open = () => mount(CashuChangeRecovery, { props: { storeId: 'store-a' }, global });
const wrappers: Array<ReturnType<typeof open>> = [];
const panel = async () => { const wrapper = open(); wrappers.push(wrapper); await flushPromises(); return wrapper; };
const begin = async (wrapper: Pick<ReturnType<typeof open>, 'get' | 'findAll'>, action = 'export') => {
    await wrapper.get(`[data-testid=cashu-change-${action}]`).trigger('click');
    await wrapper.get('[data-testid=cashu-change-consent]').setValue(true);
    if (mocks.user.can_use_password_login) await wrapper.get('#cashu-change-password').setValue('synthetic-password');
    await wrapper.get('form').trigger('submit'); await flushPromises();
};
const code = async (wrapper: Pick<ReturnType<typeof open>, 'findAll'>, value = '123456') => {
    const inputs = wrapper.findAll('input[autocomplete=one-time-code]');
    for (let index = 0; index < 6; index++) await inputs[index]!.setValue(value[index]);
    await flushPromises();
};

beforeEach(() => {
    vi.resetAllMocks(); mocks.user.can_use_password_login = false;
    mocks.summary.mockResolvedValue(summary); mocks.request.mockResolvedValue(challenge); mocks.resend.mockResolvedValue({ ...challenge, sends_left: 2 });
    mocks.export.mockResolvedValue(new Blob(['["synthetic-export-only-marker"]'], { type: 'application/json' })); mocks.verify.mockResolvedValue(true);
    mocks.createUrl.mockReturnValue('blob:synthetic-download');
    vi.stubGlobal('URL', class extends originalUrl { static createObjectURL = mocks.createUrl; static revokeObjectURL = mocks.revokeUrl; });
    vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
    mocks.payments.mockResolvedValue({ data: { data: { total: 1, items: [{ quote_id: 'quote-a', settlement_state: 'SETTLED', reported_settlement_state: 'SETTLED' }], wallet_safety: { status: 'blocked', unresolved_payments: 0, unresolved_change: 1 } } } });
});
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); vi.useRealTimers(); vi.restoreAllMocks(); vi.unstubAllGlobals(); });

describe('Cashu change recovery panel', () => {
    it('explains a five-sat balance below the hundred-sat sweep threshold', async () => {
        const wrapper = await panel();
        expect(wrapper.get('[data-testid=cashu-change-pending]').text()).toBe('5 sat');
        expect(wrapper.get('[data-testid=cashu-change-small-balance]').text()).toContain('5 sat below the 100 sat');
        expect(wrapper.get('[data-testid=cashu-change-export]').attributes('disabled')).toBeUndefined();
        expect(wrapper.get('[data-testid=cashu-change-verify]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('a').attributes('href')).toBe('/documentation/cashu-change-recovery');
        expect(mocks.request).not.toHaveBeenCalled(); expect(mocks.export).not.toHaveBeenCalled();
    });

    it('preserves a signed-64-bit amount string exactly', async () => {
        mocks.summary.mockResolvedValue({ ...summary, pending_sat: '9223372036854775807' });
        const wrapper = await panel(); expect(wrapper.get('[data-testid=cashu-change-pending]').text()).toBe('9223372036854775807 sat');
    });

    it.each([
        { ...summary, status: 'unsupported', version: null, can_recover: false, pending_sat: null },
        { ...summary, status: 'unavailable', version: null, can_recover: false, pending_sat: null },
        { ...summary, pending_sat: '05' },
        { ...summary, pending_sat: 5 },
        { ...summary, pending_sat: '9223372036854775808' },
        { ...summary, available_records: '1' },
    ])('does not invent zero balances or recovery capability from unsupported/malformed evidence: %j', async value => {
        mocks.summary.mockResolvedValue(value); const wrapper = await panel();
        expect(wrapper.find('[data-testid=cashu-change-export]').exists()).toBe(false);
        expect(wrapper.find('[data-testid=cashu-change-pending]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('0 sat'); expect(mocks.request).not.toHaveBeenCalled();
    });

    it('keeps admin/support summary read-only when can_recover is false', async () => {
        mocks.summary.mockResolvedValue({ ...summary, can_recover: false });
        const wrapper = await panel(); expect(wrapper.find('[data-testid=cashu-change-owner-only]').exists()).toBe(true);
        expect(wrapper.find('[data-testid=cashu-change-export]').exists()).toBe(false); expect(mocks.request).not.toHaveBeenCalled();
    });

    it.each([
        { ...summary, pending_sat: '0', unresolved_records: 0, available_records: 0 },
        { ...summary, available_records: 0, sweeping_records: 1 },
        { ...summary, available_records: 0, pending_sat: '0', unsupported_unit_records: 1 },
    ])('does not request an export code without eligible records: %j', async value => {
        mocks.summary.mockResolvedValue(value); const wrapper = await panel();
        expect(wrapper.get('[data-testid=cashu-change-export]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-testid=cashu-change-verify]').attributes('disabled')).toBeDefined();
        await wrapper.get('[data-testid=cashu-change-export]').trigger('click');
        expect(wrapper.find('form').exists()).toBe(false); expect(mocks.request).not.toHaveBeenCalled();
        if (value.unresolved_records === 0) expect(wrapper.get('[data-testid=cashu-change-empty]').text()).toContain('checked separately');
    });

    it('requires consent, password where applicable, and an action-specific code before exporting', async () => {
        mocks.user.can_use_password_login = true; const wrapper = await panel();
        await wrapper.get('[data-testid=cashu-change-export]').trigger('click');
        expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeDefined();
        await wrapper.get('[data-testid=cashu-change-consent]').setValue(true);
        expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeDefined();
        await wrapper.get('#cashu-change-password').setValue('synthetic-password'); await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(mocks.request).toHaveBeenCalledWith('store-a', 'export', { password: 'synthetic-password' }, expect.any(AbortSignal));
        expect(wrapper.find('#cashu-change-password').exists()).toBe(false);
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(6); expect(mocks.export).not.toHaveBeenCalled();
        await code(wrapper);
        expect(mocks.export).toHaveBeenCalledWith('store-a', '123456', expect.any(AbortSignal));
        expect(wrapper.text()).toContain('Export alone does not unblock wallet changes.');
        expect(wrapper.emitted('changed')).toHaveLength(1);
    });

    it('downloads only through a temporary URL with the fixed filename and no token parsing/storage/preview', async () => {
        const blob = new Blob(['["synthetic-export-only-marker"]'], { type: 'application/json' });
        const text = vi.fn(); Object.defineProperty(blob, 'text', { value: text }); mocks.export.mockResolvedValue(blob);
        const storage = vi.spyOn(Storage.prototype, 'setItem');
        let filename = ''; vi.mocked(HTMLAnchorElement.prototype.click).mockImplementation(function (this: HTMLAnchorElement) { filename = this.download; });
        const wrapper = await panel(); await begin(wrapper); await code(wrapper);
        expect(filename).toBe('cashumelt-change.json'); expect(text).not.toHaveBeenCalled(); expect(storage).not.toHaveBeenCalled();
        expect(wrapper.html()).not.toContain('synthetic-export-only-marker'); expect(document.querySelector('a[download]')).toBeNull();
        expect(mocks.createUrl).toHaveBeenCalledWith(blob); wrapper.unmount();
        expect(mocks.revokeUrl).toHaveBeenCalledWith('blob:synthetic-download');
    });

    it('revokes a completed download URL after its short lifetime', async () => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        const wrapper = await panel(); await begin(wrapper); await code(wrapper);
        await vi.advanceTimersByTimeAsync(1000); expect(mocks.revokeUrl).toHaveBeenCalledWith('blob:synthetic-download');
    });

    it('uses a fresh verify challenge and refreshes change plus safety without claiming all holds cleared', async () => {
        mocks.summary.mockResolvedValue({ ...summary, available_records: 0, exported_records: 1 });
        const wrapper = await panel(); await begin(wrapper, 'verify'); await code(wrapper);
        expect(mocks.request).toHaveBeenCalledWith('store-a', 'verify', {}, expect.any(AbortSignal));
        expect(mocks.verify).toHaveBeenCalledWith('store-a', '123456', expect.any(AbortSignal));
        expect(mocks.summary).toHaveBeenCalledTimes(2); expect(wrapper.emitted('changed')).toHaveLength(1);
        expect(wrapper.text()).toContain('Other records may still block changes.'); expect(mocks.export).not.toHaveBeenCalled();
        await begin(wrapper, 'verify'); expect(mocks.request).toHaveBeenCalledTimes(2);
    });

    it('keeps a wrong code retryable but never repeats the provider operation automatically', async () => {
        mocks.export.mockRejectedValueOnce({ response: { status: 422, data: { code: 'code_mismatch', message: 'private-response-marker' } } });
        const wrapper = await panel(); await begin(wrapper); await code(wrapper);
        expect(wrapper.get('[role=alert]').text()).toContain('The code does not match.');
        expect(wrapper.text()).not.toContain('private-response-marker'); expect(mocks.export).toHaveBeenCalledTimes(1);
        await wrapper.findAll('input[autocomplete=one-time-code]')[0]!.setValue('2'); await flushPromises();
        expect(mocks.export).toHaveBeenCalledTimes(2);
    });

    it('discards an uncertain authorization and requires a fresh code, exposing no raw transport details', async () => {
        mocks.export.mockRejectedValue({ response: { status: 502, data: { code: 'cashu_change_unavailable', message: 'private-response-marker' } }, config: { data: 'private-request-marker' } });
        const wrapper = await panel(); await begin(wrapper); await code(wrapper);
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(0);
        expect(wrapper.get('[role=alert]').text()).toContain('The result could not be confirmed.');
        expect(wrapper.html()).not.toContain('private-response-marker'); expect(wrapper.html()).not.toContain('private-request-marker');
        expect(mocks.export).toHaveBeenCalledTimes(1); expect(mocks.createUrl).not.toHaveBeenCalled();
        await begin(wrapper); expect(mocks.request).toHaveBeenCalledTimes(2); expect(mocks.export).toHaveBeenCalledTimes(1);
    });

    it('resends only the current action challenge', async () => {
        const wrapper = await panel(); await begin(wrapper);
        await wrapper.findAll('button').find(button => button.text() === 'account.email_code_resend')!.trigger('click'); await flushPromises();
        expect(mocks.resend).toHaveBeenCalledWith('store-a', 'export', expect.any(AbortSignal)); expect(mocks.export).not.toHaveBeenCalled();
    });

    it('ignores an export response after A-B-A navigation and aborts obsolete requests', async () => {
        let resolve!: (value: Blob) => void; mocks.export.mockImplementation(() => new Promise(r => { resolve = r; }));
        const wrapper = await panel(); await begin(wrapper); await code(wrapper);
        const signal = mocks.export.mock.calls[0]![2] as AbortSignal;
        await wrapper.setProps({ storeId: 'store-b' }); await flushPromises(); await wrapper.setProps({ storeId: 'store-a' }); await flushPromises();
        resolve(new Blob(['["obsolete-export-marker"]'], { type: 'application/json' })); await flushPromises();
        expect(signal.aborted).toBe(true); expect(mocks.createUrl).not.toHaveBeenCalled(); expect(wrapper.emitted('changed')).toBeUndefined();
        expect(wrapper.findAll('input[autocomplete=one-time-code]')).toHaveLength(0);
    });

    it('ignores export completion after unmount', async () => {
        let resolve!: (value: Blob) => void; mocks.export.mockImplementation(() => new Promise(r => { resolve = r; }));
        const wrapper = await panel(); await begin(wrapper); await code(wrapper); wrapper.unmount();
        resolve(new Blob(['["obsolete-export-marker"]'], { type: 'application/json' })); await flushPromises();
        expect(mocks.createUrl).not.toHaveBeenCalled(); expect(wrapper.emitted('changed')).toBeUndefined();
    });

    it('keeps real payment history visible while the separate change capability is unsupported', async () => {
        mocks.summary.mockResolvedValue({ status: 'unsupported', can_recover: false });
        const wrapper = mount(StoreCashuPayments, { props: { store: { id: 'store-a' } }, global });
        await flushPromises();
        expect(wrapper.get('tbody').text()).toContain('Settled'); expect(wrapper.text()).toContain('CashuMelt 1.4.0.4');
        expect(mocks.paymentPost).not.toHaveBeenCalled(); wrapper.unmount();
    });

    it('refreshes actual payment safety after the integrated recovery operation', async () => {
        const wrapper = mount(StoreCashuPayments, { props: { store: { id: 'store-a' } }, global }); await flushPromises();
        const recovery = wrapper.getComponent(CashuChangeRecovery);
        await begin(recovery); await code(recovery);
        expect(mocks.summary).toHaveBeenCalledTimes(2); expect(mocks.payments).toHaveBeenCalledTimes(2);
        expect(wrapper.get('[data-testid=cashu-wallet-safety-status]').text()).toContain('Cashu is blocking wallet changes.');
        expect(mocks.paymentPost).not.toHaveBeenCalled(); wrapper.unmount();
    });
});
