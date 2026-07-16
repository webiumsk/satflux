import { describe, expect, it } from "vitest";
import {
    dedupeRowsById,
    findDuplicateCompanyGroups,
    matchCompanyByIdentity,
    normalizeCompanyIdentityKey,
} from "@/evolu/duplicateCompanies";

describe("normalizeCompanyIdentityKey", () => {
    it("keys by name only when registration number is missing", () => {
        expect(normalizeCompanyIdentityKey("Webium LLC", null)).toBe("webium llc");
        expect(normalizeCompanyIdentityKey(" Webium LLC ", "")).toBe("webium llc");
    });

    it("includes the registration number when present", () => {
        expect(normalizeCompanyIdentityKey("Webium LLC", "2024-0012345")).toBe(
            "webium llc|2024-0012345",
        );
    });
});

describe("dedupeRowsById", () => {
    it("keeps the first occurrence of each id", () => {
        const rows = [
            { id: "a", n: 1 },
            { id: "b", n: 2 },
            { id: "a", n: 3 },
        ];
        expect(dedupeRowsById(rows)).toEqual([
            { id: "a", n: 1 },
            { id: "b", n: 2 },
        ]);
    });
});

describe("findDuplicateCompanyGroups with phantom repeats", () => {
    it("does not treat same-id repeats as duplicates", () => {
        const rows = [
            { id: "one", legal_name: "Webium s.r.o.", registration_number: null },
            { id: "one", legal_name: "Webium s.r.o.", registration_number: null },
        ];
        expect(findDuplicateCompanyGroups(rows)).toEqual([]);
    });

    it("does not group companies by legal name alone", () => {
        const rows = [
            { id: "one", legal_name: "MONACOR", registration_number: null },
            { id: "two", legal_name: "MONACOR", registration_number: null },
        ];
        expect(findDuplicateCompanyGroups(rows)).toEqual([]);
    });

    it("still finds real duplicates with a shared registration number among phantom repeats", () => {
        const rows = [
            { id: "one", legal_name: "MONACOR", registration_number: "12345678" },
            { id: "one", legal_name: "MONACOR", registration_number: "12345678" },
            { id: "two", legal_name: "MONACOR", registration_number: "12345678" },
        ];
        const groups = findDuplicateCompanyGroups(rows);
        expect(groups).toHaveLength(1);
        expect(groups[0].map((row) => row.id).sort()).toEqual(["one", "two"]);
    });
});

describe("matchCompanyByIdentity", () => {
    const server = (id: string, legal_name: string, registration_number: string | null) => ({
        id,
        legal_name,
        registration_number,
    });

    it("prefers an exact name + registration match", () => {
        const rows = [
            server("a", "Webium LLC", null),
            server("b", "Webium LLC", "123"),
        ];
        expect(
            matchCompanyByIdentity(rows, { legal_name: "Webium LLC", registration_number: "123" })?.id,
        ).toBe("b");
    });

    it("falls back to a name-only match when the server row lacks the registration number", () => {
        const rows = [server("a", "Webium LLC", null)];
        expect(
            matchCompanyByIdentity(rows, { legal_name: "Webium LLC", registration_number: "123" })?.id,
        ).toBe("a");
    });

    it("falls back to a name-only match when the local identity lacks the registration number", () => {
        const rows = [server("a", "Webium LLC", "123")];
        expect(
            matchCompanyByIdentity(rows, { legal_name: "webium llc ", registration_number: null })?.id,
        ).toBe("a");
    });

    it("does not match two companies whose registration numbers differ", () => {
        const rows = [server("a", "Webium LLC", "999")];
        expect(
            matchCompanyByIdentity(rows, { legal_name: "Webium LLC", registration_number: "123" }),
        ).toBeNull();
    });

    it("ignores a same-named candidate excluded by a different registration number", () => {
        const rows = [
            server("a", "Webium LLC", null),
            server("b", "Webium LLC", "456"),
        ];
        expect(
            matchCompanyByIdentity(rows, { legal_name: "Webium LLC", registration_number: "123" })?.id,
        ).toBe("a");
    });

    it("refuses an ambiguous name-only fallback (two eligible same-named candidates)", () => {
        const rows = [
            server("a", "Webium LLC", null),
            server("b", "Webium LLC", null),
        ];
        expect(
            matchCompanyByIdentity(rows, { legal_name: "Webium LLC", registration_number: "123" }),
        ).toBeNull();
    });

    it("returns null for a blank name or no candidates", () => {
        expect(matchCompanyByIdentity([], { legal_name: "Webium LLC" })).toBeNull();
        expect(
            matchCompanyByIdentity([server("a", "Webium LLC", null)], { legal_name: "  " }),
        ).toBeNull();
    });
});
