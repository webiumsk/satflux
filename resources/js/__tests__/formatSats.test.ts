import { describe, expect, it } from 'vitest';
import { formatSats } from '../utils/formatSats';

describe('formatSats', () => {
    it('formats with locale grouping', () => {
        expect(formatSats(99350, 'en')).toBe('99,350 sats');
        expect(formatSats(99350, 'sk')).toBe(`${(99350).toLocaleString('sk')} sats`);
    });

    it('truncates fractions and handles zero', () => {
        expect(formatSats(1234.9, 'en')).toBe('1,234 sats');
        expect(formatSats(0, 'en')).toBe('0 sats');
    });

    it('falls back to 0 for non-finite input', () => {
        expect(formatSats(Number.NaN, 'en')).toBe('0 sats');
        expect(formatSats(Number.POSITIVE_INFINITY, 'en')).toBe('0 sats');
    });
});
