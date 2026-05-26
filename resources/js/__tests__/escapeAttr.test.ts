import { describe, it, expect } from 'vitest';
import { escapeAttr } from '../utils/escapeAttr';

describe('escapeAttr', () => {
    it('escapes double quotes', () => {
        expect(escapeAttr('"hello"')).toBe('&quot;hello&quot;');
    });

    it('escapes single quotes', () => {
        expect(escapeAttr("it's")).toBe('it&#39;s');
    });

    it('escapes angle brackets', () => {
        expect(escapeAttr('<script>')).toBe('&lt;script&gt;');
    });

    it('escapes ampersands', () => {
        expect(escapeAttr('a&b')).toBe('a&amp;b');
    });

    it('blocks classic XSS injection vector', () => {
        const payload = '" /><script>alert(1)</script><input value="';
        const result = escapeAttr(payload);
        expect(result).not.toContain('"');
        expect(result).not.toContain('<');
        expect(result).not.toContain('>');
    });

    it("blocks single-quote injection vector", () => {
        const payload = "' onmouseover='alert(1)";
        expect(escapeAttr(payload)).not.toContain("'");
    });

    it('passes through safe numeric values', () => {
        expect(escapeAttr(42)).toBe('42');
        expect(escapeAttr(3.14)).toBe('3.14');
    });

    it('returns empty string for null/undefined', () => {
        expect(escapeAttr(null)).toBe('');
        expect(escapeAttr(undefined)).toBe('');
    });

    it('returns empty string for empty input', () => {
        expect(escapeAttr('')).toBe('');
    });
});
