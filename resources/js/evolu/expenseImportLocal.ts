import type { Evolu } from "@evolu/common/local-first";
import type {
    ExpenseImportFieldKey,
    ExpenseImportMapping,
} from "@/composables/useExpenseImportFields";
import { REQUIRED_EXPENSE_IMPORT_FIELDS } from "@/composables/useExpenseImportFields";
import { normalizeHeader } from "./contactImportLocal";
import { downloadCsvBlob } from "./documentBulkLocal";
import { insertLocalExpense, type ExpenseSavePayload } from "./expenseCrud";
import type { EvoluExpenseRow } from "./expenseMap";
import { allExpensesQuery } from "./client";
import { parseSpreadsheetFile } from "./spreadsheetParse";
import type { CompanyId, InvoicingLocalSchema } from "./schema";

export { downloadCsvBlob };

const MAX_ROWS = 2000;

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
    internal_number: ["interne cislo", "interne cis", "internal number"],
    issue_date: ["datum vystavenia", "datum vy", "issue date", "vystavenie"],
    due_date: ["datum splatnosti", "datum sp", "due date", "splatnost"],
    delivery_date: ["datum dodania", "datum do", "delivery date"],
    total: ["spolu", "suma", "total", "amount"],
    currency: ["mena", "currency"],
    paid_at: ["datum uhrady", "datum uh", "paid at", "payment date"],
    variable_symbol: ["variabilny symbol", "vs", "variable symbol"],
    constant_symbol: ["konstantny symbol", "ks", "constant symbol"],
    specific_symbol: ["specificky symbol", "ss", "specific symbol"],
    tags: ["tagy", "tags"],
    internal_note: ["interna poznamka", "interna p", "poznamka", "note", "notes"],
    supplier_registration_number: ["dodavatel - ico", "dodavatel ico", "ico"],
    supplier_tax_id: ["dodavatel - dic", "dodavatel dic", "dic"],
    supplier_email: ["dodavatel - e-mail", "dodavatel - email", "dodavatel email"],
    supplier_street: ["dodavatel - ulica", "dodavatel ulica"],
    supplier_city: ["dodavatel - mesto", "dodavatel mesto"],
    supplier_country: ["dodavatel - stat", "dodavatel stat"],
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

function normalizeMapping(mapping: ExpenseImportMapping): ExpenseImportMapping {
    const normalized: ExpenseImportMapping = {};
    for (const field of Object.keys(HEADER_ALIASES) as ExpenseImportFieldKey[]) {
        const value = mapping[field];
        normalized[field] = value == null || value === "" ? null : Number(value);
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
    const normalized = value.replace(/\s/g, "").replace(",", ".");
    const n = Number(normalized);
    return Number.isFinite(n) ? n : null;
}

function parseDate(value: string | null): string | null {
    if (!value) return null;
    const iso = value.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
    const dotted = value.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})/);
    if (dotted) {
        return `${dotted[3]}-${dotted[2].padStart(2, "0")}-${dotted[1].padStart(2, "0")}`;
    }
    const slashed = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
    if (slashed) {
        return `${slashed[3]}-${slashed[2].padStart(2, "0")}-${slashed[1].padStart(2, "0")}`;
    }
    return null;
}

function resolveTitle(row: string[], mapping: ExpenseImportMapping): string | null {
    const title = cell(row, mapping.title);
    if (title) return title;
    const supplier = cell(row, mapping.supplier_name);
    const category = cell(row, mapping.category);
    if (supplier && category) return `${supplier} - ${category}`;
    return supplier ?? category;
}

function buildInternalNote(row: string[], mapping: ExpenseImportMapping): string | null {
    const parts: string[] = [];
    const userNote = cell(row, mapping.internal_note);
    if (userNote) parts.push(userNote);
    const tags = cell(row, mapping.tags);
    if (tags) parts.push(`Tags: ${tags}`);
    const supplier = cell(row, mapping.supplier_name);
    const category = cell(row, mapping.category);
    if (supplier && !cell(row, mapping.title)) parts.push(`Supplier: ${supplier}`);
    if (category && !cell(row, mapping.title)) parts.push(`Category: ${category}`);

    const supplierLines = [
        cell(row, mapping.supplier_registration_number) ? `IČO: ${cell(row, mapping.supplier_registration_number)}` : null,
        cell(row, mapping.supplier_tax_id) ? `DIČ: ${cell(row, mapping.supplier_tax_id)}` : null,
        cell(row, mapping.supplier_email),
        [cell(row, mapping.supplier_street), cell(row, mapping.supplier_city), cell(row, mapping.supplier_country)]
            .filter(Boolean)
            .join(", ") || null,
    ].filter(Boolean) as string[];

    if (supplierLines.length) parts.push(supplierLines.join("\n"));
    parts.push("import");
    const note = parts.join("\n\n").trim();
    return note || null;
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

    const paidAt = parseDate(cell(row, mapping.paid_at));

    return {
        title: resolveTitle(row, mapping),
        external_number: cell(row, mapping.external_number),
        variable_symbol: cell(row, mapping.variable_symbol) ?? cell(row, mapping.external_number),
        constant_symbol: cell(row, mapping.constant_symbol),
        specific_symbol: cell(row, mapping.specific_symbol),
        issue_date: issueDate,
        delivery_date: parseDate(cell(row, mapping.delivery_date)) ?? issueDate,
        due_date: parseDate(cell(row, mapping.due_date)),
        total,
        currency: (cell(row, mapping.currency) || "EUR").toUpperCase(),
        internal_note: buildInternalNote(row, mapping),
        mark_paid: paidAt != null,
    };
}

export async function previewLocalExpenseImport(file: File): Promise<ExpenseImportPreviewResult> {
    const parsed = await parseSpreadsheetFile(file);
    return {
        headers: parsed.headers,
        suggested_mapping: suggestExpenseImportMapping(parsed.headers),
        row_count: parsed.rows.filter((row) => !isEmptyRow(row)).length,
    };
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

export async function importLocalExpensesFromFile(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    file: File,
    mappingInput: ExpenseImportMapping,
    existingRows: EvoluExpenseRow[],
): Promise<ExpenseImportResult> {
    const mapping = normalizeMapping(mappingInput);
    const parsed = await parseSpreadsheetFile(file);
    const existingNumbers = new Set(
        existingRows.filter((row) => row.companyId === companyId).map((row) => row.internalNumber),
    );
    let workingRows = existingRows.filter((row) => row.companyId === companyId);

    let imported = 0;
    let skipped = 0;
    const errors: ExpenseImportRowError[] = [];
    let rowNumber = 1;

    for (const row of parsed.rows) {
        rowNumber += 1;
        if (isEmptyRow(row)) continue;
        if (imported + skipped >= MAX_ROWS) {
            errors.push({ row: rowNumber, message: "Row limit exceeded." });
            break;
        }

        try {
            const explicitInternal = cell(row, mapping.internal_number);
            const draft = rowToPayload(row, mapping, existingNumbers);

            const result = insertLocalExpense(evolu, companyId, draft, workingRows, {
                internalNumber: explicitInternal ?? undefined,
            });
            if (!result.ok) throw new Error("insert_failed");

            await evolu.loadQuery(allExpensesQuery);
            workingRows = evolu.getQueryRows(allExpensesQuery).filter(
                (r) => r.companyId === companyId,
            ) as EvoluExpenseRow[];
            for (const r of workingRows) {
                existingNumbers.add(r.internalNumber);
            }
            imported += 1;
        } catch (e: unknown) {
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
}
