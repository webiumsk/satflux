import { flushPromises } from "@vue/test-utils";
import { createPinia, setActivePinia } from "pinia";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { createRandomBytes } from "@evolu/common";
import { createAppOwner, createOwnerSecret, createSharedOwner, type Evolu, type SyncOwner } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "@/evolu/schema";

const mocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), csrf: vi.fn(), stores: { stores: [], currentStore: null } }));
vi.mock("@/services/api", () => ({ default: { get: mocks.get, post: mocks.post } }));
vi.mock("@/services/csrf", () => ({ ensureCsrfCookie: mocks.csrf }));
vi.mock("@/services/chorala", () => ({ syncChoralaIdentity: vi.fn() }));
vi.mock("@/evolu/bootstrap", () => ({ ensureEvoluBoundToAccountSeed: vi.fn() }));
vi.mock("@/store/stores", () => ({ useStoresStore: () => mocks.stores }));
vi.mock("@/router", () => ({ default: { replace: vi.fn() } }));

const removed = "wss://removed.example.test";
const kept = "wss://kept.example.test";
const cleanups: Array<() => void> = [];

/** Keep canonical announcements observable without delivering an automatic echo. */
class Channel {
    static current: Channel;
    onmessage: ((event: MessageEvent<unknown>) => void) | null = null;
    postMessage = vi.fn();
    constructor() { Channel.current = this; }
}

beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
    localStorage.clear();
    sessionStorage.clear();
    vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://default.example.test");
    vi.stubEnv("VITE_INVOICING_LOCAL_FIRST", "true");
    vi.stubGlobal("BroadcastChannel", Channel);
    mocks.csrf.mockResolvedValue(undefined);
    mocks.get.mockRejectedValue(new Error("Synthetic unavailable canonical profile"));
});
afterEach(() => {
    for (const cleanup of cleanups.splice(0).reverse()) cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
});

/** Create a fresh tab module and observe only registered origins, never owner secrets. */
async function setup(retainedOwner = createAppOwner(createOwnerSecret({ randomBytes: createRandomBytes() }))) {
    setActivePinia(createPinia());
    const { useAuthStore } = await import("@/store/auth");
    const lifecycle = await import("@/evolu/relayPreferenceLifecycle");
    const cache = await import("@/evolu/relayOverrideStorage");
    const { deriveRecoveryPublicKeyHex } = await import("@/services/accountSeed");
    const publicKey = deriveRecoveryPublicKeyHex(retainedOwner.mnemonic!);
    const auth = useAuthStore();
    cleanups.push(() => auth.$dispose());
    auth.user = { id: 7, email: "synthetic@example.test", recovery_public_key: publicKey };
    const active = new Map<symbol, SyncOwner>();
    const history: string[][] = [];
    const evolu = {
        appOwner: Promise.resolve(retainedOwner),
        /** Track each registration until its reference is released. */
        useOwner(owner: SyncOwner) {
            const token = Symbol();
            active.set(token, owner);
            history.push((owner.transports ?? []).map(t => new URL(t.url).origin));
            return () => { active.delete(token); };
        },
    } as unknown as Evolu<InvoicingLocalSchema>;
    const shared = createSharedOwner(createOwnerSecret({ randomBytes: createRandomBytes() }));
    const profile = (urls: string[]) => ({ id: 7, email: "synthetic@example.test", recovery_public_key: publicKey,
        evolu_relays: urls.map(url => ({ url, enabled: true })) });
    return { auth, lifecycle, cache, publicKey, retainedOwner, evolu, shared, history, profile,
        register() {
            cleanups.push(lifecycle.registerEvoluRelayOwner(evolu, retainedOwner));
            cleanups.push(lifecycle.registerEvoluRelayOwner(evolu, shared));
        },
        urls: () => [...new Set([...active.values()].flatMap(owner => owner.transports ?? []).map(t => new URL(t.url).origin))],
    };
}

it.each(["raw", "legacy", "guarded"] as const)("never authorizes startup from %s cache while canonical profile is unavailable", async format => {
    const x = await setup();
    if (format === "raw") x.cache.writeRelayOverrideUrl(removed);
    else if (format === "legacy") x.cache.writeRelayOverrideUrl(removed, true, x.publicKey);
    else x.cache.writeRelayOverrideRelays([{ url: removed, enabled: true }], x.publicKey);
    x.register();
    expect(x.urls()).toEqual([]);
    await flushPromises();
    expect(x.urls()).toEqual([]);
    expect(x.history.flat()).not.toContain(removed);
});

it("joins the router's initial canonical read rather than superseding its authentication result", async () => {
    const x = await setup();
    let finish!: (value: unknown) => void;
    mocks.get.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
    const initialRead = x.auth.fetchUserWithOutcome();
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(1));
    x.register();
    finish({ data: x.profile([kept]) });
    await expect(initialRead).resolves.toBe("loaded");
    await flushPromises();
    expect(mocks.get).toHaveBeenCalledTimes(1);
    expect(x.urls()).toEqual([kept]);
});

it.each(["resume", "storage", "failed_notice"] as const)("does not revive stale persisted destinations after a failed canonical write on %s", async trigger => {
    const x = await setup();
    mocks.get.mockResolvedValueOnce({ data: x.profile([removed]) });
    await x.auth.fetchUser();
    x.register();
    const originalSet = Storage.prototype.setItem;
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(function (this: Storage, key: string, value: string) {
        if (key === x.cache.EVOLU_RELAY_OVERRIDE_STORAGE_KEY) throw new Error("Synthetic cache write failure");
        originalSet.call(this, key, value);
    });
    mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
    await x.auth.fetchUser();
    expect(x.urls()).toEqual([kept]);
    expect(x.cache.readRelayOverride()).toMatchObject({ relays: [{ url: removed, enabled: true }] });
    const historyStart = x.history.length;
    if (trigger === "resume") {
        window.dispatchEvent(new Event("pagehide"));
        window.dispatchEvent(new Event("pageshow"));
    } else if (trigger === "storage") {
        window.dispatchEvent(new StorageEvent("storage", { key: x.cache.EVOLU_RELAY_OVERRIDE_STORAGE_KEY }));
    } else {
        Channel.current.onmessage?.({ data: { urls: [kept], accountPublicKey: x.publicKey, persisted: false } } as MessageEvent);
    }
    expect(x.urls()).toEqual([]);
    await flushPromises();
    expect(x.urls()).toEqual([]);
    expect(x.history.slice(historyStart).flat()).not.toContain(removed);
});

it.each([false, true])("invalidates in-flight canonical proof even when failed-write notices leave persisted cache unchanged (old401=%s)", async unauthorized => {
    const x = await setup();
    mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
    await x.auth.fetchUser();
    x.register();
    let finish!: (value: unknown) => void;
    let reject!: (error: unknown) => void;
    mocks.get.mockClear();
    mocks.get.mockImplementationOnce(() => new Promise((resolve, no) => { finish = resolve; reject = no; }))
        .mockResolvedValueOnce({ data: x.profile([kept]) });
    Channel.current.postMessage.mockClear();
    window.dispatchEvent(new Event("pagehide"));
    window.dispatchEvent(new Event("pageshow"));
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(1));
    Channel.current.onmessage?.({ data: { urls: [kept], accountPublicKey: x.publicKey, persisted: false } } as MessageEvent);
    if (unauthorized) reject({ response: { status: 401 } });
    else finish({ data: x.profile([removed]) });
    await flushPromises();
    expect(mocks.get).toHaveBeenCalledTimes(2);
    expect(x.auth.user?.id).toBe(7);
    expect(x.urls()).toEqual([kept]);
    expect(Channel.current.postMessage).not.toHaveBeenCalled();
});

it("never lets a superseded validation completion pause a newer manual canonical selection", async () => {
    const x = await setup();
    let finish!: (value: unknown) => void;
    mocks.get.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }))
        .mockResolvedValueOnce({ data: x.profile([kept]) });
    x.register();
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(1));
    await expect(x.auth.fetchUser()).resolves.toBe(true);
    finish({ data: x.profile([removed]) });
    await flushPromises();
    expect(x.urls()).toEqual([kept]);
});

/** Establish an account's canonical configuration while the persistent device-off choice remains set. */
async function setupOff() {
    const x = await setup();
    x.cache.writeRelayOverrideDisabled();
    mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
    await x.auth.fetchUser();
    x.register();
    mocks.get.mockClear();
    return x;
}

it("keeps Off through a fresh resume read and clears it only with the winning account proof", async () => {
    const x = await setupOff();
    let finish!: (value: unknown) => void;
    mocks.get.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
    const resume = x.auth.resumeEvoluRelaySyncOnDevice();
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(1));
    expect(x.cache.readRelayOverride()).toEqual({ kind: "disabled" });
    expect(x.urls()).toEqual([]);
    finish({ data: x.profile([kept]) });
    await expect(resume).resolves.toBe(true);
    expect(x.cache.readRelayOverride()).toMatchObject({ kind: "relays", relays: [{ url: kept, enabled: true }] });
    expect(x.urls()).toEqual([kept]);
});

it.each(["unavailable", "unauthorized", "account", "logout", "dispose", "superseded", "revision"] as const)(
    "never clears Off or activates a relay after an unsuccessful resume (%s)", async failure => {
        const x = await setupOff();
        let finish!: (value: unknown) => void;
        let reject!: (error: unknown) => void;
        mocks.get.mockImplementationOnce(() => new Promise((resolve, no) => { finish = resolve; reject = no; }));
        const resume = x.auth.resumeEvoluRelaySyncOnDevice();
        await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(1));
        if (failure === "logout") await x.auth.logout();
        if (failure === "dispose") x.auth.$dispose();
        if (failure === "superseded") {
            mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
            await x.auth.fetchUser();
        }
        if (failure === "revision") {
            mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
            window.dispatchEvent(new StorageEvent("storage", { key: x.cache.EVOLU_RELAY_OVERRIDE_STORAGE_KEY }));
        }
        if (failure === "unavailable") reject({ response: { status: 503 } });
        else if (failure === "unauthorized") reject({ response: { status: 401 } });
        else finish({ data: { ...x.profile([kept]), id: failure === "account" ? 8 : 7 } });
        await expect(resume).resolves.toBe(false);
        expect(x.cache.readRelayOverride()).toEqual({ kind: "disabled" });
        expect(x.urls()).toEqual([]);
    },
);

it("retains Off when its storage removal fails after successful canonical validation", async () => {
    const x = await setupOff();
    const originalRemove = Storage.prototype.removeItem;
    vi.spyOn(Storage.prototype, "removeItem").mockImplementation(function (this: Storage, key: string) {
        if (key === x.cache.EVOLU_RELAY_OVERRIDE_STORAGE_KEY) throw new Error("Synthetic Off removal failure");
        originalRemove.call(this, key);
    });
    mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
    await expect(x.auth.resumeEvoluRelaySyncOnDevice()).resolves.toBe(false);
    expect(x.cache.readRelayOverride()).toEqual({ kind: "disabled" });
    expect(x.urls()).toEqual([]);
});

it("persists identical keyless server metadata whether this tab has local default-owner proof or not", async () => {
    const x = await setup();
    const seed = await import("@/services/accountSeed");
    const { clearLocalDefaultRelayOwner } = await import("@/evolu/localDefaultRelayProof");
    seed.storeAccountMnemonic(x.retainedOwner.mnemonic!);
    expect(x.auth.captureLocalDefaultRelayOwnerConfirmation()(x.retainedOwner.mnemonic!)).toBe(true);
    const profile = { ...x.profile(["wss://default.example.test"]), recovery_public_key: null };
    mocks.get.mockResolvedValue({ data: profile });
    await x.auth.fetchUser();
    x.register();
    expect(x.urls()).toEqual(["wss://default.example.test"]);
    const provenTabCache = localStorage.getItem(x.cache.EVOLU_RELAY_OVERRIDE_STORAGE_KEY);
    expect(x.cache.readRelayOverride()).toMatchObject({ accountPublicKey: null });
    clearLocalDefaultRelayOwner();
    await x.auth.fetchUser();
    expect(x.urls()).toEqual([]);
    expect(localStorage.getItem(x.cache.EVOLU_RELAY_OVERRIDE_STORAGE_KEY)).toBe(provenTabCache);
});

it("uses fresh canonical startup proof instead of a matching but stale persisted destination", async () => {
    const x = await setup();
    x.cache.writeRelayOverrideRelays([{ url: removed, enabled: true }], x.publicKey);
    mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
    x.register();
    expect(x.urls()).toEqual([]);
    await flushPromises();
    expect(x.urls()).toEqual([kept]);
    expect(x.history.flat()).not.toContain(removed);
});

it.each(["empty", "disabled", "kept"] as const)("revalidates canonical %s after failed persistence before bfcache owners can resume", async mode => {
    const x = await setup();
    mocks.get.mockResolvedValueOnce({ data: x.profile([removed]) });
    await x.auth.fetchUser();
    x.register();
    const originalSet = Storage.prototype.setItem;
    vi.spyOn(Storage.prototype, "setItem").mockImplementation(function (this: Storage, key: string, value: string) {
        if (key === x.cache.EVOLU_RELAY_OVERRIDE_STORAGE_KEY) throw new Error("Synthetic cache write failure");
        originalSet.call(this, key, value);
    });
    const profile = { ...x.profile(mode === "empty" ? [] : [mode === "kept" ? kept : removed]),
        evolu_relays: mode === "disabled" ? [{ url: removed, enabled: false }]
            : x.profile(mode === "empty" ? [] : [kept]).evolu_relays };
    mocks.get.mockResolvedValue({ data: profile });
    await x.auth.fetchUser();
    const historyStart = x.history.length;
    window.dispatchEvent(new Event("pagehide"));
    window.dispatchEvent(new Event("pageshow"));
    expect(x.urls()).toEqual([]);
    await flushPromises();
    expect(x.urls()).toEqual(mode === "kept" ? [kept] : []);
    expect(x.history.slice(historyStart).flat()).not.toContain(removed);
});

it("caps retries after repeated in-flight invalidations even when the persisted cache never changes", async () => {
    const x = await setup();
    mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
    await x.auth.fetchUser();
    x.register();
    mocks.get.mockClear();
    const finish: Array<(value: unknown) => void> = [];
    mocks.get.mockImplementation(() => new Promise(resolve => { finish.push(resolve); }));
    window.dispatchEvent(new Event("pagehide"));
    window.dispatchEvent(new Event("pageshow"));
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(1));
    Channel.current.onmessage?.({ data: { urls: [], accountPublicKey: x.publicKey, persisted: false } } as MessageEvent);
    finish[0]({ data: x.profile([removed]) });
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(2));
    Channel.current.onmessage?.({ data: { urls: [], accountPublicKey: x.publicKey, persisted: false } } as MessageEvent);
    finish[1]({ data: x.profile([removed]) });
    await flushPromises();
    expect(mocks.get).toHaveBeenCalledTimes(2);
    expect(x.urls()).toEqual([]);
});

it.each([401, 503])("pauses already-live owners before resuming a missed device-off event and stays Off on HTTP%s", async status => {
    const x = await setup();
    mocks.get.mockResolvedValueOnce({ data: x.profile([kept]) });
    await x.auth.fetchUser();
    x.register();
    expect(x.urls()).toEqual([kept]);
    x.cache.writeRelayOverrideDisabled();
    expect(x.urls()).toEqual([kept]);
    mocks.get.mockClear();
    let reject!: (error: unknown) => void;
    mocks.get.mockImplementationOnce(() => new Promise((_resolve, no) => { reject = no; }));
    const resume = x.auth.resumeEvoluRelaySyncOnDevice();
    expect(x.urls()).toEqual([]);
    await vi.waitFor(() => expect(mocks.get).toHaveBeenCalledTimes(1));
    reject({ response: { status } });
    await expect(resume).resolves.toBe(false);
    expect(x.cache.readRelayOverride()).toEqual({ kind: "disabled" });
    expect(x.urls()).toEqual([]);
});
