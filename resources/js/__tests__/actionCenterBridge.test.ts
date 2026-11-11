import { beforeEach, describe, expect, it, vi } from "vitest";
import { findActionCenterBridgeCompany } from "@/services/companyActionCenter";
import { clearCompanyShares, markCompanyShareRegistryReady, setCompanyShare } from "@/evolu/companyShareRegistry";

const mocks = vi.hoisted(() => ({ list: vi.fn(), create: vi.fn(), loadQuery: vi.fn() }));
vi.mock("@/services/api", () => ({ default: { get: vi.fn() }, invoicingApi: { companies: { list: mocks.list, create: mocks.create } } }));
vi.mock("@/evolu/client", () => ({ evolu: { loadQuery: mocks.loadQuery, appOwner: Promise.resolve({ id: "owner-a" }) }, allCompaniesDetailQuery: "companies" }));

const bridge = "018f07ad-8e24-7bcf-8a1d-276190f60001";
beforeEach(() => {
    vi.clearAllMocks(); clearCompanyShares(); markCompanyShareRegistryReady();
    mocks.loadQuery.mockResolvedValue([{ id: "local-a", ownerId: "owner-a", legalName: "Demo Company", registrationNumber: "12345" }]);
    mocks.list.mockResolvedValue([]);
});

describe("read-only action center bridge lookup", () => {
    it("does not create a server company when none exists", async () => {
        expect(await findActionCenterBridgeCompany("local-a", new AbortController().signal)).toBeNull();
        expect(mocks.list).toHaveBeenCalledOnce();
        expect(mocks.create).not.toHaveBeenCalled();
    });
    it("finds an existing canonical identity without changing it", async () => {
        mocks.list.mockResolvedValue([{ id: bridge, legal_name: "Demo Company", registration_number: "12345" }]);
        expect(await findActionCenterBridgeCompany("local-a", new AbortController().signal)).toBe(bridge);
        expect(mocks.create).not.toHaveBeenCalled();
    });
    it("refuses private rows belonging to a different owner before server lookup", async () => {
        mocks.loadQuery.mockResolvedValue([{ id: "local-a", ownerId: "other-owner", legalName: "Demo Company" }]);
        await expect(findActionCenterBridgeCompany("local-a", new AbortController().signal)).rejects.toThrow();
        expect(mocks.list).not.toHaveBeenCalled();
    });
    it("uses a validated shared-company bridge pin instead of guessing identity", async () => {
        mocks.loadQuery.mockResolvedValue([{ id: "local-a", ownerId: "shared-owner", legalName: "Demo Company" }]);
        setCompanyShare({ companyId: "local-a", ownerId: "shared-owner" as never, role: "accountant", status: "active", bridgeCompanyId: bridge });
        expect(await findActionCenterBridgeCompany("local-a", new AbortController().signal)).toBe(bridge);
        expect(mocks.list).not.toHaveBeenCalled();
    });
    it("refuses revoked or mismatched share metadata", async () => {
        setCompanyShare({ companyId: "local-a", ownerId: "shared-owner" as never, role: "member", status: "revoked", bridgeCompanyId: bridge });
        await expect(findActionCenterBridgeCompany("local-a", new AbortController().signal)).rejects.toThrow();
        expect(mocks.list).not.toHaveBeenCalled();
    });
    it("refuses an already aborted lookup", async () => {
        const controller = new AbortController(); controller.abort();
        await expect(findActionCenterBridgeCompany("local-a", controller.signal)).rejects.toThrow();
        expect(mocks.list).not.toHaveBeenCalled();
    });
});
