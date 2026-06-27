import { describe, expect, it } from "vitest";
import { parseSpreadsheetFile } from "../evolu/spreadsheetParse";
import * as XLSX from "xlsx";

describe("parseSpreadsheetFile xlsx dates", () => {
    it("normalizes excel date cells to ISO dates", async () => {
        const worksheet = XLSX.utils.aoa_to_sheet([
            ["Č. faktúry", "Dátum vystavenia", "Dátum splatnosti", "Názov / Meno", "Suma"],
            ["2026001", new Date(2026, 0, 4), new Date(2026, 0, 14), "Test s.r.o.", 100],
        ]);
        const workbook = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(workbook, worksheet, "Sheet1");
        const buffer = XLSX.write(workbook, { type: "array", bookType: "xlsx" });
        const file = new File([buffer], "invoices.xlsx", {
            type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        });

        const parsed = await parseSpreadsheetFile(file);

        expect(parsed.rows[0][1]).toBe("2026-01-04");
        expect(parsed.rows[0][2]).toBe("2026-01-14");
        expect(parsed.rows[0][3]).toBe("Test s.r.o.");
    });
});
