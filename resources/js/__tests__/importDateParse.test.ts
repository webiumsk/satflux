import { describe, expect, it } from "vitest";
import { parseImportDate } from "../evolu/importDateParse";

describe("parseImportDate", () => {
    it("parses european dot dates", () => {
        expect(parseImportDate("15.06.2026", "dmy_dot")).toBe("2026-06-15");
        expect(parseImportDate("5.1.2024", "dmy_dot")).toBe("2024-01-05");
        expect(parseImportDate("04.01.2026 0:00:00", "dmy_dot")).toBe("2026-01-04");
    });

    it("parses excel short slash dates from sheetjs", () => {
        expect(parseImportDate("1/4/26", "auto")).toBe("2026-01-04");
        expect(parseImportDate("1/4/26", "mdy_slash")).toBe("2026-01-04");
        expect(parseImportDate("1/14/26", "auto")).toBe("2026-01-14");
    });

    it("parses iso dash dates", () => {
        expect(parseImportDate("2024-01-15", "ymd_dash")).toBe("2024-01-15");
        expect(parseImportDate("2024-01-15", "auto")).toBe("2024-01-15");
    });

    it("parses excel serial numbers in a safe range", () => {
        expect(parseImportDate("44927", "auto")).toBe("2023-01-01");
        expect(parseImportDate("46026", "auto")).toBe("2026-01-04");
    });

    it("does not treat invoice numbers as excel serials", () => {
        expect(parseImportDate("20260042", "auto")).toBeNull();
    });
});
