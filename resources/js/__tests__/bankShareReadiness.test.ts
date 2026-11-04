import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { companyShareInfo, isCompanyShareRegistryReady, clearCompanyShares, markCompanyShareRegistryReady, waitCompanyShareRegistryReady } from "@/evolu/companyShareRegistry";
import { loadCompanyShareRegistry, resetCompanyShareRegistry, type CompanyShareRow } from "@/evolu/companyShareBootstrap";

const mocks = vi.hoisted(() => ({ resume: vi.fn(), purge: vi.fn(), rekey: vi.fn(), register: vi.fn(), unregister: vi.fn() }));
vi.mock("@/evolu/client", () => ({ allCompanySharesQuery: "shares" }));
vi.mock("@/evolu/sharedOwner", () => ({
    decodeOwnerSecret: () => "synthetic-decoded-fixture",
    sharedOwnerFromSecret: () => ({ id: "synthetic-shared" }),
    registerSharedOwner: mocks.register, unregisterSharedOwner: mocks.unregister,
}));
vi.mock("@/evolu/companyShareMigration", () => ({ resumePendingCompanyShareMigrations: mocks.resume, purgeRevokedShareResidue: mocks.purge }));
vi.mock("@/evolu/companyShareRekey", () => ({ resumePendingCompanyShareRekeys: mocks.rekey }));

let rows: CompanyShareRow[] = [];
let patch: (() => void) | undefined;
let subscribeFails = false;
const unsubscribe = vi.fn();
const evolu = {
    appOwner: Promise.resolve({ id: "synthetic-app" }),
    loadQuery: vi.fn(async () => rows),
    getQueryRows: vi.fn(() => rows),
    subscribeQuery: vi.fn(() => (callback: () => void) => {
        if (subscribeFails) throw new Error("synthetic_subscribe_failure");
        patch = callback;
        return unsubscribe;
    }),
};
const shareRow = (status: CompanyShareRow["status"] = "active"): CompanyShareRow => ({
    id: "synthetic-share-row", ownerId: "synthetic-app", companyId: "synthetic-company", sharedOwnerId: "synthetic-shared",
    secretB64: "synthetic-encoded-fixture", role: "member", status,
    bridgeCompanyId: "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b",
});

beforeEach(async () => {
    await resetCompanyShareRegistry(evolu as never);
    vi.clearAllMocks(); rows = []; patch = undefined; subscribeFails = false;
    mocks.resume.mockResolvedValue(undefined); mocks.purge.mockResolvedValue(undefined); mocks.rekey.mockResolvedValue(undefined);
});
afterEach(async () => { await resetCompanyShareRegistry(evolu as never); vi.restoreAllMocks(); });

it("keeps validated patches subscribed when resumable maintenance rejects after readiness", async () => {
    rows = [shareRow("migrating")];
    mocks.resume.mockRejectedValueOnce(new Error("synthetic_maintenance_failure"));
    await expect(loadCompanyShareRegistry(evolu as never)).rejects.toThrow("synthetic_maintenance_failure");
    expect(evolu.subscribeQuery).toHaveBeenCalledTimes(1);
    expect(isCompanyShareRegistryReady()).toBe(true);
    expect(patch).toBeTypeOf("function");
    rows = [{ ...shareRow(), bridgeCompanyId: "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6c" }];
    patch!();
    expect(companyShareInfo("synthetic-company")?.bridgeCompanyId).toBe(rows[0].bridgeCompanyId);
    expect(companyShareInfo("synthetic-company")?.status).toBe("active");
    expect(unsubscribe).not.toHaveBeenCalled();
});

it("does not publish readiness or run maintenance when subscription installation fails", async () => {
    rows = [shareRow("migrating")]; subscribeFails = true;
    await expect(loadCompanyShareRegistry(evolu as never)).rejects.toThrow("synthetic_subscribe_failure");
    expect(isCompanyShareRegistryReady()).toBe(false);
    expect(mocks.resume).not.toHaveBeenCalled();
    expect(mocks.purge).not.toHaveBeenCalled();
});

it("removes the abort listener and reactive waiter after initial readiness", async () => {
    clearCompanyShares();
    const controller = new AbortController();
    const remove = vi.spyOn(controller.signal, "removeEventListener");
    const waiting = waitCompanyShareRegistryReady(controller.signal);
    markCompanyShareRegistryReady();
    await waiting;
    expect(remove).toHaveBeenCalledTimes(1);
    clearCompanyShares(); markCompanyShareRegistryReady(); controller.abort();
    expect(remove).toHaveBeenCalledTimes(1);
});

it("aborts an unresolved readiness wait and leaves no callback after bootstrap later completes", async () => {
    clearCompanyShares();
    const controller = new AbortController();
    const remove = vi.spyOn(controller.signal, "removeEventListener");
    const waiting = waitCompanyShareRegistryReady(controller.signal).catch((error) => error);
    controller.abort();
    expect(await waiting).toMatchObject({ message: "company_share_registry_wait_aborted" });
    expect(remove).toHaveBeenCalledTimes(1);
    markCompanyShareRegistryReady();
    expect(remove).toHaveBeenCalledTimes(1);
});
