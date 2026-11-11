import { describe, expect, it } from "vitest";
import { emptyActionCenterCategories, validateActionCenterPayload } from "@/services/companyActionCenter";

describe("action center response validation", () => {
    const valid = () => ({ checked_at: "2026-11-10T12:00:00Z", categories: emptyActionCenterCategories("ready"), restricted_sources: [] });
    it("accepts complete bounded source results", () => { expect(validateActionCenterPayload(valid()).categories).toHaveLength(4); });
    it("refuses missing or duplicated categories rather than treating them as clear", () => {
        const missing = valid(); missing.categories.pop();
        const duplicate = valid(); duplicate.categories[3] = duplicate.categories[0];
        expect(() => validateActionCenterPayload(missing)).toThrow();
        expect(() => validateActionCenterPayload(duplicate)).toThrow();
    });
    it("refuses unavailable sources containing stale items or success counts", () => {
        const data = valid(); data.categories[0].state = "unavailable";
        expect(() => validateActionCenterPayload(data)).toThrow();
    });
    it("refuses invalid counts and unchecked timestamps", () => {
        const data = valid(); data.categories[0].count = -1;
        expect(() => validateActionCenterPayload(data)).toThrow();
        expect(() => validateActionCenterPayload({ ...valid(), checked_at: "unknown" })).toThrow();
    });
});
