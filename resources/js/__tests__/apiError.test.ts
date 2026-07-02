import { describe, expect, it } from 'vitest';
import { classifyApiErrorForFlash } from '../services/apiError';

function axiosError(overrides: Record<string, unknown> = {}) {
    return {
        isAxiosError: true,
        config: {},
        ...overrides,
    };
}

describe('classifyApiErrorForFlash', () => {
    it('classifies missing response as network error', () => {
        expect(classifyApiErrorForFlash(axiosError())).toBe('network');
    });

    it('classifies 5xx as server error', () => {
        expect(classifyApiErrorForFlash(axiosError({ response: { status: 500 } }))).toBe('server');
        expect(classifyApiErrorForFlash(axiosError({ response: { status: 503 } }))).toBe('server');
    });

    it('classifies 429 as rate limited', () => {
        expect(classifyApiErrorForFlash(axiosError({ response: { status: 429 } }))).toBe('rate_limited');
    });

    it('leaves 4xx to component-level handling', () => {
        for (const status of [400, 401, 403, 404, 419, 422]) {
            expect(classifyApiErrorForFlash(axiosError({ response: { status } }))).toBeNull();
        }
    });

    it('ignores cancelled requests', () => {
        expect(classifyApiErrorForFlash(axiosError({ code: 'ERR_CANCELED' }))).toBeNull();
    });

    it('ignores non-axios errors', () => {
        expect(classifyApiErrorForFlash(new Error('boom'))).toBeNull();
        expect(classifyApiErrorForFlash(undefined)).toBeNull();
        expect(classifyApiErrorForFlash('nope')).toBeNull();
    });
});
