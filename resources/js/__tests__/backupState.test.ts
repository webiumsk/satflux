import { beforeEach, describe, expect, it } from "vitest";
import {
    getAnyLastExportMeta,
    getLastExportMeta,
    lastExportStorageKey,
    setLastExportMeta,
    shouldBlockLogout,
} from "@/services/backupState";

beforeEach(() => {
    localStorage.clear();
});

describe("shouldBlockLogout", () => {
    it("never guards regular (non-guest) accounts", () => {
        expect(
            shouldBlockLogout({ isGuest: false, hasLegacyPhrase: true, hasLocalData: true }),
        ).toBe("none");
    });

    it("blocks a guest whose only phrase copy sits in this browser and who has local data", () => {
        expect(
            shouldBlockLogout({ isGuest: true, hasLegacyPhrase: true, hasLocalData: true }),
        ).toBe("block");
    });

    it("lightly confirms any other guest", () => {
        expect(
            shouldBlockLogout({ isGuest: true, hasLegacyPhrase: true, hasLocalData: false }),
        ).toBe("confirm");
        expect(
            shouldBlockLogout({ isGuest: true, hasLegacyPhrase: false, hasLocalData: true }),
        ).toBe("confirm");
        expect(
            shouldBlockLogout({ isGuest: true, hasLegacyPhrase: false, hasLocalData: false }),
        ).toBe("confirm");
    });
});

describe("last export meta", () => {
    const meta = {
        exportedAt: "2026-07-12T10:00:00.000Z",
        sha256: "abc",
        tableCounts: { document: 3 },
    };

    it("stores and reads per-owner meta", () => {
        setLastExportMeta("owner-a", meta);
        expect(getLastExportMeta("owner-a")).toEqual(meta);
        expect(getLastExportMeta("owner-b")).toBeNull();
    });

    it("returns the newest meta across owners", () => {
        setLastExportMeta("owner-a", { ...meta, exportedAt: "2026-07-01T00:00:00.000Z" });
        setLastExportMeta("owner-b", { ...meta, exportedAt: "2026-07-10T00:00:00.000Z" });
        expect(getAnyLastExportMeta()?.exportedAt).toBe("2026-07-10T00:00:00.000Z");
    });

    it("ignores corrupted entries", () => {
        localStorage.setItem(lastExportStorageKey("owner-x"), "{broken");
        localStorage.setItem(lastExportStorageKey("owner-y"), JSON.stringify({ nope: 1 }));
        expect(getLastExportMeta("owner-x")).toBeNull();
        expect(getAnyLastExportMeta()).toBeNull();
    });
});
