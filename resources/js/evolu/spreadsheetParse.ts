import * as XLSX from "xlsx";
import {
    formatExcelSerialNumber,
    formatLocalDateParts,
    isExcelDateSerial,
} from "./importDateParse";

function stripUtf8Bom(text: string): string {
    return text.charCodeAt(0) === 0xfeff ? text.slice(1) : text;
}

export type ParsedSpreadsheet = {
    headers: string[];
    rows: string[][];
};

function parseCsvRows(text: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = "";
    let inQuotes = false;

    for (let i = 0; i < text.length; i += 1) {
        const ch = text[i];
        const next = text[i + 1];

        if (inQuotes) {
            if (ch === '"' && next === '"') {
                cell += '"';
                i += 1;
            } else if (ch === '"') {
                inQuotes = false;
            } else {
                cell += ch;
            }
            continue;
        }

        if (ch === '"') {
            inQuotes = true;
        } else if (ch === "," || ch === ";") {
            row.push(cell);
            cell = "";
        } else if (ch === "\n" || (ch === "\r" && next === "\n")) {
            row.push(cell);
            if (row.some((value) => value.trim() !== "")) {
                rows.push(row);
            }
            row = [];
            cell = "";
            if (ch === "\r") i += 1;
        } else if (ch !== "\r") {
            cell += ch;
        }
    }

    row.push(cell);
    if (row.some((value) => value.trim() !== "")) {
        rows.push(row);
    }

    return rows;
}

function readWorksheetCell(sheet: XLSX.WorkSheet, row: number, column: number): string {
    const address = XLSX.utils.encode_cell({ r: row, c: column });
    const cell = sheet[address];
    if (!cell) return "";

    if (cell.t === "d" && cell.v instanceof Date) {
        return formatLocalDateParts(cell.v) ?? "";
    }

    if (cell.t === "n" && typeof cell.v === "number") {
        if (isExcelDateSerial(cell.v)) {
            return formatExcelSerialNumber(cell.v) ?? "";
        }
        return Number.isInteger(cell.v) ? String(cell.v) : String(cell.v);
    }

    if (typeof cell.w === "string" && cell.w.trim() !== "") {
        return cell.w.trim();
    }

    if (cell.v == null) return "";
    if (typeof cell.v === "string") return cell.v.trim();
    if (typeof cell.v === "boolean") return cell.v ? "1" : "0";
    return String(cell.v).trim();
}

function sheetToMatrix(sheet: XLSX.WorkSheet): string[][] {
    const ref = sheet["!ref"];
    if (!ref) {
        throw new Error("no_data_rows");
    }

    const range = XLSX.utils.decode_range(ref);
    const matrix: string[][] = [];

    for (let row = range.s.r; row <= range.e.r; row += 1) {
        const values: string[] = [];
        for (let column = range.s.c; column <= range.e.c; column += 1) {
            values.push(readWorksheetCell(sheet, row, column));
        }
        matrix.push(values);
    }

    return matrix;
}

function rowsFromMatrix(matrix: string[][]): ParsedSpreadsheet {
    if (matrix.length < 2) {
        throw new Error("no_data_rows");
    }
    const [headerRow, ...dataRows] = matrix;
    const headers = (headerRow ?? []).map((cell) => cell.trim());
    const rows = dataRows.filter((row) => row.some((cell) => cell.trim() !== ""));

    return { headers, rows };
}

export async function parseSpreadsheetFile(file: File): Promise<ParsedSpreadsheet> {
    const lower = file.name.toLowerCase();

    if (lower.endsWith(".csv")) {
        const text = stripUtf8Bom(await file.text());
        const allRows = parseCsvRows(text);
        if (allRows.length < 2) throw new Error("no_data_rows");
        const [headerRow, ...dataRows] = allRows;
        return {
            headers: headerRow.map((h) => h.trim()),
            rows: dataRows,
        };
    }

    if (lower.endsWith(".xlsx") || lower.endsWith(".xls")) {
        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: "array", cellDates: true });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        if (!sheet) throw new Error("no_data_rows");
        return rowsFromMatrix(sheetToMatrix(sheet));
    }

    throw new Error("unsupported_file");
}
