import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';

const state = vi.hoisted(() => ({
  compliance: vi.fn(), send: vi.fn(), refresh: vi.fn(),
  localStatus: vi.fn(), localSend: vi.fn(), localRefresh: vi.fn(), ensureBridge: vi.fn(),
}));
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key, locale: ref('en') }) }));
vi.mock('../services/api', () => ({ invoicingApi: { documents: { efaktura: {
  compliance: (...args: unknown[]) => state.compliance(...args),
  send: (...args: unknown[]) => state.send(...args), refresh: (...args: unknown[]) => state.refresh(...args),
} } } }));
vi.mock('../evolu/ephemeralBridge', () => ({
  fetchEphemeralEfakturaStatus: (...args: unknown[]) => state.localStatus(...args),
  sendEphemeralEfaktura: (...args: unknown[]) => state.localSend(...args),
  refreshEphemeralEfakturaStatus: (...args: unknown[]) => state.localRefresh(...args),
}));
vi.mock('../evolu/bridgeCompanyEnsure', () => ({ ensureBridgeCompanyIdForLocalCompany: (...args: unknown[]) => state.ensureBridge(...args) }));
import Panel from '../components/invoicing/InvoiceEfakturaPanel.vue';

const company = (id = 'company-a', url = 'https://testpostman.slovakodata.com') => ({
  id, jurisdiction: 'eu_sk', country: 'SK', tax_id: '2020000001', vat_payer: true, vat_status: 'payer',
  app_settings: { efaktura_enabled: true, efaktura_sapi_base_url: url,
    efaktura_sapi_client_id: 'synthetic-client', efaktura_sapi_client_secret_set: true },
});
const row = (metadata: Record<string, unknown> = {}, status = 'submitted') => ({ id: 'submission-a', provider: 'peppol', status,
  external_id: 'synthetic-provider-id', submitted_at: '2026-11-12T10:00:00Z',
  response_payload: { epostman: { submission_state: 'accepted', retry_allowed: false, delivery_status_available: false, ...metadata } },
});
const wrappers: ReturnType<typeof mount>[] = [];
function mountPanel(overrides: Record<string, unknown> = {}) {
  const wrapper = mount(Panel, { props: { companyId: 'company-a', documentId: 'document-a', company: company(),
    selectedContact: { country: 'SK', tax_id: '2020000002' }, ...overrides }, global: { stubs: { RouterLink: true } } });
  wrappers.push(wrapper);
  return wrapper;
}
function button(wrapper: ReturnType<typeof mount>, key: string) {
  return wrapper.findAll('button').find((b) => b.text() === `invoicing.${key}`);
}
function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}
beforeEach(() => {
  for (const fn of Object.values(state)) fn.mockReset();
  state.compliance.mockResolvedValue([]); state.localStatus.mockResolvedValue([]);
  state.ensureBridge.mockResolvedValue({ ok: true, bridgeCompanyId: 'bridge-a' });
  state.send.mockResolvedValue({}); state.localSend.mockResolvedValue(row()); state.refresh.mockResolvedValue({});
});
afterEach(() => { for (const wrapper of wrappers.splice(0)) wrapper.unmount(); vi.useRealTimers(); vi.restoreAllMocks(); });

describe('manual ePostman outbound panel', () => {
  it('distinguishes acceptance from delivery and offers the fixed test portal without send or refresh', async () => {
    state.compliance.mockResolvedValue([row()]);
    const wrapper = mountPanel(); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_panel_status_accepted');
    expect(wrapper.text()).toContain('efaktura_panel_epostman_accepted');
    expect(wrapper.text()).toContain('synthetic-provider-id');
    expect(wrapper.get('a').attributes('href')).toBe('https://testpostman.slovakodata.com');
    expect(wrapper.findAll('button')).toHaveLength(0);
    expect(state.refresh).not.toHaveBeenCalled();
  });

  it('uses the production portal only for the canonical production origin', async () => {
    state.compliance.mockResolvedValue([row()]);
    const wrapper = mountPanel({ company: company('company-a', 'https://POSTMAN.SLOVAKODATA.COM:443/') }); await flushPromises();
    expect(wrapper.get('a').attributes('href')).toBe('https://postman.slovakodata.com');
  });

  it.each(['https://postman.slovakodata.com.attacker.test', 'https://postman.slovakodata.com:444'])('does not treat a foreign origin as an official portal: %s', async (url) => {
    state.compliance.mockResolvedValue([row()]);
    const wrapper = mountPanel({ company: company('company-a', url) }); await flushPromises();
    expect(wrapper.find('[data-testid="epostman-submission-state"]').exists()).toBe(false);
    expect(button(wrapper, 'efaktura_panel_refresh_status')).toBeDefined();
  });

  it('binds local status and manual send to the selected local company bridge', async () => {
    const snapshot = { company: company(), contact: null, document: { type: 'invoice' }, lines: [] };
    const wrapper = mountPanel({ localFirst: true, evoluDocumentId: 'local-document-a', ephemeralSnapshot: snapshot,
      bridgeCompanyId: 'unrelated-default-bridge' }); await flushPromises();
    expect(state.ensureBridge).toHaveBeenCalledExactlyOnceWith('company-a');
    expect(state.localStatus).toHaveBeenCalledExactlyOnceWith('local-document-a', 'bridge-a');
    await button(wrapper, 'efaktura_panel_send')!.trigger('click'); await flushPromises();
    expect(state.localSend).toHaveBeenCalledExactlyOnceWith(snapshot, 'local-document-a', 'bridge-a', { requireBridge: true });
    expect(wrapper.text()).toContain('efaktura_panel_status_accepted');
    expect(wrapper.emitted('sent')).toHaveLength(1);
  });

  it.each([false, true])('does not allow a duplicate send while the initial history is loading (local=%s)', async (localFirst) => {
    const pending = deferred<unknown[]>();
    state.compliance.mockReturnValue(pending.promise); state.localStatus.mockReturnValue(pending.promise);
    const wrapper = mountPanel({ localFirst, evoluDocumentId: 'local-document-a' }); await flushPromises();
    expect(button(wrapper, 'efaktura_panel_send')).toBeUndefined();
    pending.resolve([row()]); await flushPromises();
    expect(wrapper.findAll('button')).toHaveLength(0);
  });

  it('pauses send on a history failure and permits a harmless history retry', async () => {
    state.compliance.mockRejectedValueOnce(new Error('synthetic history failure'));
    const wrapper = mountPanel(); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_panel_status_unavailable');
    expect(wrapper.text()).not.toContain('efaktura_panel_not_sent');
    expect(button(wrapper, 'efaktura_panel_send')).toBeUndefined();
    state.compliance.mockResolvedValue([row()]);
    await button(wrapper, 'efaktura_panel_retry_load')!.trigger('click'); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_panel_status_accepted');
    expect(state.send).not.toHaveBeenCalled();
  });

  it('blocks local sending while saved settings still need bridge synchronization', async () => {
    const value = company();
    Object.assign(value.app_settings, { efaktura_inbound_bridge_sync_pending: true });
    const wrapper = mountPanel({ localFirst: true, evoluDocumentId: 'local-document-a', company: value }); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_epostman_bridge_save_failed');
    expect(state.ensureBridge).not.toHaveBeenCalled();
    expect(button(wrapper, 'efaktura_panel_send')).toBeUndefined();
  });

  it('blocks sending when the selected local company has no verified bridge', async () => {
    state.ensureBridge.mockResolvedValue({ ok: false });
    const wrapper = mountPanel({ localFirst: true, evoluDocumentId: 'local-document-a' }); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_panel_bridge_not_configured');
    expect(state.localStatus).not.toHaveBeenCalled();
    expect(button(wrapper, 'efaktura_panel_send')).toBeUndefined();
  });

  it.each(['failed', 'rejected', 'skipped', 'pending', 'submitted', 'approved'])('does not infer permission to resend an ePostman row from status %s', async (status) => {
    state.compliance.mockResolvedValue([row({}, status)]);
    const wrapper = mountPanel(); await flushPromises();
    expect(button(wrapper, 'efaktura_panel_resend')).toBeUndefined();
    expect(button(wrapper, 'efaktura_panel_retry_submission')).toBeUndefined();
  });

  it('only offers an uncertain retry inside the server-authorized deadline', async () => {
    state.compliance.mockResolvedValue([row({ submission_state: 'uncertain', retry_allowed: true,
      retry_until: new Date(Date.now() + 60000).toISOString() })]);
    const wrapper = mountPanel(); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_panel_status_uncertain');
    expect(wrapper.text()).toContain('efaktura_panel_submission_uncertain');
    expect(wrapper.text()).toContain('efaktura_panel_retry_until');
    await button(wrapper, 'efaktura_panel_retry_submission')!.trigger('click'); await flushPromises();
    expect(state.send).toHaveBeenCalledExactlyOnceWith('company-a', 'document-a');
    expect(state.refresh).not.toHaveBeenCalled();
  });

  it.each([
    { retry_until: 'invalid' }, { retry_until: new Date(0).toISOString() }, { retry_until: null },
    { retry_until: '2099-01-01T00:00:00Z', reconciliation_required: true },
  ])('does not retry an unsafe or expired submission %j', async (metadata) => {
    state.compliance.mockResolvedValue([row({ submission_state: 'uncertain', retry_allowed: true, ...metadata })]);
    const wrapper = mountPanel(); await flushPromises();
    expect(button(wrapper, 'efaktura_panel_retry_submission')).toBeUndefined();
  });

  it('removes the retry action when the deadline passes while the page stays open', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    state.compliance.mockResolvedValue([row({ submission_state: 'uncertain', retry_allowed: true,
      retry_until: new Date(Date.now() + 100).toISOString() })]);
    const wrapper = mountPanel(); await flushPromises();
    expect(button(wrapper, 'efaktura_panel_retry_submission')).toBeDefined();
    await vi.advanceTimersByTimeAsync(101);
    expect(button(wrapper, 'efaktura_panel_retry_submission')).toBeUndefined();
    expect(wrapper.text()).toContain('efaktura_panel_reconciliation_required');
    expect(wrapper.text()).not.toContain('efaktura_panel_retry_until');
  });

  it('preserves custom-provider submitted status refresh', async () => {
    state.compliance.mockResolvedValue([row()]);
    const wrapper = mountPanel({ company: company('company-a', 'https://custom.example') }); await flushPromises();
    await button(wrapper, 'efaktura_panel_refresh_status')!.trigger('click'); await flushPromises();
    expect(state.refresh).toHaveBeenCalledExactlyOnceWith('company-a', 'document-a');
  });

  it('ignores a late history response from another company/document', async () => {
    const pending = deferred<unknown[]>();
    state.compliance.mockReturnValueOnce(pending.promise);
    const wrapper = mountPanel(); await flushPromises();
    await wrapper.setProps({ companyId: 'company-b', documentId: 'document-b', company: company('company-b') }); await flushPromises();
    pending.resolve([row()]); await flushPromises();
    expect(wrapper.text()).not.toContain('synthetic-provider-id');
    expect(button(wrapper, 'efaktura_panel_send')).toBeDefined();
  });

  it('waits for the matching company to load after the route company changes', async () => {
    const wrapper = mountPanel(); await flushPromises();
    await wrapper.setProps({ companyId: 'company-b', documentId: 'document-b' }); await flushPromises();
    expect(button(wrapper, 'efaktura_panel_send')).toBeUndefined();
    expect(state.compliance).toHaveBeenCalledExactlyOnceWith('company-a', 'document-a');
    await wrapper.setProps({ company: company('company-b') }); await flushPromises();
    expect(state.compliance).toHaveBeenLastCalledWith('company-b', 'document-b');
  });

  it('discards an accepted response after the provider connection changes', async () => {
    const pending = deferred<unknown>(); state.send.mockReturnValueOnce(pending.promise);
    const wrapper = mountPanel(); await flushPromises();
    await button(wrapper, 'efaktura_panel_send')!.trigger('click');
    await wrapper.setProps({ company: company('company-a', 'https://postman.slovakodata.com') }); await flushPromises();
    pending.resolve({ message: 'synthetic previous connection result' }); await flushPromises();
    expect(wrapper.emitted('sent')).toBeUndefined();
    expect(wrapper.text()).not.toContain('synthetic previous connection result');
    expect(state.compliance).toHaveBeenCalledTimes(2);
  });

  it('does not turn a late send failure into the next company’s error or retry request', async () => {
    let reject!: (reason: unknown) => void;
    state.send.mockReturnValueOnce(new Promise((_done, fail) => { reject = fail; }));
    const wrapper = mountPanel(); await flushPromises();
    await button(wrapper, 'efaktura_panel_send')!.trigger('click');
    await wrapper.setProps({ companyId: 'company-b', documentId: 'document-b', company: company('company-b') }); await flushPromises();
    reject({ response: { data: { message: 'synthetic stale failure' } } }); await flushPromises();
    expect(wrapper.text()).not.toContain('synthetic stale failure');
    expect(state.compliance).toHaveBeenCalledTimes(2);
  });

  it('ignores a late bridge resolution without fetching the next company’s status using it', async () => {
    const pending = deferred<{ ok: true; bridgeCompanyId: string }>();
    state.ensureBridge.mockReturnValueOnce(pending.promise).mockResolvedValue({ ok: true, bridgeCompanyId: 'bridge-b' });
    const wrapper = mountPanel({ localFirst: true, evoluDocumentId: 'local-a' }); await flushPromises();
    await wrapper.setProps({ companyId: 'company-b', documentId: 'document-b', evoluDocumentId: 'local-b', company: company('company-b') }); await flushPromises();
    pending.resolve({ ok: true, bridgeCompanyId: 'bridge-a' }); await flushPromises();
    expect(state.localStatus).toHaveBeenCalledExactlyOnceWith('local-b', 'bridge-b');
  });

  it.each(['navigate', 'unmount'])('does not publish send success after %s', async (action) => {
    const pending = deferred<unknown>();
    state.send.mockReturnValueOnce(pending.promise);
    const wrapper = mountPanel(); await flushPromises();
    await button(wrapper, 'efaktura_panel_send')!.trigger('click');
    if (action === 'navigate') {
      await wrapper.setProps({ companyId: 'company-b', documentId: 'document-b', company: company('company-b') }); await flushPromises();
    } else wrapper.unmount();
    pending.resolve({ message: 'synthetic stale success' }); await flushPromises();
    expect(wrapper.emitted('sent')).toBeUndefined();
    expect(wrapper.text()).not.toContain('synthetic stale success');
    expect(state.compliance).toHaveBeenCalledTimes(action === 'navigate' ? 2 : 1);
  });

  it('serializes double clicks before the provider send completes', async () => {
    const pending = deferred<unknown>(); state.send.mockReturnValueOnce(pending.promise);
    const wrapper = mountPanel(); await flushPromises();
    const send = button(wrapper, 'efaktura_panel_send')!;
    await send.trigger('click'); await send.trigger('click');
    expect(state.send).toHaveBeenCalledTimes(1);
    pending.resolve({}); await flushPromises();
  });
});

describe('automatic ePostman observations', () => {
  const statusRow = (extra: Record<string, unknown> = {}) => row({ delivery_status_available: true,
    enterprise_status: { status: 'UNCONFIRMED', mls_c3_status: 'TIMEOUT', mls_c5_status: 'OK',
      checked_at: '2026-11-12T12:00:00Z', error_code: null, ...extra } });
  const keyedCompany = () => {
    const value = company(); Object.assign(value.app_settings, { efaktura_epostman_api_key_set: true }); return value;
  };
  it('keeps SAPI acceptance separate from unconfirmed delivery, C3 timeout, and C5 OK', async () => {
    state.compliance.mockResolvedValue([statusRow()]);
    const wrapper = mountPanel({ company: keyedCompany() }); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_panel_status_accepted');
    expect(wrapper.get('[data-testid="epostman-enterprise-status"]').text()).toContain('efaktura_status_value_UNCONFIRMED');
    expect(wrapper.text()).toContain('efaktura_status_value_TIMEOUT');
    expect(wrapper.text()).toContain('efaktura_status_value_OK');
    expect(wrapper.text()).toContain('efaktura_status_scope');
    await button(wrapper, 'efaktura_panel_refresh_status')!.trigger('click'); await flushPromises();
    expect(state.refresh).toHaveBeenCalledExactlyOnceWith('company-a', 'document-a');
    expect(state.send).not.toHaveBeenCalled();
  });

  it('keeps previous observations and uses finite status/error labels for unfamiliar provider data', async () => {
    state.compliance.mockResolvedValue([statusRow({ status: '<untrusted-provider-status>', error_code: '<untrusted-provider-error>' })]);
    const wrapper = mountPanel({ company: keyedCompany() }); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_status_value_OTHER');
    expect(wrapper.text()).toContain('efaktura_status_error_check_failed');
    expect(wrapper.text()).toContain('efaktura_status_previous_observation');
    expect(wrapper.text()).not.toContain('<untrusted-provider-');
  });

  it('uses the portal fallback for an old submission without binding and never offers another send', async () => {
    state.compliance.mockResolvedValue([row({ enterprise_status: { error_code: 'binding_unavailable' } })]);
    const wrapper = mountPanel({ company: keyedCompany() }); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_status_error_binding_unavailable');
    expect(button(wrapper, 'efaktura_panel_refresh_status')).toBeUndefined();
    expect(button(wrapper, 'efaktura_panel_send')).toBeUndefined();
  });

  it('only reads the cache periodically and stops/fences on hidden tab, connection change and unmount', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    const visibility = vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    state.compliance.mockResolvedValue([statusRow()]);
    const wrapper = mountPanel({ company: keyedCompany() }); await flushPromises();
    await vi.advanceTimersByTimeAsync(30001); await flushPromises();
    expect(state.compliance).toHaveBeenCalledTimes(2);
    expect(state.refresh).not.toHaveBeenCalled(); expect(state.send).not.toHaveBeenCalled();
    visibility.mockReturnValue('hidden'); document.dispatchEvent(new Event('visibilitychange'));
    await vi.advanceTimersByTimeAsync(60000); expect(state.compliance).toHaveBeenCalledTimes(2);
    visibility.mockReturnValue('visible'); document.dispatchEvent(new Event('visibilitychange'));
    const delayed = deferred<unknown[]>(); state.compliance.mockReturnValueOnce(delayed.promise);
    await vi.advanceTimersByTimeAsync(30001);
    state.compliance.mockResolvedValue([]);
    await wrapper.setProps({ companyId: 'company-b', documentId: 'document-b', company: company('company-b') }); await flushPromises();
    delayed.resolve([statusRow()]); await flushPromises();
    expect(wrapper.find('[data-testid="epostman-enterprise-status"]').exists()).toBe(false);
    wrapper.unmount();
    const count = state.compliance.mock.calls.length;
    await vi.advanceTimersByTimeAsync(60000); expect(state.compliance).toHaveBeenCalledTimes(count);
  });

  it('retains displayed cache data after a background cache GET fails', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    state.compliance.mockResolvedValueOnce([statusRow()]).mockRejectedValueOnce(new Error('synthetic cache failure'));
    const wrapper = mountPanel({ company: keyedCompany() }); await flushPromises();
    await vi.advanceTimersByTimeAsync(30001); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_status_value_UNCONFIRMED');
    expect(wrapper.text()).toContain('efaktura_status_cache_unavailable');
    expect(state.send).not.toHaveBeenCalled();
  });
  it('does not overwrite a manual local refresh with a cache GET started earlier', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    const delayed = deferred<unknown[]>();
    state.localStatus.mockResolvedValueOnce([statusRow()]).mockReturnValueOnce(delayed.promise);
    state.localRefresh.mockResolvedValue([statusRow({ status: 'DELIVERED' })]);
    const wrapper = mountPanel({ localFirst: true, evoluDocumentId: 'local-document-a', company: keyedCompany() }); await flushPromises();
    await vi.advanceTimersByTimeAsync(30001);
    await button(wrapper, 'efaktura_panel_refresh_status')!.trigger('click'); await flushPromises();
    delayed.resolve([statusRow()]); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_status_value_DELIVERED');
    expect(wrapper.text()).not.toContain('efaktura_status_value_UNCONFIRMED');
    expect(state.localSend).not.toHaveBeenCalled();
  });

  it('reads and refreshes a bound historical ePostman status with independent Enterprise credentials after SAPI/contact changes', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'Date'] });
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    state.compliance.mockResolvedValue([statusRow()]);
    const value = keyedCompany();
    Object.assign(value.app_settings, { efaktura_sapi_client_id: '', efaktura_sapi_client_secret_set: false });
    const wrapper = mountPanel({ company: value, selectedContact: { country: 'US' } }); await flushPromises();
    expect(wrapper.text()).toContain('efaktura_status_value_UNCONFIRMED');
    expect(button(wrapper, 'efaktura_panel_send')).toBeUndefined();
    await button(wrapper, 'efaktura_panel_refresh_status')!.trigger('click'); await flushPromises();
    expect(state.refresh).toHaveBeenCalledExactlyOnceWith('company-a', 'document-a');
    const reads = state.compliance.mock.calls.length;
    await vi.advanceTimersByTimeAsync(30001); await flushPromises();
    expect(state.compliance).toHaveBeenCalledTimes(reads + 1);
    expect(state.send).not.toHaveBeenCalled();
  });

});
