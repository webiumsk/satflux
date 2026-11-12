import * as XLSX from "xlsx";
import { formatLocalDateParts } from "./importDateParse";

function stripUtf8Bom(text: string): string {
    return text.charCodeAt(0) === 0xfeff ? text.slice(1) : text;
}

export type ParsedSpreadsheet = {
    headers: string[];
    rows: string[][];
    /** Present only for an explicit XLSX header position, using physical one-based rows. */
    rowNumbers?: number[];
    preambleRows?: string[][];
    /** Raw SheetJS cell types; formulas are explicitly marked instead of using cached values. */
    cellTypes?: string[][];
};

export type SpreadsheetLimits = {
    maxRows?: number;
    maxColumns?: number;
    maxFileBytes?: number;
    /** Provider-specific absolute zero-based XLSX header row; no header detection. */
    headerRowIndex?: number;
    singleSheet?: boolean;
};

function parseCsvRows(text: string, limits: SpreadsheetLimits): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let cell = "";
    let inQuotes = false;
    // Detect the separator from the first record, ignoring quoted punctuation.
    let quoted = false;
    let commas = 0;
    let semicolons = 0;
    for (let i = 0; i < text.length; i += 1) {
        if (text[i] === '"') {
            if (quoted && text[i + 1] === '"') i += 1;
            else quoted = !quoted;
        } else if (!quoted) {
            if (text[i] === "\n" || text[i] === "\r") break;
            if (text[i] === ",") commas += 1;
            if (text[i] === ";") semicolons += 1;
        }
    }
    const separator = semicolons > commas ? ";" : ",";

    function checkSize() {
        if (limits.maxRows != null && rows.length > limits.maxRows + 1) {
            throw new Error("too_many_rows");
        }
        if (limits.maxColumns != null && row.length >= limits.maxColumns) {
            throw new Error("too_many_columns");
        }
    }

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
        } else if (ch === separator) {
            row.push(cell);
            checkSize();
            cell = "";
        } else if (ch === "\n" || (ch === "\r" && next === "\n")) {
            row.push(cell);
            if (row.some((value) => value.trim() !== "")) {
                rows.push(row);
                if (limits.maxRows != null && rows.length > limits.maxRows + 1) {
                    throw new Error("too_many_rows");
                }
            }
            row = [];
            cell = "";
            if (ch === "\r") i += 1;
        } else if (ch !== "\r") {
            cell += ch;
        }
    }

    if (inQuotes) throw new Error("invalid_csv");
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

    // Formula text is data. Never treat a cached formula result as an amount/date.
    if (cell.f) return `=${cell.f}`;

    if (cell.t === "d" && cell.v instanceof Date) {
        return formatLocalDateParts(cell.v) ?? "";
    }

    if (cell.t === "n" && typeof cell.v === "number") {
        // Preserve zero-padded identifiers without applying currency/grouping formats.
        if (Number.isInteger(cell.v) && typeof cell.z === "string" && /^0{2,}$/.test(cell.z)) {
            return XLSX.utils.format_cell(cell);
        }
        return String(cell.v);
    }

    if (typeof cell.w === "string" && cell.w.trim() !== "") {
        return cell.w.trim();
    }

    if (cell.v == null) return "";
    if (typeof cell.v === "string") return cell.v.trim();
    if (typeof cell.v === "boolean") return cell.v ? "1" : "0";
    return String(cell.v).trim();
}

function sheetToMatrix(sheet: XLSX.WorkSheet, limits: SpreadsheetLimits): string[][] {
    const ref = sheet["!ref"];
    if (!ref) {
        throw new Error("no_data_rows");
    }

    const range = XLSX.utils.decode_range(ref);
    if (limits.maxRows != null && range.e.r - (limits.headerRowIndex ?? range.s.r) > limits.maxRows) {
        throw new Error("too_many_rows");
    }
    if (limits.maxColumns != null && range.e.c - (limits.headerRowIndex == null ? range.s.c : 0) + 1 > limits.maxColumns) {
        throw new Error("too_many_columns");
    }
    const matrix: string[][] = [];

    for (let row = limits.headerRowIndex == null ? range.s.r : 0; row <= range.e.r; row += 1) {
        const values: string[] = [];
        for (let column = limits.headerRowIndex == null ? range.s.c : 0; column <= range.e.c; column += 1) {
            values.push(readWorksheetCell(sheet, row, column));
        }
        matrix.push(values);
    }

    return matrix;
}

function rowsFromMatrix(matrix: string[][], headerRowIndex?: number): ParsedSpreadsheet {
    if (headerRowIndex != null) {
        if (!Number.isInteger(headerRowIndex) || headerRowIndex < 0 || headerRowIndex > 100
            || matrix.length < headerRowIndex + 2) throw new Error("no_data_rows");
        const retained = matrix.slice(headerRowIndex + 1)
            .map((row, index) => ({ row, number: headerRowIndex + index + 2 }))
            .filter(({ row }) => row.some(cell => cell.trim() !== ""));
        return { headers: matrix[headerRowIndex].map(cell => cell.trim()), rows: retained.map(({ row }) => row),
            rowNumbers: retained.map(({ number }) => number), preambleRows: matrix.slice(0, headerRowIndex) };
    }
    if (matrix.length < 2) {
        throw new Error("no_data_rows");
    }
    const [headerRow, ...dataRows] = matrix;
    const headers = (headerRow ?? []).map((cell) => cell.trim());
    const rows = dataRows.filter((row) => row.some((cell) => cell.trim() !== ""));

    return { headers, rows };
}

export async function parseSpreadsheetFile(
    file: File,
    limits: SpreadsheetLimits = {},
): Promise<ParsedSpreadsheet> {
    if (limits.headerRowIndex != null && (!Number.isInteger(limits.headerRowIndex)
        || limits.headerRowIndex < 0 || limits.headerRowIndex > 100)) throw new Error("unsupported_file");
    if (limits.maxFileBytes != null && file.size > limits.maxFileBytes) {
        throw new Error("file_too_large");
    }
    const lower = file.name.toLowerCase();

    if (lower.endsWith(".csv")) {
        if (limits.headerRowIndex != null || limits.singleSheet) throw new Error("unsupported_file");
        const text = stripUtf8Bom(await file.text());
        const allRows = parseCsvRows(text, limits);
        if (allRows.length < 2) throw new Error("no_data_rows");
        const [headerRow, ...dataRows] = allRows;
        if (limits.maxRows != null && dataRows.length > limits.maxRows) {
            throw new Error("too_many_rows");
        }
        if (limits.maxColumns != null && allRows.some((row) => row.length > limits.maxColumns!)) {
            throw new Error("too_many_columns");
        }
        return {
            headers: headerRow.map((h) => h.trim()),
            rows: dataRows,
        };
    }

    if (lower.endsWith(".xlsx") || lower.endsWith(".xls")) {
        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: "array", cellDates: true, cellNF: true });
        if (limits.singleSheet && workbook.SheetNames.length !== 1) throw new Error("unsupported_file");
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        if (!sheet) throw new Error("no_data_rows");
        const result = rowsFromMatrix(sheetToMatrix(sheet, limits), limits.headerRowIndex);
        if (limits.headerRowIndex != null) {
            result.cellTypes = result.rowNumbers!.map(row => result.headers.map((_, column) => {
                const cell = sheet[XLSX.utils.encode_cell({ r: row - 1, c: column })];
                return cell?.f != null || cell?.F != null ? "formula" : cell?.t ?? "blank";
            }));
        }
        return result;
    }

    throw new Error("unsupported_file");
}
