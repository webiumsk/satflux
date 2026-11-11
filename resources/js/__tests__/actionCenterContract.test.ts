import { describe, expect, it } from 'vitest';
import emptyCompany from './fixtures/action-center-empty.json';
import { validateActionCenterPayload } from '@/services/companyActionCenter';

describe('actual company action-center API contract', () => {
    it('accepts the same fresh-company fixture checked by the PHP API test', () => {
        const data = validateActionCenterPayload(emptyCompany);
        expect(data.categories[2]).toMatchObject({ state: 'not_configured', count: null, items: [], has_more: false });
    });
});
