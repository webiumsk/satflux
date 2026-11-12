import { describe, expect, it } from "vitest";
import * as XLSX from "xlsx";
import { parseSpreadsheetFile } from "@/evolu/spreadsheetParse";

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

function workbookFile(sheet: XLSX.WorkSheet): File {
    const book = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(book, sheet, "Expenses");
    const bytes = XLSX.write(book, { type: "array", bookType: "xlsx" });
    return { name: "synthetic.xlsx", size: bytes.byteLength, arrayBuffer: async () => bytes } as File;
}

function csvFile(text: string): File {
    return { name: "synthetic.csv", size: text.length, text: async () => text } as File;
}

describe("shared spreadsheet import parsing", () => {
    it("keeps ordinary totals numeric, date cells as dates and padded identifiers intact", async () => {
        const sheet = XLSX.utils.aoa_to_sheet([
            ["Spolu", "Dátum vystavenia", "Interné číslo dokladu"],
            [45000, new Date(2025, 0, 15), 123],
        ], { cellDates: true });
        sheet.A2.z = "#,##0.00";
        sheet.C2.z = "000000";
        const parsed = await parseSpreadsheetFile(workbookFile(sheet));
        expect(parsed.rows[0]).toEqual(["45000", "2025-01-15", "000123"]);
    });

    it.each([
        [1234.56, "0"],
        [12.5, "00"],
        [-12.5, "000"],
        [1000.01, "000000"],
    ])("does not round amount %s using its %s display mask", async (amount, mask) => {
        const sheet = XLSX.utils.aoa_to_sheet([["Spolu"], [amount]]);
        sheet.A2.z = mask;
        expect((await parseSpreadsheetFile(workbookFile(sheet))).rows).toEqual([[String(amount)]]);
    });

    it("preserves integer identifier padding while leaving a plain integer format unchanged", async () => {
        const sheet = XLSX.utils.aoa_to_sheet([
            ["Nulové číslo", "Interné číslo", "Celé číslo"],
            [0, 123, 42],
        ]);
        sheet.A2.z = "00";
        sheet.B2.z = "000000";
        sheet.C2.z = "0";
        expect((await parseSpreadsheetFile(workbookFile(sheet))).rows).toEqual([["00", "000123", "42"]]);
    });

    it("keeps CSV identifiers as text", async () => {
        const parsed = await parseSpreadsheetFile(csvFile("Phone;Account;Total\n+421900000000;123456789012345678901234567890;12.50"));
        expect(parsed.rows).toEqual([["+421900000000", "123456789012345678901234567890", "12.50"]]);
    });

    it("returns formula text instead of a cached numeric result", async () => {
        const sheet = XLSX.utils.aoa_to_sheet([["Spolu"], [2]]);
        sheet.A2.f = "1+1";
        expect((await parseSpreadsheetFile(workbookFile(sheet))).rows).toEqual([["=1+1"]]);
    });

    it("uses semicolons as separators while preserving decimal commas", async () => {
        const parsed = await parseSpreadsheetFile(csvFile('Názov;Spolu;Poznámka\nTest;120,50;"a;b, c"'));
        expect(parsed.rows).toEqual([["Test", "120,50", "a;b, c"]]);
    });

    it("keeps semicolons inside comma-separated quoted data", async () => {
        expect((await parseSpreadsheetFile(csvFile('Title,Total\n"One; two",12.50'))).rows)
            .toEqual([["One; two", "12.50"]]);
    });

    it("rejects malformed CSV quotes", async () => {
        await expect(parseSpreadsheetFile(csvFile('Title,Total\n"open,12')))
            .rejects.toThrow("invalid_csv");
    });

    it("checks file, row and column limits before importing", async () => {
        await expect(parseSpreadsheetFile(csvFile("A\n1"), { maxFileBytes: 2 }))
            .rejects.toThrow("file_too_large");
        await expect(parseSpreadsheetFile(csvFile("A\n1\n2"), { maxRows: 1 }))
            .rejects.toThrow("too_many_rows");
        await expect(parseSpreadsheetFile(csvFile("A;B\n1;2"), { maxColumns: 1 }))
            .rejects.toThrow("too_many_columns");
        const sheet = XLSX.utils.aoa_to_sheet([["A"], [1]]);
        await expect(parseSpreadsheetFile(workbookFile(sheet), { maxRows: 0, maxColumns: 256 }))
            .rejects.toThrow("too_many_rows");
    });

    it("keeps the default result shape while opting into an absolute provider header and physical rows", async () => {
        const sheet = XLSX.utils.aoa_to_sheet([[], ["Synthetic title"], ["Metadata"], [], ["Number", "Amount"], ["0001", 12], [], ["0002", 13]]);
        const file = workbookFile(sheet);
        const ordinary = await parseSpreadsheetFile(file);
        expect(ordinary.headers).toEqual(["", ""]);
        expect(ordinary.rows[0]).toEqual(["Synthetic title", ""]);
        expect(Object.keys(ordinary).sort()).toEqual(["headers", "rows"]);
        const provider = await parseSpreadsheetFile(file, { headerRowIndex: 4, maxRows: 3, maxColumns: 2, singleSheet: true });
        expect(provider.headers).toEqual(["Number", "Amount"]);
        expect(provider.rows).toEqual([["0001", "12"], ["0002", "13"]]);
        expect(provider.rowNumbers).toEqual([6, 8]);
        expect(provider.cellTypes).toEqual([["s", "n"], ["s", "n"]]);
        expect(provider.preambleRows?.[1]).toEqual(["Synthetic title", ""]);
    });
});
