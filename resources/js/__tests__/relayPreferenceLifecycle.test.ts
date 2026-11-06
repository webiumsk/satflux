import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { createRandomBytes } from "@evolu/common";
import { createAppOwner, createOwnerSecret, createSharedOwner, type Evolu, type SyncOwner } from "@evolu/common/local-first";
import { deriveRecoveryPublicKeyHex } from "@/services/accountSeed";
import * as accountSeed from "@/services/accountSeed";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { EVOLU_RELAY_OVERRIDE_STORAGE_KEY, readRelayOverride, writeRelayOverrideDisabled, writeRelayOverrideRelays, writeRelayOverrideUrl } from "@/evolu/relayOverrideStorage";
import { getActiveEvoluRelayUrls, reconcileEvoluRelayPreference, registerEvoluRelayOwner, subscribeActiveEvoluRelayUrls } from "@/evolu/relayPreferenceLifecycle";
import { resolveEvoluRelayUrls, type EvoluRelayConfiguration } from "@/evolu/config";
import { useAuthStore } from "@/store/auth";
import { resolveLocalDefaultRelayOwner } from "@/evolu/localDefaultRelayProof";

const authMocks = vi.hoisted(() => ({ get: vi.fn(), csrf: vi.fn() }));
vi.mock("@/services/api", () => ({ default: { get: authMocks.get } }));
vi.mock("@/services/csrf", () => ({ ensureCsrfCookie: authMocks.csrf }));
vi.mock("@/services/chorala", () => ({ syncChoralaIdentity: vi.fn() }));
vi.mock("@/evolu/bootstrap", () => ({ ensureEvoluBoundToAccountSeed: vi.fn() }));
vi.mock("@/store/stores", () => ({ useStoresStore: () => ({ stores: [], currentStore: null }) }));

const DEFAULT = "wss://default.example.test";
const FREE = "wss://free.evoluhq.com";
const CUSTOM = "wss://custom.example.test";
const OTHER = "wss://other.example.test";
const randomBytes = createRandomBytes();
const app = createAppOwner(createOwnerSecret({ randomBytes }));
const otherApp = createAppOwner(createOwnerSecret({ randomBytes }));
const shared = createSharedOwner(createOwnerSecret({ randomBytes }));
const otherShared = createSharedOwner(createOwnerSecret({ randomBytes }));
// SDK-created owners retain their generated mnemonic; the public interface also permits redacted owners.
const publicKey = deriveRecoveryPublicKeyHex(app.mnemonic!);
const otherPublicKey = deriveRecoveryPublicKeyHex(otherApp.mnemonic!);
const disposals: Array<() => void> = [];
let disposeAuth: (() => void) | undefined;

/** Capture public preference notices without opening an actual cross-tab transport. */
class PreferenceChannel {
    static instance: PreferenceChannel;
    onmessage: ((event: MessageEvent<unknown>) => void) | null = null;
    postMessage = vi.fn();
    constructor() { PreferenceChannel.instance = this; }
}

/** Model independent public SDK registration handles and observe only their destination origins. */
function client(owner: Promise<typeof app> = Promise.resolve(app)) {
    const active = new Map<symbol, SyncOwner>();
    const calls: string[] = [];
    const evolu = {
        appOwner: owner,
        useOwner: (value: SyncOwner) => {
            const token = Symbol();
            active.set(token, value);
            calls.push("register");
            return () => { active.delete(token); calls.push("release"); };
        },
    } as unknown as Evolu<InvoicingLocalSchema>;
    return {
        evolu, calls,
        destinations: (ownerId: string) => [...new Set([...active.values()]
            .filter(value => value.id === ownerId)
            .flatMap(value => value.transports ?? [])
            .map(transport => new URL(transport.url).origin))],
    };
}

/** Keep each test's public owner registration cleanup explicit. */
function register(evolu: Evolu<InvoicingLocalSchema>, owner: SyncOwner): void {
    disposals.push(registerEvoluRelayOwner(evolu, owner));
}

/** Apply a canonical server preference using the production atomic cache and notification APIs. */
function canonical(url: string, includeDefault: boolean, accountPublicKey: string | null = publicKey): void {
    writeRelayOverrideUrl(url, includeDefault, accountPublicKey);
    reconcileEvoluRelayPreference(resolveEvoluRelayUrls(url, includeDefault), accountPublicKey);
}

/** Deliver a synthetic storage event; handlers must read current storage rather than old event values. */
function storageChanged(): void {
    window.dispatchEvent(new StorageEvent("storage", { key: EVOLU_RELAY_OVERRIDE_STORAGE_KEY }));
}

beforeAll(() => { vi.stubGlobal("BroadcastChannel", PreferenceChannel); });
afterAll(() => { vi.unstubAllGlobals(); });
beforeEach(() => {
    localStorage.clear();
    sessionStorage.clear();
    vi.stubEnv("VITE_EVOLU_RELAY_URL", DEFAULT);
    canonical(CUSTOM, true);
    PreferenceChannel.instance.postMessage.mockReset();
    authMocks.get.mockReset();
    authMocks.csrf.mockResolvedValue(undefined);
});
afterEach(() => {
    for (const dispose of disposals.splice(0)) dispose();
    disposeAuth?.();
    disposeAuth = undefined;
    vi.restoreAllMocks();
    vi.unstubAllEnvs();
    localStorage.clear();
    sessionStorage.clear();
});

it("retires default registrations for both owner kinds and keeps the replacement registered before release", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    expect(probe.destinations(app.id)).toEqual([CUSTOM, DEFAULT, FREE]);
    expect(probe.destinations(shared.id)).toEqual([CUSTOM, DEFAULT, FREE]);
    probe.calls.length = 0;
    canonical(CUSTOM, false);
    expect(probe.destinations(app.id)).toEqual([CUSTOM]);
    expect(probe.destinations(shared.id)).toEqual([CUSTOM]);
    expect(probe.calls).toEqual(["register", "release", "register", "release"]);
});

it("caches successful public derivations by owner object and keeps distinct owners separate", async () => {
    const fresh = createAppOwner(createOwnerSecret({ randomBytes }));
    const key = deriveRecoveryPublicKeyHex(fresh.mnemonic!);
    canonical(CUSTOM, false, key);
    const derive = vi.spyOn(accountSeed, "deriveRecoveryPublicKeyHex");
    const probe = client(Promise.resolve(fresh));
    register(probe.evolu, fresh);
    register(probe.evolu, fresh);
    register(probe.evolu, shared);
    await Promise.resolve();
    expect(derive).toHaveBeenCalledTimes(1);
    register(probe.evolu, { ...fresh });
    await Promise.resolve();
    expect(derive).toHaveBeenCalledTimes(2);
});

it.each([undefined, "synthetic invalid mnemonic"])(
    "does not cache an unavailable or unsuccessful public derivation (%s)", async unavailable => {
        const fresh = createAppOwner(createOwnerSecret({ randomBytes }));
        const key = deriveRecoveryPublicKeyHex(fresh.mnemonic!);
        canonical(CUSTOM, false, key);
        // Only this synthetic fixture is mutable; SDK owner objects expose readonly mnemonics.
        const mutable = { ...fresh, mnemonic: unavailable };
        const probe = client(Promise.resolve(mutable as typeof fresh));
        const derive = vi.spyOn(accountSeed, "deriveRecoveryPublicKeyHex");
        register(probe.evolu, mutable as SyncOwner);
        await Promise.resolve();
        expect(probe.destinations(fresh.id)).toEqual([]);
        if (unavailable) expect(derive).toHaveBeenCalledTimes(2);
        else expect(derive).not.toHaveBeenCalled();
        derive.mockClear();
        mutable.mnemonic = fresh.mnemonic!;
        register(probe.evolu, mutable as SyncOwner);
        register(probe.evolu, mutable as SyncOwner);
        await Promise.resolve();
        expect(derive).toHaveBeenCalledTimes(1);
    },
);

it("keeps retained owners paused for another account across a fresh client and guarded default selection", async () => {
    const retained = client();
    register(retained.evolu, app);
    register(retained.evolu, shared);
    canonical(OTHER, false, otherPublicKey);
    expect(retained.destinations(app.id)).toEqual([]);
    expect(retained.destinations(shared.id)).toEqual([]);
    canonical("", false, otherPublicKey);
    expect(readRelayOverride()).toMatchObject({ kind: "url", url: "", accountPublicKey: otherPublicKey });
    const reloaded = client();
    register(reloaded.evolu, app);
    await Promise.resolve();
    expect(reloaded.destinations(app.id)).toEqual([]);
});

it("a confirmed new owner cannot re-enable old owner registrations on the same client", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    canonical(OTHER, false, otherPublicKey);
    Object.defineProperty(probe.evolu, "appOwner", { value: Promise.resolve(otherApp) });
    register(probe.evolu, otherApp);
    register(probe.evolu, otherShared);
    await Promise.resolve();
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
    expect(probe.destinations(otherApp.id)).toEqual([OTHER]);
    expect(probe.destinations(otherShared.id)).toEqual([OTHER]);
});

it("explicit missing account identity remains paused while legacy cache retains its original destination", async () => {
    canonical(CUSTOM, false, null);
    const paused = client();
    register(paused.evolu, app);
    await Promise.resolve();
    expect(paused.destinations(app.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ accountPublicKey: null });
    writeRelayOverrideUrl(CUSTOM);
    reconcileEvoluRelayPreference([CUSTOM]);
    const legacy = client();
    register(legacy.evolu, app);
    await Promise.resolve();
    expect(legacy.destinations(app.id)).toEqual([CUSTOM]);
    expect(readRelayOverride()).toEqual({ kind: "url", url: CUSTOM });
});

it("does not replace a newer canonical selection when an older appOwner promise resolves", async () => {
    let resolve!: (owner: typeof app) => void;
    const probe = client(new Promise(value => { resolve = value; }));
    register(probe.evolu, shared);
    canonical(OTHER, false, otherPublicKey);
    resolve(app);
    await Promise.resolve();
    expect(probe.destinations(shared.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ url: OTHER, accountPublicKey: otherPublicKey });
});

it("applies canonical custom-only locally when persistence fails and invalidates other tabs without stale re-enabling", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => { throw new Error("synthetic storage unavailable"); });
    canonical(CUSTOM, false);
    expect(probe.destinations(app.id)).toEqual([CUSTOM]);
    expect(probe.destinations(shared.id)).toEqual([CUSTOM]);
    const notice = PreferenceChannel.instance.postMessage.mock.calls.at(-1)?.[0];
    expect(notice.persisted).toBe(false);
    PreferenceChannel.instance.onmessage?.({ data: { ...notice, urls: [CUSTOM, DEFAULT] } } as MessageEvent);
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
});

it("reads the newest persisted preference when an older successful cross-tab notice arrives", async () => {
    const probe = client();
    register(probe.evolu, app);
    await Promise.resolve();
    canonical(CUSTOM, false);
    PreferenceChannel.instance.onmessage?.({ data: { urls: [CUSTOM, DEFAULT], accountPublicKey: publicKey, persisted: true } } as MessageEvent);
    expect(probe.destinations(app.id)).toEqual([CUSTOM]);
});

it("releases tab references on pagehide and rejects unassociated changed destinations on pageshow", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    window.dispatchEvent(new Event("pagehide"));
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
    const releasedCalls = probe.calls.length;
    writeRelayOverrideUrl(OTHER);
    storageChanged();
    expect(probe.calls).toHaveLength(releasedCalls);
    window.dispatchEvent(new Event("pageshow"));
    expect(probe.destinations(app.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ accountPublicKey: null });
    canonical(OTHER, false);
    expect(probe.destinations(app.id)).toEqual([OTHER]);
});

it("pauses all destinations when reads fail even though storage.length is available", async () => {
    const probe = client();
    register(probe.evolu, app);
    await Promise.resolve();
    vi.spyOn(Storage.prototype, "getItem").mockImplementation(() => { throw new Error("synthetic read unavailable"); });
    reconcileEvoluRelayPreference([CUSTOM], publicKey);
    expect(probe.destinations(app.id)).toEqual([]);
});

it("honors device-off across active owner registrations and does not overwrite its sentinel", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    writeRelayOverrideDisabled();
    storageChanged();
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
    reconcileEvoluRelayPreference([CUSTOM], publicKey);
    expect(probe.destinations(app.id)).toEqual([]);
    expect(readRelayOverride().kind).toBe("disabled");
});

it.each([false, true])("canonical auth fetch retires every removed default registration with failed persistence=%s", async failed => {
    vi.stubEnv("VITE_INVOICING_LOCAL_FIRST", "true");
    setActivePinia(createPinia());
    const auth = useAuthStore();
    disposeAuth = () => auth.$dispose();
    auth.user = { id: 7, email: "synthetic@example.test", recovery_public_key: publicKey,
        evolu_relay_url: CUSTOM, evolu_relay_add_default: true };
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    if (failed) vi.spyOn(Storage.prototype, "setItem").mockImplementation(() => { throw new Error("synthetic storage unavailable"); });
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relay_add_default: false } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([CUSTOM]);
    expect(probe.destinations(shared.id)).toEqual([CUSTOM]);
});

it("optional cross-tab posting failure does not undo a successful canonical auth fetch", async () => {
    vi.stubEnv("VITE_INVOICING_LOCAL_FIRST", "true");
    setActivePinia(createPinia());
    const auth = useAuthStore();
    disposeAuth = () => auth.$dispose();
    auth.user = { id: 7, email: "synthetic@example.test", recovery_public_key: publicKey };
    const probe = client();
    register(probe.evolu, app);
    PreferenceChannel.instance.postMessage.mockImplementation(() => { throw new Error("synthetic channel unavailable"); });
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relay_url: CUSTOM, evolu_relay_add_default: false } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([CUSTOM]);
});

it("reads destination URLs and their public guard from exactly one atomic cache snapshot", async () => {
    canonical(CUSTOM, false);
    const probe = client();
    register(probe.evolu, app);
    await Promise.resolve();
    let reads = 0;
    vi.spyOn(Storage.prototype, "getItem").mockImplementation(() => JSON.stringify(++reads === 1
        ? { url: CUSTOM, addDefault: false, accountPublicKey: publicKey }
        : { url: OTHER, addDefault: false, accountPublicKey: otherPublicKey }));
    storageChanged();
    expect(reads).toBe(1);
    expect(probe.destinations(app.id)).toEqual([CUSTOM]);
});

it("cannot drop a guard by replacing a different account's selection with the same unassociated URL", async () => {
    const probe = client();
    register(probe.evolu, app);
    canonical(OTHER, false, otherPublicKey);
    writeRelayOverrideUrl(OTHER);
    storageChanged();
    await Promise.resolve();
    expect(probe.destinations(app.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ url: OTHER, accountPublicKey: null });
});

it("a canonical missing public identity pauses even when the cached URLs are unchanged", async () => {
    vi.stubEnv("VITE_INVOICING_LOCAL_FIRST", "true");
    setActivePinia(createPinia());
    const auth = useAuthStore();
    disposeAuth = () => auth.$dispose();
    auth.user = { id: 7, email: "synthetic@example.test" };
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relay_url: CUSTOM, evolu_relay_add_default: true } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ accountPublicKey: null });
});

/** Create a signed-in keyless account without granting a local owner association. */
function keylessAuth() {
    vi.stubEnv("VITE_INVOICING_LOCAL_FIRST", "true");
    setActivePinia(createPinia());
    const auth = useAuthStore();
    disposeAuth = () => auth.$dispose();
    auth.user = { id: 7, email: "synthetic@example.test", evolu_relay_url: null, recovery_public_key: null };
    return auth;
}

it("syncs a keyless default-only profile after explicit local confirmation while retained other owners stay paused", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    const confirm = auth.captureLocalDefaultRelayOwnerConfirmation();
    expect(confirm(app.mnemonic!)).toBe(true);
    const probe = client();
    const retained = client(Promise.resolve(otherApp));
    register(probe.evolu, app);
    register(probe.evolu, shared);
    register(retained.evolu, otherApp);
    register(retained.evolu, otherShared);
    await Promise.resolve();
    authMocks.get.mockResolvedValue({ data: { ...auth.user } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([DEFAULT, FREE]);
    expect(probe.destinations(shared.id)).toEqual([DEFAULT, FREE]);
    expect(retained.destinations(otherApp.id)).toEqual([]);
    expect(retained.destinations(otherShared.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ url: "", accountPublicKey: publicKey });
});

it("keeps a keyless default-only profile paused without explicit confirmation despite a retained phrase", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    const probe = client();
    register(probe.evolu, app);
    authMocks.get.mockResolvedValue({ data: { ...auth.user } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([]);
});

it.each([CUSTOM, "wss://invalid.example.test/path"])("does not authorize a keyless nonempty custom field using local default proof (%s)", async custom => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    expect(auth.captureLocalDefaultRelayOwnerConfirmation()(app.mnemonic!)).toBe(true);
    const probe = client();
    register(probe.evolu, app);
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relay_url: custom } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([]);
});

it("preserves device-off despite a confirmed keyless default owner", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    expect(auth.captureLocalDefaultRelayOwnerConfirmation()(app.mnemonic!)).toBe(true);
    const probe = client();
    register(probe.evolu, app);
    writeRelayOverrideDisabled();
    authMocks.get.mockResolvedValue({ data: { ...auth.user } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([]);
    expect(readRelayOverride().kind).toBe("disabled");
});

it("prefers a canonical server identity over local default proof", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    expect(auth.captureLocalDefaultRelayOwnerConfirmation()(app.mnemonic!)).toBe(true);
    const probe = client();
    register(probe.evolu, app);
    authMocks.get.mockResolvedValue({ data: { ...auth.user, recovery_public_key: otherPublicKey } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ accountPublicKey: otherPublicKey });
});

it("preserves exact-account confirmation during initial profile loading after a tab reload", async () => {
    const auth = keylessAuth();
    const profile = { ...auth.user };
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    expect(auth.captureLocalDefaultRelayOwnerConfirmation()(app.mnemonic!)).toBe(true);
    auth.$dispose();
    setActivePinia(createPinia());
    const reloaded = useAuthStore();
    disposeAuth = () => reloaded.$dispose();
    const probe = client();
    register(probe.evolu, app);
    authMocks.get.mockResolvedValue({ data: profile });
    await expect(reloaded.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual([DEFAULT, FREE]);
});

it("rejects confirmation captured before a logout signal even after the same account signs in again", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    const confirm = auth.captureLocalDefaultRelayOwnerConfirmation();
    localStorage.setItem("satflux.auth.logout.v1", "synthetic-logout");
    // Capturing again observes the signal and clears the previous account generation.
    auth.captureLocalDefaultRelayOwnerConfirmation();
    auth.user = { id: 7, email: "synthetic@example.test" };
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    expect(confirm(app.mnemonic!)).toBe(false);
    expect(resolveLocalDefaultRelayOwner(7)).toBeNull();
});

it("clears confirmation on known account switches and rejects stale A-to-B-to-A callbacks", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    const confirm = auth.captureLocalDefaultRelayOwnerConfirmation();
    expect(confirm(app.mnemonic!)).toBe(true);
    authMocks.get.mockResolvedValueOnce({ data: { id: 8, email: "other@example.test" } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    authMocks.get.mockResolvedValueOnce({ data: { id: 7, email: "synthetic@example.test" } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(confirm(app.mnemonic!)).toBe(false);
    expect(resolveLocalDefaultRelayOwner(7)).toBeNull();
});

it("rejects a late overlapping local phrase confirmation without replacing the newer public proof", () => {
    const auth = keylessAuth();
    const first = auth.captureLocalDefaultRelayOwnerConfirmation();
    const second = auth.captureLocalDefaultRelayOwnerConfirmation();
    accountSeed.storeAccountMnemonic(otherApp.mnemonic!);
    expect(second(otherApp.mnemonic!)).toBe(true);
    expect(first(app.mnemonic!)).toBe(false);
    expect(resolveLocalDefaultRelayOwner(7) === otherPublicKey).toBe(true);
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    expect(resolveLocalDefaultRelayOwner(7)).toBeNull();
});

it("invalidates captured confirmation when session expiry is followed by the same account returning", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    const confirm = auth.captureLocalDefaultRelayOwnerConfirmation();
    expect(confirm(app.mnemonic!)).toBe(true);
    authMocks.get.mockRejectedValueOnce({ response: { status: 401 } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("guest");
    authMocks.get.mockResolvedValueOnce({ data: { id: 7, email: "synthetic@example.test" } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(confirm(app.mnemonic!)).toBe(false);
    expect(resolveLocalDefaultRelayOwner(7)).toBeNull();
});


/** Apply a full canonical row list through the real auth refresh path. */
async function canonicalRows(relays: EvoluRelayConfiguration[], recoveryPublicKey: string | null = publicKey) {
    const auth = keylessAuth();
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relays: relays, recovery_public_key: recoveryPublicKey } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    return auth;
}

it("applies three canonical destinations to every owner and retires a disabled or removed row without changing keys", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    const rows = [CUSTOM, DEFAULT, OTHER].map(url => ({ url, enabled: true }));
    const auth = await canonicalRows(rows);
    expect(probe.destinations(app.id)).toEqual([CUSTOM, DEFAULT, OTHER]);
    expect(probe.destinations(shared.id)).toEqual([CUSTOM, DEFAULT, OTHER]);
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relays: [{ ...rows[0], enabled: false }, rows[2]] } });
    await auth.fetchUser();
    expect(probe.destinations(app.id)).toEqual([OTHER]);
    expect(probe.destinations(shared.id)).toEqual([OTHER]);
    expect(readRelayOverride()).toMatchObject({ kind: "relays", relays: [{ ...rows[0], enabled: false }, rows[2]], accountPublicKey: publicKey });
});

it("keeps a canonical empty account list local-only across a fresh client", async () => {
    await canonicalRows([]);
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ kind: "relays", relays: [] });
});

it("retains full-list owner guards across initial load and another-account canonical fetch", async () => {
    writeRelayOverrideRelays([{ url: OTHER, enabled: true }], otherPublicKey);
    window.dispatchEvent(new Event("pageshow"));
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
    await canonicalRows([{ url: OTHER, enabled: true }], otherPublicKey);
    expect(probe.destinations(app.id)).toEqual([]);
});

it("allows confirmed keyless default subsets while disabled custom rows remain disconnected", async () => {
    const auth = keylessAuth();
    accountSeed.storeAccountMnemonic(app.mnemonic!);
    expect(auth.captureLocalDefaultRelayOwnerConfirmation()(app.mnemonic!)).toBe(true);
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relays: [
        { url: CUSTOM, enabled: false }, { url: FREE, enabled: true },
    ] } });
    await auth.fetchUser();
    expect(probe.destinations(app.id)).toEqual([FREE]);
    expect(probe.destinations(shared.id)).toEqual([FREE]);
    authMocks.get.mockResolvedValue({ data: { ...auth.user, evolu_relays: [
        { url: CUSTOM, enabled: true }, { url: FREE, enabled: true },
    ] } });
    await auth.fetchUser();
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
});

it("reconciles full rows after device sync is re-enabled and preserves the off sentinel during canonical fetch", async () => {
    const rows = [{ url: CUSTOM, enabled: false }, { url: OTHER, enabled: true }];
    const auth = await canonicalRows(rows);
    const probe = client();
    register(probe.evolu, app);
    writeRelayOverrideDisabled();
    auth.reconcileEvoluRelayPreferenceFromProfile();
    expect(readRelayOverride().kind).toBe("disabled");
    expect(probe.destinations(app.id)).toEqual([]);
    localStorage.removeItem(EVOLU_RELAY_OVERRIDE_STORAGE_KEY);
    auth.reconcileEvoluRelayPreferenceFromProfile();
    expect(readRelayOverride()).toMatchObject({ kind: "relays", relays: rows, accountPublicKey: publicKey });
    expect(probe.destinations(app.id)).toEqual([OTHER]);
});

it("reports only this tab's permitted AppOwner URLs and notifies guarded registration changes", async () => {
    const changed = vi.fn();
    const unsubscribe = subscribeActiveEvoluRelayUrls(changed);
    const probe = client();
    register(probe.evolu, shared);
    await Promise.resolve();
    expect(getActiveEvoluRelayUrls()).toEqual([]);
    register(probe.evolu, app);
    await Promise.resolve();
    expect(getActiveEvoluRelayUrls()).toEqual([CUSTOM, DEFAULT, FREE]);
    expect(changed).toHaveBeenCalledTimes(1);
    canonical(CUSTOM, false, otherPublicKey);
    expect(getActiveEvoluRelayUrls()).toEqual([]);
    expect(changed).toHaveBeenCalledTimes(2);
    canonical(CUSTOM, false);
    expect(getActiveEvoluRelayUrls()).toEqual([CUSTOM]);
    window.dispatchEvent(new Event("pagehide"));
    expect(getActiveEvoluRelayUrls()).toEqual([]);
    window.dispatchEvent(new Event("pageshow"));
    expect(getActiveEvoluRelayUrls()).toEqual([CUSTOM]);
    unsubscribe();
});


it("adopts multi-row notifications from the newest atomic cache and keeps missing identity paused across fresh registrations", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    const rows = [CUSTOM, DEFAULT, OTHER].map(url => ({ url, enabled: true }));
    writeRelayOverrideRelays(rows, publicKey);
    PreferenceChannel.instance.onmessage?.({ data: { urls: rows.map(row => row.url), accountPublicKey: publicKey, persisted: true } } as MessageEvent);
    expect(probe.destinations(app.id)).toEqual([CUSTOM, DEFAULT, OTHER]);
    const disabled = rows.map(row => ({ ...row, enabled: row.url === OTHER }));
    writeRelayOverrideRelays(disabled, publicKey);
    PreferenceChannel.instance.onmessage?.({ data: { urls: rows.map(row => row.url), accountPublicKey: publicKey, persisted: true } } as MessageEvent);
    expect(probe.destinations(app.id)).toEqual([OTHER]);
    expect(probe.destinations(shared.id)).toEqual([OTHER]);
    writeRelayOverrideRelays(disabled);
    storageChanged();
    expect(probe.destinations(app.id)).toEqual([]);
    const reloaded = client();
    register(reloaded.evolu, app);
    await Promise.resolve();
    expect(reloaded.destinations(app.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ kind: "relays", relays: disabled, accountPublicKey: null });
});

it.each([true, false])("never revives removed relays from an older overlapping profile read (kept enabled=%s)", async enabled => {
    const previous = [{ url: CUSTOM, enabled: true }];
    const current = [{ url: OTHER, enabled }];
    const auth = await canonicalRows(previous);
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    authMocks.get.mockClear();
    let finishOld!: (value: unknown) => void;
    authMocks.get.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve; }))
        .mockResolvedValueOnce({ data: { ...auth.user, evolu_relays: current } });
    const oldRead = auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(1));
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(probe.destinations(app.id)).toEqual(enabled ? [OTHER] : []);
    finishOld({ data: { ...auth.user, evolu_relays: previous } });
    await expect(oldRead).resolves.toBe("unavailable");
    expect(auth.user?.evolu_relays).toEqual(current);
    expect(readRelayOverride()).toMatchObject({ kind: "relays", relays: current });
    expect(probe.destinations(app.id)).toEqual(enabled ? [OTHER] : []);
    expect(probe.destinations(shared.id)).toEqual(enabled ? [OTHER] : []);
});

it("a superseded unauthorized profile response cannot clear newer authentication or relay registrations", async () => {
    const current = [{ url: OTHER, enabled: true }];
    const auth = await canonicalRows([{ url: CUSTOM, enabled: true }]);
    const probe = client();
    register(probe.evolu, app);
    await Promise.resolve();
    authMocks.get.mockClear();
    let rejectOld!: (error: unknown) => void;
    authMocks.get.mockImplementationOnce(() => new Promise((_resolve, reject) => { rejectOld = reject; }))
        .mockResolvedValueOnce({ data: { ...auth.user, evolu_relays: current } });
    const oldRead = auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(1));
    await auth.fetchUser();
    rejectOld({ response: { status: 401 } });
    await expect(oldRead).resolves.toBe("unavailable");
    expect(auth.user?.id).toBe(7);
    expect(readRelayOverride()).toMatchObject({ kind: "relays", relays: current });
    expect(probe.destinations(app.id)).toEqual([OTHER]);
});

it("an older account response cannot restore its identity or destinations after the latest account wins", async () => {
    const auth = await canonicalRows([{ url: CUSTOM, enabled: true }]);
    const original = { ...auth.user };
    const oldOwner = client();
    const newOwner = client(Promise.resolve(otherApp));
    register(oldOwner.evolu, app);
    register(oldOwner.evolu, shared);
    register(newOwner.evolu, otherApp);
    await Promise.resolve();
    authMocks.get.mockClear();
    let finishOld!: (value: unknown) => void;
    authMocks.get.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve; }))
        .mockResolvedValueOnce({ data: { id: 8, email: "other@example.test", recovery_public_key: otherPublicKey,
            evolu_relays: [{ url: OTHER, enabled: true }] } });
    const oldRead = auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(1));
    await auth.fetchUser();
    finishOld({ data: original });
    await expect(oldRead).resolves.toBe("unavailable");
    expect(auth.user?.id).toBe(8);
    expect(readRelayOverride()).toMatchObject({ accountPublicKey: otherPublicKey });
    expect(oldOwner.destinations(app.id)).toEqual([]);
    expect(oldOwner.destinations(shared.id)).toEqual([]);
    expect(newOwner.destinations(otherApp.id)).toEqual([OTHER]);
});

it("keeps the last confirmed destinations when a newer profile read fails and an older read succeeds later", async () => {
    const current = [{ url: OTHER, enabled: true }];
    const auth = await canonicalRows(current);
    const probe = client();
    register(probe.evolu, app);
    await Promise.resolve();
    authMocks.get.mockClear();
    let finishOld!: (value: unknown) => void;
    authMocks.get.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve; }))
        .mockRejectedValueOnce({ response: { status: 503 } });
    const oldRead = auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(1));
    await expect(auth.fetchUser()).resolves.toBe(false);
    finishOld({ data: { ...auth.user, evolu_relays: [{ url: CUSTOM, enabled: true }] } });
    await expect(oldRead).resolves.toBe("unavailable");
    expect(readRelayOverride()).toMatchObject({ relays: current });
    expect(probe.destinations(app.id)).toEqual([OTHER]);
});

it.each([false, true])("retries a canonical profile changed by another tab without relying on storage events (old unauthorized=%s)", async unauthorized => {
    const oldRows = [{ url: CUSTOM, enabled: true }];
    const currentRows = [{ url: OTHER, enabled: true }];
    const auth = await canonicalRows(oldRows);
    const originalProfile = { ...auth.user };
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    authMocks.get.mockClear();
    let finishOld!: (value: unknown) => void;
    let rejectOld!: (error: unknown) => void;
    authMocks.get.mockImplementationOnce(() => new Promise((resolve, reject) => { finishOld = resolve; rejectOld = reject; }))
        .mockResolvedValueOnce({ data: { ...originalProfile, evolu_relays: currentRows } });
    const read = auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(1));
    writeRelayOverrideRelays(currentRows, publicKey);
    if (unauthorized) rejectOld({ response: { status: 401 } });
    else finishOld({ data: originalProfile });
    await expect(read).resolves.toBe("loaded");
    expect(authMocks.get).toHaveBeenCalledTimes(2);
    expect(auth.user?.evolu_relays).toEqual(currentRows);
    expect(readRelayOverride()).toMatchObject({ relays: currentRows });
    expect(probe.destinations(app.id)).toEqual([OTHER]);
    expect(probe.destinations(shared.id)).toEqual([OTHER]);
});

it("caps cross-tab profile retries and never commits a response after a second cache change", async () => {
    const auth = await canonicalRows([{ url: CUSTOM, enabled: true }]);
    const probe = client();
    register(probe.evolu, app);
    await Promise.resolve();
    authMocks.get.mockClear();
    const finish: Array<(value: unknown) => void> = [];
    authMocks.get.mockImplementation(() => new Promise(resolve => { finish.push(resolve); }));
    const read = auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(1));
    writeRelayOverrideRelays([{ url: OTHER, enabled: true }], publicKey);
    finish[0]({ data: { ...auth.user, evolu_relays: [{ url: CUSTOM, enabled: true }] } });
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(2));
    expect(probe.destinations(app.id)).toEqual([]);
    expect(readRelayOverride()).toMatchObject({ relays: [{ url: OTHER, enabled: true }] });
    writeRelayOverrideRelays([], publicKey);
    finish[1]({ data: { ...auth.user, evolu_relays: [{ url: OTHER, enabled: true }] } });
    await expect(read).resolves.toBe("unavailable");
    expect(authMocks.get).toHaveBeenCalledTimes(2);
    expect(readRelayOverride()).toMatchObject({ relays: [] });
    expect(probe.destinations(app.id)).toEqual([]);
});


it("never restores an old account identity when a missed cross-tab preference change requires canonical retry", async () => {
    const auth = await canonicalRows([{ url: CUSTOM, enabled: true }]);
    const originalProfile = { ...auth.user };
    const oldOwner = client();
    const newOwner = client(Promise.resolve(otherApp));
    register(oldOwner.evolu, app);
    register(oldOwner.evolu, shared);
    register(newOwner.evolu, otherApp);
    await Promise.resolve();
    authMocks.get.mockClear();
    let finishOld!: (value: unknown) => void;
    authMocks.get.mockImplementationOnce(() => new Promise(resolve => { finishOld = resolve; }))
        .mockResolvedValueOnce({ data: { id: 8, email: "other@example.test", recovery_public_key: otherPublicKey,
            evolu_relays: [{ url: OTHER, enabled: true }] } });
    const read = auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(authMocks.get).toHaveBeenCalledTimes(1));
    writeRelayOverrideRelays([{ url: OTHER, enabled: true }], otherPublicKey);
    finishOld({ data: originalProfile });
    await expect(read).resolves.toBe("loaded");
    expect(auth.user?.id).toBe(8);
    expect(readRelayOverride()).toMatchObject({ accountPublicKey: otherPublicKey });
    expect(oldOwner.destinations(app.id)).toEqual([]);
    expect(oldOwner.destinations(shared.id)).toEqual([]);
    expect(newOwner.destinations(otherApp.id)).toEqual([OTHER]);
});

it("pauses owned references without clearing authentication when preferences change during a failed CSRF wait", async () => {
    const auth = await canonicalRows([{ url: CUSTOM, enabled: true }]);
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    authMocks.get.mockClear();
    let failCsrf!: (error: unknown) => void;
    authMocks.csrf.mockImplementationOnce(() => new Promise((_resolve, reject) => { failCsrf = reject; }));
    const read = auth.fetchUserWithOutcome();
    writeRelayOverrideRelays([{ url: OTHER, enabled: true }], publicKey);
    failCsrf({ response: { status: 401 } });
    await expect(read).resolves.toBe("unavailable");
    expect(auth.user?.id).toBe(7);
    expect(authMocks.get).not.toHaveBeenCalled();
    expect(readRelayOverride()).toMatchObject({ relays: [{ url: OTHER, enabled: true }] });
    expect(probe.destinations(app.id)).toEqual([]);
    expect(probe.destinations(shared.id)).toEqual([]);
});

it("retains authentication but pauses relay destinations when public cache storage stays unavailable", async () => {
    const auth = await canonicalRows([{ url: CUSTOM, enabled: true }]);
    const probe = client();
    register(probe.evolu, app);
    await Promise.resolve();
    const originalGet = Storage.prototype.getItem;
    vi.spyOn(Storage.prototype, "getItem").mockImplementation(function (this: Storage, key: string) {
        if (key === EVOLU_RELAY_OVERRIDE_STORAGE_KEY) throw new Error("synthetic public cache unavailable");
        return originalGet.call(this, key);
    });
    authMocks.get.mockResolvedValue({ data: { ...auth.user } });
    await expect(auth.fetchUserWithOutcome()).resolves.toBe("loaded");
    expect(auth.user?.id).toBe(7);
    expect(probe.destinations(app.id)).toEqual([]);
});
