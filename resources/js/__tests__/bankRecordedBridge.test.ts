import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { effectScope, watch } from "vue";
import type { OwnerId } from "@evolu/common/local-first";
import api from "@/services/api";
import { ensureBridgeCompanyIdForLocalCompany } from "@/evolu/bridgeCompanyEnsure";
import { clearCompanyShares, companyShareRoutingKey, markCompanyShareRegistryReady, removeCompanyShare, setCompanyShare, type CompanyShareInfo } from "@/evolu/companyShareRegistry";

const fixtures = vi.hoisted(() => ({ rows: [] as { id: string; ownerId?: string; legalName: string; registrationNumber: string }[], load: vi.fn() }));
vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "companies",
    evolu: { loadQuery: fixtures.load, appOwner: Promise.resolve({ id: "synthetic-app" }) },
}));
const originalAdapter = api.defaults.adapter;
const pin = "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b";
const otherPin = "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6c";
const share = (patch: Partial<CompanyShareInfo> = {}): CompanyShareInfo => ({ companyId: "local-a", ownerId: "synthetic-shared" as OwnerId, status: "active", role: "member", bridgeCompanyId: pin, ...patch });
const bankMode = { preferRecordedBridgeId: true };

beforeEach(() => {
    clearCompanyShares(); markCompanyShareRegistryReady(); fixtures.load.mockReset();
    fixtures.rows = [{ id: "local-a", ownerId: "synthetic-app", legalName: "Synthetic company", registrationNumber: "123" }];
    fixtures.load.mockImplementation(async () => fixtures.rows);
});
afterEach(() => { clearCompanyShares(); api.defaults.adapter = originalAdapter; vi.restoreAllMocks(); });

it.each(["active", "migrating"] as const)("uses an eligible %s pin before legal identity, without consulting a duplicate server company", async (status) => {
    setCompanyShare(share({ status, bridgeCompanyId: ` ${pin} ` }));
    fixtures.rows[0] = { ...fixtures.rows[0], ownerId: "synthetic-shared", legalName: "" };
    const transport = vi.fn(async () => { throw new Error("pinned bank route must not look up or create a duplicate"); });
    api.defaults.adapter = transport;
    await expect(ensureBridgeCompanyIdForLocalCompany("local-a", undefined, bankMode))
        .resolves.toEqual({ ok: true, bridgeCompanyId: pin });
    expect(fixtures.load).toHaveBeenCalledWith("companies");
    expect(transport).not.toHaveBeenCalled();
});

it("retains canonical identity fallback for a definite private AppOwner row", async () => {
    const requests: string[] = [];
    api.defaults.adapter = async (config) => {
        requests.push(config.method!);
        return { config, status: 200, statusText: "Synthetic", headers: {}, data: { data: [{ id: "private-canonical", legal_name: "Synthetic company", registration_number: "123" }] } };
    };
    await expect(ensureBridgeCompanyIdForLocalCompany("local-a", undefined, bankMode))
        .resolves.toEqual({ ok: true, bridgeCompanyId: "private-canonical" });
    expect(requests).toEqual(["get"]);
});

it.each(["unready", "missing-pin", "malformed-pin", "revoked", "rekeying", "unbound-shared", "foreign-owner", "missing-owner", "missing-row", "ambiguous-rows"] as const)("fails closed for %s without identity lookup or creation", async (condition) => {
    setCompanyShare(share());
    fixtures.rows[0].ownerId = "synthetic-shared";
    if (condition === "unready") clearCompanyShares();
    else if (condition === "missing-pin") setCompanyShare(share({ bridgeCompanyId: null }));
    else if (condition === "malformed-pin") setCompanyShare(share({ bridgeCompanyId: "local-not-server-uuid" }));
    else if (condition === "revoked" || condition === "rekeying") setCompanyShare(share({ status: condition }));
    else if (condition === "unbound-shared") removeCompanyShare("local-a");
    else if (condition === "foreign-owner") fixtures.rows[0].ownerId = "different-owner";
    else if (condition === "missing-owner") delete fixtures.rows[0].ownerId;
    else if (condition === "missing-row") fixtures.rows = [];
    else fixtures.rows.push({ ...fixtures.rows[0], ownerId: "synthetic-app" });
    const transport = vi.fn(async () => { throw new Error("no identity fallback for unresolved shared routing"); });
    api.defaults.adapter = transport;
    await expect(ensureBridgeCompanyIdForLocalCompany("local-a", undefined, bankMode)).resolves.toEqual({ ok: false });
    expect(transport).not.toHaveBeenCalled();
});

it("leaves default helper callers on their existing identity route despite a recorded share", async () => {
    setCompanyShare(share());
    fixtures.rows[0].ownerId = "synthetic-shared";
    const requests: string[] = [];
    api.defaults.adapter = async (config) => {
        requests.push(config.method!);
        return { config, status: 200, statusText: "Synthetic", headers: {}, data: { data: [{ id: "identity-default", legal_name: "Synthetic company", registration_number: "123" }] } };
    };
    await expect(ensureBridgeCompanyIdForLocalCompany("local-a")).resolves.toEqual({ ok: true, bridgeCompanyId: "identity-default" });
    expect(requests).toEqual(["get"]);
});

it("observes selected routing ABA/readiness changes, ignoring unrelated and repeated semantic updates", () => {
    const scope = effectScope();
    const changes: string[] = [];
    scope.run(() => watch(() => companyShareRoutingKey("local-a"), (key) => changes.push(key), { flush: "sync" }));
    setCompanyShare(share());
    expect(changes).toHaveLength(1);
    setCompanyShare(share());
    setCompanyShare(share({ companyId: "unrelated" }));
    expect(changes).toHaveLength(1);
    setCompanyShare(share({ bridgeCompanyId: otherPin }));
    setCompanyShare(share());
    expect(changes).toHaveLength(3);
    expect(changes[0]).toBe(changes[2]);
    removeCompanyShare("unrelated");
    expect(changes).toHaveLength(3);
    clearCompanyShares(); markCompanyShareRegistryReady();
    expect(changes).toHaveLength(5);
    scope.stop();
});

it("declines a recorded mapping changed while company metadata was being loaded", async () => {
    setCompanyShare(share()); fixtures.rows[0].ownerId = "synthetic-shared";
    let release!: (rows: typeof fixtures.rows) => void;
    fixtures.load.mockReturnValueOnce(new Promise((resolve) => { release = resolve; }));
    const pending = ensureBridgeCompanyIdForLocalCompany("local-a", undefined, bankMode);
    await vi.waitFor(() => expect(fixtures.load).toHaveBeenCalledTimes(1));
    setCompanyShare(share({ bridgeCompanyId: otherPin }));
    release(fixtures.rows);
    await expect(pending).resolves.toEqual({ ok: false });
});
