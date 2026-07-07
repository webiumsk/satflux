import type { AxiosError } from 'axios';

/**
 * Error categories that get a global flash fallback. 4xx are deliberately
 * excluded: validation (422) is component-level UX and 401 belongs to the
 * router guard (redirect loops otherwise).
 */
export type GlobalApiErrorKind = 'network' | 'server' | 'rate_limited';

export function classifyApiErrorForFlash(error: unknown): GlobalApiErrorKind | null {
    if (!error || typeof error !== 'object') {
        return null;
    }

    const err = error as AxiosError;
    if (!err.isAxiosError) {
        return null;
    }
    // Cancelled requests (navigation away, aborted polling) are not failures.
    if (err.code === 'ERR_CANCELED') {
        return null;
    }

    const status = err.response?.status;
    if (status === undefined) {
        // Request left the browser but no response came back (offline, DNS, timeout).
        return 'network';
    }
    if (status === 429) {
        return 'rate_limited';
    }
    if (status >= 500) {
        return 'server';
    }

    return null;
}

export const GLOBAL_API_ERROR_MESSAGE_KEYS: Record<GlobalApiErrorKind, string> = {
    network: 'errors.network',
    rate_limited: 'errors.rate_limited',
    server: 'errors.server_error',
};
