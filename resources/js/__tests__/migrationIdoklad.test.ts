import { describe, expect, it } from "vitest";
import { readFileSync } from "node:fs";
import * as XLSX from "xlsx";
import source from "./fixtures/migration-idoklad/source.json";
import { IdokladMigrationError, parseIdokladSpreadsheet } from "@/evolu/migrationIdoklad";
import { buildStoredZip } from "@/evolu/zipStore";

type Cell = string | number | Date | boolean;
function matrix(): Cell[][] {
    const rows: Cell[][] = [[], ["Zoznam vystavených faktúr"], ["Synthetic export metadata"], [], [...source.headers],
        ...source.rows.map(row => [...row])];
    for (const row of rows.slice(5)) for (const column of [8, 9, 10, 11]) {
        const value = row[column];
        if (typeof value === "string" && value) {
            const [year, month, day] = value.split("-").map(Number);
            row[column] = new Date(year, month - 1, day);
        }
    }
    return rows;
}

function workbook(rows = matrix(), edit?: (sheet: XLSX.WorkSheet, book: XLSX.WorkBook) => void, compression = false): Uint8Array {
    const sheet = XLSX.utils.aoa_to_sheet(rows, { cellDates: true });
    const book = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(book, sheet, "Invoices");
    edit?.(sheet, book);
    return new Uint8Array(XLSX.write(book, { type: "array", bookType: "xlsx", compression }));
}

function changed(column: number, value: Cell): Uint8Array {
    const rows = matrix(); rows[5][column] = value;
    return workbook(rows);
}

async function code(column: number, value: Cell): Promise<string | undefined> {
    const result = await parseIdokladSpreadsheet(changed(column, value), "synthetic.xlsx");
    return result.records[0].kind === "invalid" ? result.records[0].code : undefined;
}

/** Rewrite a real worksheet XML while recomputing valid ZIP sizes and CRCs. */
function worksheetXml(edit: (xml: string) => string, duplicateCaseAlias = false,
    editEntries?: (entries: { name: string; content: string }[]) => void): Uint8Array {
    const archive = XLSX.CFB.read(workbook(), { type: "array" }) as {
        FullPaths: string[]; FileIndex: { type: number; content: Uint8Array }[];
    };
    const entries = archive.FullPaths.flatMap((path, index) => {
        if (archive.FileIndex[index].type !== 2 || path.includes("\u0001")) return [];
        const name = path.slice("Root Entry/".length);
        const content = new TextDecoder().decode(archive.FileIndex[index].content);
        return [{ name, content: name === "xl/worksheets/sheet1.xml" ? edit(content) : content }];
    });
    if (duplicateCaseAlias) entries.push({ ...entries.find(entry => entry.name === "xl/worksheets/sheet1.xml")!, name: "XL/WORKSHEETS/SHEET1.XML" });
    editEntries?.(entries);
    return buildStoredZip(entries, new Date(2026, 9, 1));
}

describe("iDoklad native XLSX list facts", () => {
    it("reads the retained fully synthetic XLSX fixture with four records", async () => {
        const bytes = readFileSync("resources/js/__tests__/fixtures/migration-idoklad/export-payments.xlsx");
        expect((await parseIdokladSpreadsheet(bytes, "export-payments.xlsx")).records.map(record => record.kind))
            .toEqual(["parsed", "parsed", "parsed", "parsed"]);
    });

    it("preserves four source records at physical rows6..9 without marking any record ready", async () => {
        const result = await parseIdokladSpreadsheet(workbook(), "synthetic.xlsx");
        expect(result.invoiceCount).toBe(4);
        expect(result.records.map(record => [record.row, record.kind])).toEqual([[6, "parsed"], [7, "parsed"], [8, "parsed"], [9, "parsed"]]);
        const first = result.records[0];
        expect(first).toMatchObject({ row: 6, invoice_number: "ID-TEST-1", sourceSubtotal: "100.00", sourceTax: "23.00",
            invoice: { row: 6, issue_date: "2026-10-01", due_date: "2026-10-15", delivery_date: "2026-10-01",
                total: "123.00", amount_paid: "123.00", balance: "0.00", state: "paid", paid_at: "2026-10-16",
                variable_symbol: "00000001", contact_attributes: { registration_number: "00054321", vat_id: "SK0000000001", tax_id: "0000000001" } } });
        if (first.kind !== "parsed") throw new Error("Expected synthetic parsed record");
        expect(first.invoice.structured).toBeUndefined();
        expect(first.invoice.sourceIdentity).toBeUndefined();
        expect(result.records[1]).toMatchObject({ sourceSubtotal: "200.00", sourceTax: "46.00",
            invoice: { state: "partial", amount_paid: "50.00", balance: "196.00", paid_at: null } });
        expect(result.records.slice(2).map(record => record.kind === "parsed" && record.invoice.state)).toEqual(["unpaid", "unpaid"]);
    });

    it("retains physical numbering across blank source rows", async () => {
        const rows = matrix(); rows.splice(6, 0, []);
        expect((await parseIdokladSpreadsheet(workbook(rows), "synthetic.xlsx")).records.map(record => record.row)).toEqual([6, 8, 9, 10]);
    });

    it("accepts a bounded deflated workbook after validating actual expanded bytes", async () => {
        expect((await parseIdokladSpreadsheet(workbook(matrix(), undefined, true), "synthetic.xlsx")).invoiceCount).toBe(4);
    });

    it("copies input bytes before asynchronous decompression and parsing", async () => {
        const bytes = workbook(matrix(), undefined, true);
        const pending = parseIdokladSpreadsheet(bytes, "synthetic.xlsx");
        bytes.fill(0);
        expect((await pending).invoiceCount).toBe(4);
    });

    it("preserves numbers and buyer identifiers with leading zeros", async () => {
        const rows = matrix(); rows[5][0] = "00001234";
        const first = (await parseIdokladSpreadsheet(workbook(rows), "synthetic.xlsx")).records[0];
        expect(first).toMatchObject({ invoice_number: "00001234", invoice: { contact_attributes: { registration_number: "00054321" } } });
    });

    it("leaves duplicate source invoice numbers for the later common destination validator", async () => {
        const rows = matrix(); rows[6][0] = rows[5][0];
        const result = await parseIdokladSpreadsheet(workbook(rows), "synthetic.xlsx");
        expect(result.records).toHaveLength(4);
        expect(result.records.slice(0, 2).map(record => record.invoice_number)).toEqual(["ID-TEST-1", "ID-TEST-1"]);
    });

    it("does not echo oversized invalid invoice identifiers into preview records", async () => {
        const result = await parseIdokladSpreadsheet(changed(0, "x".repeat(1000)), "synthetic.xlsx");
        expect(result.records[0]).toMatchObject({ kind: "invalid", invoice_number: "", code: "idoklad_invalid_invoice" });
    });
});

describe("iDoklad exact payment, money and date evidence", () => {
    it.each([
        [17, "", "idoklad_payment_evidence"], [17, 0, "idoklad_payment_evidence"],
        [17, 50, "idoklad_payment_evidence"], [17, 124, "idoklad_payment_evidence"],
        [17, -1, "idoklad_payment_evidence"], [17, 123.001, "idoklad_payment_evidence"],
        [11, "", "idoklad_payment_evidence"], [11, "2026-02-30", "idoklad_payment_evidence"],
        [16, "Unknown", "idoklad_unsupported"], [16, "Neuhradené", "idoklad_payment_evidence"],
        [12, 123.001, "idoklad_totals"], [13, 99.99, "idoklad_totals"], [14, 22.99, "idoklad_totals"],
        [12, "1e2", "idoklad_totals"], [12, -123, "idoklad_totals"], [12, "9999999999999", "idoklad_totals"],
        [8, "2026-02-30", "idoklad_invalid_invoice"], [9, "", "idoklad_invalid_invoice"],
        [10, "2026-13-01", "idoklad_invalid_invoice"],
    ] as const)("returns a controlled row rejection for column%s value%s", async (column, value, expected) => {
        expect(await code(column, value)).toBe(expected);
    });

    it.each(["BTC", "LBTC", "JPY", "KWD", "XXX"])("does not silently round unsupported currency %s", async currency => {
        expect(await code(15, currency)).toBe("idoklad_unsupported_currency");
    });

    it("requires partial status to agree with explicit paid amount and the proven unknown date", async () => {
        for (const [column, value] of [[17, 0], [17, 246], [11, "2026-10-16"]] as const) {
            const rows = matrix(); rows[6][column] = value;
            const record = (await parseIdokladSpreadsheet(workbook(rows), "synthetic.xlsx")).records[1];
            expect(record).toMatchObject({ kind: "invalid", code: "idoklad_payment_evidence" });
        }
    });

    it("does not infer a VAT rate from the net/VAT ratio", async () => {
        const rows = matrix(); rows[5][12] = 125; rows[5][14] = 25; rows[5][17] = 125;
        const record = (await parseIdokladSpreadsheet(workbook(rows), "synthetic.xlsx")).records[0];
        expect(record).toMatchObject({ kind: "parsed", sourceSubtotal: "100.00", sourceTax: "25.00" });
        if (record.kind === "parsed") expect(record.invoice.structured).toBeUndefined();
    });

    it("never uses cached formula output for financial or identity evidence", async () => {
        for (const address of ["M6", "A6", "F6"]) {
            const record = (await parseIdokladSpreadsheet(workbook(matrix(), sheet => {
                sheet[address].f = "100+23";
            }), "synthetic.xlsx")).records[0];
            expect(record).toMatchObject({ kind: "invalid", code: "idoklad_invalid_invoice" });
        }
    });

    it("rejects cached array-formula member amounts as well as the formula owner", async () => {
        const result = await parseIdokladSpreadsheet(workbook(matrix(), sheet => {
            sheet.M6.f = "123";
            sheet.M6.F = "M6:M9";
        }), "synthetic.xlsx");
        expect(result.records.every(record => record.kind === "invalid" && record.code === "idoklad_invalid_invoice")).toBe(true);
    });

    it("rejects boolean monetary cells and preserves evidence of numeric versus string source values", async () => {
        for (const [column, value, expected] of [[12, true, "idoklad_totals"], [13, "100", "idoklad_totals"],
            [17, true, "idoklad_payment_evidence"], [0, 1234, "idoklad_invalid_invoice"],
            [5, 54321, "idoklad_invalid_invoice"], [8, "2026-10-01", "idoklad_invalid_invoice"]] as const) {
            expect(await code(column, value)).toBe(expected);
        }
    });
});

describe("iDoklad format and resource limits", () => {
    it("rejects an understated dimension instead of silently dropping three physical invoices", async () => {
        const bytes = worksheetXml(xml => xml.replace('ref="A1:V9"', 'ref="A1:V6"'));
        await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toThrow("idoklad_invalid_export");
    });

    it("accounts for all physical records when the optional worksheet dimension is absent", async () => {
        const bytes = worksheetXml(xml => xml.replace(/<dimension[^>]*\/>/, ""));
        const result = await parseIdokladSpreadsheet(bytes, "synthetic.xlsx");
        expect(result.invoiceCount).toBe(4);
        expect(result.records.map(record => [record.row, record.kind])).toEqual([[6, "parsed"], [7, "parsed"], [8, "parsed"], [9, "parsed"]]);
    });

    it.each([
        (xml: string) => xml.replace(/(<dimension[^>]*\/>)/, "$1$1"),
        (xml: string) => xml.replace('ref="A1:V9"', 'ref="A1:U9"'),
        (xml: string) => xml.replace('r="A6"', 'r="A7"'),
        (xml: string) => xml.replace('r="A6"', 'r="A06"'),
        (xml: string) => xml.replace('r="A6"', ''),
        (xml: string) => xml.replace(/(<row r="6"[^>]*>)([\s\S]*?)(<\/row>)/, "$1$2$3$1$2$3"),
        (xml: string) => xml.replace(/<c r="B6"[^>]*>[\s\S]*?<\/c>/, /<c r="A6"[^>]*>[\s\S]*?<\/c>/.exec(xml)![0]),
        (xml: string) => xml.replace('<row r="6"', '<row r="06"'),
        (xml: string) => xml.replace('<row r="6"', '<row'),
        (xml: string) => xml.replace('</sheetData>', '</sheetData><row r="10"><c r="A10"><v>1</v></c></row>'),
    ])("rejects missing, duplicate or contradictory physical coordinates %#", async edit => {
        await expect(parseIdokladSpreadsheet(worksheetXml(edit), "synthetic.xlsx")).rejects.toThrow("idoklad_invalid_export");
    });

    it.each([
        (xml: string) => xml.replace('r="A6"', 'r="W6"'),
        (xml: string) => xml.replace('<row r="9"', '<row r="2006"'),
        (xml: string) => xml.replace('ref="A1:V9"', 'ref="A1:V2006"'),
    ])("bounds sparse physical rows and columns even when declared dimensions appear small %#", async edit => {
        await expect(parseIdokladSpreadsheet(worksheetXml(edit), "synthetic.xlsx")).rejects.toThrow("idoklad_limit_exceeded");
    });

    it("rejects case-alias ZIP paths so SheetJS cannot select an unvalidated worksheet", async () => {
        await expect(parseIdokladSpreadsheet(worksheetXml(xml => xml, true), "synthetic.xlsx"))
            .rejects.toThrow("idoklad_invalid_export");
    });

    it("rejects an active relationship redirected to an unvalidated custom worksheet", async () => {
        const bytes = worksheetXml(xml => xml, false, entries => {
            const sheet = entries.find(entry => entry.name === "xl/worksheets/sheet1.xml")!;
            entries.push({ name: "xl/custom.xml", content: sheet.content.replace('ref="A1:V9"', 'ref="A1:V6"') });
            const relations = entries.find(entry => entry.name === "xl/_rels/workbook.xml.rels")!;
            relations.content = relations.content.replace('Target="worksheets/sheet1.xml"', 'Target="custom.xml"');
            const types = entries.find(entry => entry.name === "[Content_Types].xml")!;
            types.content = types.content.replace('</Types>', '<Override PartName="/xl/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        });
        await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toThrow("idoklad_unsupported");
    });

    it("resolves a workbook content type from the XML default while preserving worksheet overrides", async () => {
        const bytes = worksheetXml(xml => xml, false, entries => {
            const types = entries.find(entry => entry.name === "[Content_Types].xml")!;
            types.content = types.content.replace(/<Override PartName="\/xl\/workbook\.xml"[^>]*\/>/, "")
                .replace('<Default Extension="xml" ContentType="application/xml"/>', '<Default Extension="xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>');
        });
        const result = await parseIdokladSpreadsheet(bytes, "synthetic.xlsx");
        expect(result.invoiceCount).toBe(4);
        expect(result.records.every(record => record.kind === "parsed")).toBe(true);
    });

    it("rejects wrong effective workbook types and duplicate normalized defaults or overrides", async () => {
        for (const [edit, code] of [
            [(xml: string) => xml.replace(/<Override PartName="\/xl\/workbook\.xml"[^>]*\/>/, ""), "idoklad_unsupported"],
            [(xml: string) => xml.replace('</Types>', '<Default Extension="XML" ContentType="application/xml"/></Types>'), "idoklad_invalid_export"],
            [(xml: string) => xml.replace('</Types>', '<Override PartName="/XL/WORKSHEETS/SHEET1.XML" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>'), "idoklad_invalid_export"],
        ] as const) {
            const bytes = worksheetXml(xml => xml, false, entries => {
                const types = entries.find(entry => entry.name === "[Content_Types].xml")!;
                types.content = edit(types.content);
            });
            await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toThrow(code);
        }
    });

    it("rejects external sheet relationships and inconsistent active sheet IDs", async () => {
        for (const edit of [(xml: string) => xml.replace('Target="worksheets/sheet1.xml"', 'TargetMode="External" Target="https://example.invalid/sheet.xml"'),
            (xml: string) => xml.replace('Id="rId1"', 'Id="unexpected"')]) {
            const bytes = worksheetXml(xml => xml, false, entries => {
                const relations = entries.find(entry => entry.name === "xl/_rels/workbook.xml.rels")!;
                relations.content = edit(relations.content);
            });
            await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toBeInstanceOf(IdokladMigrationError);
        }
    });

    it("rejects multiple workbook content types even if the expected XML remains present", async () => {
        const bytes = worksheetXml(xml => xml, false, entries => {
            const types = entries.find(entry => entry.name === "[Content_Types].xml")!;
            types.content = types.content.replace('</Types>', '<Override PartName="/xl/custom.xml" ContentType="application/vnd.ms-excel.sheet.macroEnabled.main+xml"/></Types>');
        });
        await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toThrow("idoklad_unsupported");
    });

    it("requires the exact row5 schema and title rather than guessing header positions", async () => {
        for (const edit of [(rows: Cell[][]) => { rows[4][17] = ""; }, (rows: Cell[][]) => { rows[1][0] = "Other export"; },
            (rows: Cell[][]) => { rows.shift(); }, (rows: Cell[][]) => { rows[4][6] = "IČO"; }]) {
            const rows = matrix(); edit(rows);
            await expect(parseIdokladSpreadsheet(workbook(rows), "synthetic.xlsx")).rejects.toThrow("idoklad_invalid_export");
        }
    });

    it("refuses multiple worksheets and out-of-bound declared dimensions", async () => {
        await expect(parseIdokladSpreadsheet(workbook(matrix(), (_, book) => {
            XLSX.utils.book_append_sheet(book, XLSX.utils.aoa_to_sheet([["Hidden source"]]), "Other");
        }), "synthetic.xlsx")).rejects.toThrow("idoklad_invalid_export");
        for (const ref of ["A1:V2006", "A1:W9"]) {
            await expect(parseIdokladSpreadsheet(workbook(matrix(), sheet => { sheet["!ref"] = ref; }), "synthetic.xlsx"))
                .rejects.toThrow("idoklad_limit_exceeded");
        }
    });

    it("refuses malformed, wrong-extension and oversized input before financial parsing", async () => {
        await expect(parseIdokladSpreadsheet(workbook(), "synthetic.csv")).rejects.toThrow("idoklad_invalid_export");
        await expect(parseIdokladSpreadsheet(new Uint8Array([80, 75, 0]), "synthetic.xlsx")).rejects.toThrow("idoklad_invalid_export");
        await expect(parseIdokladSpreadsheet(new Uint8Array(10 * 1024 * 1024 + 1), "synthetic.xlsx")).rejects.toThrow("idoklad_limit_exceeded");
    });

    it("detects stored ZIP content corruption through CRC validation", async () => {
        const bytes = workbook();
        const view = new DataView(bytes.buffer);
        const start = 30 + view.getUint16(26, true) + view.getUint16(28, true);
        bytes[start] ^= 1;
        await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toThrow("idoklad_invalid_export");
    });

    it("bounds declared ZIP expansion before decompressing", async () => {
        const bytes = workbook(matrix(), undefined, true);
        const view = new DataView(bytes.buffer);
        let central = 0;
        while (view.getUint32(central, true) !== 0x02014b50) central++;
        view.setUint32(central + 24, 8 * 1024 * 1024 + 1, true);
        await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toThrow("idoklad_limit_exceeded");
    });

    it("counts actual decompressed bytes even when central and local headers understate expansion", async () => {
        const bytes = workbook(matrix(), undefined, true);
        const view = new DataView(bytes.buffer);
        let central = 0;
        while (view.getUint32(central, true) !== 0x02014b50) central++;
        const local = view.getUint32(central + 42, true);
        view.setUint32(central + 24, 1, true);
        view.setUint32(local + 22, 1, true);
        await expect(parseIdokladSpreadsheet(bytes, "synthetic.xlsx")).rejects.toThrow("idoklad_limit_exceeded");
    });

    it("returns only finite codes without including raw source data", async () => {
        await expect(parseIdokladSpreadsheet(new TextEncoder().encode("synthetic-private-value"), "synthetic.xlsx"))
            .rejects.toBeInstanceOf(IdokladMigrationError);
    });
});
