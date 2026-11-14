import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createI18n } from 'vue-i18n';
import InvoiceLivePreview, { type InvoicePreviewForm } from '../components/invoicing/InvoiceLivePreview.vue';
import { buildIssuedSnapshotContentV1 } from '../evolu/documentSnapshotCrud';
import sk from '../locales/sk.json';

const form: InvoicePreviewForm = {
    title: 'Invoice', number: '20260001', issue_date: '2026-11-13',
    delivery_date: '2026-11-13', due_date: '2026-11-27', variable_symbol: '20260001',
    currency: 'EUR', discount_percent: 0, note_above_lines: '', note_footer: '',
    payment_bank_enabled: false, pdf_show_signature: false, pdf_show_payment_info: false,
    lines: [{
        name: 'Item', description: '', quantity: 1, unit: 'pcs', unit_price: 10,
        line_discount_percent: 0, tax_rate: 0,
    }],
};

function renderBuyer(selectedContact: Record<string, unknown> | null, documentStatus = 'draft') {
    return mount(InvoiceLivePreview, {
        props: {
            company: { legal_name: 'Synthetic Supplier', jurisdiction: 'eu_sk', vat_status: 'none' },
            selectedContact, form, documentStatus,
            totals: { subtotal: 10, tax: 0, total: 10 },
        },
        global: { plugins: [createI18n({ legacy: false, locale: 'sk', messages: { sk } })] },
    });
}

describe('invoice buyer identifiers', () => {
    it('uses canonical per-line rounding for ordinary invoices with deductions', () => {
        const wrapper = mount(InvoiceLivePreview, {
            props: {
                company: { legal_name: 'Supplier', jurisdiction: 'eu_sk', vat_status: 'payer', vat_payer: true },
                selectedContact: null,
                form: { ...form, lines: [
                    { ...form.lines[0], name: 'Fractional service', quantity: 0.5, unit_price: 1.01, tax_rate: 23 },
                    { ...form.lines[0], name: 'Deduction', unit_price: -0.01, tax_rate: 23 },
                ] },
                documentStatus: 'draft', totals: { subtotal: 0.50, tax: 0.12, total: 0.62 },
            },
            global: { plugins: [createI18n({ legacy: false, locale: 'sk', messages: { sk } })] },
        });
        const row = wrapper.findAll('tr').find(item => item.text().includes('Fractional service'));
        expect(row?.text()).toContain('0,63');
    });

    it('shows the supplied contact registration, tax and VAT identifiers with localized labels', () => {
        const wrapper = renderBuyer({
            name: 'Synthetic Buyer', registration_number: '12345678',
            tax_id: '2012345678', vat_id: 'SK2012345678',
        });
        expect(wrapper.text()).toContain('IČO: 12345678');
        expect(wrapper.text()).toContain('DIČ: 2012345678');
        expect(wrapper.text()).toContain('IČ DPH: SK2012345678');
    });

    it.each([null, { name: 'Consumer', registration_number: '', tax_id: null, vat_id: undefined }])(
        'omits absent buyer identifier rows for %j', (buyer) => {
            const wrapper = renderBuyer(buyer);
            expect(wrapper.text()).not.toContain('IČO:');
            expect(wrapper.text()).not.toContain('DIČ:');
            expect(wrapper.text()).not.toContain('IČ DPH:');
        },
    );

    it('renders issued snapshot identifiers after the source contact changes', () => {
        const contact = {
            name: 'Original Buyer', registration_number: '12345678',
            tax_id: '2012345678', vat_id: 'SK2012345678',
        };
        const snapshot = buildIssuedSnapshotContentV1({
            company: { legal_name: 'Synthetic Supplier' }, contact,
            document: { ...form, type: 'invoice' }, lines: form.lines,
        });
        expect(snapshot.ok).toBe(true);
        if (!snapshot.ok) throw new Error(snapshot.error);
        contact.registration_number = '87654321';
        contact.tax_id = '2087654321';
        contact.vat_id = 'SK2087654321';
        const wrapper = renderBuyer(snapshot.value.contact, 'issued');
        expect(wrapper.text()).toContain('IČO: 12345678');
        expect(wrapper.text()).toContain('DIČ: 2012345678');
        expect(wrapper.text()).toContain('IČ DPH: SK2012345678');
        expect(wrapper.text()).not.toContain('87654321');
    });

    it('escapes identifiers as ordinary text', () => {
        const wrapper = renderBuyer({ name: 'Buyer', registration_number: '<script>bad()</script>' });
        expect(wrapper.text()).toContain('IČO: <script>bad()</script>');
        expect(wrapper.find('script').exists()).toBe(false);
    });
});
