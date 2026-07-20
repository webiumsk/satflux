import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ref } from 'vue';
import type { VatSummary } from '../evolu/vatReport';

const state = vi.hoisted(() => ({ base: 0 }));

vi.mock('vue-i18n', () => ({
    useI18n: () => ({
        t: (key: string) => key,
        locale: { value: 'en' },
    }),
}));

vi.mock('../composables/useVatReport', () => ({
    useVatReport: () => {
        const summary: VatSummary = {
            from: '2026-01-01',
            to: '2026-12-31',
            byCurrency: [
                {
                    currency: 'EUR',
                    documentCount: 1,
                    turnover: state.base,
                    base: state.base,
                    vat: 0,
                    byRate: [],
                },
            ],
        };
        return { summary: ref(summary) };
    },
}));

async function mountAlert(limit: number, currency = 'EUR') {
    const { default: TaxLimitAlert } = await import('../components/invoicing/TaxLimitAlert.vue');
    return mount(TaxLimitAlert, {
        props: { companyId: 'company-1', limit, currency },
    });
}

describe('TaxLimitAlert', () => {
    beforeEach(() => {
        state.base = 0;
    });

    it('shows nothing below the approaching threshold', async () => {
        state.base = 30000;
        const wrapper = await mountAlert(50000);
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('shows nothing when no limit is configured', async () => {
        state.base = 90000;
        const wrapper = await mountAlert(0);
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('warns (amber) at 80% with the percentage and body message', async () => {
        state.base = 40000;
        const wrapper = await mountAlert(50000);
        const alert = wrapper.find('[role="status"]');
        expect(alert.exists()).toBe(true);
        expect(alert.classes().join(' ')).toContain('amber');
        expect(alert.text()).toContain('80%');
        expect(alert.text()).toContain('invoicing.vat_limit_alert_body');
    });

    it('escalates (red) at 95%', async () => {
        state.base = 48000;
        const wrapper = await mountAlert(50000);
        const alert = wrapper.find('[role="status"]');
        expect(alert.classes().join(' ')).toContain('red');
        expect(alert.text()).toContain('96%');
    });

    it('shows the exceeded message at or above 100%', async () => {
        state.base = 55000;
        const wrapper = await mountAlert(50000);
        const alert = wrapper.find('[role="status"]');
        expect(alert.classes().join(' ')).toContain('red');
        expect(alert.text()).toContain('invoicing.vat_limit_alert_exceeded');
    });
});
