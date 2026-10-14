import { flushPromises, mount } from '@vue/test-utils';
import { computed, reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { User } from '../store/auth';
const mocks = vi.hoisted(() => ({
  auth: { user: null as User | null, isAuthenticated: true }, post: vi.fn(), error: vi.fn(),
}));
vi.mock('../store/auth', () => ({ useAuthStore: () => mocks.auth }));
vi.mock('../store/flash', () => ({ useFlashStore: () => ({ clear: vi.fn(), error: mocks.error }) }));
vi.mock('../services/api', () => ({ default: { post: mocks.post }, invoicingApi: { companySlots: { purchase: mocks.post } } }));
vi.mock('../composables/usePricing', () => ({ usePricing: () => ({
  pricing: computed(() => ({ trial_days: 30, pro: { sats_per_year: 210000 }, company_slot_packs: [{ slots: 1, sats: 1000 }] })),
  formatSats: (n: number) => `${n} sats`, load: vi.fn(),
}) }));
vi.mock('../composables/usePlanFeatures', () => ({ usePlanFeatures: () => ({
  planFeatures: computed(() => ({ pro: { feature_keys: [] }, enterprise: { feature_keys: [] } })),
  isInvoicingFeature: () => false, load: vi.fn(),
}) }));
import UpgradeModal from '../components/stores/UpgradeModal.vue';
import CompanySlotPurchaseModal from '../components/invoicing/CompanySlotPurchaseModal.vue';
import ExpenseIsdocExtractModal from '../components/invoicing/ExpenseIsdocExtractModal.vue';
function account(): User { return {
  id: 1, email: 'synthetic@example.test', role: 'free', is_guest: false, email_verified_at: '2026-01-01',
  purchase_availability: { pro_subscriptions: false, pro_credits: false, company_slots: false, isdoc_packs: false },
}; }
describe('purchase pause presentation and keyboard controls', () => {
  beforeEach(() => { mocks.auth = reactive({ user: account(), isAuthenticated: true }); vi.clearAllMocks(); });
  it('removes the PRO purchase action and never starts checkout while paused', async () => {
    const wrapper = mount(UpgradeModal, { props: { show: true }, global: { stubs: { RouterLink: true } } });
    await flushPromises();
    expect(wrapper.text()).toContain('free_pro.purchases_paused');
    expect(wrapper.findAll('button').some(button => button.text().includes('upgrade_modal.upgrade_now'))).toBe(false);
    for (const button of wrapper.findAll('button')) await button.trigger('click');
    expect(mocks.post).not.toHaveBeenCalled(); wrapper.unmount();
  });
  it('does not remove the Enterprise action', async () => {
    const wrapper = mount(UpgradeModal, { props: { show: true, recommendedPlan: 'enterprise' } });
    mocks.post.mockResolvedValue({ data: {} });
    const purchase = wrapper.findAll('button').find(button => button.text().includes('upgrade_modal.upgrade_now'))!;
    await purchase.trigger('click'); await flushPromises();
    expect(mocks.post).toHaveBeenCalledWith('/subscriptions/checkout', { plan: 'enterprise' }); wrapper.unmount();
  });
  it('hides paused slot and ISDOC packs while retaining the existing extraction balance', async () => {
    const slots = mount(CompanySlotPurchaseModal, { props: { open: true } });
    expect(slots.text()).not.toContain('1000 sats');
    const extracts = mount(ExpenseIsdocExtractModal, { props: {
      open: true, quota: { unlimited: false, can_extract: true, purchased_credits: 4, packs: [{ credits: 10, price_eur: 5 }] },
    } });
    expect(extracts.text()).toContain('invoicing.expense_isdoc_extract_confirm');
    expect(extracts.text()).not.toContain('invoicing.expense_isdoc_packs_title');
    expect(mocks.post).not.toHaveBeenCalled(); slots.unmount(); extracts.unmount();
  });
  it('labels the dialog, traps Tab, supports Escape and returns focus to the trigger', async () => {
    const trigger = document.createElement('button'); document.body.append(trigger); trigger.focus();
    const wrapper = mount(UpgradeModal, { props: { show: true }, attachTo: document.body });
    await flushPromises();
    const dialog = wrapper.get('[role="dialog"]');
    const headingId = dialog.attributes('aria-labelledby');
    expect(document.getElementById(headingId!)?.tagName).toBe('H3');
    const buttons = wrapper.findAll('button');
    expect(document.activeElement).toBe(buttons[0]!.element);
    await buttons[0]!.trigger('keydown', { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(buttons.at(-1)!.element);
    await dialog.trigger('keydown', { key: 'Escape' });
    expect(wrapper.emitted('close')).toHaveLength(1);
    await wrapper.setProps({ show: false }); await flushPromises();
    expect(document.activeElement).toBe(trigger); wrapper.unmount(); trigger.remove();
  });
});
