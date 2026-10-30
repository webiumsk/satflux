import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PayButtonForm from '../pages/stores/PayButtonForm.vue';
import StoreSettingsPayment from '../components/stores/StoreSettingsPayment.vue';
import { isAbsoluteHttpUrl } from '../utils/httpUrl';

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }));
vi.mock('../composables/useBtcPayUrl', () => ({
  useBtcPayUrl: () => ({ btcPayUrl: { value: 'https://pay.example.test' }, load: vi.fn() }),
}));

enableAutoUnmount(afterEach);

function payButton() {
  return mount(PayButtonForm, {
    props: { store: { id: 'local-store', btcpay_store_id: 'synthetic-provider-store' } },
    global: { stubs: { Select: true } },
  });
}

describe('absolute checkout HTTP(S) destinations', () => {
  it.each(['https://example.test/thanks?order=1&lang=sk', 'http://localhost:18080/return', 'HTTPS://EXAMPLE.TEST/paid'])('accepts %s', url => {
    expect(isAbsoluteHttpUrl(url)).toBe(true);
  });

  it.each(['', '/return', '//example.test/return', 'https:example.test', 'javascript:alert(1)', 'data:text/html,unsafe', 'ftp://example.test/return', ' https://example.test', 'https://example.test/a b', 'https://example.test/\nnext', 'https://example.test\\next', 'https://'])('rejects %j', url => {
    expect(isAbsoluteHttpUrl(url)).toBe(false);
  });

  it('accepts the URL length boundary and rejects an oversized destination', () => {
    const prefix = 'https://example.test/';
    const url = prefix + 'a'.repeat(2048 - prefix.length);
    expect(isAbsoluteHttpUrl(url)).toBe(true);
    expect(isAbsoluteHttpUrl(url + 'a')).toBe(false);
  });
});

describe('Pay Button buyer identity and redirect generation', () => {
  it('emits buyerEmail without removed notification fields or an unsupported notification toggle', async () => {
    const wrapper = payButton();
    await wrapper.get('#buyerEmail').setValue('buyer@example.test');
    const html = (wrapper.find('textarea').element as HTMLTextAreaElement).value;
    expect(wrapper.text()).toContain('stores.pay_button_buyer_email_help');
    expect(wrapper.find('#receiveEmailNotifications').exists()).toBe(false);
    expect(html).toContain('name="buyerEmail" value="buyer@example.test"');
    expect(html).not.toMatch(/name="(?:notifyEmail|notificationEmail)"/);
  });

  it('blocks stale code/copy for an unsafe redirect and recovers with the original query string', async () => {
    const wrapper = payButton();
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText } });
    const generated = () => (wrapper.find('textarea').element as HTMLTextAreaElement).value;
    await wrapper.get('#browserRedirect').setValue('https://example.test/paid?order=1&lang=sk');
    expect(generated()).toContain('name="redirectURL" value="https://example.test/paid?order=1&amp;lang=sk"');
    await wrapper.get('#browserRedirect').setValue('javascript:alert(1)');
    expect(generated()).toBe('');
    expect(wrapper.get('#browserRedirect').attributes('aria-invalid')).toBe('true');
    expect(wrapper.get('#pay-button-redirect-error').text()).toBe('stores.pay_button_redirect_invalid');
    await (wrapper.vm as unknown as { copyCode(): Promise<void> }).copyCode();
    expect(writeText).not.toHaveBeenCalled();
    await wrapper.get('#browserRedirect').setValue('https://example.test/paid?order=2&lang=sk');
    expect(wrapper.find('#pay-button-redirect-error').exists()).toBe(false);
    await (wrapper.vm as unknown as { copyCode(): Promise<void> }).copyCode();
    expect(writeText).toHaveBeenCalledWith(generated());
    expect(generated()).toContain('order=2&amp;lang=sk');
  });

  it('keeps generated attribute values escaped', async () => {
    const wrapper = payButton();
    await wrapper.get('#buyerEmail').setValue('buyer@example.test"><img src=x>');
    const html = (wrapper.find('textarea').element as HTMLTextAreaElement).value;
    expect(html).toContain('buyer@example.test&quot;&gt;&lt;img src=x&gt;');
    expect(html).not.toContain('buyer@example.test"><img');
  });
});

describe('store payment duration inputs', () => {
  it('enforces whole-minute bounds without converting the entered values a second time', async () => {
    const form = { invoice_expiration: 1, monitoring_expiration: 10 };
    const wrapper = mount(StoreSettingsPayment, {
      props: { form: form as never, canEditPaymentOptions: true, isActive: true, timezoneOptions: [], speedPolicyOptions: [], networkFeeModeOptions: [] },
      global: { stubs: { Select: true, ProPlanBadge: true } },
    });
    const invoice = wrapper.get('#invoice_expiration');
    const monitoring = wrapper.get('#monitoring_expiration');
    for (const [field, min] of [[invoice, 1], [monitoring, 10]] as const) {
      await field.setValue(min - 1);
      expect((field.element as HTMLInputElement).validity.rangeUnderflow).toBe(true);
      await field.setValue(min);
      expect((field.element as HTMLInputElement).checkValidity()).toBe(true);
      await field.setValue(34560);
      expect((field.element as HTMLInputElement).checkValidity()).toBe(true);
      await field.setValue(34561);
      expect((field.element as HTMLInputElement).validity.rangeOverflow).toBe(true);
      await field.setValue(min + 0.5);
      expect((field.element as HTMLInputElement).validity.stepMismatch).toBe(true);
    }
    await invoice.setValue(30);
    await monitoring.setValue(60);
    expect(form).toEqual({ invoice_expiration: 30, monitoring_expiration: 60 });
  });
});
