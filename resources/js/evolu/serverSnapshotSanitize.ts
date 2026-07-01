import { booleanToSqliteBoolean, type SqliteBoolean } from "@evolu/common";
import type { InvoicingDataSnapshot } from "./invoicingSnapshot";

type InvoicingTable = keyof InvoicingDataSnapshot;

const SQLITE_BOOLEAN_FIELDS: Partial<Record<InvoicingTable, readonly string[]>> = {
    company: ["vatPayer"],
    contact: ["isActive"],
    document: ["pdfShowSignature", "pdfShowPaymentInfo", "paymentBankEnabled", "paymentBtcEnabled"],
    numberSeries: ["isDefault"],
    recurringProfile: ["isActive", "repeatIndefinitely", "issueLastDayOfMonth", "pdfShowSignature", "pdfShowPaymentInfo", "paymentBtcEnabled", "paymentBankEnabled", "sendEmailAfterIssue"],
    companyWarehouse: ["deductOnIssue", "isDefault", "isActive"],
    companyStockItem: ["trackInventory", "excludeFromSuggester"],
};

function emptyToNull(value: unknown): string | null {
    if (value == null) return null;
    const trimmed = String(value).trim();
    return trimmed === "" ? null : trimmed;
}

function nullifyEmptyStrings(row: Record<string, unknown>): Record<string, unknown> {
    const out: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(row)) {
        if (value === "") {
            out[key] = null;
            continue;
        }
        out[key] = value;
    }
    return out;
}

function normalizeSqliteBoolean(value: unknown): SqliteBoolean | null {
    if (value === null || value === undefined) return null;
    if (value === 0 || value === false || value === "0") return booleanToSqliteBoolean(false);
    if (value === 1 || value === true || value === "1") return booleanToSqliteBoolean(true);
    return null;
}

function applySqliteBooleans(
    table: InvoicingTable,
    row: Record<string, unknown>,
): Record<string, unknown> {
    const fields = SQLITE_BOOLEAN_FIELDS[table] ?? [];
    if (fields.length === 0) return row;

    const out = { ...row };
    for (const field of fields) {
        if (!(field in out)) continue;
        out[field] = normalizeSqliteBoolean(out[field]);
    }
    return out;
}

const BANK_IMPORT_SOURCES = new Set(["csv", "camt053", "inbound_email", "manual", "wise"]);

function normalizeBankImportSource(value: unknown): string {
    const text = emptyToNull(value);
    if (text === "email") {
        return "inbound_email";
    }
    if (text && BANK_IMPORT_SOURCES.has(text)) {
        return text;
    }
    return "csv";
}

function sanitizeBankRow(table: InvoicingTable, row: Record<string, unknown>): Record<string, unknown> {
    if (table !== "bankImportBatch" && table !== "bankTransaction") {
        return row;
    }
    return {
        ...row,
        source: normalizeBankImportSource(row.source),
    };
}

function sanitizeRow(table: InvoicingTable, row: Record<string, unknown>): Record<string, unknown> {
    return sanitizeBankRow(table, applySqliteBooleans(table, nullifyEmptyStrings(row)));
}

/** Final coercion pass so server export rows match Evolu upsert validators. */
export function sanitizeSnapshotRowsForUpsert(snapshot: InvoicingDataSnapshot): InvoicingDataSnapshot {
    const out = {} as InvoicingDataSnapshot;
    for (const table of Object.keys(snapshot) as InvoicingTable[]) {
        out[table] = snapshot[table].map((row) => sanitizeRow(table, row as Record<string, unknown>));
    }
    return out;
}

/** Drop rows that are missing a primary id after preparation. */
export function dropRowsWithoutId(snapshot: InvoicingDataSnapshot): InvoicingDataSnapshot {
    const out = {} as InvoicingDataSnapshot;
    for (const table of Object.keys(snapshot) as InvoicingTable[]) {
        out[table] = snapshot[table].filter((row) => emptyToNull(row.id) != null);
    }
    return out;
}
