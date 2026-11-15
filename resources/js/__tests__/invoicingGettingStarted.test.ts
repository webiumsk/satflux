import { mount, flushPromises } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({
  contacts: [] as unknown[],
  documents: [] as unknown[],
  fetched: null as Record<string, unknown> | null,
}));

vi.mock('vue-i18n', () => ({
  useI18n: () => ({
    t: (key: string, params?: Record<string, unknown>) =>
      params ? `${key}:${JSON.stringify(params)}` : key,
    locale: { value: 'en' },
  }),
}));
vi.mock('../evolu/flags', () => ({ isInvoicingLocalFirst: () => false }));
vi.mock('../services/api', () => ({
  invoicingApi: {
    contacts: { list: async () => ({ data: state.contacts, meta: {} }) },
    documents: { list: async () => state.documents },
    companies: { get: async () => state.fetched },
  },
}));

import Card from '../components/invoicing/InvoicingGettingStartedCard.vue';

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

function fullCompany(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    id: 'c1',
    legal_name: 'ACME s.r.o.',
    street: 'Main 1',
    city: 'Bratislava',
    postal_code: '81101',
    tax_id: '2023980035',
    logo_url: 'data:image/png;base64,xxx',
    ...overrides,
  };
}

function mountCard(company: Record<string, unknown> | null, compact = false) {
  return mount(Card, {
    props: { companyId: 'c1', company, ...(compact ? { compact } : {}) },
    global: { stubs: { RouterLink: RouterLinkStub } },
  });
}

describe('InvoicingGettingStartedCard', () => {
  beforeEach(() => {
    state.contacts = [];
    state.documents = [];
    state.fetched = null;
    localStorage.clear();
  });

  it('hides once every step is complete', async () => {
    state.contacts = [{ id: 'k1' }];
    state.documents = [{ id: 'd1' }];
    const wrapper = mountCard(fullCompany());
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
  });

  it('shows outstanding steps with a 0-of-4 progress label', async () => {
    const wrapper = mountCard(fullCompany({ street: '', city: '', postal_code: '', logo_url: '' }));
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('invoicingOnboarding.checklist_progress:{"done":0,"total":4}');
    expect(wrapper.text()).toContain('invoicingOnboarding.checklist_profile');
    expect(wrapper.text()).toContain('invoicingOnboarding.checklist_document');
    expect(wrapper.find('details').exists()).toBe(false);
  });

  it('counts profile and branding as done from the company record alone', async () => {
    const wrapper = mountCard(fullCompany());
    await flushPromises();
    // Full profile + logo done; no contacts or documents yet => 2 of 4.
    expect(wrapper.text()).toContain('invoicingOnboarding.checklist_progress:{"done":2,"total":4}');
  });

  it('treats a company missing address or tax id as an incomplete profile', async () => {
    state.contacts = [{ id: 'k1' }];
    state.documents = [{ id: 'd1' }];
    const wrapper = mountCard(fullCompany({ postal_code: '', tax_id: '', registration_number: '' }));
    await flushPromises();
    // Profile incomplete, everything else done => still visible, 3 of 4.
    expect(wrapper.find('[role="status"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('invoicingOnboarding.checklist_progress:{"done":3,"total":4}');
  });

  it('dismiss hides the card and persists per company', async () => {
    const wrapper = mountCard(fullCompany({ logo_url: '' }));
    await flushPromises();
    expect(wrapper.find('[role="status"]').exists()).toBe(true);

    await wrapper.find('button').trigger('click');
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
    expect(localStorage.getItem('satflux.invoicing_getting_started.c1')).toBe('1');

    const remounted = mountCard(fullCompany({ logo_url: '' }));
    await flushPromises();
    expect(remounted.find('[role="status"]').exists()).toBe(false);
  });

  it('stays hidden when no company record can be resolved', async () => {
    const wrapper = mountCard(null);
    await flushPromises();
    // Server mode, prop omitted and self-fetch yields nothing => nothing to show.
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
  });

  it('self-fetches the company record in server mode when not passed as a prop', async () => {
    state.fetched = fullCompany({ logo_url: '' });
    const wrapper = mountCard(null);
    await flushPromises();
    // Fetched profile complete, logo missing, no contacts/documents => 1 of 4.
    expect(wrapper.find('[role="status"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('invoicingOnboarding.checklist_progress:{"done":1,"total":4}');
  });

  it('offers a closed native disclosure with progress and actions outside the summary in compact mode', async () => {
    const wrapper = mountCard(fullCompany({ logo_url: '' }), true);
    await flushPromises();

    const details = wrapper.get('details');
    expect((details.element as HTMLDetailsElement).open).toBe(false);
    expect(details.attributes('role')).toBeUndefined();
    const summary = details.get('summary');
    expect(summary.text()).toContain('invoicingOnboarding.checklist_title');
    expect(summary.text()).toContain('invoicingOnboarding.checklist_progress:{"done":1,"total":4}');
    expect(summary.find('button, a').exists()).toBe(false);

    await summary.trigger('click');
    expect((details.element as HTMLDetailsElement).open).toBe(true);
    expect(details.get('ul').text()).toContain('invoicingOnboarding.checklist_branding');
    await details.get('button').trigger('click');
    expect(wrapper.find('details').exists()).toBe(false);
    expect(localStorage.getItem('satflux.invoicing_getting_started.c1')).toBe('1');
  });

  it('resets disclosure expansion when the company changes without persisting expansion', async () => {
    const wrapper = mountCard(fullCompany({ logo_url: '' }), true);
    await flushPromises();
    const previousDetails = wrapper.get('details').element as HTMLDetailsElement;
    await wrapper.get('summary').trigger('click');
    expect(previousDetails.open).toBe(true);

    await wrapper.setProps({ companyId: 'c2', company: fullCompany({ id: 'c2', logo_url: '' }) });
    await flushPromises();
    const currentDetails = wrapper.get('details').element as HTMLDetailsElement;
    expect(currentDetails).not.toBe(previousDetails);
    expect(currentDetails.open).toBe(false);
    expect(localStorage.length).toBe(0);
    await wrapper.get('summary').trigger('click');
    await wrapper.get('button').trigger('click');
    expect(localStorage.getItem('satflux.invoicing_getting_started.c2')).toBe('1');
    expect(localStorage.getItem('satflux.invoicing_getting_started.c1')).toBeNull();
  });
});
