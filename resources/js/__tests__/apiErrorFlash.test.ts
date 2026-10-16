import { beforeEach, describe, expect, it, vi } from 'vitest';

const { errorKeySpy } = vi.hoisted(() => ({ errorKeySpy: vi.fn() }));

vi.mock('../store/flash', () => ({
    useFlashStore: () => ({ errorKey: errorKeySpy }),
}));

import api from '../services/api';

/** Invoke the registered response-error interceptor exactly as axios would. */
function rejectThroughInterceptor(error: unknown): Promise<unknown> {
    const handlers = (api.interceptors.response as unknown as {
        handlers: Array<{ rejected?: (e: unknown) => Promise<unknown> } | null>;
    }).handlers;
    const handler = handlers.find((h) => h?.rejected);
    if (!handler?.rejected) {
        throw new Error('response error interceptor not registered');
    }
    return handler.rejected(error);
}

function axiosError(overrides: Record<string, unknown> = {}) {
    return {
        isAxiosError: true,
        config: {},
        ...overrides,
    };
}

async function flushDynamicImports() {
    // notifyGlobalApiError resolves the flash store via dynamic import
    await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('global API error flash interceptor', () => {
    beforeEach(() => {
        errorKeySpy.mockClear();
    });

    it('flashes a translated key for server errors', async () => {
        const err = axiosError({ response: { status: 500 } });
        await expect(rejectThroughInterceptor(err)).rejects.toBe(err);
        await flushDynamicImports();
        expect(errorKeySpy).toHaveBeenCalledWith('errors.server_error');
    });

    it('flashes for network errors and 429', async () => {
        await expect(rejectThroughInterceptor(axiosError())).rejects.toBeTruthy();
        await expect(
            rejectThroughInterceptor(axiosError({ response: { status: 429 } })),
        ).rejects.toBeTruthy();
        await flushDynamicImports();
        expect(errorKeySpy).toHaveBeenCalledWith('errors.network');
        expect(errorKeySpy).toHaveBeenCalledWith('errors.rate_limited');
    });

    it('respects the skipErrorFlash opt-out', async () => {
        const err = axiosError({ response: { status: 500 }, config: { skipErrorFlash: true } });
        await expect(rejectThroughInterceptor(err)).rejects.toBe(err);
        await flushDynamicImports();
        expect(errorKeySpy).not.toHaveBeenCalled();
    });

    it('localizes stable server purchase denials for stale clients', async () => {
        for (const code of ['pro_purchase_temporarily_disabled', 'company_slot_purchase_temporarily_disabled', 'isdoc_pack_purchase_temporarily_disabled']) {
            await expect(rejectThroughInterceptor(axiosError({ response: { status: 403, data: { code } } }))).rejects.toBeTruthy();
        }
        await flushDynamicImports();
        expect(errorKeySpy).toHaveBeenCalledTimes(3);
        expect(errorKeySpy).toHaveBeenCalledWith('free_pro.purchases_paused');
    });

    it('stays silent for component-level 4xx errors', async () => {
        for (const status of [401, 404, 422]) {
            await expect(
                rejectThroughInterceptor(axiosError({ response: { status } })),
            ).rejects.toBeTruthy();
        }
        await flushDynamicImports();
        expect(errorKeySpy).not.toHaveBeenCalled();
    });
});
