import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import type { VatSummary } from '../evolu/vatReport';

const state = vi.hoisted(() => ({
    loading: false,
    summary: null as VatSummary | null,
}));

vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { companyId: 'company-1' } }),
}));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({ t: (key: string) => key }),
}));

// Avoid pulling in evolu/client (real Evolu instance) via the CSV helper
// and the heavy invoicing shell/header components.
vi.mock('../evolu/documentBulkLocal', () => ({
    downloadCsvBlob: vi.fn(),
}));
vi.mock('../components/invoicing/InvoicingPageShell.vue', () => ({
    default: { name: 'InvoicingPageShell', template: '<div><slot /></div>' },
}));
vi.mock('../components/invoicing/InvoicingAppHeader.vue', () => ({
    default: { name: 'InvoicingAppHeader', template: '<div />' },
}));

vi.mock('../composables/useVatReport', () => ({
    useVatReport: () => ({
        loading: ref(state.loading),
        period: ref({ preset: 'this_year', customFrom: '', customTo: '' }),
        summary: ref(state.summary),
    }),
}));

// The report page pulls the period preset labels only; keep the real module.
async function mountPage() {
    const { default: VatReport } = await import('../pages/invoicing/VatReport.vue');
    return mount(VatReport, {
        global: {
            stubs: {
                InvoicingPageShell: { template: '<div><slot /></div>' },
                InvoicingAppHeader: true,
            },
        },
    });
}

const summaryFixture: VatSummary = {
    from: '2026-01-01',
    to: '2026-12-31',
    byCurrency: [
        {
            currency: 'EUR',
            documentCount: 3,
            turnover: 346,
            base: 300,
            vat: 46,
            byRate: [
                { rate: 0, base: 100, vat: 0, gross: 100 },
                { rate: 23, base: 200, vat: 46, gross: 246 },
            ],
        },
    ],
};

describe('VatReport page', () => {
    beforeEach(() => {
        state.loading = false;
        state.summary = summaryFixture;
    });

    it('renders per-rate rows and per-currency totals', async () => {
        const wrapper = await mountPage();
        const text = wrapper.text();

        expect(text).toContain('EUR');
        expect(text).toContain('23%');
        expect(text).toContain('246.00'); // 23% gross
        expect(text).toContain('300.00'); // net base total
        expect(text).toContain('46.00'); // vat total
        expect(wrapper.find('table').exists()).toBe(true);
    });

    it('shows the empty state when no documents fall in the period', async () => {
        state.summary = { from: '2026-01-01', to: '2026-12-31', byCurrency: [] };
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('invoicing.vat_report_empty');
        expect(wrapper.find('table').exists()).toBe(false);
    });
});
