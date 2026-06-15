import { describe, expect, it, vi } from 'vitest';
import { isPublicMarketingPath, navigateToAppPath, normalizePath } from '../utils/publicMarketingRoutes';

describe('publicMarketingRoutes', () => {
    it('accepts known public paths', () => {
        expect(isPublicMarketingPath('/')).toBe(true);
        expect(isPublicMarketingPath('pricing')).toBe(true);
        expect(isPublicMarketingPath('legal/privacy')).toBe(true);
        expect(isPublicMarketingPath('documentation/getting-started')).toBe(true);
        expect(isPublicMarketingPath('auth/verify-email/1/abc123')).toBe(true);
    });

    it('rejects unknown or over-segmented paths', () => {
        expect(isPublicMarketingPath('dashboard')).toBe(false);
        expect(isPublicMarketingPath('stores/abc')).toBe(false);
        expect(isPublicMarketingPath('legal/foo')).toBe(false);
        expect(isPublicMarketingPath('documentation/a/b')).toBe(false);
        expect(isPublicMarketingPath('auth/verify-email/1/abc/extra')).toBe(false);
    });

    it('normalizePath strips slashes', () => {
        expect(normalizePath('/pricing/')).toBe('pricing');
    });
});

describe('navigateToAppPath', () => {
    it('collapses leading slashes to prevent protocol-relative URLs', () => {
        const assign = vi.fn();
        vi.stubGlobal('location', { assign });

        try {
            navigateToAppPath('//evil.example');
            expect(assign).toHaveBeenCalledWith('/evil.example');

            navigateToAppPath('dashboard');
            expect(assign).toHaveBeenCalledWith('/dashboard');
        } finally {
            vi.unstubAllGlobals();
        }
    });
});
