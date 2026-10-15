import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import StoreCashuPayments from '../components/stores/StoreCashuPayments.vue';

const api = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('../services/api', () => ({ default: api }));
vi.mock('../store/auth', () => ({ useAuthStore: () => ({ user: { can_use_password_login: true } }) }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
const legacy = { quote_id: 'opaque-quote', invoice_id: 'invoice-id', settlement_state: 'MANUAL_REVIEW', original_settings_required: true };
const open = () => mount(StoreCashuPayments, { props: { store: { id: 'store-a' } }, global: { stubs: { Select: true, CashuChangeRecovery: true } } });

describe('Cashu merchant recovery', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    api.get.mockImplementation(async (url: string) => url.endsWith('/settings')
      ? { data: { data: { mint_url: 'https://original.example', lightning_address: 'merchant@linky.example' } } }
      : { data: { data: { items: [legacy], total: 1 } } });
  });

  it('selects the existing quote, requires consent, confirms then retries once', async () => {
    api.post.mockImplementation(async (url: string) => ({ data: { data: url.endsWith('/confirm-original') ? { confirmed: true } : { settled: true } } }));
    const wrapper = open(); await flushPromises();
    await wrapper.get('[data-testid=cashu-recover]').trigger('click'); await flushPromises();
    expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeDefined();
    expect(wrapper.text()).toContain('stores.cashu_recovery_suggestions');
    await wrapper.get('#cashu-recovery-password').setValue('password');
    await wrapper.get('input[type=checkbox]').setValue(true);
    await wrapper.get('form').trigger('submit'); await flushPromises();
    expect(api.post.mock.calls).toEqual([
      ['/stores/store-a/cashu/payments/opaque-quote/confirm-original', { mint_url: 'https://original.example', lightning_address: 'merchant@linky.example', confirmed_original: true, password: 'password' }],
      ['/stores/store-a/cashu/payments/opaque-quote/retry'],
    ]);
    expect(wrapper.find('form').exists()).toBe(false);
    expect(wrapper.text()).toContain('stores.cashu_retry_settled'); wrapper.unmount();
  });

  it('keeps the recovery form and does not retry when the plugin rejects the mint', async () => {
    api.post.mockRejectedValue({ response: { data: { errors: { recovery: ['Wrong original mint'] } } } });
    const wrapper = open(); await flushPromises();
    await wrapper.get('[data-testid=cashu-recover]').trigger('click'); await flushPromises();
    await wrapper.get('#cashu-recovery-password').setValue('password');
    await wrapper.get('input[type=checkbox]').setValue(true);
    await wrapper.get('form').trigger('submit'); await flushPromises();
    expect(api.post).toHaveBeenCalledTimes(1);
    expect(wrapper.get('[role=alert]').text()).toBe('Wrong original mint');
    expect((wrapper.get('#cashu-recovery-password').element as HTMLInputElement).value).toBe(''); wrapper.unmount();
  });

  it('does not retry if confirmation was not acknowledged', async () => {
    api.post.mockResolvedValue({ data: { data: { confirmed: false } } });
    const wrapper = open(); await flushPromises();
    await wrapper.get('[data-testid=cashu-recover]').trigger('click'); await flushPromises();
    await wrapper.get('#cashu-recovery-password').setValue('password');
    await wrapper.get('input[type=checkbox]').setValue(true);
    await wrapper.get('form').trigger('submit'); await flushPromises();
    expect(api.post).toHaveBeenCalledTimes(1);
    expect(wrapper.get('[role=alert]').text()).toBe('stores.cashu_recovery_unconfirmed'); wrapper.unmount();
  });

  it('does not retry a different store when the user switches during confirmation', async () => {
    let resolve!: (value: unknown) => void;
    api.post.mockImplementation(() => new Promise(r => { resolve = r; }));
    const wrapper = open(); await flushPromises();
    await wrapper.get('[data-testid=cashu-recover]').trigger('click'); await flushPromises();
    await wrapper.get('#cashu-recovery-password').setValue('password');
    await wrapper.get('input[type=checkbox]').setValue(true);
    await wrapper.get('form').trigger('submit');
    await wrapper.get('form').trigger('submit');
    expect(api.post).toHaveBeenCalledTimes(1);
    await wrapper.setProps({ store: { id: 'store-b' } }); await flushPromises();
    resolve({ data: { data: { confirmed: true } } }); await flushPromises();
    expect(api.post).toHaveBeenCalledTimes(1);
    expect(wrapper.find('form').exists()).toBe(false); wrapper.unmount();
  });

  it('shows settlement errors returned by a successful retry HTTP response', async () => {
    api.get.mockResolvedValue({ data: { data: { items: [{ ...legacy, settlement_state: 'PENDING', original_settings_required: false }], total: 1 } } });
    api.post.mockResolvedValue({ data: { data: { settled: false, error: 'Mint unavailable' } } });
    const wrapper = open(); await flushPromises();
    await wrapper.findAll('button').find(b => b.text() === 'stores.cashu_payments_retry')!.trigger('click'); await flushPromises();
    expect(wrapper.text()).toContain('Mint unavailable'); wrapper.unmount();
  });
});
