import { afterEach, describe, expect, it } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';
import ExpenseTextPdfReviewModal, { type ExpenseTextPdfReviewValues } from '@/components/invoicing/ExpenseTextPdfReviewModal.vue';

const values: ExpenseTextPdfReviewValues = {
  supplier_name: 'Retained synthetic supplier', supplier_iban: 'SK6807200002891987426353',
  title: 'Synthetic title', external_number: 'SYN-9', variable_symbol: '00019',
  issue_date: '2026-11-17', total: '12.00', currency: 'EUR', internal_note: 'Payment amount: 2.00',
};
let wrapper: VueWrapper | undefined;
let trigger: HTMLButtonElement | undefined;
function render(input: ExpenseTextPdfReviewValues | null = values) {
  wrapper = mount(ExpenseTextPdfReviewModal, { props: { open: true, values: input }, attachTo: document.body,
    global: { plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })], stubs: { Teleport: true } },
  });
  return wrapper;
}
afterEach(() => { wrapper?.unmount(); wrapper = undefined; trigger?.remove(); trigger = undefined; });

describe('text-PDF expense value review', () => {
  it('shows exact merged values and waits for an explicit save action', async () => {
    const modal = render(); await flushPromises();
    expect(modal.text()).toContain('Retained synthetic supplier');
    expect(modal.text()).toContain('00019');
    expect(modal.text()).toContain('12.00');
    expect(modal.text()).toContain('Payment amount: 2.00');
    expect(modal.findAll('input,textarea,select')).toHaveLength(0);
    expect(modal.emitted('confirm')).toBeUndefined();
    expect(modal.emitted('cancel')).toBeUndefined();
    await modal.findAll('button').find(button => button.text() === en.invoicing.expense_pdf_text_review_save)!.trigger('click');
    expect(modal.emitted('confirm')).toEqual([[]]);
    expect(values.total).toBe('12.00');
  });

  it('escapes document content as text rather than rendering markup', () => {
    const modal = render({ ...values, title: '<img src=x onerror=alert(1)>' });
    expect(modal.text()).toContain('<img src=x onerror=alert(1)>');
    expect(modal.find('img').exists()).toBe(false);
  });

  it('keeps an empty preview attachment-only', async () => {
    const modal = render(null);
    expect(modal.text()).toContain(en.invoicing.expense_pdf_text_review_empty);
    expect(modal.findAll('button').some(button => button.text() === en.invoicing.expense_pdf_text_review_save)).toBe(false);
    await modal.findAll('button').find(button => button.text() === en.invoicing.expense_pdf_text_review_skip)!.trigger('click');
    expect(modal.emitted('cancel')).toEqual([[]]);
    expect(modal.emitted('confirm')).toBeUndefined();
  });

  it('traps focus, treats Escape as attachment-only and restores the trigger', async () => {
    trigger = document.createElement('button'); document.body.append(trigger); trigger.focus();
    const modal = render(); await flushPromises();
    const dialog = modal.get('[role="dialog"]');
    expect(dialog.attributes('aria-modal')).toBe('true');
    expect(dialog.attributes('aria-labelledby')).toBe(modal.get('h3').attributes('id'));
    const buttons = modal.findAll('button');
    expect(document.activeElement).toBe(buttons[0]!.element);
    await dialog.trigger('keydown', { key: 'Tab', shiftKey: true });
    expect(document.activeElement).toBe(buttons.at(-1)!.element);
    await dialog.trigger('keydown', { key: 'Escape' });
    expect(modal.emitted('cancel')).toEqual([[]]);
    await modal.setProps({ open: false });
    expect(document.activeElement).toBe(trigger);
  });
});
