import { describe, expect, it } from 'vitest';
import {
    INVOICE_TEMPLATE_VERSION,
    buildTemplateSnapshot,
    parseTemplateSnapshot,
    serializeTemplateSnapshot,
    templateToDraftDocument,
} from '../evolu/invoiceTemplate';

const samplePayload = {
    type: 'invoice',
    title: 'Web development',
    company_contact_id: 'contact-1',
    store_id: '',
    currency: 'EUR',
    discount_percent: 0,
    note_footer: 'Thank you',
    // volatile - should be dropped from the template:
    issue_date: '2026-03-01',
    due_date: '2026-03-15',
    delivery_date: '2026-03-01',
    variable_symbol: '2026001',
    lines: [{ name: 'Dev', quantity: 1, unit: 'h', unit_price: 100, tax_rate: 23 }],
};

describe('buildTemplateSnapshot', () => {
    it('drops volatile date/number fields and keeps the rest', () => {
        const snapshot = buildTemplateSnapshot(samplePayload);
        expect(snapshot.version).toBe(INVOICE_TEMPLATE_VERSION);
        expect(snapshot.document.issue_date).toBeUndefined();
        expect(snapshot.document.due_date).toBeUndefined();
        expect(snapshot.document.delivery_date).toBeUndefined();
        expect(snapshot.document.variable_symbol).toBeUndefined();
        expect(snapshot.document.title).toBe('Web development');
        expect(snapshot.document.company_contact_id).toBe('contact-1');
        expect(snapshot.document.lines).toHaveLength(1);
    });

    it('does not mutate the source payload', () => {
        buildTemplateSnapshot(samplePayload);
        expect(samplePayload.issue_date).toBe('2026-03-01');
    });
});

describe('serialize / parse round-trip', () => {
    it('round-trips a snapshot', () => {
        const snapshot = buildTemplateSnapshot(samplePayload);
        const parsed = parseTemplateSnapshot(serializeTemplateSnapshot(snapshot));
        expect(parsed).toEqual(snapshot);
    });

    it('returns null for malformed JSON', () => {
        expect(parseTemplateSnapshot('not json')).toBeNull();
        expect(parseTemplateSnapshot('123')).toBeNull();
        expect(parseTemplateSnapshot('{"nope":true}')).toBeNull();
    });

    it('defaults the version when missing', () => {
        const parsed = parseTemplateSnapshot(JSON.stringify({ document: { title: 'X' } }));
        expect(parsed?.version).toBe(INVOICE_TEMPLATE_VERSION);
        expect(parsed?.document.title).toBe('X');
    });
});

describe('templateToDraftDocument', () => {
    it('forces a fresh draft with empty number and dates', () => {
        const snapshot = buildTemplateSnapshot(samplePayload);
        const draft = templateToDraftDocument(snapshot);
        expect(draft.status).toBe('draft');
        expect(draft.number).toBe('');
        expect(draft.issue_date).toBe('');
        expect(draft.due_date).toBe('');
        expect(draft.delivery_date).toBe('');
        // template content is preserved
        expect(draft.title).toBe('Web development');
        expect(draft.company_contact_id).toBe('contact-1');
        expect(draft.lines).toHaveLength(1);
    });
});
