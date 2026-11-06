import { afterAll, afterEach, beforeAll, beforeEach, expect, it, vi } from "vitest";
import { createPinia, setActivePinia } from "pinia";
import { createRandomBytes } from "@evolu/common";
import { createAppOwner, createOwnerSecret, createSharedOwner, type Evolu, type SyncOwner } from "@evolu/common/local-first";
import { deriveRecoveryPublicKeyHex } from "@/services/accountSeed";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { EVOLU_RELAY_OVERRIDE_STORAGE_KEY, readRelayOverride, writeRelayOverrideDisabled, writeRelayOverrideUrl } from "@/evolu/relayOverrideStorage";
import { reconcileEvoluRelayPreference, registerEvoluRelayOwner } from "@/evolu/relayPreferenceLifecycle";
import { useAuthStore } from "@/store/auth";

const authMocks = vi.hoisted(() => ({ get: vi.fn(), csrf: vi.fn() }));
vi.mock("@/services/api", () => ({ default: { get: authMocks.get } }));
vi.mock("@/services/csrf", () => ({ ensureCsrfCookie: authMocks.csrf }));
vi.mock("@/services/chorala", () => ({ syncChoralaIdentity: vi.fn() }));
vi.mock("@/evolu/bootstrap", () => ({ ensureEvoluBoundToAccountSeed: vi.fn() }));
vi.mock("@/store/stores", () => ({ useStoresStore: () => ({ stores: [], currentStore: null }) }));

const DEFAULT = "wss://default.example.test";
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
    reconcileEvoluRelayPreference(url ? [url, ...(includeDefault ? [DEFAULT] : [])] : [DEFAULT], accountPublicKey);
}

/** Deliver a synthetic storage event; handlers must read current storage rather than old event values. */
function storageChanged(): void {
    window.dispatchEvent(new StorageEvent("storage", { key: EVOLU_RELAY_OVERRIDE_STORAGE_KEY }));
}

beforeAll(() => { vi.stubGlobal("BroadcastChannel", PreferenceChannel); });
afterAll(() => { vi.unstubAllGlobals(); });
beforeEach(() => {
    localStorage.clear();
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
});

it("retires default registrations for both owner kinds and keeps the replacement registered before release", async () => {
    const probe = client();
    register(probe.evolu, app);
    register(probe.evolu, shared);
    await Promise.resolve();
    expect(probe.destinations(app.id)).toEqual([CUSTOM, DEFAULT]);
    expect(probe.destinations(shared.id)).toEqual([CUSTOM, DEFAULT]);
    probe.calls.length = 0;
    canonical(CUSTOM, false);
    expect(probe.destinations(app.id)).toEqual([CUSTOM]);
    expect(probe.destinations(shared.id)).toEqual([CUSTOM]);
    expect(probe.calls).toEqual(["register", "release", "register", "release"]);
});

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
