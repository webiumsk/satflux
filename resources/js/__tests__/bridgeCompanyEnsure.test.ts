import { afterEach, describe, expect, it, vi } from "vitest";

async function loadHelper(options: {
    rows?: Array<Record<string, unknown>>;
    resolveResult?: string | null;
    loadFails?: boolean;
}) {
    vi.resetModules();
    const resolveOrCreateBridgeCompanyId = vi.fn().mockResolvedValue(options.resolveResult ?? "bridge-1");
    vi.doMock("@/evolu/numberAllocatorBridge", () => ({ resolveOrCreateBridgeCompanyId }));
    vi.doMock("@/evolu/client", () => ({
        allCompaniesDetailQuery: "allCompaniesDetailQuery",
        evolu: {
            loadQuery: options.loadFails
                ? vi.fn().mockRejectedValue(new Error("boom"))
                : vi.fn().mockResolvedValue(options.rows ?? []),
        },
    }));
    const module = await import("@/evolu/bridgeCompanyEnsure");
    return { module, resolveOrCreateBridgeCompanyId };
}

afterEach(() => {
    vi.doUnmock("@/evolu/numberAllocatorBridge");
    vi.doUnmock("@/evolu/client");
});

const localRow = {
    id: "local-1",
    legalName: "Webium s.r.o.",
    registrationNumber: "12345678",
    jurisdiction: "eu_sk",
    country: "SK",
    defaultCurrency: "EUR",
};

describe("ensureBridgeCompanyIdForLocalCompany", () => {
    it("resolves the bridge by THIS company's identity with creation hints", async () => {
        const { module, resolveOrCreateBridgeCompanyId } = await loadHelper({ rows: [localRow] });

        const result = await module.ensureBridgeCompanyIdForLocalCompany("local-1");

        expect(result).toBe("bridge-1");
        expect(resolveOrCreateBridgeCompanyId).toHaveBeenCalledWith({
            legal_name: "Webium s.r.o.",
            registration_number: "12345678",
            jurisdiction: "eu_sk",
            country: "SK",
            default_currency: "EUR",
        });
    });

    it("returns null for an unknown or nameless local company without calling the resolver", async () => {
        const { module, resolveOrCreateBridgeCompanyId } = await loadHelper({
            rows: [{ id: "local-2", legalName: null }],
        });

        expect(await module.ensureBridgeCompanyIdForLocalCompany("missing")).toBeNull();
        expect(await module.ensureBridgeCompanyIdForLocalCompany("local-2")).toBeNull();
        expect(await module.ensureBridgeCompanyIdForLocalCompany("")).toBeNull();
        expect(resolveOrCreateBridgeCompanyId).not.toHaveBeenCalled();
    });

    it("degrades to null when the local query fails", async () => {
        const { module } = await loadHelper({ loadFails: true });
        expect(await module.ensureBridgeCompanyIdForLocalCompany("local-1")).toBeNull();
    });
});
