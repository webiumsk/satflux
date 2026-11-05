import { createPinia, setActivePinia } from 'pinia';
import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useAuthStore } from '../store/auth';

const mocks = vi.hoisted(() => ({
    get: vi.fn(), post: vi.fn(), csrf: vi.fn(), hydrate: vi.fn(),
    stores: { stores: [] as unknown[], currentStore: null as unknown },
}));
vi.mock('../services/api', () => ({ default: { get: mocks.get, post: mocks.post } }));
vi.mock('../services/csrf', () => ({ ensureCsrfCookie: mocks.csrf }));
vi.mock('../store/stores', () => ({ useStoresStore: () => mocks.stores }));
vi.mock('../services/chorala', () => ({ syncChoralaIdentity: vi.fn() }));
vi.mock('../evolu/flags', () => ({ isInvoicingLocalFirst: () => false }));
vi.mock('../evolu/bootstrap', () => ({ ensureEvoluBoundToAccountSeed: vi.fn() }));
vi.mock('../services/guestRecovery', () => ({
    clearStoredGuestMnemonic: vi.fn(), getStoredGuestMnemonic: () => null,
    hydrateAccountMnemonicSession: mocks.hydrate, storeGuestMnemonic: vi.fn(),
    guestRecoveryMessage: vi.fn(), guestRecoveryPublicKeyHexFromMnemonic: vi.fn(), signGuestRecoveryMessage: vi.fn(),
}));

let store: ReturnType<typeof useAuthStore>;

beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    vi.resetAllMocks();
    setActivePinia(createPinia());
    mocks.csrf.mockResolvedValue(undefined);
    mocks.get.mockResolvedValue({ data: { id: 71, email: 'synthetic@example.test' } });
    mocks.post.mockResolvedValue({ data: {} });
    mocks.stores.stores = [];
    mocks.stores.currentStore = null;
    store = useAuthStore();
});

afterEach(() => { store.$dispose(); });

/** Hold a synthetic transport result to exercise the existing logout-generation guards. */
function deferred<T>() {
    let resolve!: (value: T) => void;
    const promise = new Promise<T>(yes => { resolve = yes; });
    return { promise, resolve };
}

describe('canonical authentication outcome compatibility', () => {
    it('reports loaded for a canonical user and keeps the existing boolean success contract', async () => {
        expect(await store.fetchUserWithOutcome()).toBe('loaded');
        expect(store.isAuthenticated).toBe(true);
        expect(mocks.get).toHaveBeenCalledTimes(1);
        expect(mocks.hydrate).toHaveBeenCalledTimes(1);
        expect(await store.fetchUser()).toBe(true);
        expect(mocks.get).toHaveBeenCalledTimes(2);
    });

    it.each([401, 403])('reports confirmed guest for HTTP%s while preserving boolean false and tenant clearing', async status => {
        store.user = { id: 72, email: 'retained@example.test' };
        mocks.stores.stores = [{ id: 'synthetic-store' }];
        mocks.stores.currentStore = { id: 'synthetic-store' };
        mocks.get.mockRejectedValue({ response: { status } });
        expect(await store.fetchUserWithOutcome()).toBe('guest');
        expect(store.user).toBeNull();
        expect(mocks.stores.stores).toEqual([]);
        expect(mocks.stores.currentStore).toBeNull();
        expect(mocks.get).toHaveBeenCalledTimes(1);
        expect(await store.fetchUser()).toBe(false);
        expect(mocks.get).toHaveBeenCalledTimes(2);
    });

    it.each([new Error('Synthetic offline check'), { response: { status: 503 } }])('retains existing user on an unavailable check and never reclassifies it as guest', async error => {
        store.user = { id: 72, email: 'retained@example.test' };
        mocks.get.mockRejectedValue(error);
        expect(await store.fetchUserWithOutcome()).toBe('unavailable');
        expect(store.user?.id).toBe(72);
        expect(await store.fetchUser()).toBe(false);
        expect(store.user?.id).toBe(72);
    });

    it('preserves the unverified-account exception as unavailable rather than guest', async () => {
        store.user = { id: 72, email: 'retained@example.test' };
        mocks.get.mockRejectedValue({ response: { status: 403, data: { code: 'email_not_verified' } } });
        expect(await store.fetchUserWithOutcome()).toBe('unavailable');
        expect(store.user?.id).toBe(72);
        expect(await store.fetchUser()).toBe(false);
    });

    it('rejects stale user responses after the existing logout generation changes', async () => {
        const response = deferred<{ data: { id: number; email: string } }>();
        mocks.get.mockReturnValueOnce(response.promise);
        const check = store.fetchUserWithOutcome();
        await flushPromises();
        await store.logout();
        response.resolve({ data: { id: 71, email: 'synthetic@example.test' } });
        expect(await check).toBe('unavailable');
        expect(store.user).toBeNull();
    });

    it('does not request a user after logout invalidates in-flight CSRF preparation', async () => {
        const csrf = deferred<void>();
        mocks.csrf.mockReturnValueOnce(csrf.promise);
        const check = store.fetchUserWithOutcome();
        await store.logout();
        csrf.resolve();
        expect(await check).toBe('unavailable');
        expect(mocks.get).not.toHaveBeenCalled();
        expect(store.user).toBeNull();
    });
});
