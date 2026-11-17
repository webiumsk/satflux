import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { ref } from 'vue';
import { createI18n } from 'vue-i18n';
import en from '@/locales/en.json';

vi.mock('@/composables/useEffectiveAccess', () => ({ useEffectiveAccess: () => ({ purchases: ref({ isdoc_packs: true }) }) }));
import ExpenseIsdocExtractModal from '@/components/invoicing/ExpenseIsdocExtractModal.vue';
import ExpenseAttachmentPanel from '@/components/invoicing/ExpenseAttachmentPanel.vue';

const global = () => ({ plugins: [createI18n({ legacy: false, locale: 'en', messages: { en } })] });
let wrapper: VueWrapper | undefined;
afterEach(() => { wrapper?.unmount(); wrapper = undefined; });

describe('text PDF extraction review UI', () => {
  it('distinguishes text extraction and requests review without changing extraction allowance', async () => {
    wrapper = mount(ExpenseIsdocExtractModal, { props: {
      open: true, source: 'pdf_text', quota: { unlimited: false, can_extract: true, free_remaining: 3, free_limit: 20 },
    }, global: global() });
    expect(wrapper.find('h3').text()).toBe(en.invoicing.expense_pdf_text_detected_title);
    expect(wrapper.text()).toContain(en.invoicing.expense_pdf_text_review_notice);
    expect(wrapper.emitted('confirm')).toBeUndefined();
    await wrapper.findAll('button').find(button => button.text() === en.invoicing.expense_isdoc_extract_confirm)!.trigger('click');
    expect(wrapper.emitted('confirm')).toEqual([[]]);
    await wrapper.setProps({ source: 'isdoc' });
    expect(wrapper.find('h3').text()).toBe(en.invoicing.expense_isdoc_detected_title);
    expect(wrapper.text()).not.toContain(en.invoicing.expense_pdf_text_review_notice);
  });

  it('keeps extraction unavailable when the shared allowance is exhausted', () => {
    wrapper = mount(ExpenseIsdocExtractModal, { props: {
      open: true, source: 'pdf_text', allowPurchases: false, quota: { unlimited: false, can_extract: false, free_remaining: 0 },
    }, global: global() });
    expect(wrapper.findAll('button').some(button => button.text() === en.invoicing.expense_isdoc_extract_confirm)).toBe(false);
    expect(wrapper.text()).toContain(en.invoicing.expense_isdoc_quota_exceeded_local);
  });

  it('uses the extract action for readable text without claiming embedded ISDOC', async () => {
    wrapper = mount(ExpenseAttachmentPanel, { props: {
      fileName: 'synthetic.pdf', hasFile: true, hasPending: true, previewUrl: null, previewKind: 'pdf',
      detecting: false, detectError: '', hasIsdoc: false, detectionStatus: 'text_found',
    }, global: global() });
    expect(wrapper.text()).toContain(en.invoicing.expense_attachment_status_text_found);
    const extract = wrapper.findAll('button').find(button => button.text() === en.invoicing.expense_attachment_extract_isdoc)!;
    await extract.trigger('click');
    expect(wrapper.emitted('check-isdoc')).toEqual([[]]);
  });
});
