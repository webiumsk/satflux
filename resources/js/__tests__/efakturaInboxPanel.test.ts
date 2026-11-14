import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const state = vi.hoisted(() => ({ detail: vi.fn(), imported: vi.fn(), refresh: vi.fn(), check: vi.fn() }));
const entries = ref([{
  inbox_id: 'synthetic-receipt', evolu_expense_id: 'synthetic-id', external_document_id: 'invoice/1', inbox_status: 'pending',
  created_at: '2026-11-10T10:00:00Z', draft: null,
  summary: { supplier_name: 'Synthetic supplier', external_number: 'CN-1', total: '-19.00', currency: 'EUR' },
}]);
const bridge = ref<string | null>('bridge-a');
const health = ref<{ state: string; last_poll_at: string | null; failed: number }>({ state: 'ok', last_poll_at: null, failed: 0 });
const receivingEnabled = ref(true);
const checkingMailbox = ref(false);
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }));
vi.mock('@/evolu/client', () => ({ useInvoicingEvolu: () => ({}) }));
vi.mock('@/evolu/efakturaInboxImport', () => ({ fetchEfakturaInboxDetail: (...args: unknown[]) => state.detail(...args),
  importEfakturaInboxEntry: (...args: unknown[]) => state.imported(...args) }));
vi.mock('@/evolu/efakturaInboxLive', () => ({ useEfakturaInboxLive: () => ({ entries, bridgeCompanyId: bridge, health, receivingEnabled, checkingMailbox }),
  checkEfakturaInboxMailbox: (...args: unknown[]) => state.check(...args),
  refreshEfakturaInboxLive: (...args: unknown[]) => state.refresh(...args) }));
import Panel from '@/components/invoicing/EfakturaInboxPanel.vue';

const original = '<CreditNote xmlns:cac="urn:test:cac" xmlns:cbc="urn:test:cbc"><cac:CreditNoteLine><cbc:CreditedQuantity>2</cbc:CreditedQuantity><cbc:LineExtensionAmount>19.00</cbc:LineExtensionAmount><cac:Item><cbc:Name>&lt;script&gt;unsafe()&lt;/script&gt;</cbc:Name></cac:Item></cac:CreditNoteLine></CreditNote>';
const detail = () => ({ ...entries.value[0], ubl: original, original_sha256: 'a'.repeat(64),
  draft: { document_type: 'credit_note', issue_date: '2026-11-10', due_date: '2026-11-24', internal_note: 'Synthetic credit note' } });
const button = (wrapper: ReturnType<typeof mount>, key: string) => wrapper.findAll('button').find((b) => b.text() === `invoicing.${key}`)!;

beforeEach(() => {
  entries.value = [{ inbox_id: 'synthetic-receipt', evolu_expense_id: 'synthetic-id', external_document_id: 'invoice/1', inbox_status: 'pending',
    created_at: '2026-11-10T10:00:00Z', draft: null,
    summary: { supplier_name: 'Synthetic supplier', external_number: 'CN-1', total: '-19.00', currency: 'EUR' } }];
  bridge.value = 'bridge-a';
  health.value = { state: 'ok', last_poll_at: null, failed: 0 }; receivingEnabled.value = true; checkingMailbox.value = false;
  state.detail.mockReset().mockImplementation(async () => detail());
  state.imported.mockReset(); state.refresh.mockReset(); state.check.mockReset().mockResolvedValue(true);
});
afterEach(() => vi.restoreAllMocks());

describe('received document panel', () => {
  it('keeps checking the mailbox available when receiving is enabled and the stored inbox is empty', async () => {
    entries.value = [];
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    expect(wrapper.text()).toContain('invoicing.efaktura_inbox_empty');
    expect(button(wrapper, 'efaktura_inbox_check_mailbox').exists()).toBe(true);
    await button(wrapper, 'efaktura_inbox_check_mailbox').trigger('click'); await flushPromises();
    expect(state.check).toHaveBeenCalledExactlyOnceWith('company-a');
    expect(state.refresh).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('refreshes stored documents and status without polling the provider', async () => {
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_refresh').trigger('click'); await flushPromises();
    expect(state.refresh).toHaveBeenCalledExactlyOnceWith(true, { throwOnError: true });
    expect(state.check).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('prevents duplicate checking, refresh and import until the mailbox check finishes', async () => {
    let finish!: () => void;
    state.check.mockImplementationOnce(() => new Promise<void>((resolve) => { finish = resolve; }));
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_check_mailbox').trigger('click');
    expect(button(wrapper, 'efaktura_inbox_checking_mailbox').attributes('disabled')).toBeDefined();
    expect(button(wrapper, 'efaktura_inbox_refresh').attributes('disabled')).toBeDefined();
    expect(button(wrapper, 'efaktura_inbox_import').attributes('disabled')).toBeDefined();
    await button(wrapper, 'efaktura_inbox_checking_mailbox').trigger('click');
    await button(wrapper, 'efaktura_inbox_refresh').trigger('click');
    await button(wrapper, 'efaktura_inbox_import').trigger('click');
    expect(state.check).toHaveBeenCalledTimes(1);
    expect(state.refresh).not.toHaveBeenCalled();
    expect(state.imported).not.toHaveBeenCalled();
    finish(); await flushPromises();
    expect(button(wrapper, 'efaktura_inbox_check_mailbox').attributes('disabled')).toBeUndefined();
    wrapper.unmount();
  });

  it('retains documents and offers a retry after a mailbox check fails', async () => {
    state.check.mockRejectedValueOnce(new Error('Synthetic mailbox failure'));
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_check_mailbox').trigger('click'); await flushPromises();
    expect(wrapper.get('[role="alert"]').text()).toBe('Synthetic mailbox failure');
    expect(wrapper.text()).toContain('Synthetic supplier');
    await button(wrapper, 'efaktura_inbox_check_mailbox').trigger('click'); await flushPromises();
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    expect(state.check).toHaveBeenCalledTimes(2);
    wrapper.unmount();
  });

  it('discards a late mailbox failure after the company and bridge change', async () => {
    let fail!: (error: Error) => void;
    state.check.mockImplementationOnce(() => new Promise((_resolve, reject) => { fail = reject; }));
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_check_mailbox').trigger('click');
    bridge.value = 'bridge-b'; await wrapper.setProps({ companyId: 'company-b' });
    fail(new Error('Obsolete mailbox failure')); await flushPromises();
    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    expect(button(wrapper, 'efaktura_inbox_check_mailbox').attributes('disabled')).toBeUndefined();
    expect(state.check).toHaveBeenCalledExactlyOnceWith('company-a');
    wrapper.unmount();
  });

  it('does not offer provider polling without a resolved bridge or when receiving is off', async () => {
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    bridge.value = null; await flushPromises();
    expect(button(wrapper, 'efaktura_inbox_check_mailbox').attributes('disabled')).toBeDefined();
    await button(wrapper, 'efaktura_inbox_check_mailbox').trigger('click');
    expect(state.check).not.toHaveBeenCalled();
    receivingEnabled.value = false; await flushPromises();
    expect(button(wrapper, 'efaktura_inbox_check_mailbox')).toBeUndefined();
    wrapper.unmount();
  });

  it('shows a readable credit note and escapes original XML and line descriptions', async () => {
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_view').trigger('click'); await flushPromises();
    expect(wrapper.text()).toContain('Synthetic supplier');
    expect(wrapper.text()).toContain('-19.00 EUR');
    expect(wrapper.text()).toContain('invoicing.efaktura_inbox_credit_note');
    expect(wrapper.text()).toContain('<script>unsafe()</script>');
    expect(wrapper.findAll('script')).toHaveLength(0);
    expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    expect(wrapper.find('pre').text()).toBe(original);
    expect(button(wrapper, 'efaktura_inbox_dismiss')).toBeUndefined();
    wrapper.unmount();
  });

  it('discards a late document response after switching company and bridge', async () => {
    let resolve!: (value: unknown) => void;
    state.detail.mockImplementation(() => new Promise((done) => { resolve = done; }));
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_view').trigger('click');
    const response = detail();
    bridge.value = 'bridge-b'; await wrapper.setProps({ companyId: 'company-b' });
    resolve(response); await flushPromises();
    expect(wrapper.find('pre').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Synthetic credit note');
    expect(state.detail).toHaveBeenCalledExactlyOnceWith('bridge-a', 'synthetic-receipt');
    wrapper.unmount();
  });

  it('never downloads a late original into another company view', async () => {
    const create = vi.fn(); vi.stubGlobal('URL', { createObjectURL: create, revokeObjectURL: vi.fn() });
    let resolve!: (value: unknown) => void;
    state.detail.mockImplementation(() => new Promise((done) => { resolve = done; }));
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_download_original').trigger('click');
    const response = detail();
    bridge.value = 'bridge-b'; await wrapper.setProps({ companyId: 'company-b' });
    resolve(response); await flushPromises();
    expect(create).not.toHaveBeenCalled();
    wrapper.unmount(); vi.unstubAllGlobals();
  });

  it('keeps a document visible when its original cannot be safely stored', async () => {
    state.imported.mockResolvedValue({ ok: false, error: 'original_too_large' });
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_import').trigger('click'); await flushPromises();
    expect(entries.value).toHaveLength(1);
    expect(wrapper.find('[role="alert"]').text()).toBe('invoicing.efaktura_inbox_error_original_too_large');
    expect(wrapper.emitted('imported')).toBeUndefined();
    expect(button(wrapper, 'efaktura_inbox_download_original').exists()).toBe(true);
    wrapper.unmount();
  });

  it.each(['failed', 'stale', 'not_polled', 'disabled', 'unavailable'])('shows a persistent receiving warning for an empty inbox: %s', async (status) => {
    entries.value = [];
    health.value = { state: status, last_poll_at: '2026-11-10T10:00:00Z', failed: status === 'failed' ? 1 : 0 };
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    expect(wrapper.find('[data-testid="efaktura-inbound-health"]').text()).toContain(`invoicing.efaktura_inbox_health_${status}`);
    expect(wrapper.find('[data-testid="efaktura-inbox-panel"]').exists()).toBe(true);
    receivingEnabled.value = false;
    await flushPromises();
    expect(wrapper.find('[data-testid="efaktura-inbound-health"]').exists()).toBe(false);
    wrapper.unmount();
  });
});

it('formats calendar-only draft dates in UTC and keeps timestamp parsing separate', async () => {
  const RealDate = Date;
  const calls: unknown[][] = [];
  const format = vi.spyOn(RealDate.prototype, 'toLocaleDateString');
  vi.stubGlobal('Date', new Proxy(RealDate, { construct(target, args) {
    calls.push(args);
    return Reflect.construct(target, args);
  } }));
  try {
    const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
    await button(wrapper, 'efaktura_inbox_view').trigger('click'); await flushPromises();
    expect(calls).toContainEqual(['2026-11-10T00:00:00Z']);
    expect(calls).toContainEqual(['2026-11-24T00:00:00Z']);
    expect(format).toHaveBeenCalledWith('en', { timeZone: 'UTC' });
    expect(wrapper.text()).toContain('11/10/2026');
    expect(calls).toContainEqual(['2026-11-10T10:00:00Z']);
    wrapper.unmount();
  } finally { vi.unstubAllGlobals(); }
});

it('preserves a skipped local calendar day and a year below 100 without constructor reinterpretation', async () => {
  state.detail.mockResolvedValue({ ...detail(), draft: { issue_date: '2011-12-30', due_date: '0099-01-01' } });
  const wrapper = mount(Panel, { props: { companyId: 'company-a' } });
  await button(wrapper, 'efaktura_inbox_view').trigger('click'); await flushPromises();
  expect(wrapper.text()).toContain('12/30/2011');
  expect(wrapper.text()).toContain('1/1/99');
  wrapper.unmount();
});
