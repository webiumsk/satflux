import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from "axios";
import api from "@/services/api";
import { clearCompanyShares, markCompanyShareRegistryReady } from "@/evolu/companyShareRegistry";
import { ensureBridgeCompanyIdForLocalCompany } from "@/evolu/bridgeCompanyEnsure";

const localCompany = {
    id: "synthetic-local", ownerId: "synthetic-app", legalName: "Synthetic company", registrationNumber: "123",
    jurisdiction: "eu_sk", country: "SK", defaultCurrency: "EUR",
};
vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "companies",
    evolu: { loadQuery: vi.fn(async () => [localCompany]), appOwner: Promise.resolve({ id: "synthetic-app" }) },
}));
const flash = vi.hoisted(() => ({ errorKey: vi.fn() }));
vi.mock("@/store/flash", () => ({ useFlashStore: () => flash }));
const originalAdapter = api.defaults.adapter;

beforeEach(() => { flash.errorKey.mockClear(); clearCompanyShares(); markCompanyShareRegistryReady(); });
afterEach(() => { clearCompanyShares(); api.defaults.adapter = originalAdapter; vi.restoreAllMocks(); vi.useRealTimers(); });

it("propagates caller signal and refreshed remaining timeout through lookup, create and canonical re-list", async () => {
    const requests: InternalAxiosRequestConfig[] = [];
    let lists = 0;
    const adapter: AxiosAdapter = async (config) => {
        requests.push(config);
        const data = config.method === "post" ? { id: "bridge-new" }
            : ++lists === 1 ? [] : [{ id: "bridge-older", legal_name: localCompany.legalName, registration_number: "123" }];
        remaining = config.method === "post" ? 3_000 : 8_000;
        return { config, data: { data }, status: 200, statusText: "Synthetic", headers: {} };
    };
    api.defaults.adapter = adapter;
    const controller = new AbortController();
    let remaining = 10_000;
    const controls = vi.fn(() => ({ signal: controller.signal, timeout: remaining, skipErrorFlash: true }));
    await expect(ensureBridgeCompanyIdForLocalCompany("synthetic-local", controls, { preferRecordedBridgeId: true }))
        .resolves.toEqual({ ok: true, bridgeCompanyId: "bridge-older" });
    expect(controls.mock.calls.length).toBeGreaterThanOrEqual(3);
    expect(requests.map(({ method, timeout }) => [method, timeout])).toEqual([["get", 10_000], ["post", 8_000], ["get", 3_000]]);
    expect(requests.every((request) => request.signal === requests[0].signal && request.signal instanceof AbortSignal && request.skipErrorFlash === true && request.withCredentials === true)).toBe(true);
    expect(requests.every((request) => request.url === "/invoicing/companies")).toBe(true);
    expect(JSON.parse(requests[1].data)).toEqual({ legal_name: localCompany.legalName, registration_number: "123", jurisdiction: "eu_sk", country: "SK", default_currency: "EUR" });
    expect(flash.errorKey).not.toHaveBeenCalled();
});

it("retains default transport behavior for ordinary bridge callers", async () => {
    const requests: InternalAxiosRequestConfig[] = [];
    api.defaults.adapter = async (config) => {
        requests.push(config);
        return { config, data: { data: [{ id: "existing", legal_name: localCompany.legalName, registration_number: "123" }] }, status: 200, statusText: "Synthetic", headers: {} };
    };
    await expect(ensureBridgeCompanyIdForLocalCompany("synthetic-local"))
        .resolves.toEqual({ ok: true, bridgeCompanyId: "existing" });
    expect(requests).toHaveLength(1);
    expect(requests[0]).toMatchObject({ timeout: 0, withCredentials: true });
    expect(requests[0].signal).toBeUndefined();
    expect(requests[0].skipErrorFlash).toBeUndefined();
});

it("keeps an ambiguously created bridge after POST timeout and converges only on an explicit later lookup", async () => {
    vi.useFakeTimers();
    vi.spyOn(console, "error").mockImplementation(() => {});
    const requests: InternalAxiosRequestConfig[] = [];
    let created = false;
    api.defaults.adapter = async (config) => {
        requests.push(config);
        if (config.method === "post") {
            created = true; // The server commits metadata before its response is lost.
            return new Promise((_resolve, reject) => setTimeout(() => reject(new AxiosError("synthetic_timeout", "ECONNABORTED", config)), config.timeout));
        }
        return { config, data: { data: created ? [{ id: "created-before-timeout", legal_name: localCompany.legalName, registration_number: "123" }] : [] }, status: 200, statusText: "Synthetic", headers: {} };
    };
    const controls = () => ({ timeout: 10_000, skipErrorFlash: true, signal: new AbortController().signal });
    const first = ensureBridgeCompanyIdForLocalCompany("synthetic-local", controls, { preferRecordedBridgeId: true });
    await vi.advanceTimersByTimeAsync(10_000);
    await expect(first).resolves.toEqual({ ok: false });
    expect(requests.map(({ method }) => method)).toEqual(["get", "post"]);
    expect(flash.errorKey).not.toHaveBeenCalled();
    await expect(ensureBridgeCompanyIdForLocalCompany("synthetic-local", controls, { preferRecordedBridgeId: true }))
        .resolves.toEqual({ ok: true, bridgeCompanyId: "created-before-timeout" });
    expect(requests.map(({ method }) => method)).toEqual(["get", "post", "get"]);
    expect(created).toBe(true);
});
