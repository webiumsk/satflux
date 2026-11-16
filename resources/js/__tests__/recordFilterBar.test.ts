import { afterEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { reactive } from 'vue';
import { createI18n } from 'vue-i18n';
import InvoicingRecordFilterBar from '@/components/invoicing/InvoicingRecordFilterBar.vue';
import type { IssuePeriodState } from '@/composables/useInvoicingIssuePeriod';

const wrappers: VueWrapper[] = [];
afterEach(() => { wrappers.splice(0).forEach(wrapper => wrapper.unmount()); });
function i18n() {
  return createI18n({ legacy: false, locale: 'en', missingWarn: false, fallbackWarn: false, messages: { en: {} } });
}
function fixture(layout: 'bar' | 'drawer' = 'bar', kind: 'expense' | 'bank' = 'expense') {
  const period = reactive<IssuePeriodState>({ preset: 'all', customFrom: '', customTo: '' });
  const draft = reactive({ search: '', amountMin: '', amountMax: '', due: 'all', dueFrom: '', dueTo: '', direction: 'all' });
  const wrapper = mount(InvoicingRecordFilterBar, {
    props: { kind, layout, statusFilters: [{ id: 'all', label: 'All' }, { id: 'paid', label: 'Paid' }],
      activeFilter: 'all', issuePeriod: period, advancedDraft: draft, hasActiveAdvanced: false },
    global: { plugins: [i18n()] },
  });
  wrappers.push(wrapper);
  return { wrapper, period, draft };
}
async function openAdvanced(wrapper: VueWrapper) {
  await wrapper.get('button[aria-controls$="-advanced"]').trigger('click');
}
async function openPeriod(wrapper: VueWrapper) {
  await wrapper.get('button[aria-controls$="-period"]').trigger('click');
}

describe('shared expense and bank record filters', () => {
  it('keeps ordinary amount input as strings and submits one applied period snapshot', async () => {
    const { wrapper, draft } = fixture();
    await openAdvanced(wrapper);
    await wrapper.get('input[id$="-amount-min"]').setValue('12.30');
    await wrapper.get('input[id$="-amount-max"]').setValue('24.50');
    await wrapper.get('input[type="search"]').setValue('Synthetic supplier 001');
    expect(draft.amountMin).toBe('12.30');
    expect(draft.amountMax).toBe('24.50');
    expect(wrapper.emitted('advanced-apply')).toBeUndefined();
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('advanced-apply')).toEqual([[{ preset: 'all', customFrom: '', customTo: '' }]]);
  });

  it('rejects reversed and noncanonical amount ranges instead of applying broader criteria', async () => {
    const { wrapper, draft } = fixture();
    await openAdvanced(wrapper);
    for (const [min, max] of [['25', '12'], ['1e2', ''], ['-1', ''], ['0.001', ''], ['Infinity', ''], ['9007199254740992', '']]) {
      draft.amountMin = min; draft.amountMax = max;
      await wrapper.get('form').trigger('submit');
      expect(wrapper.emitted('advanced-apply')).toBeUndefined();
      expect(wrapper.get('[role="alert"]').text()).toBe('invoicing.filter_invalid_range');
    }
    draft.amountMin = '0'; draft.amountMax = '0.00';
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('advanced-apply')).toHaveLength(1);
  });

  it('keeps custom dates separate from applied parent criteria and refuses a reversed range', async () => {
    const { wrapper, period } = fixture();
    await openPeriod(wrapper);
    await wrapper.get('input[id$="-from"]').setValue('2026-03-02');
    await wrapper.get('input[id$="-to"]').setValue('2026-03-01');
    expect(period).toEqual({ preset: 'all', customFrom: '', customTo: '' });
    const apply = wrapper.findAll('button').find(button => button.text() === 'invoicing.period_apply_range')!;
    await apply.trigger('click');
    expect(wrapper.emitted('period-change')).toBeUndefined();
    await wrapper.get('input[id$="-to"]').setValue('2026-03-31');
    await apply.trigger('click');
    expect(wrapper.emitted('period-change')).toEqual([[{ preset: 'custom', customFrom: '2026-03-02', customTo: '2026-03-31' }]]);
    expect(period.preset).toBe('all');
  });

  it('commits mobile period and search only on Apply and discards unapplied period when cleared', async () => {
    const { wrapper, draft, period } = fixture('drawer', 'bank');
    await openPeriod(wrapper);
    await wrapper.get('input[id$="-from"]').setValue('2026-02-01');
    draft.search = 'Synthetic bank reference';
    expect(wrapper.emitted('period-change')).toBeUndefined();
    expect(wrapper.emitted('advanced-apply')).toBeUndefined();
    expect(wrapper.vm.applyAdvanced()).toBe(true);
    expect(wrapper.emitted('advanced-apply')).toEqual([[{ preset: 'custom', customFrom: '2026-02-01', customTo: '' }]]);
    expect(period.preset).toBe('all');
    draft.search = '';
    wrapper.vm.resetDraft();
    wrapper.vm.applyAdvanced();
    expect(wrapper.emitted('advanced-apply')?.[1]).toEqual([{ preset: 'all', customFrom: '', customTo: '' }]);
  });

  it('provides a mobile form submit target for native Enter in the search field', async () => {
    const { wrapper, draft } = fixture('drawer', 'bank');
    draft.search = 'Synthetic mobile search';
    const submit = wrapper.get('form button[type="submit"]');
    expect(submit.attributes('tabindex')).toBe('-1');
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('advanced-apply')).toHaveLength(1);
  });

  it('does not apply an unsubmitted desktop date draft when applying advanced search', async () => {
    const { wrapper, draft } = fixture();
    await openPeriod(wrapper);
    await wrapper.get('input[id$="-from"]').setValue('2026-03-02');
    await wrapper.get('input[id$="-to"]').setValue('2026-03-01');
    await wrapper.get('button[aria-controls$="-period"]').trigger('keydown', { key: 'Escape' });
    await openAdvanced(wrapper);
    draft.search = 'Synthetic current filter';
    await wrapper.get('form').trigger('submit');
    expect(wrapper.emitted('period-change')).toBeUndefined();
    expect(wrapper.emitted('advanced-apply')).toEqual([[{ preset: 'all', customFrom: '', customTo: '' }]]);
  });

  it('rejects invalid search characters while preserving ordinary Unicode and literal wildcard text', async () => {
    const { wrapper, draft } = fixture('drawer');
    for (const search of ['bad\0text', '\uD800', 'x'.repeat(201)]) {
      draft.search = search;
      expect(wrapper.vm.applyAdvanced()).toBe(false);
      expect(wrapper.emitted('advanced-apply')).toBeUndefined();
    }
    draft.search = 'Žltá faktúra 10%_ 😀';
    expect(wrapper.vm.applyAdvanced()).toBe(true);
    expect(wrapper.emitted('advanced-apply')).toHaveLength(1);
  });

  it('uses associated unique labels for desktop and drawer controls', () => {
    const wrapper = mount({
      components: { InvoicingRecordFilterBar },
      setup: () => ({ period: { preset: 'all', customFrom: '', customTo: '' },
        draft: { search: '', amountMin: '', amountMax: '', due: 'all' } }),
      template: '<div><InvoicingRecordFilterBar v-for="n in 2" :key="n" kind="expense" layout="drawer" :status-filters="[]" active-filter="all" :issue-period="period" :advanced-draft="draft" :has-active-advanced="false" /></div>',
    }, { global: { plugins: [i18n()] } });
    wrappers.push(wrapper);
    const ids = wrapper.findAll('input,select').map(control => control.attributes('id'));
    expect(new Set(ids).size).toBe(ids.length);
    for (const id of ids) expect(wrapper.findAll('label').some(label => label.attributes('for') === id)).toBe(true);
  });

  it('exposes pressed and expanded state and closes the period menu with Escape', async () => {
    const { wrapper } = fixture();
    expect(wrapper.get('button[aria-pressed="true"]').text()).toBe('All');
    const toggle = wrapper.get('button[aria-controls$="-period"]');
    expect(toggle.attributes('aria-expanded')).toBe('false');
    await toggle.trigger('click');
    expect(toggle.attributes('aria-expanded')).toBe('true');
    await toggle.trigger('keydown', { key: 'Escape' });
    expect(toggle.attributes('aria-expanded')).toBe('false');
  });
});
