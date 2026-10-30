import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import CrowdfundForm from '../pages/stores/CrowdfundForm.vue';

const mocks = vi.hoisted(() => ({ updateApp: vi.fn(), success: vi.fn(), error: vi.fn() }));
vi.mock('../store/apps', () => ({ useAppsStore: () => mocks }));
vi.mock('../store/flash', () => ({ useFlashStore: () => mocks }));
vi.mock('../services/api', () => ({ default: {} }));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: { value: 'en' } }) }));

enableAutoUnmount(afterEach);
beforeEach(() => {
  vi.clearAllMocks();
  mocks.updateApp.mockResolvedValue(undefined);
});

async function crowdfund(period = 'Day', amount = 5) {
  const wrapper = mount(CrowdfundForm, {
    props: {
      app: { id: 'synthetic-app', name: 'Synthetic campaign', config: { description: '<p>Campaign</p>', resetEvery: period, resetEveryAmount: amount } },
      store: { id: 'local-store', default_currency: 'EUR' },
    } as never,
    global: { stubs: { Select: true, RichTextEditor: true, PerkEditDrawer: true, AdditionalOptions: true, DatePicker: true } },
  });
  await flushPromises();
  return wrapper;
}

describe('Crowdfund recurring interval form contract', () => {
  it.each([['Day', 5], ['Week', 2]])('preserves %s %s as an integer before server normalization', async (period, amount) => {
    const wrapper = await crowdfund(period, amount);
    await wrapper.get('#crowdfund-form').trigger('submit');
    await flushPromises();
    expect(mocks.updateApp).toHaveBeenCalledWith('local-store', 'synthetic-app', expect.objectContaining({
      config: expect.objectContaining({ resetEvery: period, resetEveryAmount: amount, startDate: expect.any(Number) }),
    }));
  });

  it.each([0, -1, 1.5])('rejects a non-positive or fractional interval %s without clamping or saving', async amount => {
    const wrapper = await crowdfund();
    await wrapper.get('#resetEveryAmount').setValue(amount);
    await wrapper.get('#crowdfund-form').trigger('submit');
    await flushPromises();
    expect(mocks.updateApp).not.toHaveBeenCalled();
    expect(mocks.error).toHaveBeenCalledWith('stores.crowdfund_reset_interval_invalid');
    expect((wrapper.get('#resetEveryAmount').element as HTMLInputElement).value).toBe(String(amount));
  });

  it('keeps a rejected interval draft and shows localized server date-bound errors', async () => {
    const wrapper = await crowdfund('Year', 10000);
    mocks.updateApp.mockRejectedValueOnce({ response: { status: 422, data: { errors: { 'config.startDate': ['Synthetic provider date error'] } } } });
    await wrapper.get('#crowdfund-form').trigger('submit');
    await flushPromises();
    expect(mocks.error).toHaveBeenCalledWith('stores.crowdfund_reset_interval_invalid');
    expect(mocks.success).not.toHaveBeenCalled();
    expect((wrapper.get('#resetEveryAmount').element as HTMLInputElement).value).toBe('10000');
    await wrapper.get('#resetEveryAmount').setValue(2);
    await wrapper.get('#crowdfund-form').trigger('submit');
    await flushPromises();
    expect(mocks.updateApp).toHaveBeenCalledTimes(2);
    expect(mocks.success).toHaveBeenCalled();
  });
});
