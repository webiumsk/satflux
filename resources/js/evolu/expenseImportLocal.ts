import { bankPaymentCurrency, positiveBankAmount } from "./bankPaymentValues";
import { normalizedExpenseNumber, normalizedExpenseTitle, expenseCandidate, type ExpenseDuplicateSuggestion } from "./expenseDuplicateSuggestions";
import type { Evolu } from "@evolu/common/local-first";
import type {
    ExpenseImportFieldKey,
    ExpenseImportMapping,
} from "@/composables/useExpenseImportFields";
import { REQUIRED_EXPENSE_IMPORT_FIELDS } from "@/composables/useExpenseImportFields";
import { normalizeHeader } from "./contactImportLocal";
import { downloadCsvBlob } from "./documentBulkLocal";
import {
    insertLocalExpense,
    nextLocalExpenseInternalNumber,
    type ExpenseSavePayload,
} from "./expenseCrud";
import type { EvoluExpenseRow } from "./expenseMap";
import { parseSpreadsheetFile } from "./spreadsheetParse";
import type { CompanyId, InvoicingLocalSchema } from "./schema";
import { parseImportDate, toIsoDate } from "./importDateParse";
import { IMPORT_YIELD_EVERY_ROWS, yieldToEventLoop } from "./importYield";
import { withExpenseReconciliation } from "./expenseReconciliation";
import { BankReconciliationUnknownError } from "./bankReconciliation";

export { downloadCsvBlob };

const SPREADSHEET_LIMITS = {
    maxRows: 2000,
    maxColumns: 256,
    maxFileBytes: 10 * 1024 * 1024,
};
const MAX_TOTAL = 999999999999.99;

export const EXPENSE_IMPORT_EXAMPLE_HEADERS = [
    "Názov",
    "Kategória",
    "Dodávateľ",
    "Číslo dokladu",
    "Interné číslo",
    "Dátum vystavenia",
    "Dátum splatnosti",
    "Dátum dodania",
    "Spolu",
    "Mena",
    "Dátum úhrady",
    "Variabilný symbol",
    "Konštantný symbol",
    "Špecifický symbol",
    "Tagy",
    "Interná poznámka",
    "Dodávateľ - IČO",
    "Dodávateľ - DIČ",
    "Dodávateľ - E-mail",
    "Dodávateľ - Ulica",
    "Dodávateľ - Mesto",
    "Dodávateľ - Štát",
] as const;

const HEADER_ALIASES: Record<ExpenseImportFieldKey, string[]> = {
    title: ["nazov", "name", "title", "popis"],
    category: ["kategoria", "category"],
    supplier_name: ["dodavatel", "supplier", "supplier name"],
    external_number: ["cislo dokladu", "cislo dokl", "external number", "invoice number"],
    internal_number: ["interne cislo dokladu", "interne cislo", "interne cis", "internal number"],
    issue_date: ["datum vystavenia", "datum vy", "issue date", "vystavenie"],
    due_date: ["datum splatnosti", "datum sp", "due date", "splatnost"],
    delivery_date: ["datum dodania", "datum do", "delivery date"],
    total: ["spolu", "suma", "total", "amount"],
    currency: ["mena", "currency"],
    paid_at: ["datum uhrady", "datum uh", "paid at", "payment date"],
    payment_method: ["druh uhrady", "payment method"],
    payment_amount: ["suma uhrady", "payment amount"],
    payment_currency: ["mena uhrady", "payment currency"],
    payment_exchange_rate: ["kurz uhrady", "payment exchange rate"],
    variable_symbol: ["variabilny symbol", "vs", "variable symbol"],
    constant_symbol: ["konstantny symbol", "ks", "constant symbol"],
    specific_symbol: ["specificky symbol", "ss", "specific symbol"],
    tags: ["tagy", "tags"],
    internal_note: ["interna poznamka", "interna p", "poznamka", "note", "notes"],
    supplier_registration_number: ["dodavatel - ico", "dodavatel ico", "ico"],
    supplier_tax_id: ["dodavatel - dic", "dodavatel dic", "dic"],
    supplier_vat_id: ["dodavatel ic dph", "dodavatel - ic dph", "supplier vat id"],
    supplier_phone: ["dodavatel telefon", "dodavatel - telefon", "supplier phone"],
    supplier_bank_account: ["bankovy ucet dodavatela", "supplier bank account"],
    supplier_email: ["dodavatel - e-mail", "dodavatel - email", "dodavatel email"],
    supplier_street: ["dodavatel - ulica", "dodavatel ulica", "dodavatel adresa"],
    supplier_postal_code: ["dodavatel psc", "dodavatel - psc", "supplier postal code"],
    supplier_city: ["dodavatel - mesto", "dodavatel mesto"],
    supplier_country: ["dodavatel - stat", "dodavatel stat", "dodavatel krajina"],
    item_quantity: ["pocet poloziek", "item quantity"],
    item_units: ["jednotky poloziek", "item units"],
    items: ["polozky", "items"],
};

export type ExpenseImportPreviewResult = {
    headers: string[];
    suggested_mapping: ExpenseImportMapping;
    row_count: number;
};

export type ExpenseImportRowError = {
    row: number;
    internal_number?: string | null;
    message: string;
};

export type ExpenseImportResult = {
    imported: number;
    skipped: number;
    errors: ExpenseImportRowError[];
};

function buildAliasLookup(): Map<string, ExpenseImportFieldKey> {
    const lookup = new Map<string, ExpenseImportFieldKey>();
    for (const [field, aliases] of Object.entries(HEADER_ALIASES) as [ExpenseImportFieldKey, string[]][]) {
        for (const alias of aliases) {
            lookup.set(normalizeHeader(alias), field);
        }
    }
    return lookup;
}

const ALIAS_LOOKUP = buildAliasLookup();

export function suggestExpenseImportMapping(headers: string[]): ExpenseImportMapping {
    const mapping: ExpenseImportMapping = {};
    for (const field of Object.keys(HEADER_ALIASES) as ExpenseImportFieldKey[]) {
        mapping[field] = null;
    }
    headers.forEach((header, index) => {
        if (!header.trim()) return;
        const field = ALIAS_LOOKUP.get(normalizeHeader(header));
        if (field && mapping[field] == null) {
            mapping[field] = index;
        }
    });
    return mapping;
}

function normalizeMapping(mapping: ExpenseImportMapping, columnCount: number): ExpenseImportMapping {
    const normalized: ExpenseImportMapping = {};
    for (const field of Object.keys(HEADER_ALIASES) as ExpenseImportFieldKey[]) {
        const value: unknown = mapping[field];
        if (value == null || value === "") {
            normalized[field] = null;
            continue;
        }
        const index = typeof value === "number" ? value : Number(value);
        if (
            (typeof value !== "number" && typeof value !== "string")
            || (typeof value === "string" && !/^(0|[1-9]\d*)$/.test(value))
            || !Number.isInteger(index)
            || index < 0
            || index >= columnCount
        ) {
            throw new Error(`invalid_mapping:${field}`);
        }
        normalized[field] = index;
    }
    for (const field of REQUIRED_EXPENSE_IMPORT_FIELDS) {
        if (normalized[field] == null) {
            throw new Error(`missing_mapping:${field}`);
        }
    }
    return normalized;
}

function cell(row: string[], index: number | null | undefined): string | null {
    if (index == null) return null;
    const value = row[index] ?? "";
    const trimmed = value.trim();
    return trimmed === "" ? null : trimmed;
}

function parseAmount(value: string | null): number | null {
    if (!value) return null;
    const normalized = value.replace(/\s/g, "");
    if (!/^[+-]?\d+(?:[.,]\d+)?$/.test(normalized)) return null;
    const n = Number(normalized.replace(",", "."));
    return Number.isFinite(n) && n >= 0 && n <= MAX_TOTAL ? n : null;
}

function parseDate(value: string | null): string | null {
    const parsed = parseImportDate(value, "dmy_dot");
    if (!parsed) return null;
    const match = parsed.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match ? toIsoDate(Number(match[1]), Number(match[2]), Number(match[3])) : null;
}

function optionalDate(row: string[], mapping: ExpenseImportMapping, field: "delivery_date" | "due_date" | "paid_at"): string | null {
    const raw = cell(row, mapping[field]);
    if (!raw) return null;
    const date = parseDate(raw);
    if (!date) throw new Error(`invalid_${field}`);
    return date;
}

function resolveTitle(row: string[], mapping: ExpenseImportMapping): string | null {
    const title = cell(row, mapping.title);
    if (title) return title;
    const supplier = cell(row, mapping.supplier_name);
    const category = cell(row, mapping.category);
    if (supplier && category) return `${supplier} - ${category}`;
    return supplier ?? category;
}

const NOTE_FIELDS: Array<[ExpenseImportFieldKey, string]> = [
    ["tags", "Tags"],
    ["supplier_name", "Supplier"],
    ["category", "Category"],
    ["supplier_registration_number", "Supplier registration number"],
    ["supplier_tax_id", "Supplier tax ID"],
    ["supplier_vat_id", "Supplier VAT ID"],
    ["supplier_phone", "Supplier phone"],
    ["supplier_email", "Supplier email"],
    ["supplier_street", "Supplier street"],
    ["supplier_postal_code", "Supplier postal code"],
    ["supplier_city", "Supplier city"],
    ["supplier_country", "Supplier country"],
    ["supplier_bank_account", "Supplier bank account"],
    ["payment_method", "Payment method"],
    ["payment_amount", "Payment amount"],
    ["payment_currency", "Payment currency"],
    ["payment_exchange_rate", "Payment exchange rate"],
    ["item_quantity", "Item quantity"],
    ["item_units", "Item units"],
    ["items", "Items"],
];

function buildInternalNote(
    row: string[],
    mapping: ExpenseImportMapping,
    unresolvedPaymentDate: string | null,
): string {
    const parts: string[] = [];
    const userNote = cell(row, mapping.internal_note);
    if (userNote) parts.push(userNote);
    for (const [field, label] of NOTE_FIELDS) {
        const value = cell(row, mapping[field]);
        if (value) parts.push(`${label}: ${value}`);
    }
    if (unresolvedPaymentDate) parts.push(`Payment date: ${unresolvedPaymentDate}`);
    parts.push("import");
    const note = parts.join("\n\n");
    if (note.length > 4000) throw new Error("internal_note_too_long");
    return note;
}

function isEmptyRow(row: string[]): boolean {
    return row.every((value) => value.trim() === "");
}

function internalNumberFromRow(row: string[], mapping: ExpenseImportMapping): string | null {
    return cell(row, mapping.internal_number);
}

function rowToPayload(
    row: string[],
    mapping: ExpenseImportMapping,
    existingNumbers: Set<string>,
): ExpenseSavePayload & { mark_paid: boolean } {
    const issueDate = parseDate(cell(row, mapping.issue_date));
    const total = parseAmount(cell(row, mapping.total));
    if (!issueDate) throw new Error("invalid_issue_date");
    if (total == null || total < 0) throw new Error("invalid_total");

    const explicitInternal = cell(row, mapping.internal_number);
    if (explicitInternal && existingNumbers.has(explicitInternal)) {
        throw new Error(`duplicate_internal_number:${explicitInternal}`);
    }

    const paidAt = optionalDate(row, mapping, "paid_at");
    const paymentAmountRaw = cell(row, mapping.payment_amount);
    const paymentAmount = parseAmount(paymentAmountRaw);
    if (paymentAmountRaw && paymentAmount == null) throw new Error("invalid_payment_amount");
    const currency = (cell(row, mapping.currency) || "EUR").toUpperCase();
    if (!/^[A-Z]{3}$/.test(currency)) throw new Error("invalid_currency");
    const paymentCurrency = cell(row, mapping.payment_currency)?.toUpperCase();
    const markPaid = paidAt != null && (
        paymentAmountRaw == null
        || (paymentAmount != null && paymentAmount >= total && (!paymentCurrency || paymentCurrency === currency))
    );

    const title = resolveTitle(row, mapping);
    const textFields: Array<[string, string | null, number]> = [
        ["title", title, 255],
        ["internal_number", explicitInternal, 64],
        ["external_number", cell(row, mapping.external_number), 64],
        ["variable_symbol", cell(row, mapping.variable_symbol) ?? cell(row, mapping.external_number), 32],
        ["constant_symbol", cell(row, mapping.constant_symbol), 16],
        ["specific_symbol", cell(row, mapping.specific_symbol), 16],
    ];
    for (const [field, value, limit] of textFields) {
        if (value && value.length > limit) throw new Error(`invalid_${field}`);
    }

    return {
        title,
        external_number: cell(row, mapping.external_number),
        variable_symbol: cell(row, mapping.variable_symbol) ?? cell(row, mapping.external_number),
        constant_symbol: cell(row, mapping.constant_symbol),
        specific_symbol: cell(row, mapping.specific_symbol),
        issue_date: issueDate,
        delivery_date: optionalDate(row, mapping, "delivery_date") ?? issueDate,
        due_date: optionalDate(row, mapping, "due_date"),
        total,
        currency,
        internal_note: buildInternalNote(row, mapping, markPaid ? null : paidAt),
        mark_paid: markPaid,
        paid_at: markPaid ? paidAt : null,
    };
}

export async function previewLocalExpenseImport(file: File): Promise<ExpenseImportPreviewResult> {
    const parsed = await parseSpreadsheetFile(file, SPREADSHEET_LIMITS);
    return {
        headers: parsed.headers,
        suggested_mapping: suggestExpenseImportMapping(parsed.headers),
        row_count: parsed.rows.filter((row) => !isEmptyRow(row)).length,
    };
}

export type ExpenseImportDuplicateWarning = ExpenseDuplicateSuggestion & { row: number; in_file: boolean };

/** Parse the mapped file without financial writes and show at most twenty advisory existing/in-file matches. */
export async function previewLocalExpenseImportDuplicates(
    companyId: string, ownerId: string, file: File, mappingInput: ExpenseImportMapping,
    existingRows: readonly EvoluExpenseRow[],
): Promise<ExpenseImportDuplicateWarning[]> {
    const parsed = await parseSpreadsheetFile(file, SPREADSHEET_LIMITS);
    const mapping = normalizeMapping(mappingInput, parsed.headers.length);
    const byNumber = new Map<string, EvoluExpenseRow>();
    const byTitle = new Map<string, EvoluExpenseRow>();
    /** Build duplicate keys from the exact two-decimal total persisted by the import. */
    const keys = (draft: { external_number?: string | null; title?: string | null; issue_date?: string | null; total: string | number; currency?: string | null }) => {
        const amount = positiveBankAmount(Number(draft.total).toFixed(2));
        const currency = bankPaymentCurrency(draft.currency);
        if (!amount || !currency) return { number: null, title: null };
        const number = normalizedExpenseNumber(draft.external_number);
        const title = normalizedExpenseTitle(draft.title);
        return {
            number: number ? JSON.stringify([amount, currency, number]) : null,
            title: title && draft.issue_date ? JSON.stringify([amount, currency, title, draft.issue_date]) : null,
        };
    };
    /** Keep the first deterministic existing document for each supported duplicate key. */
    const indexRow = (row: EvoluExpenseRow) => {
        const key = keys({ external_number: row.externalNumber, title: row.title, issue_date: row.issueDate,
            total: row.total || '', currency: row.currency });
        if (key.number && !byNumber.has(key.number)) byNumber.set(key.number, row);
        if (key.title && !byTitle.has(key.title)) byTitle.set(key.title, row);
    };
    existingRows.filter((row) => row.companyId === companyId && Reflect.get(row, 'ownerId') === ownerId
        && row.status !== 'cancelled' && !row.cancelledAt && Reflect.get(row, 'isDeleted') !== 1)
        .sort((a, b) => a.internalNumber.localeCompare(b.internalNumber) || a.id.localeCompare(b.id)).forEach(indexRow);
    const previewIds = new Set<string>();
    const warnings: ExpenseImportDuplicateWarning[] = [];
    for (const [index, cells] of parsed.rows.entries()) {
        if (isEmptyRow(cells)) continue;
        if (index > 0 && index % IMPORT_YIELD_EVERY_ROWS === 0) await yieldToEventLoop();
        let draft: ExpenseSavePayload;
        try { draft = rowToPayload(cells, mapping, new Set()); }
        catch { continue; } // The real import reports invalid rows; advisory checks never change that decision.
        const key = keys(draft);
        const numbered = key.number ? byNumber.get(key.number) : undefined;
        const matched = numbered ?? (key.title ? byTitle.get(key.title) : undefined);
        if (matched) warnings.push({ expense: expenseCandidate(matched), reason: numbered ? 'external_number' : 'title_date_amount',
            row: index + 2, in_file: previewIds.has(matched.id) });
        if (warnings.length >= 20) break;
        const id = `preview-row-${index + 2}`;
        previewIds.add(id);
        indexRow({ id, companyId, ownerId, internalNumber: String(index + 2),
            externalNumber: draft.external_number ?? null, title: draft.title ?? null,
            issueDate: draft.issue_date, total: String(draft.total), currency: draft.currency ?? null,
            status: "recorded", cancelledAt: null,
        } as unknown as EvoluExpenseRow);
    }
    return warnings;
}

export function downloadExpenseImportExampleCsv(): void {
    const rows = [
        [...EXPENSE_IMPORT_EXAMPLE_HEADERS],
        [
            "Monacor káble",
            "Hifiaudio",
            "MONACOR",
            "FV2024001",
            "",
            "15.01.2024",
            "29.01.2024",
            "15.01.2024",
            "120,50",
            "EUR",
            "",
            "2024001",
            "",
            "",
            "audio",
            "",
            "12345678",
            "2012345678",
            "info@monacor.sk",
            "Hlavná 1",
            "Bratislava",
            "SK",
        ],
    ];
    const csv = rows.map((row) => row.map((cell) => `"${cell.replace(/"/g, '""')}"`).join(",")).join("\n");
    downloadCsvBlob(csv, "expense_import_example.csv");
}

/**
 * Parse the file before acquiring the writer gate, then allocate numbers from fresh company rows.
 * Report known row errors, but propagate uncertain worker outcomes without continuing the import.
 */
export async function importLocalExpensesFromFile(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    file: File,
    mappingInput: ExpenseImportMapping,
    existingRows: EvoluExpenseRow[],
): Promise<ExpenseImportResult> {
    const parsed = await parseSpreadsheetFile(file, SPREADSHEET_LIMITS);
    const mapping = normalizeMapping(mappingInput, parsed.headers.length);
    return withExpenseReconciliation(evolu, async (scope) => {
    evolu = scope.evolu;
    existingRows = scope.context.expenses;
    const existingNumbers = new Set(
        existingRows.filter((row) => row.companyId === companyId).map((row) => row.internalNumber),
    );
    const reservedNumbers = new Set(
        parsed.rows.map((row) => cell(row, mapping.internal_number)).filter((number): number is string => number != null),
    );
    const workingRows = existingRows.filter((row) => row.companyId === companyId);

    let imported = 0;
    let skipped = 0;
    const errors: ExpenseImportRowError[] = [];
    let rowNumber = 1;

    for (const row of parsed.rows) {
        rowNumber += 1;
        if (isEmptyRow(row)) continue;
        if ((rowNumber - 1) % IMPORT_YIELD_EVERY_ROWS === 0) await yieldToEventLoop();

        try {
            const explicitInternal = cell(row, mapping.internal_number);
            const draft = rowToPayload(row, mapping, existingNumbers);
            let internalNumber = explicitInternal;
            if (!internalNumber) {
                const prefix = `EXP${new Date().getFullYear()}`;
                const suggested = nextLocalExpenseInternalNumber(companyId, workingRows);
                let sequence = BigInt(suggested.slice(prefix.length));
                internalNumber = `${prefix}${sequence.toString().padStart(4, "0")}`;
                while (reservedNumbers.has(internalNumber) || existingNumbers.has(internalNumber)) {
                    sequence += 1n;
                    internalNumber = `${prefix}${sequence.toString().padStart(4, "0")}`;
                }
            }

            const result = await insertLocalExpense(evolu, companyId, draft, workingRows, { internalNumber });
            if (!result.ok) throw new Error("insert_failed");

            // Reserve successful inserts immediately. A later query failure must
            // never report a saved expense as skipped or allow a duplicate number.
            existingNumbers.add(internalNumber);
            workingRows.push({
                id: result.value.id,
                companyId,
                internalNumber,
                status: draft.mark_paid ? "paid" : "recorded",
                title: draft.title ?? null,
                externalNumber: draft.external_number ?? null,
                variableSymbol: draft.variable_symbol ?? null,
                constantSymbol: draft.constant_symbol ?? null,
                specificSymbol: draft.specific_symbol ?? null,
                issueDate: draft.issue_date,
                deliveryDate: draft.delivery_date ?? null,
                dueDate: draft.due_date ?? null,
                paidAt: draft.paid_at ?? null,
                cancelledAt: null,
                total: Number(draft.total).toFixed(2),
                currency: draft.currency ?? "EUR",
                internalNote: draft.internal_note ?? null,
            });
            imported += 1;
        } catch (e: unknown) {
            if (e instanceof BankReconciliationUnknownError) throw e;
            skipped += 1;
            const message = e instanceof Error ? e.message : "import_failed";
            errors.push({
                row: rowNumber,
                internal_number: internalNumberFromRow(row, mapping),
                message,
            });
        }
    }

    return { imported, skipped, errors };
    });
}
