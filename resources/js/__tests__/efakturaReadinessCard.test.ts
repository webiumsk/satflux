import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed } from 'vue';

const state = vi.hoisted(() => ({
  featureEnabled: true,
  contacts: [] as Record<string, unknown>[],
  contactPages: [] as Record<string, unknown>[][],
  contactRequests: [] as string[],
  pendingContactResponse: null as Promise<{
    data: Record<string, unknown>[];
    meta: { last_page: number };
  }> | null,
}));

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, params?: Record<string, unknown>) =>
      params ? `${key}:${JSON.stringify(params)}` : key,
    locale: { value: 'en' },
  }),
}));
vi.mock('../evolu/flags', () => ({ isInvoicingLocalFirst: () => false }));
vi.mock('../composables/useEfakturaFeature', () => ({
  useEfakturaFeature: () => ({
    enabled: computed(() => state.featureEnabled),
    mandatoryFrom: computed(() => '2027-01-01'),
    load: async () => state.featureEnabled,
  }),
}));
vi.mock('../services/api', () => ({
  invoicingApi: {
    contacts: {
      list: async (companyId: string, params?: { page?: number }) => {
        state.contactRequests.push(companyId);
        if (state.pendingContactResponse) {
          const response = state.pendingContactResponse;
          state.pendingContactResponse = null;
          return response;
        }
        const pages = state.contactPages.length ? state.contactPages : [state.contacts];
        const page = Number(params?.page ?? 1);
        return { data: pages[page - 1] ?? [], meta: { last_page: pages.length } };
      },
    },
    companies: { get: vi.fn() },
  },
}));

import EfakturaReadinessCard from '../components/invoicing/EfakturaReadinessCard.vue';

function skPayerCompany(appSettings: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    jurisdiction: 'eu_sk',
    vat_payer: true,
    vat_status: 'payer',
    tax_id: '2023980035',
    app_settings: appSettings,
  };
}

function mountCard(company: Record<string, unknown> | null, compact = false) {
  return mount(EfakturaReadinessCard, {
    props: { companyId: 'company-1', company, ...(compact ? { compact } : {}) },
    global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } },
  });
}

describe('EfakturaReadinessCard', () => {
  beforeEach(() => {
    window.localStorage.clear();
    state.featureEnabled = true;
    state.contacts = [{ name: 'Buyer', country: 'SK', tax_id: null, registration_number: null }];
    state.contactPages = [];
    state.contactRequests = [];
    state.pendingContactResponse = null;
  });

  it('renders nothing for companies outside the e-faktura scope', async () => {
    const wrapper = mountCard({ jurisdiction: 'eu_cz', vat_payer: true, vat_status: 'payer' });
    await flushPromises();
    expect(wrapper.text()).toBe('');
  });

  it('shows the full checklist with done and pending steps when the module is on', async () => {
    const wrapper = mountCard(
      skPayerCompany({
        efaktura_enabled: true,
        efaktura_sapi_base_url: 'https://sapi.test',
        efaktura_sapi_client_id: 'client',
        efaktura_sapi_client_secret_set: true,
      }),
    );
    await flushPromises();

    expect(wrapper.text()).toContain('efaktura_readiness_intro');
    expect(wrapper.text()).toContain('efaktura_readiness_provider');
    expect(wrapper.text()).toContain('efaktura_readiness_tested');
    // One SK contact without IDs feeds the contacts step counter.
    expect(wrapper.text()).toContain('"count":1');
    expect(wrapper.find('details').exists()).toBe(false);
  });

  it('shows only the teaser and the contacts step while the module is globally off', async () => {
    state.featureEnabled = false;
    const wrapper = mountCard(skPayerCompany());
    await flushPromises();

    expect(wrapper.text()).toContain('efaktura_readiness_teaser');
    expect(wrapper.text()).toContain('efaktura_readiness_contacts');
    expect(wrapper.text()).not.toContain('efaktura_readiness_provider');
  });

  it('hides itself entirely once everything is ready', async () => {
    state.contacts = [{ name: 'Buyer', country: 'SK', tax_id: '1234567890' }];
    const wrapper = mountCard(
      skPayerCompany({
        efaktura_enabled: true,
        efaktura_sapi_base_url: 'https://sapi.test',
        efaktura_sapi_client_id: 'client',
        efaktura_sapi_client_secret_set: true,
        efaktura_connection_tested_at: '2026-07-23T10:00:00Z',
        efaktura_auto_send: false,
      }),
    );
    await flushPromises();

    // auto_send is only a recommendation - it must not keep the card alive.
    expect(wrapper.text()).toBe('');
  });

  it('counts uncovered contacts across every API page', async () => {
    state.contactPages = [
      [{ name: 'A', country: 'SK', tax_id: null }],
      [
        { name: 'B', country: 'SK', tax_id: null },
        { name: 'C', country: 'SK', tax_id: '1234567890' },
      ],
    ];
    const wrapper = mountCard(skPayerCompany());
    await flushPromises();

    // Two uncovered SK contacts live on different pages - both must count.
    expect(wrapper.text()).toContain('"count":2');
  });

  it('shows the inbound-only checklist for SK non-payers when the module is on', async () => {
    const wrapper = mountCard({
      ...skPayerCompany({ efaktura_enabled: true, efaktura_sapi_base_url: 'https://sapi.test' }),
      vat_payer: false,
      vat_status: 'none',
    });
    await flushPromises();

    const text = wrapper.text();
    expect(text).toContain('efaktura_readiness_inbound_title');
    expect(text).toContain('efaktura_readiness_inbound_enabled');
    // Receiving does not depend on buyer IDs nor on auto-send.
    expect(text).not.toContain('efaktura_readiness_contacts');
    expect(text).not.toContain('efaktura_readiness_auto_send');
  });

  it('stays hidden for non-payers while the module is globally off', async () => {
    state.featureEnabled = false;
    const wrapper = mountCard({ ...skPayerCompany(), vat_payer: false, vat_status: 'none' });
    await flushPromises();

    expect(wrapper.text()).toBe('');
  });

  it('hides the inbound-only checklist once receiving is fully set up', async () => {
    const wrapper = mountCard({
      ...skPayerCompany({
        efaktura_enabled: true,
        efaktura_sapi_base_url: 'https://sapi.test',
        efaktura_sapi_client_id: 'client',
        efaktura_sapi_client_secret_set: true,
        efaktura_connection_tested_at: '2026-07-23T10:00:00Z',
        efaktura_inbound_enabled: true,
      }),
      vat_payer: false,
      vat_status: 'none',
    });
    await flushPromises();

    expect(wrapper.text()).toBe('');
  });

  it('re-evaluates buyer identifiers when an inbound-only company becomes a full payer', async () => {
    const wrapper = mountCard({ ...skPayerCompany(), vat_payer: false, vat_status: 'none' });
    await flushPromises();
    expect(wrapper.text()).not.toContain('efaktura_readiness_contacts');

    // One SK contact without IDs is set up in beforeEach - the switch to
    // full mode must walk the contacts and surface it.
    await wrapper.setProps({ company: skPayerCompany() });
    await flushPromises();

    expect(wrapper.text()).toContain('efaktura_readiness_title');
    expect(wrapper.text()).toContain('efaktura_readiness_contacts:{"count":1}');
  });

  it('snoozes via localStorage', async () => {
    const wrapper = mountCard(skPayerCompany());
    await flushPromises();
    expect(wrapper.text()).toContain('efaktura_readiness_title');

    await wrapper.find('button').trigger('click');
    expect(wrapper.text()).toBe('');
    expect(window.localStorage.getItem('satflux.efaktura_readiness_snooze.company-1')).not.toBeNull();

    const again = mountCard(skPayerCompany());
    await flushPromises();
    expect(again.text()).toBe('');
  });
  it('uses only the independent receiving credentials and proof for a receiving-only company', async () => {
    const company: Record<string, unknown> = { ...skPayerCompany({ efaktura_enabled: true, efaktura_inbound_enabled: true,
      efaktura_inbound_use_shared_connection: false,
      efaktura_inbound_sapi_base_url: 'https://testpostman.slovakodata.com',
      efaktura_inbound_sapi_client_id: 'synthetic-receiver', efaktura_inbound_sapi_client_secret_set: true,
      efaktura_inbound_connection_tested_at: '2026-11-13T10:00:00Z' }), vat_status: 'none', vat_payer: false };
    const wrapper = mountCard(company); await flushPromises();
    expect(wrapper.text()).toBe('');
    await wrapper.setProps({ company: { ...company, app_settings: { ...(company.app_settings as Record<string, unknown>),
      efaktura_inbound_sapi_client_secret_set: false, efaktura_sapi_client_secret_set: true } } });
    expect(wrapper.text()).toContain('efaktura_readiness_credentials');
    wrapper.unmount();
  });

  it('requires separate receiving setup when a full payer enables a separate receiver', async () => {
    state.contacts = [];
    const wrapper = mountCard(skPayerCompany({ efaktura_enabled: true, efaktura_sapi_base_url: 'https://sapi.test',
      efaktura_sapi_client_id: 'synthetic-sender', efaktura_sapi_client_secret_set: true,
      efaktura_connection_tested_at: '2026-11-13T10:00:00Z', efaktura_inbound_enabled: true,
      efaktura_inbound_use_shared_connection: false }));
    await flushPromises();
    expect(wrapper.text()).toContain('efaktura_readiness_receiving_connection');
    wrapper.unmount();
  });

  it('keeps the unverified title visible in a closed native disclosure and snoozes from expanded content', async () => {
    const wrapper = mountCard(skPayerCompany(), true);
    await flushPromises();
    const details = wrapper.get('details');
    expect((details.element as HTMLDetailsElement).open).toBe(false);
    expect(details.attributes('role')).toBeUndefined();
    const summary = details.get('summary');
    expect(summary.text()).toContain('invoicing.efaktura_readiness_title');
    expect(summary.find('button, a').exists()).toBe(false);
    expect(summary.text()).not.toContain('efaktura_readiness_intro');

    await summary.trigger('click');
    expect((details.element as HTMLDetailsElement).open).toBe(true);
    expect(details.get('p').text()).toContain('invoicing.efaktura_readiness_intro');
    expect(details.get('ul').text()).toContain('efaktura_readiness_credentials');
    await details.get('button').trigger('click');
    expect(wrapper.find('details').exists()).toBe(false);
    const snoozedUntil = Number(localStorage.getItem('satflux.efaktura_readiness_snooze.company-1'));
    expect(snoozedUntil).toBeGreaterThan(Date.now() + 13 * 24 * 60 * 60 * 1000);
  });

  it('keeps receiving-only guidance and the unverified receiving title in compact mode', async () => {
    const wrapper = mountCard({ ...skPayerCompany(), vat_payer: false, vat_status: 'none' }, true);
    await flushPromises();
    expect(wrapper.get('summary').text()).toContain('invoicing.efaktura_readiness_inbound_title');
    expect(wrapper.get('p').text()).toContain('invoicing.efaktura_readiness_inbound_intro');
    expect(wrapper.get('ul').text()).not.toContain('efaktura_readiness_contacts');
    expect(wrapper.get('ul').text()).not.toContain('efaktura_readiness_auto_send');
    expect(state.contactRequests).toEqual([]);
  });

  it('reloads coverage and collapses when switching between companies in the same mode', async () => {
    const wrapper = mountCard(skPayerCompany(), true);
    await flushPromises();
    const previousDetails = wrapper.get('details').element as HTMLDetailsElement;
    await wrapper.get('summary').trigger('click');
    expect(previousDetails.open).toBe(true);

    await wrapper.setProps({ companyId: 'company-2', company: skPayerCompany() });
    await flushPromises();
    expect(state.contactRequests).toEqual(['company-1', 'company-2']);
    const currentDetails = wrapper.get('details').element as HTMLDetailsElement;
    expect(currentDetails).not.toBe(previousDetails);
    expect(currentDetails.open).toBe(false);
    expect(localStorage.length).toBe(0);
    await wrapper.get('summary').trigger('click');
    await wrapper.get('button').trigger('click');
    expect(localStorage.getItem('satflux.efaktura_readiness_snooze.company-2')).not.toBeNull();
    expect(localStorage.getItem('satflux.efaktura_readiness_snooze.company-1')).toBeNull();
  });

  it('ignores delayed coverage from the previous company after a same-mode switch', async () => {
    let resolvePrevious!: (response: { data: Record<string, unknown>[]; meta: { last_page: number } }) => void;
    state.pendingContactResponse = new Promise((resolve) => { resolvePrevious = resolve; });
    const wrapper = mountCard(skPayerCompany(), true);
    await flushPromises();
    expect(wrapper.find('details').exists()).toBe(false);

    await wrapper.setProps({ companyId: 'company-2', company: skPayerCompany() });
    await flushPromises();
    expect(wrapper.get('ul').text()).toContain('efaktura_readiness_contacts:{"count":1}');
    const currentDetails = wrapper.get('details').element;
    await wrapper.get('summary').trigger('click');

    resolvePrevious({ data: [], meta: { last_page: 1 } });
    await flushPromises();
    expect(wrapper.get('ul').text()).toContain('efaktura_readiness_contacts:{"count":1}');
    expect(wrapper.get('details').element).toBe(currentDetails);
    expect((currentDetails as HTMLDetailsElement).open).toBe(true);
  });
});
