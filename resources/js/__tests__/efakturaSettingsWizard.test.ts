import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, nextTick } from 'vue';

type TestCompany = Record<string, unknown> & { app_settings: Record<string, unknown> };

const state = vi.hoisted(() => ({
  presets: [] as { id: string; name: string; base_url: string }[],
  testResult: { ok: true, tested_at: '2026-07-23T10:00:00Z' } as Record<string, unknown>,
  testCalls: [] as Record<string, unknown>[],
  localFirst: false,
  localRows: [] as TestCompany[],
  onLocalUpdate: null as null | ((company: TestCompany) => void),
  notifySaved: vi.fn(),
}));

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, params?: Record<string, unknown>) =>
      params ? `${key}:${JSON.stringify(params)}` : key,
    locale: { value: 'en' },
  }),
}));
vi.mock('../composables/useInvoicingSaveFeedback', () => ({
  useInvoicingSaveFeedback: () => ({ notifySaved: state.notifySaved }),
}));
vi.mock('../evolu/flags', () => ({ isInvoicingLocalFirst: () => state.localFirst }));
vi.mock('../evolu/client', () => ({
  allCompaniesDetailQuery: {},
  useInvoicingEvolu: () => ({ getQueryRows: () => state.localRows }),
}));
vi.mock('../evolu/companyMap', () => ({ evoluCompanyToApi: (row: unknown) => row }));
vi.mock('../evolu/companySettingsCrud', () => ({ updateLocalEfakturaSettings: vi.fn((_evolu, id, payload) => {
  const row = state.localRows.find((company) => company.id === id);
  if (row) {
    row.app_settings = { ...row.app_settings, ...payload };
    state.onLocalUpdate?.(row);
  }
  return { ok: true };
}) }));
vi.mock('../evolu/bridgeCompanyEnsure', () => ({ ensureBridgeCompanyIdForLocalCompany: vi.fn(async () => ({ ok: true, bridgeCompanyId: 'bridge-1' })) }));
vi.mock('../evolu/efakturaInboxLive', () => ({ refreshEfakturaInboxLive: vi.fn(), reloadEfakturaInboxLive: vi.fn() }));
vi.mock('../composables/useInvoicingCompany', () => ({ asCompanyId: (id: string) => id }));
vi.mock('../store/stores', () => ({ useStoresStore: () => ({ stores: [] }) }));
vi.mock('../composables/useEfakturaFeature', () => ({
  useEfakturaFeature: () => ({
    enabled: computed(() => true),
    loaded: computed(() => true),
    cpdsPresets: computed(() => state.presets),
    mandatoryFrom: computed(() => '2027-01-01'),
    load: async () => true,
  }),
}));
vi.mock('../services/api', () => ({
  invoicingApi: {
    efaktura: {
      testConnection: async (_companyId: string, payload: Record<string, unknown>) => {
        state.testCalls.push(payload);
        return state.testResult;
      },
      testConnectionEphemeral: vi.fn(async (payload) => { state.testCalls.push(payload); return state.testResult; }),
      pollInbound: vi.fn(),
    },
    companies: { updateAppSettings: vi.fn(), get: vi.fn(), update: vi.fn() },
  },
}));

import EfakturaForm from '../components/invoicing/CompanyEfakturaSettingsForm.vue';
import { invoicingApi } from '../services/api';
import { updateLocalEfakturaSettings } from '../evolu/companySettingsCrud';

function skCompany(appSettings: Record<string, unknown> = {}): TestCompany {
  return {
    id: 'company-1',
    legal_name: 'Synthetic company',
    jurisdiction: 'eu_sk',
    tax_id: '2023980035',
    registration_number: '47615681',
    country: 'SK',
    app_settings: { efaktura_enabled: true, ...appSettings },
  };
}

function mountForm(company: Record<string, unknown>, mode: 'full' | 'inbound_only' = 'full') {
  return mount(EfakturaForm, {
    props: { companyId: 'company-1', company, mode },
    global: { stubs: { 'i18n-t': true } },
  });
}

describe('eFaktura settings wizard', () => {
  it('does not resurrect a disabled changed connection secret after the actual local settings merge and remount', async () => {
    state.localFirst = true;
    state.localRows = [skCompany({ ...connectedSettings(), efaktura_sapi_client_secret: 'synthetic-stored-secret' })];
    const real = await vi.importActual<typeof import('../evolu/companySettingsCrud')>('../evolu/companySettingsCrud');
    const client = {
      getQueryRows: () => state.localRows.map((row) => ({ ...row, appSettingsJson: JSON.stringify(row.app_settings) })),
      update: (_table: string, patch: { id: string; appSettingsJson: string }) => {
        state.localRows.find((row) => row.id === patch.id)!.app_settings = JSON.parse(patch.appSettingsJson);
        return { ok: true };
      },
    };
    const persist = (_evolu: unknown, id: string, payload: Parameters<typeof real.updateLocalEfakturaSettings>[2]) =>
      real.updateLocalEfakturaSettings(client as never, id as never, payload);
    vi.mocked(updateLocalEfakturaSettings).mockImplementationOnce(persist as never).mockImplementationOnce(persist as never);
    let wrapper = mountForm(state.localRows[0]);
    await wrapper.get('#efaktura-cpds-preset').setValue('epostman-test');
    await wrapper.get('#efaktura-client-id').setValue('synthetic-new-client');
    await wrapper.find('input[type="checkbox"]').setValue(false);
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(state.localRows[0].app_settings).not.toHaveProperty('efaktura_sapi_client_secret');
    expect(state.localRows[0].app_settings.efaktura_sapi_client_secret_set).toBe(false);
    wrapper.unmount();
    wrapper = mountForm(state.localRows[0]);
    await wrapper.find('input[type="checkbox"]').setValue(true);
    await testButton(wrapper).trigger('click'); await flushPromises();
    expect(state.testCalls).toHaveLength(1);
    expect(state.testCalls[0].efaktura_sapi_client_secret).toBeNull();
    wrapper.unmount();
  });

  it('shows persistent bridge retry guidance while receiving remains enabled', () => {
    const wrapper = mountForm(skCompany({ ...connectedSettings(), efaktura_inbound_bridge_sync_pending: true }));
    expect(wrapper.get('[data-testid="efaktura-sync-retry-alert"]').text()).toContain('efaktura_epostman_bridge_save_failed');
    wrapper.unmount();
  });

  it('blocks server save until the asynchronously loaded company matches the selected company', async () => {
    const wrapper = mountForm(skCompany(connectedSettings()));
    await wrapper.setProps({ companyId: 'company-2' });
    expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined();
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(invoicingApi.companies.updateAppSettings).not.toHaveBeenCalled();
    await wrapper.setProps({ company: { ...skCompany(connectedSettings()), id: 'company-2' } });
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(invoicingApi.companies.updateAppSettings).toHaveBeenCalledWith('company-2', expect.anything());
    wrapper.unmount();
  });

  it('resynchronizes server form values when company details arrive before the selected id changes', async () => {
    const wrapper = mountForm(skCompany(connectedSettings()));
    const companyB = { ...skCompany({ ...connectedSettings(), efaktura_sapi_client_id: 'synthetic-company-b-client' }), id: 'company-2' };
    await wrapper.setProps({ company: companyB });
    expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined();
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(invoicingApi.companies.updateAppSettings).not.toHaveBeenCalled();
    await wrapper.setProps({ companyId: 'company-2' });
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(invoicingApi.companies.updateAppSettings).toHaveBeenCalledWith('company-2', expect.objectContaining({ efaktura_sapi_client_id: 'synthetic-company-b-client' }));
    wrapper.unmount();
  });

  it('synchronizes and validates the local DIČ before clearing the bridge participant override', async () => {
    state.localFirst = true;
    state.localRows = [skCompany({ ...connectedSettings(), efaktura_sapi_client_secret: 'synthetic-stored-secret',
      efaktura_peppol_participant_id_derived: '0245:0000000000' })];
    const wrapper = mountForm(state.localRows[0]);
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(invoicingApi.companies.update).toHaveBeenCalledExactlyOnceWith('bridge-1', { tax_id: state.localRows[0].tax_id });
    expect(invoicingApi.companies.updateAppSettings).toHaveBeenCalledWith('bridge-1', expect.objectContaining({ efaktura_peppol_participant_id: '' }));
    expect(vi.mocked(invoicingApi.companies.update).mock.invocationCallOrder[0])
      .toBeLessThan(vi.mocked(invoicingApi.companies.updateAppSettings).mock.invocationCallOrder[0]);
    wrapper.unmount();
  });

  it.each(['foreign-jurisdiction', 'different-company', 'wrong-tax-response'])('does not enable a bridge with mismatched receiving identity: %s', async (kind) => {
    state.localFirst = true;
    state.localRows = [skCompany({ ...connectedSettings(), efaktura_sapi_client_secret: 'synthetic-stored-secret' })];
    if (kind === 'foreign-jurisdiction') vi.mocked(invoicingApi.companies.get).mockResolvedValue({ ...skCompany(), id: 'bridge-1', jurisdiction: 'us_wy', country: 'US' });
    if (kind === 'different-company') vi.mocked(invoicingApi.companies.get).mockResolvedValue({ ...skCompany(), id: 'bridge-1', legal_name: 'Unrelated synthetic company' });
    if (kind === 'wrong-tax-response') vi.mocked(invoicingApi.companies.update).mockResolvedValue({ ...skCompany(), id: 'bridge-1', tax_id: '0000000000' });
    const wrapper = mountForm(state.localRows[0]);
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(invoicingApi.companies.updateAppSettings).not.toHaveBeenCalled();
    if (kind !== 'wrong-tax-response') expect(invoicingApi.companies.update).not.toHaveBeenCalled();
    expect(status(wrapper)).toContain('status_save_failed');
    wrapper.unmount();
  });

  afterEach(() => vi.restoreAllMocks());
  beforeEach(() => {
    state.presets = [];
    state.testResult = { ok: true, tested_at: '2026-07-23T10:00:00Z' };
    state.testCalls = [];
    state.localFirst = false;
    state.localRows = [];
    state.onLocalUpdate = null;
    state.notifySaved.mockClear();
    invoicingApi.companies.updateAppSettings = vi.fn(async (_id, payload) => skCompany({ ...payload, efaktura_sapi_client_secret_set: true })) as typeof invoicingApi.companies.updateAppSettings;
    vi.mocked(invoicingApi.companies.get).mockReset().mockResolvedValue({ ...skCompany(), id: 'bridge-1', tax_id: null });
    vi.mocked(invoicingApi.companies.update).mockReset().mockImplementation(async (id, payload) => ({ ...skCompany(), ...payload, id }));
    vi.mocked(invoicingApi.efaktura.pollInbound).mockReset();
    vi.mocked(updateLocalEfakturaSettings).mockClear();
  });

  it('selecting a CPDS preset fills the base URL and hides the manual input', async () => {
    state.presets = [
      { id: 'p1', name: 'Postman One', base_url: 'https://one.test' },
      { id: 'p2', name: 'Postman Two', base_url: 'https://two.test' },
    ];
    const wrapper = mountForm(skCompany());
    await nextTick();

    const select = wrapper.find('#efaktura-cpds-preset');
    expect(select.exists()).toBe(true);
    await select.setValue('p2');

    expect(wrapper.find('#efaktura-base-url').exists()).toBe(false);
    expect(wrapper.text()).toContain('https://two.test');
  });

  it('the "other" option reveals the manual URL input', async () => {
    state.presets = [{ id: 'p1', name: 'Postman One', base_url: 'https://one.test' }];
    const wrapper = mountForm(skCompany());
    await nextTick();

    await wrapper.find('#efaktura-cpds-preset').setValue('custom');
    expect(wrapper.find('#efaktura-base-url').exists()).toBe(true);
  });

  it('a stored base URL matching a preset preselects it', async () => {
    state.presets = [{ id: 'p1', name: 'Postman One', base_url: 'https://one.test' }];
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: 'https://one.test' }));
    await nextTick();
    await nextTick();

    expect((wrapper.find('#efaktura-cpds-preset').element as HTMLSelectElement).value).toBe('p1');
  });

  it('the connection test reports success and failure codes', async () => {
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: 'https://one.test' }));
    await nextTick();

    const testButton = wrapper
      .findAll('button')
      .find((b) => b.text().includes('efaktura_test_connection'));
    expect(testButton).toBeDefined();

    await testButton!.trigger('click');
    await new Promise((resolve) => setTimeout(resolve));
    expect(wrapper.text()).toContain('invoicing.efaktura_test_ok');
    expect(state.testCalls).toHaveLength(1);

    state.testResult = { ok: false, code: 'invalid_credentials' };
    await testButton!.trigger('click');
    await new Promise((resolve) => setTimeout(resolve));
    expect(wrapper.text()).toContain('invoicing.efaktura_test_error_invalid_credentials');
  });

  it('shows the derived Peppol ID hint when no explicit override is set', async () => {
    const wrapper = mountForm(skCompany());
    await nextTick();

    // The derived hint carries the DIC-based ID computed client-side.
    expect(wrapper.text()).toContain('efaktura_peppol_derived_hint');
    expect(wrapper.text()).toContain('0245:2023980035');
  });

  it('a preset stored with a trailing slash still matches the saved URL', async () => {
    state.presets = [{ id: 'p1', name: 'Postman One', base_url: 'https://one.test/' }];
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: 'https://one.test' }));
    await nextTick();
    await nextTick();

    expect((wrapper.find('#efaktura-cpds-preset').element as HTMLSelectElement).value).toBe('p1');
  });

  it('editing credentials invalidates the stored connection test', async () => {
    const wrapper = mountForm(
      skCompany({
        efaktura_sapi_base_url: 'https://one.test',
        efaktura_sapi_client_id: 'client-1',
        efaktura_connection_tested_at: '2026-07-20T10:00:00Z',
      }),
    );
    await nextTick();

    expect(wrapper.text()).toContain('efaktura_connection_tested_at');

    await wrapper.find('#efaktura-client-id').trigger('input');
    await nextTick();

    expect(wrapper.text()).not.toContain('efaktura_connection_tested_at');
  });

  it('turning the module on for the first time preselects receiving without opting into auto-send', async () => {
    const wrapper = mountForm({
      ...skCompany(),
      app_settings: { efaktura_enabled: false },
    });
    await nextTick();

    const enable = wrapper.find('input[type="checkbox"]');
    await enable.setValue(true);
    await nextTick();

    const autoSend = wrapper
      .findAll('label')
      .find((l) => l.text().includes('efaktura_auto_send'))
      ?.find('input');
    expect((autoSend?.element as HTMLInputElement).checked).toBe(false);
    const receiving = wrapper.findAll('label').find((label) => label.text().includes('efaktura_inbound_enabled'))?.find('input');
    expect((receiving?.element as HTMLInputElement).checked).toBe(true);
  });

  it('offers fixed ePostman production and test choices and suppresses unsupported sending', async () => {
    state.presets = [
      { id: 'epostman-production', name: 'ePostman', base_url: 'https://postman.slovakodata.com' },
      { id: 'epostman-test', name: 'ePostman test', base_url: 'https://testpostman.slovakodata.com' },
    ];
    const wrapper = mountForm(skCompany({ efaktura_auto_send: true }));
    await wrapper.find('#efaktura-cpds-preset').setValue('epostman-production');
    expect(wrapper.find('#efaktura-base-url').exists()).toBe(false);
    expect(wrapper.find('[data-testid="epostman-setup"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('efaktura_epostman_receiving_only');
    expect(wrapper.findAll('label').some((label) => label.text().includes('efaktura_auto_send'))).toBe(false);
    expect(wrapper.text()).not.toContain('efaktura_epostman_test_notice');
    await wrapper.find('#efaktura-cpds-preset').setValue('epostman-test');
    expect(wrapper.text()).toContain('efaktura_epostman_test_notice');
    expect(wrapper.text()).toContain('https://testpostman.slovakodata.com');
  });

  it('does not reuse a saved provider secret when selecting another environment', async () => {
    state.presets = [
      { id: 'epostman-production', name: 'ePostman', base_url: 'https://postman.slovakodata.com' },
      { id: 'epostman-test', name: 'ePostman test', base_url: 'https://testpostman.slovakodata.com' },
    ];
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: 'https://postman.slovakodata.com',
      efaktura_sapi_client_id: 'synthetic-prod', efaktura_sapi_client_secret_set: true }));
    await wrapper.find('#efaktura-cpds-preset').setValue('epostman-test');
    expect((wrapper.find('#efaktura-client-id').element as HTMLInputElement).value).toBe('');
    const testButton = wrapper.findAll('button').find((button) => button.text().includes('efaktura_test_connection'))!;
    await testButton.trigger('click');
    expect(wrapper.text()).toContain('efaktura_connection_new_credentials');
    expect(state.testCalls).toHaveLength(0);
    wrapper.unmount();
  });

  it.each(['https://postman.slovakodata.com:443', 'https://POSTMAN.SLOVAKODATA.COM/'])('recognizes the canonical ePostman origin without enabling sending: %s', async (baseUrl) => {
    state.presets = [{ id: 'epostman-production', name: 'ePostman', base_url: 'https://postman.slovakodata.com' }];
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: baseUrl, efaktura_auto_send: true }));
    await nextTick();
    expect((wrapper.find('#efaktura-cpds-preset').element as HTMLSelectElement).value).toBe('epostman-production');
    expect(wrapper.find('[data-testid="epostman-receiving-only"]').exists()).toBe(true);
    expect(wrapper.findAll('label').some((label) => label.text().includes('efaktura_auto_send'))).toBe(false);
    expect(wrapper.find('#efaktura-peppol-id').exists()).toBe(false);
    wrapper.unmount();
  });

  it.each(['https://postman.slovakodata.com.attacker.test', 'https://postman.slovakodata.com:444'])('does not recognize a foreign origin as the named provider: %s', async (baseUrl) => {
    state.presets = [{ id: 'epostman-production', name: 'ePostman', base_url: 'https://postman.slovakodata.com' }];
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: baseUrl }));
    await nextTick();
    expect((wrapper.find('#efaktura-cpds-preset').element as HTMLSelectElement).value).toBe('custom');
    expect(wrapper.find('[data-testid="epostman-setup"]').exists()).toBe(false);
    wrapper.unmount();
  });

  it.each(['credentials', 'provider', 'company'])('ignores a late auth success after changing %s', async (changed) => {
    state.presets = [{ id: 'epostman-test', name: 'ePostman test', base_url: 'https://testpostman.slovakodata.com' }];
    const { invoicingApi } = await import('../services/api');
    let resolve!: (value: unknown) => void;
    vi.spyOn(invoicingApi.efaktura, 'testConnection').mockImplementation(() => new Promise((done) => { resolve = done; }) as never);
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: 'https://postman.slovakodata.com',
      efaktura_sapi_client_id: 'synthetic-prod', efaktura_sapi_client_secret_set: true }));
    const testButton = wrapper.findAll('button').find((button) => button.text().includes('efaktura_test_connection'))!;
    await testButton.trigger('click');
    if (changed === 'credentials') await wrapper.find('#efaktura-client-id').setValue('synthetic-replacement');
    if (changed === 'provider') await wrapper.find('#efaktura-cpds-preset').setValue('epostman-test');
    if (changed === 'company') await wrapper.setProps({ companyId: 'company-2', company: { ...skCompany(), id: 'company-2' } });
    resolve({ ok: true, tested_at: '2026-11-10T10:00:00Z' }); await flushPromises();
    expect(wrapper.text()).not.toContain('invoicing.efaktura_test_ok');
    expect(wrapper.text()).not.toContain('efaktura_connection_tested_at');
    wrapper.unmount();
  });

  it('inbound-only mode hides issuing options and preselects receiving instead', async () => {
    const wrapper = mountForm({ ...skCompany(), app_settings: { efaktura_enabled: false } }, 'inbound_only');
    await nextTick();

    expect(wrapper.find('[data-testid="efaktura-inbound-only-notice"]').exists()).toBe(true);

    await wrapper.find('input[type="checkbox"]').setValue(true);
    await nextTick();

    const labels = wrapper.findAll('label').map((l) => l.text());
    expect(labels.some((text) => text.includes('efaktura_auto_send'))).toBe(false);
    expect(wrapper.find('#efaktura-peppol-id').exists()).toBe(false);

    const inbound = wrapper
      .findAll('label')
      .find((l) => l.text().includes('efaktura_inbound_enabled'))
      ?.find('input');
    expect((inbound?.element as HTMLInputElement).checked).toBe(true);
  });

  it('inbound-only mode never sends auto-send or a sender Peppol ID to the server', async () => {
    const updateAppSettings = vi.fn(async (_id: string, payload: Record<string, unknown>) => ({
      ...skCompany(),
      app_settings: payload,
    }));
    const { invoicingApi } = await import('../services/api');
    (invoicingApi.companies.updateAppSettings as unknown) = updateAppSettings;

    const wrapper = mountForm(
      {
        ...skCompany(),
        app_settings: {
          efaktura_enabled: true,
          efaktura_auto_send: true,
          efaktura_peppol_participant_id: '0245:2023980035',
          efaktura_sapi_base_url: 'https://sapi.test',
          efaktura_sapi_client_id: 'client',
        },
      },
      'inbound_only',
    );
    await nextTick();

    await wrapper.find('form').trigger('submit.prevent');
    await nextTick();

    expect(updateAppSettings).toHaveBeenCalledTimes(1);
    const payload = updateAppSettings.mock.calls[0][1];
    expect(payload.efaktura_auto_send).toBe(false);
    expect(payload.efaktura_peppol_participant_id).toBe('');
  });

  const connectedSettings = () => ({
    efaktura_sapi_base_url: 'https://testpostman.slovakodata.com',
    efaktura_sapi_client_id: 'synthetic-client',
    efaktura_sapi_client_secret_set: true,
    efaktura_inbound_enabled: true,
    efaktura_connection_tested_at: '2026-11-12T10:00:00Z',
  });
  const status = (wrapper: ReturnType<typeof mountForm>) => wrapper.get('[data-testid="epostman-connection-status"]').text();
  const testButton = (wrapper: ReturnType<typeof mountForm>) => wrapper.findAll('button').find((button) => button.text().includes('efaktura_test_connection'))!;
  const pollButton = (wrapper: ReturnType<typeof mountForm>) => wrapper.findAll('button').find((button) => button.text().includes('efaktura_inbound_poll_now'))!;

  it('offers built-in production and sandbox without administrator configuration', async () => {
    const wrapper = mountForm(skCompany());
    await wrapper.get('#efaktura-cpds-preset').setValue('epostman-test');
    expect(wrapper.find('#efaktura-base-url').exists()).toBe(false);
    expect(wrapper.get('[data-testid="epostman-credentials-guide"]').text()).toContain('efaktura_epostman_sapi_not_api_key');
    expect(wrapper.findAll('a').some((link) => link.attributes('href') === 'https://testpostman.slovakodata.com')).toBe(true);
    expect(status(wrapper)).toContain('status_unsaved');
    wrapper.unmount();
  });

  it('requires a ten-digit DIČ rather than treating IČO fallback as an ePostman identity', () => {
    const wrapper = mountForm({ ...skCompany(connectedSettings()), tax_id: null, registration_number: '12345678' });
    expect(wrapper.text()).toContain('efaktura_epostman_missing_identity');
    expect(status(wrapper)).toContain('status_setup');
    wrapper.unmount();
  });

  it('clears a typed secret on manual provider changes and does not restore it when switching back', async () => {
    const wrapper = mountForm(skCompany({ efaktura_sapi_base_url: 'https://custom.test', efaktura_sapi_client_id: 'synthetic-client', efaktura_sapi_client_secret_set: true }));
    await wrapper.get('#efaktura-client-secret').setValue('synthetic-not-a-real-secret');
    await wrapper.get('#efaktura-base-url').setValue('https://another.test');
    expect((wrapper.get('#efaktura-client-secret').element as HTMLInputElement).value).toBe('');
    expect((wrapper.get('#efaktura-client-id').element as HTMLInputElement).value).toBe('');
    await wrapper.get('#efaktura-base-url').setValue('https://custom.test');
    await testButton(wrapper).trigger('click');
    expect(state.testCalls).toHaveLength(0);
    wrapper.unmount();
  });

  it('a failed retest supersedes a previous saved success', async () => {
    const wrapper = mountForm(skCompany(connectedSettings()));
    expect(status(wrapper)).toContain('status_saved');
    state.testResult = { ok: false, code: 'invalid_credentials' };
    await testButton(wrapper).trigger('click'); await flushPromises();
    expect(status(wrapper)).toContain('status_test_failed');
    expect(wrapper.text()).not.toContain('efaktura_connection_tested_at');
    wrapper.unmount();
  });

  it.each([
    [0, 0, 'empty'], [1, 0, 'received'], [1, 1, 'poll_failed'],
  ])('represents provider download history without claiming pending expenses: %s/%s', (imported, failed, expected) => {
    const wrapper = mountForm(skCompany({ ...connectedSettings(), efaktura_inbound_last_poll_at: '2026-11-12T11:00:00Z', efaktura_inbound_last_poll_stats: { imported, acknowledged: 0, skipped: 0, failed } }));
    expect(status(wrapper)).toContain(`status_${expected}`);
    wrapper.unmount();
  });

  it('blocks polling unsaved credentials and invalidates earlier download evidence', async () => {
    const wrapper = mountForm(skCompany({ ...connectedSettings(), efaktura_inbound_last_poll_at: '2026-11-12T11:00:00Z', efaktura_inbound_last_poll_stats: { imported: 1, acknowledged: 1, skipped: 0, failed: 0 } }));
    await wrapper.get('#efaktura-client-id').setValue('synthetic-replacement');
    expect(status(wrapper)).toContain('status_unsaved');
    expect(pollButton(wrapper).attributes('disabled')).toBeDefined();
    await pollButton(wrapper).trigger('click');
    expect(invoicingApi.efaktura.pollInbound).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('preserves an unchanged stored secret when saving', async () => {
    const wrapper = mountForm(skCompany(connectedSettings()));
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(invoicingApi.companies.updateAppSettings).toHaveBeenCalledTimes(1);
    expect(vi.mocked(invoicingApi.companies.updateAppSettings).mock.calls[0][1]).not.toHaveProperty('efaktura_sapi_client_secret');
    wrapper.unmount();
  });

  it('keeps ephemeral local-first verification unsaved until the matching settings are saved', async () => {
    state.localFirst = true;
    const company = skCompany({ ...connectedSettings(), efaktura_sapi_base_url: '', efaktura_sapi_client_id: '', efaktura_sapi_client_secret_set: false, efaktura_connection_tested_at: null });
    state.localRows = [company];
    const wrapper = mountForm(company);
    await wrapper.get('#efaktura-cpds-preset').setValue('epostman-test');
    await wrapper.get('#efaktura-client-id').setValue('synthetic-new');
    await wrapper.get('#efaktura-client-secret').setValue('synthetic-secret');
    await testButton(wrapper).trigger('click'); await flushPromises();
    expect(updateLocalEfakturaSettings).not.toHaveBeenCalled();
    expect(status(wrapper)).toContain('status_unsaved');
    expect((wrapper.get('#efaktura-client-id').element as HTMLInputElement).value).toBe('synthetic-new');
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(updateLocalEfakturaSettings).toHaveBeenCalledWith(expect.anything(), 'company-1', expect.objectContaining({ efaktura_sapi_client_id: 'synthetic-new', efaktura_connection_tested_at: '2026-07-23T10:00:00Z' }));
    expect(invoicingApi.companies.updateAppSettings).toHaveBeenCalledWith('bridge-1', expect.objectContaining({ efaktura_sapi_client_id: 'synthetic-new', efaktura_sapi_client_secret: 'synthetic-secret' }));
    wrapper.unmount();
  });

  it('reports local bridge failure, retains retry credentials and handles own parent updates during saving', async () => {
    state.localFirst = true;
    const company = skCompany({ ...connectedSettings(), efaktura_sapi_client_secret: 'synthetic-stored-secret' });
    state.localRows = [company];
    const wrapper = mountForm(company);
    state.onLocalUpdate = (updated) => { void wrapper.setProps({ company: { ...updated, app_settings: { ...updated.app_settings } } }); };
    vi.mocked(invoicingApi.companies.updateAppSettings).mockRejectedValueOnce(new Error('synthetic bridge failure'));
    await wrapper.get('#efaktura-client-secret').setValue('synthetic-replacement-secret');
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(status(wrapper)).toContain('status_save_failed');
    expect(state.notifySaved).not.toHaveBeenCalled();
    expect((wrapper.get('#efaktura-client-secret').element as HTMLInputElement).value).toBe('synthetic-replacement-secret');
    expect(wrapper.get('fieldset').attributes('disabled')).toBeUndefined();
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(state.notifySaved).toHaveBeenCalledTimes(1);
    expect(status(wrapper)).not.toContain('status_save_failed');
    expect(wrapper.get('fieldset').attributes('disabled')).toBeUndefined();
    wrapper.unmount();
  });

  it.each(['save', 'poll'])('ignores a late %s result after selecting another company', async (operation) => {
    let resolve!: (value: Record<string, unknown>) => void;
    if (operation === 'save') vi.mocked(invoicingApi.companies.updateAppSettings).mockImplementationOnce(() => new Promise((done) => { resolve = done; }) as never);
    else vi.mocked(invoicingApi.efaktura.pollInbound).mockImplementationOnce(() => new Promise((done) => { resolve = done; }) as never);
    const wrapper = mountForm(skCompany(connectedSettings()));
    if (operation === 'save') await wrapper.get('form').trigger('submit.prevent');
    else await pollButton(wrapper).trigger('click');
    await wrapper.setProps({ companyId: 'company-2', company: { ...skCompany(connectedSettings()), id: 'company-2' } });
    resolve(operation === 'save' ? skCompany({ ...connectedSettings(), efaktura_sapi_client_id: 'stale-id' }) : { imported: 1, acknowledged: 1, failed: 0, skipped: 0, polled_at: '2026-11-12T11:00:00Z' });
    await flushPromises();
    expect((wrapper.get('#efaktura-client-id').element as HTMLInputElement).value).toBe('synthetic-client');
    expect(status(wrapper)).toContain('status_saved');
    expect(wrapper.emitted('updated')).toBeUndefined();
    expect(wrapper.get('fieldset').attributes('disabled')).toBeUndefined();
    wrapper.unmount();
  });


  it('retains bridge failure across remount and clears the encrypted retry marker only after successful retry', async () => {
    state.localFirst = true;
    state.localRows = [skCompany({ ...connectedSettings(), efaktura_sapi_client_secret: 'synthetic-stored-secret' })];
    let wrapper = mountForm(state.localRows[0]);
    vi.mocked(invoicingApi.companies.updateAppSettings).mockRejectedValueOnce(new Error('synthetic failure'));
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(state.localRows[0].app_settings.efaktura_inbound_bridge_sync_pending).toBe(true);
    wrapper.unmount();
    wrapper = mountForm(state.localRows[0]);
    expect(status(wrapper)).toContain('status_save_failed');
    expect(pollButton(wrapper).attributes('disabled')).toBeDefined();
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(state.localRows[0].app_settings.efaktura_inbound_bridge_sync_pending).toBe(false);
    expect(status(wrapper)).not.toContain('status_save_failed');
    expect(vi.mocked(invoicingApi.companies.updateAppSettings).mock.calls.every(([, payload]) => !Object.prototype.hasOwnProperty.call(payload, 'efaktura_inbound_bridge_sync_pending'))).toBe(true);
    wrapper.unmount();
  });

  it('keeps interrupted bridge synchronization pending and treats a failed marker-clear write as retryable', async () => {
    state.localFirst = true;
    state.localRows = [skCompany({ ...connectedSettings(), efaktura_sapi_client_secret: 'synthetic-stored-secret', efaktura_inbound_bridge_sync_pending: true })];
    const wrapper = mountForm(state.localRows[0]);
    expect(status(wrapper)).toContain('status_save_failed');
    const original = vi.mocked(updateLocalEfakturaSettings).getMockImplementation()!;
    vi.mocked(updateLocalEfakturaSettings).mockImplementationOnce(original).mockReturnValueOnce({ ok: false } as never);
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(state.localRows[0].app_settings.efaktura_inbound_bridge_sync_pending).toBe(true);
    expect(status(wrapper)).toContain('status_save_failed');
    expect(state.notifySaved).not.toHaveBeenCalled();
    wrapper.unmount();
  });


  it('shows an unfinished server disable after failed bridge synchronization and remount', async () => {
    state.localFirst = true;
    state.localRows = [skCompany({ ...connectedSettings(), efaktura_sapi_client_secret: 'synthetic-stored-secret' })];
    let wrapper = mountForm(state.localRows[0]);
    await wrapper.get('input[type="checkbox"]').setValue(false);
    vi.mocked(invoicingApi.companies.updateAppSettings).mockRejectedValueOnce(new Error('synthetic disable failure'));
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(state.localRows[0].app_settings.efaktura_inbound_bridge_sync_pending).toBe(true);
    wrapper.unmount();
    wrapper = mountForm(state.localRows[0]);
    expect(wrapper.get('[data-testid="efaktura-sync-retry-alert"]').text()).toContain('efaktura_epostman_bridge_save_failed');
    expect(wrapper.find('[data-testid="epostman-connection-status"]').exists()).toBe(false);
    await wrapper.get('form').trigger('submit.prevent'); await flushPromises();
    expect(state.localRows[0].app_settings.efaktura_inbound_bridge_sync_pending).toBe(false);
    expect(vi.mocked(invoicingApi.companies.updateAppSettings).mock.lastCall?.[1]).toMatchObject({ efaktura_enabled: false, efaktura_inbound_enabled: false });
    expect(wrapper.find('[data-testid="efaktura-sync-retry-alert"]').exists()).toBe(false);
    wrapper.unmount();
  });

});
