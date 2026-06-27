import { describe, expect, it } from "vitest";
import { normalizeIsoCountryCode } from "@/utils/isoCountryCode";

describe("normalizeIsoCountryCode", () => {
    it("maps Slovak country names to SK", () => {
        expect(normalizeIsoCountryCode("Slovensko")).toBe("SK");
        expect(normalizeIsoCountryCode("slovakia")).toBe("SK");
    });

    it("keeps ISO codes uppercase", () => {
        expect(normalizeIsoCountryCode("sk")).toBe("SK");
        expect(normalizeIsoCountryCode("CZ")).toBe("CZ");
    });

    it("returns null for unknown long values", () => {
        expect(normalizeIsoCountryCode("Unknownland")).toBeNull();
        expect(normalizeIsoCountryCode("")).toBeNull();
        expect(normalizeIsoCountryCode(null)).toBeNull();
    });
});
