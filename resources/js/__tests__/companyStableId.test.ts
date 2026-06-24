import { describe, expect, it } from "vitest";
import { stableCompanyIdFromIdentity } from "@/evolu/companyStableId";

describe("stableCompanyIdFromIdentity", () => {
    it("returns the same id for the same legal name", () => {
        const a = stableCompanyIdFromIdentity("Webium s.r.o.", "123");
        const b = stableCompanyIdFromIdentity("Webium s.r.o.", "123");
        expect(a).toBeTruthy();
        expect(a).toBe(b);
    });

    it("differs when registration number differs", () => {
        const a = stableCompanyIdFromIdentity("Webium s.r.o.", "123");
        const b = stableCompanyIdFromIdentity("Webium s.r.o.", "456");
        expect(a).not.toBe(b);
    });
});
