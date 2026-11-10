import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const state = vi.hoisted(() => ({ detail: vi.fn(), imported: vi.fn(), refresh: vi.fn() }));
const entries = ref([{
  inbox_id: 'synthetic-receipt', evolu_expense_id: 'synthetic-id', external_document_id: 'invoice/1', inbox_status: 'pending',
  created_at: '2026-11-10T10:00:00Z', draft: null,
  summary: { supplier_name: 'Synthetic supplier', external_number: 'CN-1', total: '-19.00', currency: 'EUR' },
}]);
const bridge = ref<string | null>('bridge-a');
const health = ref<{ state: string; last_poll_at: string | null; failed: number }>({ state: 'ok', last_poll_at: null, failed: 0 });
const receivingEnabled = ref(true);
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }));
vi.mock('@/evolu/client', () => ({ useInvoicingEvolu: () => ({}) }));
vi.mock('@/evolu/efakturaInboxImport', () => ({ fetchEfakturaInboxDetail: (...args: unknown[]) => state.detail(...args),
  importEfakturaInboxEntry: (...args: unknown[]) => state.imported(...args) }));
vi.mock('@/evolu/efakturaInboxLive', () => ({ useEfakturaInboxLive: () => ({ entries, bridgeCompanyId: bridge, health, receivingEnabled }),
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
  health.value = { state: 'ok', last_poll_at: null, failed: 0 }; receivingEnabled.value = true;
  state.detail.mockReset().mockImplementation(async () => detail());
  state.imported.mockReset(); state.refresh.mockReset();
});
afterEach(() => vi.restoreAllMocks());

describe('received document panel', () => {
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
