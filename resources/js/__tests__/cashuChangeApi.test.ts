import { beforeEach, describe, expect, it, vi } from 'vitest';
const transport = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('axios', () => ({ default: { create: () => ({ ...transport, interceptors: { request: { use: vi.fn() }, response: { use: vi.fn() } } }) } }));
import { walletApi } from '../services/api';

beforeEach(() => vi.resetAllMocks());

describe('Cashu change API privacy and contracts', () => {
    it('uses local store routes and action-bound request/resend payloads without retries', async () => {
        transport.post.mockResolvedValue({ data: { data: { challenge: { purpose: 'cashu_change_recovery' } } } });
        const signal = new AbortController().signal;
        await walletApi.cashu.change.requestConfirmation('local-uuid', 'export', { password: 'synthetic-password' }, signal);
        await walletApi.cashu.change.resendConfirmation('local-uuid', 'export', signal);
        expect(transport.post.mock.calls).toEqual([
            ['/stores/local-uuid/cashu/change/confirmation/request', { action: 'export', password: 'synthetic-password' }, { signal, skipErrorFlash: true }],
            ['/stores/local-uuid/cashu/change/confirmation/resend', { action: 'export' }, { signal, skipErrorFlash: true }],
        ]);
    });

    it('returns the export Blob uninspected and sends literal consent exactly once', async () => {
        const blob = new Blob(['["synthetic-export-marker"]'], { type: 'application/json' });
        const text = vi.fn(); Object.defineProperty(blob, 'text', { value: text });
        transport.post.mockResolvedValue({ data: blob });
        expect(await walletApi.cashu.change.exportTokens('local-uuid', '123456')).toBe(blob);
        expect(text).not.toHaveBeenCalled();
        expect(transport.post).toHaveBeenCalledExactlyOnceWith('/stores/local-uuid/cashu/change/export', { code: '123456', confirmed: true }, { signal: undefined, responseType: 'blob', skipErrorFlash: true });
    });

    it('verifies with separate authorization and accepts only literal success', async () => {
        transport.post.mockResolvedValueOnce({ data: { data: { verified: 'true' } } }).mockResolvedValueOnce({ data: { data: { verified: true } } });
        expect(await walletApi.cashu.change.verify('local-uuid', '123456')).toBe(false);
        expect(await walletApi.cashu.change.verify('local-uuid', '654321')).toBe(true);
        expect(transport.post).toHaveBeenNthCalledWith(1, '/stores/local-uuid/cashu/change/verify', { code: '123456', confirmed: true }, { signal: undefined, skipErrorFlash: true });
    });

    it('projects small HTTP JSON Blob errors to fixed codes/counters and drops private extras', async () => {
        const blob = new Blob(['{}'], { type: 'application/json' });
        Object.defineProperty(blob, 'text', { value: vi.fn().mockResolvedValue(JSON.stringify({ code: 'code_mismatch', attempts_left: 4, message: 'private-response-marker', tokens: ['private-token-marker'] })) });
        transport.post.mockRejectedValue({ response: { status: 422, data: blob }, config: { data: 'private-password-marker' } });
        const error = await walletApi.cashu.change.exportTokens('local-uuid', '123456').catch(value => value);
        expect(error).toEqual({ response: { status: 422, data: { code: 'code_mismatch', attempts_left: 4, retry_after: undefined } } });
        expect(JSON.stringify(error)).not.toContain('private-'); expect(transport.post).toHaveBeenCalledTimes(1);
    });

    it('never propagates unrecognized messages, bodies or request credentials after an uncertain failure', async () => {
        transport.post.mockRejectedValue({ response: { status: 502, data: { code: 'private-code-marker', message: 'private-response-marker', retry_after: 'private-timer-marker' } }, config: { data: 'private-request-marker' } });
        const error = await walletApi.cashu.change.exportTokens('local-uuid', '123456').catch(value => value);
        expect(error.response.data.code).toBe('cashu_change_unavailable');
        expect(JSON.stringify(error)).not.toContain('private-'); expect(transport.post).toHaveBeenCalledTimes(1);
    });

    it('does not decode blobs without an HTTP error status or with an oversized body', async () => {
        const blob = new Blob(['x'.repeat(5000)], { type: 'application/json' });
        const text = vi.fn(); Object.defineProperty(blob, 'text', { value: text });
        transport.post.mockRejectedValue({ response: { status: 502, data: blob } });
        await expect(walletApi.cashu.change.exportTokens('local-uuid', '123456')).rejects.toMatchObject({ response: { data: { code: 'cashu_change_unavailable' } } });
        expect(text).not.toHaveBeenCalled();
    });

    it('reads the summary separately with abort support and no global error flash', async () => {
        const signal = new AbortController().signal;
        transport.get.mockResolvedValue({ data: { data: { status: 'unsupported', can_recover: false } } });
        expect(await walletApi.cashu.change.getSummary('local-uuid', signal)).toEqual({ status: 'unsupported', can_recover: false });
        expect(transport.get).toHaveBeenCalledExactlyOnceWith('/stores/local-uuid/cashu/change', { signal, skipErrorFlash: true });
    });
});
