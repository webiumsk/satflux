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

        expect(result).toEqual({ ok: true, bridgeCompanyId: "bridge-1" });
        expect(resolveOrCreateBridgeCompanyId).toHaveBeenCalledWith({
            legal_name: "Webium s.r.o.",
            registration_number: "12345678",
            jurisdiction: "eu_sk",
            country: "SK",
            default_currency: "EUR",
        });
    });

    it("reports no-identity (ok, null id) for an unknown or nameless local company", async () => {
        const { module, resolveOrCreateBridgeCompanyId } = await loadHelper({
            rows: [{ id: "local-2", legalName: null }],
        });

        const noIdentity = { ok: true, bridgeCompanyId: null };
        expect(await module.ensureBridgeCompanyIdForLocalCompany("missing")).toEqual(noIdentity);
        expect(await module.ensureBridgeCompanyIdForLocalCompany("local-2")).toEqual(noIdentity);
        expect(await module.ensureBridgeCompanyIdForLocalCompany("")).toEqual(noIdentity);
        expect(resolveOrCreateBridgeCompanyId).not.toHaveBeenCalled();
    });

    it("reports a FAILURE (not a missing bridge) when the local query fails", async () => {
        const errorSpy = vi.spyOn(console, "error").mockImplementation(() => {});
        const { module } = await loadHelper({ loadFails: true });
        expect(await module.ensureBridgeCompanyIdForLocalCompany("local-1")).toEqual({ ok: false });
        expect(errorSpy).toHaveBeenCalled();
        errorSpy.mockRestore();
    });
});
