import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { useAuthStore } from '../store/auth';
import { getStoredAccountMnemonic, storeAccountMnemonic } from '../services/accountSeed';

const mocks = vi.hoisted(() => ({
    get: vi.fn(), post: vi.fn(), csrf: vi.fn(), replace: vi.fn(),
    stores: { stores: [] as unknown[], currentStore: null as unknown, fetchStores: vi.fn() },
}));
vi.mock('../services/api', () => ({ default: { get: mocks.get, post: mocks.post } }));
vi.mock('../services/csrf', () => ({ ensureCsrfCookie: mocks.csrf }));
vi.mock('../services/chorala', () => ({ syncChoralaIdentity: vi.fn() }));
vi.mock('../evolu/bootstrap', () => ({ ensureEvoluBoundToAccountSeed: vi.fn() }));
vi.mock('../store/stores', () => ({ useStoresStore: () => mocks.stores }));
vi.mock('../router', () => ({ default: { replace: mocks.replace } }));

// Public BIP39 test vector; never production recovery material.
const phrase = 'abandon '.repeat(23) + 'art';
const logoutKey = 'satflux.auth.logout.v1';
let store: ReturnType<typeof useAuthStore>;

function remoteLogout(deliverEvent = true) {
    localStorage.setItem(logoutKey, crypto.randomUUID());
    if (deliverEvent) window.dispatchEvent(new StorageEvent('storage', { key: logoutKey, storageArea: localStorage }));
}

beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    vi.resetAllMocks();
    setActivePinia(createPinia());
    mocks.csrf.mockResolvedValue(true);
    mocks.get.mockResolvedValue({ data: { id: 7, email: 'synthetic@example.test' } });
    mocks.post.mockResolvedValue({ data: {} });
    mocks.stores.stores = [{ id: 'synthetic-store' }];
    mocks.stores.currentStore = mocks.stores.stores[0];
    store = useAuthStore();
    store.user = { id: 7, email: 'synthetic@example.test' };
    storeAccountMnemonic(phrase);
});
afterEach(() => {
    store.$dispose();
    vi.restoreAllMocks();
    localStorage.clear();
    sessionStorage.clear();
});

it('a retained phrase never silently signs back in after an unauthorized profile response', async () => {
    mocks.get.mockRejectedValue({ response: { status: 401 } });
    expect(await store.fetchUser()).toBe(false);
    expect(store.user).toBeNull();
    expect(mocks.stores.stores).toEqual([]);
    expect(mocks.post).not.toHaveBeenCalled();
});

it('logout from another tab clears authentication, tenant selection, and both phrase storage formats', () => {
    localStorage.setItem('satflux.account.mnemonic.persistent.v1', phrase);
    remoteLogout();
    expect(store.user).toBeNull();
    expect(mocks.stores.stores).toEqual([]);
    expect(mocks.stores.currentStore).toBeNull();
    expect(getStoredAccountMnemonic()).toBeNull();
    expect(localStorage.getItem('satflux.account.mnemonic.persistent.v1')).toBeNull();
    expect(localStorage.getItem(logoutKey)).not.toContain('abandon');
    expect(mocks.post).not.toHaveBeenCalled();
});

it('a reloaded tab clears a stale phrase even when it missed the storage event', async () => {
    store.$dispose();
    remoteLogout(false);
    setActivePinia(createPinia());
    store = useAuthStore();
    mocks.get.mockRejectedValue({ response: { status: 401 } });
    await store.fetchUser();
    expect(getStoredAccountMnemonic()).toBeNull();
    expect(mocks.post).not.toHaveBeenCalled();
});

it('a background tab notices a missed logout when focused', () => {
    remoteLogout(false);
    window.dispatchEvent(new Event('focus'));
    expect(store.user).toBeNull();
    expect(getStoredAccountMnemonic()).toBeNull();
});

it('a late successful profile response cannot repopulate authentication after remote logout', async () => {
    let resolve!: (value: unknown) => void;
    mocks.get.mockImplementation(() => new Promise(r => { resolve = r; }));
    const pending = store.fetchUser();
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledOnce());
    remoteLogout();
    resolve({ data: { id: 7, email: 'synthetic@example.test' } });
    expect(await pending).toBe(false);
    expect(store.user).toBeNull();
    expect(getStoredAccountMnemonic()).toBeNull();
});

it('a missed logout is detected when an in-flight profile response arrives', async () => {
    let resolve!: (value: unknown) => void;
    mocks.get.mockImplementation(() => new Promise(r => { resolve = r; }));
    const pending = store.fetchUser();
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledOnce());
    remoteLogout(false);
    resolve({ data: { id: 7, email: 'synthetic@example.test' } });
    expect(await pending).toBe(false);
    expect(store.user).toBeNull();
});

it('logout while CSRF setup is pending prevents the stale profile request', async () => {
    let resolve!: (value: boolean) => void;
    mocks.csrf.mockImplementation(() => new Promise(r => { resolve = r; }));
    const pending = store.fetchUser();
    remoteLogout();
    resolve(true);
    expect(await pending).toBe(false);
    expect(mocks.get).not.toHaveBeenCalled();
});

it('local logout publishes intent and clears the phrase before its HTTP request finishes', async () => {
    let resolve!: (value: unknown) => void;
    mocks.post.mockImplementation(() => new Promise(r => { resolve = r; }));
    const pending = store.logout();
    expect(localStorage.getItem(logoutKey)).toBeTruthy();
    expect(store.user).toBeNull();
    expect(getStoredAccountMnemonic()).toBeNull();
    resolve({ data: {} });
    await pending;
});

it('failed server logout still clears local credentials and broadcasts local intent', async () => {
    const error = new Error('synthetic network failure');
    mocks.post.mockRejectedValue(error);
    await expect(store.logout()).rejects.toBe(error);
    expect(localStorage.getItem(logoutKey)).toBeTruthy();
    expect(store.user).toBeNull();
    expect(getStoredAccountMnemonic()).toBeNull();
});

it('explicit signed recovery still works after remote logout and keeps the phrase on reload', async () => {
    remoteLogout();
    mocks.post.mockImplementation(async (path: string) => path.endsWith('/challenge')
        ? { data: { data: { challenge_id: 'synthetic-challenge', nonce: 'synthetic-nonce' } } }
        : { data: { user: { id: 7 } } });
    await store.restoreGuestFromMnemonic(phrase);
    expect(store.user?.id).toBe(7);
    expect(getStoredAccountMnemonic()).toBe(phrase);
    expect(mocks.post.mock.calls.map(call => call[0])).toEqual(['/auth/guest/recovery/challenge', '/auth/guest/recovery']);
    store.$dispose();
    setActivePinia(createPinia());
    store = useAuthStore();
    await store.fetchUser();
    expect(store.user?.id).toBe(7);
    expect(getStoredAccountMnemonic()).toBe(phrase);
});

it('unrelated storage changes do not clear authentication', () => {
    window.dispatchEvent(new StorageEvent('storage', { key: 'theme', storageArea: localStorage }));
    expect(store.user?.id).toBe(7);
    expect(getStoredAccountMnemonic()).toBe(phrase);
});

it('disposing the auth store removes its cross-tab listeners', () => {
    store.$dispose();
    remoteLogout();
    expect(store.user?.id).toBe(7);
});

it('logout during a recovery challenge prevents the authentication POST', async () => {
    let resolve!: (value: unknown) => void;
    mocks.post.mockImplementation(() => new Promise(r => { resolve = r; }));
    const pending = store.restoreGuestFromMnemonic(phrase);
    const rejected = expect(pending).rejects.toThrow('cancelled by logout');
    await vi.waitFor(() => expect(mocks.post).toHaveBeenCalledOnce());
    remoteLogout();
    resolve({ data: { data: { challenge_id: 'synthetic-challenge', nonce: 'synthetic-nonce' } } });
    await rejected;
    expect(mocks.post.mock.calls.map(call => call[0])).toEqual(['/auth/guest/recovery/challenge']);
    expect(getStoredAccountMnemonic()).toBeNull();
});

it('logout while recovered store data is loading prevents callers from re-saving the phrase', async () => {
    let resolve!: (value: unknown) => void;
    mocks.post.mockImplementation(async (path: string) => path.endsWith('/challenge')
        ? { data: { data: { challenge_id: 'synthetic-challenge', nonce: 'synthetic-nonce' } } }
        : { data: { user: { id: 7 } } });
    mocks.stores.fetchStores.mockImplementation(() => new Promise(r => { resolve = r; }));
    const pending = store.restoreGuestFromMnemonic(phrase);
    const rejected = expect(pending).rejects.toThrow('cancelled by logout');
    await vi.waitFor(() => expect(mocks.stores.fetchStores).toHaveBeenCalledOnce());
    remoteLogout();
    resolve(true);
    await rejected;
    expect(store.user).toBeNull();
    expect(getStoredAccountMnemonic()).toBeNull();
});

it('storage failure does not prevent server logout or local authentication cleanup', async () => {
    vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('storage disabled'); });
    await store.logout();
    expect(mocks.post).toHaveBeenCalledWith('/auth/logout');
    expect(store.user).toBeNull();
    expect(getStoredAccountMnemonic()).toBeNull();
});

it('a profile read started during server logout cannot repopulate state after completion', async () => {
    let finishLogout!: (value: unknown) => void;
    let finishProfile!: (value: unknown) => void;
    mocks.post.mockImplementation(() => new Promise(r => { finishLogout = r; }));
    mocks.get.mockImplementation(() => new Promise(r => { finishProfile = r; }));
    const logout = store.logout();
    const firstMarker = localStorage.getItem(logoutKey);
    const profile = store.fetchUser();
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledOnce());
    finishLogout({ data: {} });
    await logout;
    expect(localStorage.getItem(logoutKey)).not.toBe(firstMarker);
    finishProfile({ data: { id: 7, email: 'synthetic@example.test' } });
    expect(await profile).toBe(false);
    expect(store.user).toBeNull();
});
