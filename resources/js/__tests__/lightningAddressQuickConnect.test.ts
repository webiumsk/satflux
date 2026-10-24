import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LightningAddressQuickConnect from '../components/stores/wallet-connection/LightningAddressQuickConnect.vue';

const api = vi.hoisted(() => ({ probe: vi.fn(), update: vi.fn(), create: vi.fn() }));
vi.mock('../services/api', () => ({ walletApi: { connection: { lnaddressProbe: api.probe, create: api.create }, cashu: { updateSettings: api.update } } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string, params?: { mint?: string }) => params?.mint ? `${key} ${params.mint}` : key }) }));

const mint = 'https://merchant-mint.example.test/Bitcoin';
const unsupported = (overrides: Record<string, unknown> = {}) => ({
  lud21: false, reason: 'no_verify', outcome: 'unsupported', code: 'lud21_unsupported',
  alternatives: [{ type: 'cashu', available: true, blocked_reason: null, mint_url: mint, requires_consent: true }],
  ...overrides,
});
const supported = { lud21: true, reason: 'ok', outcome: 'supported', code: 'lud21_supported', alternatives: [] };
const deferred = () => {
  let resolve!: (value: unknown) => void;
  const promise = new Promise((r) => { resolve = r; });
  return { promise, resolve };
};

describe('Lightning address onboarding', () => {
  beforeEach(() => vi.resetAllMocks());
  const open = (address = 'alice@coinos.io') => mount(LightningAddressQuickConnect, {
    props: { storeId: 'local-store', initialAddress: address },
    global: { stubs: { WalletTypeIcon: true } },
  });
  const submit = async (wrapper: ReturnType<typeof open>) => {
    await wrapper.get('button').trigger('click');
    await flushPromises();
  };

  it.each(['unreachable', 'invalid_lnurlp', 'invalid_verify'])('keeps the existing method on %s and permits retry', async (reason) => {
    api.probe.mockResolvedValue({ lud21: false, reason, outcome: 'unavailable', alternatives: [] });
    const wrapper = open();
    await submit(wrapper);
    expect(wrapper.text()).toContain('stores.ln_quick_probe_retry');
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    expect(api.update).not.toHaveBeenCalled();
    expect(api.create).not.toHaveBeenCalled();
    await submit(wrapper);
    expect(api.probe).toHaveBeenCalledTimes(2);
    wrapper.unmount();
  });

  it('probes a legacy Coinos address before saving a native connection', async () => {
    api.probe.mockResolvedValue(supported);
    api.create.mockResolvedValue({});
    const wrapper = open('legacy@coinos.io');
    await submit(wrapper);
    expect(api.probe).toHaveBeenCalledWith('local-store', 'legacy@coinos.io');
    expect(api.create).toHaveBeenCalledWith('local-store', { type: 'lnaddress', secret: 'type=lnaddress;ln-address=legacy@coinos.io;' });
    expect(api.update).not.toHaveBeenCalled();
    expect(wrapper.emitted('submitted')).toEqual([['lnaddress']]);
    wrapper.unmount();
  });

  it('offers Cashu for a v3 Coinos address only after probing and explicit consent, preserving its mint', async () => {
    api.probe.mockResolvedValue(unsupported());
    api.update.mockResolvedValue({});
    const wrapper = open();
    await submit(wrapper);
    expect(api.probe).toHaveBeenCalledWith('local-store', 'alice@coinos.io');
    expect(api.update).not.toHaveBeenCalled();
    expect(api.create).not.toHaveBeenCalled();
    expect(wrapper.text()).toContain(mint);
    expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    await wrapper.get('input[type=checkbox]').setValue(true);
    await submit(wrapper);
    expect(api.update).toHaveBeenCalledWith('local-store', { mint_url: mint, lightning_address: 'alice@coinos.io', enabled: true });
    expect(wrapper.emitted('submitted')).toEqual([['cashu']]);
    wrapper.unmount();
  });

  it.each([
    { alternatives: [] },
    { alternatives: [{ type: 'cashu', available: false, blocked_reason: 'plugin_unavailable', mint_url: mint, requires_consent: true }] },
    { alternatives: [{ type: 'cashu', available: true, mint_url: null, requires_consent: true }] },
    { alternatives: [{ type: 'cashu', available: true, mint_url: 'http://mint.example.test', requires_consent: true }] },
    { alternatives: [{ type: 'cashu', available: true, mint_url: mint, requires_consent: false }] },
  ])('does not offer an unavailable or incomplete Cashu alternative %#', async (overrides) => {
    api.probe.mockResolvedValue(unsupported(overrides));
    const wrapper = open();
    await submit(wrapper);
    expect(wrapper.text()).toContain('stores.ln_quick_cashu_unavailable');
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    expect(api.update).not.toHaveBeenCalled();
    expect(api.create).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('requires an authoritative capability outcome rather than a generic no_verify response', async () => {
    api.probe.mockResolvedValue(unsupported({ outcome: 'unavailable' }));
    const wrapper = open();
    await submit(wrapper);
    expect(wrapper.text()).toContain('stores.ln_quick_probe_retry');
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    wrapper.unmount();
  });

  it('discards a late probe after the address changes', async () => {
    const pending = deferred();
    api.probe.mockReturnValue(pending.promise);
    const wrapper = open();
    await wrapper.get('button').trigger('click');
    await wrapper.get('input[type=text]').setValue('other@coinos.io');
    pending.resolve(unsupported());
    await flushPromises();
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    expect(api.update).not.toHaveBeenCalled();
    expect(api.create).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it.each(['address', 'store'])('invalidates a previous Cashu verdict and consent after a %s change', async (change) => {
    api.probe.mockResolvedValue(unsupported());
    const wrapper = open();
    await submit(wrapper);
    await wrapper.get('input[type=checkbox]').setValue(true);
    if (change === 'address') await wrapper.get('input[type=text]').setValue('other@coinos.io');
    else await wrapper.setProps({ storeId: 'other-store' });
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    await submit(wrapper);
    expect(api.probe).toHaveBeenCalledTimes(2);
    expect(api.update).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('does not apply an old store response after switching away and back', async () => {
    const pending = deferred();
    api.probe.mockReturnValue(pending.promise);
    const wrapper = open();
    await wrapper.get('button').trigger('click');
    await wrapper.setProps({ storeId: 'other-store' });
    await wrapper.setProps({ storeId: 'local-store' });
    pending.resolve(supported);
    await flushPromises();
    expect(api.create).not.toHaveBeenCalled();
    expect(api.update).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('offers a fresh explicit Cashu consent when save-time revalidation rejects LUD-21', async () => {
    api.probe.mockResolvedValue(supported);
    api.create.mockRejectedValue({ response: { status: 422, data: unsupported() } });
    const wrapper = open();
    await submit(wrapper);
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(true);
    expect(api.update).not.toHaveBeenCalled();
    expect(wrapper.get<HTMLInputElement>('input[type=text]').element.value).toBe('alice@coinos.io');
    await wrapper.get('input[type=checkbox]').setValue(true);
    await submit(wrapper);
    expect(api.update).toHaveBeenCalledWith('local-store', expect.objectContaining({ mint_url: mint }));
    wrapper.unmount();
  });

  it('does not switch payment method after an uncertain native write', async () => {
    api.probe.mockResolvedValue(supported);
    api.create.mockRejectedValue({ request: {}, message: 'Network Error' });
    const wrapper = open();
    await submit(wrapper);
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    expect(api.update).not.toHaveBeenCalled();
    expect(wrapper.emitted('submitted')).toBeUndefined();
    wrapper.unmount();
  });

  it('ignores a late probe after the form is unmounted', async () => {
    const pending = deferred();
    api.probe.mockReturnValue(pending.promise);
    const wrapper = open();
    await wrapper.get('button').trigger('click');
    wrapper.unmount();
    pending.resolve(supported);
    await flushPromises();
    expect(api.create).not.toHaveBeenCalled();
    expect(api.update).not.toHaveBeenCalled();
  });
});
