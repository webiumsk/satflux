import { flushPromises, mount } from '@vue/test-utils';
import { reactive, ref, type Ref } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
const mocks = vi.hoisted(() => ({
  promotion: null as Ref<boolean> | null, open: null as Ref<boolean> | null,
  emailOnly: null as Ref<boolean> | null, challenge: null as Ref<null> | null,
  form: { email: '', password: '', password_confirmation: '' }, close: vi.fn(), loadPricing: vi.fn(),
}));
vi.mock('../composables/usePricing', () => ({ usePricing: () => ({ promotionActive: mocks.promotion, load: mocks.loadPricing }) }));
vi.mock('../composables/useGuestUpgradeSubmit', () => ({ useGuestUpgradeSubmit: () => ({
  emailOnly: mocks.emailOnly, challenge: mocks.challenge, form: mocks.form,
  loading: ref(false), privacyConsent: ref(false), termsAccepted: ref(false), canSubmit: ref(true),
  submit: vi.fn(), confirm: vi.fn(), resend: vi.fn(), discardChallenge: vi.fn(),
}) }));
vi.mock('../composables/useGuestUpgradeModal', () => ({ useGuestUpgradeModal: () => ({
  open: mocks.open, featureLabelKey: ref(null), closeGuestUpgradeModal: mocks.close,
}) }));
import GuestUpgradeForm from '../components/account/GuestUpgradeForm.vue';
import GuestUpgradeModal from '../components/account/GuestUpgradeModal.vue';
const routerLink = { template: '<a href="/documentation/free-pro-access"><slot /></a>' };
describe('guest upgrade promotion messaging and accessibility', () => {
  beforeEach(() => {
    vi.clearAllMocks(); mocks.promotion = ref(true); mocks.open = ref(true); mocks.emailOnly = ref(false);
    mocks.challenge = ref(null); mocks.form = reactive({ email: '', password: '', password_confirmation: '' });
    mocks.close.mockImplementation(() => { mocks.open!.value = false; });
  });
  it('gives every email/password field a visible label and unique prefix-based id', async () => {
    const first = mount(GuestUpgradeForm, { props: { idPrefix: 'first' }, global: { stubs: { RouterLink: routerLink } } });
    const second = mount(GuestUpgradeForm, { props: { idPrefix: 'second' }, global: { stubs: { RouterLink: routerLink } } });
    for (const field of ['email', 'password', 'password_confirmation']) {
      expect(first.get(`#first-${field}`).attributes('id')).toBe(`first-${field}`);
      const label = first.get(`label[for="first-${field}"]`);
      expect(label.isVisible()).toBe(true); expect(label.text()).not.toBe('');
      expect(second.get(`#second-${field}`).attributes('id')).toBe(`second-${field}`);
    }
    expect(first.text()).toContain('free_pro.verification_benefit');
    expect(first.get('a[href="/documentation/free-pro-access"]').text()).toBe('free_pro.details');
    mocks.promotion!.value = false; await flushPromises();
    expect(first.text()).not.toContain('free_pro.verification_benefit');
    first.unmount(); second.unmount();
  });
  it('keeps the email-only flow and traps/returns keyboard focus in the guest modal', async () => {
    mocks.emailOnly!.value = true;
    const trigger = document.createElement('button'); document.body.append(trigger); trigger.focus();
    const wrapper = mount(GuestUpgradeModal, { attachTo: document.body, global: { stubs: { Teleport: true, RouterLink: routerLink } } });
    await flushPromises();
    const dialog = wrapper.get('[role="dialog"]');
    expect(dialog.attributes('aria-modal')).toBe('true');
    expect(wrapper.get(`#${dialog.attributes('aria-labelledby')}`).text()).toBe('account.guest_upgrade_title');
    expect(wrapper.find('input[type="password"]').exists()).toBe(false);
    const buttons = wrapper.findAll('button');
    expect(document.activeElement).toBe(buttons[0]!.element);
    await buttons[0]!.trigger('keydown', { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(buttons.at(-1)!.element);
    await dialog.trigger('keydown', { key: 'Escape' }); await flushPromises();
    expect(mocks.close).toHaveBeenCalledTimes(1);
    expect(document.activeElement).toBe(trigger);
    wrapper.unmount(); trigger.remove();
  });
});
