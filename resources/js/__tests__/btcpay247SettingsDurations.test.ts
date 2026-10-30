import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import StoreSettings from '../components/stores/StoreSettings.vue';

const mocks = vi.hoisted(() => ({
  editable: true,
  get: vi.fn(),
  update: vi.fn(),
  clear: vi.fn(),
  success: vi.fn(),
  error: vi.fn(),
}));
vi.mock('vue-i18n', async importOriginal => ({ ...(await importOriginal<typeof import('vue-i18n')>()), useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('vue-router', () => ({ useRouter: () => ({}), useRoute: () => ({ query: {} }) }));
vi.mock('../composables/useEffectiveAccess', () => ({ useEffectiveAccess: () => ({ hasFeature: () => mocks.editable }) }));
vi.mock('../store/stores', () => ({ useStoresStore: () => ({}) }));
vi.mock('../store/flash', () => ({ useFlashStore: () => mocks }));
vi.mock('../services/api', () => ({ storesApi: { settings: { get: mocks.get, update: mocks.update } } }));

vi.mock('../components/stores/UpgradeModal.vue', () => ({ default: { template: '<div />' } }));

enableAutoUnmount(afterEach);
beforeEach(() => {
  vi.clearAllMocks();
  mocks.editable = true;
  mocks.update.mockResolvedValue({});
});

async function settings(invoiceSeconds = 900, monitoringSeconds = 86400, editable = true) {
  mocks.editable = editable;
  mocks.get.mockResolvedValue({ name: 'Synthetic store', invoice_expiration: invoiceSeconds, monitoring_expiration: monitoringSeconds });
  const wrapper = mount(StoreSettings, {
    props: { store: { id: 'synthetic-local-uuid' } },
    attachTo: document.body,
    global: { stubs: {
      StoreSettingsGeneral: true, StoreSettingsRates: true, StoreSettingsCheckout: true,
      StoreSettingsLightning: true, StoreSettingsEmailRules: true, UpgradeModal: true,
      Select: true, ProPlanBadge: true,
    } },
  });
  await flushPromises();
  return wrapper;
}

type SettingsWrapper = Awaited<ReturnType<typeof settings>>;
async function tab(wrapper: SettingsWrapper, name: string) {
  const button = wrapper.findAll('nav button').find(button => button.text() === `stores.settings_tab_${name}`);
  expect(button).toBeDefined();
  await button!.trigger('click');
}
async function submit(wrapper: SettingsWrapper) {
  (wrapper.get('form').element as HTMLFormElement).requestSubmit();
  await flushPromises();
}
function input(wrapper: SettingsWrapper, field: string) {
  return wrapper.get(`#${field}`).element as HTMLInputElement;
}

const durations = [
  ['invoice_expiration', 1],
  ['monitoring_expiration', 10],
] as const;

describe('store settings duration tab and permission regressions', () => {
  it.each(['general', 'rates', 'checkout', 'lightning'])('saves %s with hidden invalid legacy durations omitted and original values preserved', async name => {
    const wrapper = await settings(2073660, 0);
    await tab(wrapper, name);
    expect(input(wrapper, 'invoice_expiration').disabled).toBe(true);
    expect(input(wrapper, 'monitoring_expiration').disabled).toBe(true);
    expect((wrapper.get('form').element as HTMLFormElement).checkValidity()).toBe(true);
    await submit(wrapper);
    expect(mocks.update).toHaveBeenCalledOnce();
    const payload = mocks.update.mock.calls[0][1];
    expect(payload).not.toHaveProperty('invoice_expiration');
    expect(payload).not.toHaveProperty('monitoring_expiration');
    // No clamping or mutation of the loaded settings when saving another tab.
    await tab(wrapper, 'payment');
    expect(input(wrapper, 'invoice_expiration').value).toBe('34561');
    expect(input(wrapper, 'monitoring_expiration').value).toBe('0');
    expect(input(wrapper, 'monitoring_expiration').disabled).toBe(false);
    await submit(wrapper);
    expect(mocks.update).toHaveBeenCalledOnce();
  });

  it.each(durations)('omits only invalid inactive %s values', async (field, min) => {
    for (const minutes of [0, min - 1, 34561, NaN, Infinity, min + 0.5]) {
      const wrapper = await settings();
      // Use the real Vue model as an edited control would, including stale non-finite state.
      const payment = wrapper.findComponent({ name: 'StoreSettingsPayment' });
      payment.props('form')[field] = minutes;
      await submit(wrapper);
      const payload = mocks.update.mock.calls.at(-1)![1];
      expect(payload).not.toHaveProperty(field);
      const other = field === 'invoice_expiration' ? 'monitoring_expiration' : 'invoice_expiration';
      expect(payload[other]).toBe(other === 'invoice_expiration' ? 900 : 86400);
      wrapper.unmount();
    }
  });

  it.each(durations)('retains valid nullable inactive %s values', async field => {
    for (const value of ['', null]) {
      const wrapper = await settings();
      wrapper.findComponent({ name: 'StoreSettingsPayment' }).props('form')[field] = value;
      await submit(wrapper);
      expect(mocks.update.mock.calls.at(-1)![1][field]).toBe(value);
      wrapper.unmount();
    }
  });

  it.each([['general', false], ['payment', false]] as const)('keeps inaccessible durations out of native validation and payload (%s, editable=%s)', async (name, editable) => {
    const wrapper = await settings(0, 0, editable);
    await tab(wrapper, name);
    for (const [field] of durations) expect(input(wrapper, field).disabled).toBe(true);
    await submit(wrapper);
    expect(mocks.update).toHaveBeenCalledOnce();
    const payload = mocks.update.mock.calls[0][1];
    for (const field of ['invoice_expiration', 'monitoring_expiration', 'network_fee_mode', 'payment_tolerance', 'refund_bolt11_expiration', 'speed_policy', 'lightning_description_template']) {
      expect(payload).not.toHaveProperty(field);
    }
  });

  it.each(['general', 'payment'])('round trips valid boundary and wider durations exactly once from seconds to minutes and back on %s', async name => {
    for (const [invoiceMinutes, monitoringMinutes] of [[1, 10], [34560, 34560], [30, 60]]) {
      const wrapper = await settings(invoiceMinutes * 60, monitoringMinutes * 60);
      await tab(wrapper, name);
      expect(input(wrapper, 'invoice_expiration').value).toBe(String(invoiceMinutes));
      expect(input(wrapper, 'monitoring_expiration').value).toBe(String(monitoringMinutes));
      await submit(wrapper);
      const payload = mocks.update.mock.calls.at(-1)![1];
      expect(payload.invoice_expiration).toBe(invoiceMinutes * 60);
      expect(payload.monitoring_expiration).toBe(monitoringMinutes * 60);
      wrapper.unmount();
    }
  });

  it.each(durations)('rejects active editable invalid %s through native form submission', async (field, min) => {
    const wrapper = await settings();
    await tab(wrapper, 'payment');
    expect(input(wrapper, field).disabled).toBe(false);
    for (const value of [String(min - 1), '34561', String(min + 0.5)]) {
      await wrapper.get(`#${field}`).setValue(value);
      expect((wrapper.get('form').element as HTMLFormElement).checkValidity()).toBe(false);
      await submit(wrapper);
      expect(mocks.update).not.toHaveBeenCalled();
    }
    await wrapper.get(`#${field}`).setValue(min);
    await submit(wrapper);
    expect(mocks.update).toHaveBeenCalledOnce();
    expect(mocks.update.mock.calls[0][1][field]).toBe(min * 60);
    // Clearing remains optional: HTTP middleware normalizes '' to nullable input.
    await wrapper.get(`#${field}`).setValue('');
    expect((wrapper.get('form').element as HTMLFormElement).checkValidity()).toBe(true);
    await submit(wrapper);
    expect(mocks.update).toHaveBeenCalledTimes(2);
    expect(mocks.update.mock.calls[1][1][field]).toBe('');
    await tab(wrapper, 'general');
    await submit(wrapper);
    expect(mocks.update).toHaveBeenCalledTimes(3);
    expect(mocks.update.mock.calls[2][1][field]).toBe('');
  });
});
