import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
    getStorageEstimate,
    isStorageUsageCritical,
    requestPersistence,
    resetBrowserStorageStateForTests,
    storageUsagePercent,
} from "@/services/browserStorage";
import { isQuotaLikeEvoluError } from "@/evolu/evoluErrorMonitor";

beforeEach(() => {
    resetBrowserStorageStateForTests();
});

afterEach(() => {
    vi.unstubAllGlobals();
});

function stubStorage(overrides: Partial<StorageManager>) {
    vi.stubGlobal("navigator", { storage: overrides, onLine: true });
}

describe("storageUsagePercent", () => {
    it("computes a clamped rounded percentage", () => {
        expect(storageUsagePercent(50, 100)).toBe(50);
        expect(storageUsagePercent(850, 1000)).toBe(85);
        expect(storageUsagePercent(2000, 1000)).toBe(100);
    });

    it("returns null on unknown usage or quota", () => {
        expect(storageUsagePercent(null, 100)).toBeNull();
        expect(storageUsagePercent(50, null)).toBeNull();
        expect(storageUsagePercent(50, 0)).toBeNull();
    });
});

describe("getStorageEstimate + threshold", () => {
    it("maps the StorageManager estimate", async () => {
        stubStorage({ estimate: vi.fn().mockResolvedValue({ usage: 800, quota: 1000 }) });
        const info = await getStorageEstimate();
        expect(info).toEqual({ usageBytes: 800, quotaBytes: 1000, usagePercent: 80 });
        expect(isStorageUsageCritical(info)).toBe(true);
        expect(isStorageUsageCritical({ ...info, usagePercent: 79 })).toBe(false);
        expect(isStorageUsageCritical({ usageBytes: null, quotaBytes: null, usagePercent: null })).toBe(false);
    });

    it("degrades to unknown without the API or on failure", async () => {
        vi.stubGlobal("navigator", {});
        expect(await getStorageEstimate()).toEqual({
            usageBytes: null,
            quotaBytes: null,
            usagePercent: null,
        });

        stubStorage({ estimate: vi.fn().mockRejectedValue(new Error("nope")) });
        expect((await getStorageEstimate()).usagePercent).toBeNull();
    });
});

describe("requestPersistence", () => {
    it("asks once per load and caches the grant", async () => {
        const persist = vi.fn().mockResolvedValue(true);
        stubStorage({ persist });

        expect(await requestPersistence()).toBe(true);
        expect(await requestPersistence()).toBe(true);
        expect(persist).toHaveBeenCalledTimes(1);
    });

    it("returns null when the API is unavailable", async () => {
        vi.stubGlobal("navigator", {});
        expect(await requestPersistence()).toBeNull();
    });
});

describe("isQuotaLikeEvoluError", () => {
    it("classifies quota-shaped error descriptions", () => {
        expect(isQuotaLikeEvoluError("QuotaExceededError")).toBe(true);
        expect(isQuotaLikeEvoluError("StorageError")).toBe(true);
        expect(isQuotaLikeEvoluError("TransferError")).toBe(false);
        expect(isQuotaLikeEvoluError(null)).toBe(false);
    });
});
