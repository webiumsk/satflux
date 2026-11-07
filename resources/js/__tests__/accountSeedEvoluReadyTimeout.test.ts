import { flushPromises } from "@vue/test-utils";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

// Private windows without OPFS (e.g. Firefox private browsing): the Evolu DB
// worker never initializes and `evolu.appOwner` stays pending forever. The
// login path must degrade instead of hanging on it (GuestRestoreModal was
// stuck on "Loading..." indefinitely).
const mocks = vi.hoisted(() => ({
    owner: new Promise<unknown>(() => {}), restore: vi.fn(), snapshot: vi.fn(),
    restoreSnapshot: vi.fn(), hasData: vi.fn(), markPending: vi.fn(), ensureSubscription: vi.fn(), wait: vi.fn(),
}));
vi.mock("@/evolu/client", () => ({
    evolu: { get appOwner() { return mocks.owner; }, restoreAppOwner: mocks.restore },
}));
vi.mock("@/evolu/relaySyncWait", () => ({
    isEvoluRelaySyncPending: () => false,
    markEvoluRelaySyncPending: mocks.markPending,
    waitForInvoicingRelaySync: mocks.wait,
}));
vi.mock("@/evolu/evoluRelaySubscription", () => ({ ensureEvoluRelaySubscription: mocks.ensureSubscription }));
vi.mock("@/evolu/invoicingSnapshot", () => ({
    snapshotInvoicingData: mocks.snapshot, snapshotHasInvoicingData: mocks.hasData,
    restoreInvoicingSnapshotAsync: mocks.restoreSnapshot,
}));

/** Fresh module per test - the readiness latch is module state. */
async function loadAccountSeed() {
    return import("@/services/accountSeed");
}

beforeEach(() => {
    vi.resetModules();
    vi.clearAllMocks();
    mocks.owner = new Promise(() => {});
    mocks.restore.mockResolvedValue(undefined);
    mocks.snapshot.mockResolvedValue({ company: [] });
    mocks.hasData.mockReturnValue(false);
    mocks.wait.mockResolvedValue(true);
    vi.useFakeTimers();
    sessionStorage.clear();
});

afterEach(() => {
    vi.useRealTimers();
    sessionStorage.clear();
});

describe("account seed - Evolu readiness timeout", () => {
    it("previewOwnerSwitchImpact reports no switch when appOwner never settles", async () => {
        const {
            EVOLU_READY_PROBE_FAILED_KEY,
            EVOLU_READY_TIMEOUT_MS,
            generateAccountMnemonic24,
            previewOwnerSwitchImpact,
        } = await loadAccountSeed();

        const pending = previewOwnerSwitchImpact(generateAccountMnemonic24());
        await vi.advanceTimersByTimeAsync(EVOLU_READY_TIMEOUT_MS);
        await expect(pending).resolves.toEqual({ switches: false });
        expect(sessionStorage.getItem(EVOLU_READY_PROBE_FAILED_KEY)).toBe("1");
    });

    it("later probes fail fast after a timeout in the same page load", async () => {
        const {
            EVOLU_READY_FAST_PROBE_TIMEOUT_MS,
            EVOLU_READY_TIMEOUT_MS,
            generateAccountMnemonic24,
            previewOwnerSwitchImpact,
        } = await loadAccountSeed();

        // Arrange the in-page latch: one probe has already timed out.
        const first = previewOwnerSwitchImpact(generateAccountMnemonic24());
        await vi.advanceTimersByTimeAsync(EVOLU_READY_TIMEOUT_MS);
        await expect(first).resolves.toEqual({ switches: false });

        const second = previewOwnerSwitchImpact(generateAccountMnemonic24());
        await vi.advanceTimersByTimeAsync(EVOLU_READY_FAST_PROBE_TIMEOUT_MS);
        await expect(second).resolves.toEqual({ switches: false });
    });

    it("uses the shorter retry probe when a previous page load left the failure flag", async () => {
        const {
            EVOLU_READY_PROBE_FAILED_KEY,
            EVOLU_READY_RETRY_TIMEOUT_MS,
            generateAccountMnemonic24,
            previewOwnerSwitchImpact,
        } = await loadAccountSeed();

        sessionStorage.setItem(EVOLU_READY_PROBE_FAILED_KEY, "1");
        const pending = previewOwnerSwitchImpact(generateAccountMnemonic24());
        await vi.advanceTimersByTimeAsync(EVOLU_READY_RETRY_TIMEOUT_MS);
        await expect(pending).resolves.toEqual({ switches: false });
    });

    it("initEvoluFromAccountSeedIfNeeded fails fast when appOwner never settles", async () => {
        const {
            EVOLU_READY_TIMEOUT_MS,
            generateAccountMnemonic24,
            initEvoluFromAccountSeedIfNeeded,
            isEvoluUnavailableError,
        } = await loadAccountSeed();

        const pending = initEvoluFromAccountSeedIfNeeded(generateAccountMnemonic24());
        // Silence the unhandled-rejection window while timers advance.
        pending.catch(() => {});
        await vi.advanceTimersByTimeAsync(EVOLU_READY_TIMEOUT_MS);
        await expect(pending).rejects.toSatisfy(isEvoluUnavailableError);
    });
});


describe("account seed - optional initiating context", () => {
    it("does not issue an owner restore after its initiating context changes during the snapshot read", async () => {
        vi.useRealTimers();
        const seed = await loadAccountSeed();
        mocks.owner = Promise.resolve({ mnemonic: null });
        let finish!: (value: object) => void;
        let current = true;
        mocks.snapshot.mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
        const pending = seed.initEvoluFromAccountSeedIfNeeded(seed.generateAccountMnemonic24(), () => current);
        await flushPromises();
        expect(mocks.snapshot).toHaveBeenCalledTimes(1);
        current = false;
        finish({ company: [] });
        await pending.catch(() => {});
        expect(mocks.restore.mock.calls.length).toBe(0);
        expect(mocks.markPending).not.toHaveBeenCalled();
        expect(mocks.restoreSnapshot).not.toHaveBeenCalled();
    });

    it("stops before snapshot or owner changes when readiness resolves after the context becomes stale", async () => {
        vi.useRealTimers();
        const seed = await loadAccountSeed();
        let finishOwner!: (value: unknown) => void;
        let current = true;
        mocks.owner = new Promise(resolve => { finishOwner = resolve; });
        const pending = seed.initEvoluFromAccountSeedIfNeeded(seed.generateAccountMnemonic24(), () => current);
        await flushPromises();
        current = false;
        finishOwner({ mnemonic: null });
        await pending.catch(() => {});
        expect(mocks.snapshot).not.toHaveBeenCalled();
        expect(mocks.restore.mock.calls.length).toBe(0);
    });

    it("does not issue additional restore flags or subscriptions after an already-submitted restore loses context", async () => {
        vi.useRealTimers();
        const seed = await loadAccountSeed();
        mocks.owner = Promise.resolve({ mnemonic: null });
        let finishRestore!: (value: unknown) => void;
        let current = true;
        mocks.restore.mockImplementationOnce(() => new Promise(resolve => { finishRestore = resolve; }));
        const pending = seed.initEvoluFromAccountSeedIfNeeded(seed.generateAccountMnemonic24(), () => current);
        await flushPromises();
        expect(mocks.restore.mock.calls.length).toBe(1);
        current = false;
        finishRestore(undefined);
        await pending.catch(() => {});
        expect(seed.hasEvoluOwnerRestoreBeenAttempted()).toBe(false);
        expect(mocks.ensureSubscription).not.toHaveBeenCalled();
        expect(mocks.restoreSnapshot).not.toHaveBeenCalled();
    });

    it("retains a pending recovery snapshot and does not fall through to owner reset after cancellation", async () => {
        vi.useRealTimers();
        const seed = await loadAccountSeed();
        mocks.owner = Promise.resolve({ mnemonic: null });
        mocks.hasData.mockReturnValue(true);
        const snapshotKey = "satflux.evolu.pending_owner_migration_snapshot.v1";
        const raw = JSON.stringify({ company: [{ id: "synthetic-retained-company" }] });
        sessionStorage.setItem(snapshotKey, raw);
        let current = true;
        let finishRestore!: (value: unknown) => void;
        mocks.restoreSnapshot.mockImplementationOnce(() => new Promise(resolve => { finishRestore = resolve; }));
        const pending = seed.initEvoluFromAccountSeedIfNeeded(seed.generateAccountMnemonic24(), () => current);
        await flushPromises();
        expect(mocks.restoreSnapshot.mock.calls.length).toBe(1);
        current = false;
        finishRestore(undefined);
        await pending.catch(() => {});
        expect(sessionStorage.getItem(snapshotKey)).toBe(raw);
        expect(mocks.snapshot.mock.calls.length).toBe(0);
        expect(mocks.restore.mock.calls.length).toBe(0);
        expect(mocks.ensureSubscription.mock.calls.length).toBe(0);
    });

    it.each([false, true])("preserves ordinary initialization for an absent or current predicate (guarded=%s)", async guarded => {
        vi.useRealTimers();
        const seed = await loadAccountSeed();
        mocks.owner = Promise.resolve({ mnemonic: null });
        await seed.initEvoluFromAccountSeedIfNeeded(seed.generateAccountMnemonic24(), guarded ? () => true : undefined);
        expect(mocks.restore.mock.calls.length).toBe(1);
    });
});
