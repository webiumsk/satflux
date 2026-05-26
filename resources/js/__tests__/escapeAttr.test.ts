import { describe, it, expect } from 'vitest';

// Pure function extracted for testing — mirrors PayButtonForm.vue::escapeAttr
function escapeAttr(value: string | number | undefined | null): string {
    return String(value ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

describe('escapeAttr', () => {
    it('escapes double quotes', () => {
        expect(escapeAttr('"hello"')).toBe('&quot;hello&quot;');
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
