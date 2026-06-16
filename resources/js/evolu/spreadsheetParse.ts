import * as XLSX from "xlsx";
import { stripUtf8Bom } from "./contactImportLocal";

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

function cellToString(value: unknown): string {
    if (value == null) return "";
    if (typeof value === "string") return value.trim();
    if (typeof value === "number") {
        if (Number.isInteger(value)) return String(value);
        return String(value);
    }
    if (value instanceof Date) {
        return value.toISOString().slice(0, 10);
    }
    return String(value).trim();
}

function rowsFromMatrix(matrix: unknown[][]): ParsedSpreadsheet {
    if (matrix.length < 2) {
        throw new Error("no_data_rows");
    }
    const [headerRow, ...dataRows] = matrix;
    const headers = (headerRow ?? []).map((cell) => cellToString(cell));
    const rows = dataRows.map((row) => (row ?? []).map((cell) => cellToString(cell)));

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
        const matrix = XLSX.utils.sheet_to_json<unknown[]>(sheet, {
            header: 1,
            raw: false,
            defval: "",
        }) as unknown[][];
        return rowsFromMatrix(matrix);
    }

    throw new Error("unsupported_file");
}
