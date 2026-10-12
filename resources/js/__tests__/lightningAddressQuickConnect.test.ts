import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LightningAddressQuickConnect from '../components/stores/wallet-connection/LightningAddressQuickConnect.vue';

const api = vi.hoisted(() => ({ probe: vi.fn(), update: vi.fn(), create: vi.fn() }));
vi.mock('../services/api', () => ({ walletApi: { connection: { lnaddressProbe: api.probe, create: api.create }, cashu: { updateSettings: api.update } } }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));

describe('Lightning address onboarding', () => {
  beforeEach(() => vi.resetAllMocks());
  const open = () => mount(LightningAddressQuickConnect, { props: { storeId: 'local-store', initialAddress: 'alice@linky.fit' }, global: { stubs: { WalletTypeIcon: true } } });

  it.each(['unreachable', 'invalid_lnurlp', 'invalid_verify'])('keeps the existing method on %s and permits retry', async (reason) => {
    api.probe.mockResolvedValue({ lud21: false, reason });
    const wrapper = open();
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(wrapper.text()).toContain('stores.ln_quick_probe_retry');
    expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    expect(api.update).not.toHaveBeenCalled();
    expect(api.create).not.toHaveBeenCalled();
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(api.probe).toHaveBeenCalledTimes(2);
    wrapper.unmount();
  });

  it('requires explicit consent before making Cashu the primary method', async () => {
    api.probe.mockResolvedValue({ lud21: false, reason: 'no_verify' });
    api.update.mockResolvedValue({});
    const wrapper = open();
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(api.update).not.toHaveBeenCalled();
    await wrapper.get('input[type=checkbox]').setValue(true);
    await wrapper.get('button').trigger('click');
    await flushPromises();
    expect(api.update).toHaveBeenCalledWith('local-store', expect.objectContaining({ lightning_address: 'alice@linky.fit', enabled: true }));
    wrapper.unmount();
  });
});
