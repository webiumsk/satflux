import type { Evolu } from "@evolu/common/local-first";
import { IMPORT_YIELD_EVERY_ROWS, yieldToEventLoop } from "./importYield";
import { emptyContactForm, type ContactFormState } from "@/composables/useCompanyContact";
import {
    CONTACT_IMPORT_FIELD_KEYS,
    type ContactImportFieldKey,
    type ContactImportMapping,
} from "@/composables/useContactImportFields";
import { insertLocalContactFromForm } from "./contactCrud";
import { downloadCsvBlob } from "./documentBulkLocal";
import type { CompanyId, InvoicingLocalSchema } from "./schema";

export { downloadCsvBlob };

const MAX_ROWS = 5000;

/** SuperFaktura-compatible example headers (row 1). */
export const CONTACT_IMPORT_EXAMPLE_HEADERS = [
    "Názov klienta",
    "Ulica",
    "PSČ",
    "Mesto",
    "Štát (skratka)",
    "IČO",
    "DIČ",
    "IČ DPH",
    "E-mail",
    "Telefón",
    "Fax",
    "Poštová adresa - Názov",
    "Poštová adresa - Ulica",
    "Poštová adresa - PSČ",
    "Poštová adresa - Mesto",
    "Poštová adresa - Štát (skratka)",
    "Web",
    "Poznámka",
    "Splatnosť (dni)",
    "Zľava (%)",
    "Mena",
    "Bankový účet - IBAN",
    "Bankový účet - BIC / SWIFT",
] as const;

export type ContactImportPreviewResult = {
    headers: string[];
    suggested_mapping: ContactImportMapping;
    row_count: number;
};

export type ContactImportRowError = {
    row: number;
    message: string;
};

export type ContactImportResult = {
    imported: number;
    skipped: number;
    errors: ContactImportRowError[];
};

const HEADER_ALIASES: Record<ContactImportFieldKey, string[]> = {
    name: ["nazov klienta", "name", "client name", "meno", "odberatel", "nazov"],
    street: ["ulica", "street", "address", "adresa"],
    postal_code: ["psc", "postal code", "zip"],
    city: ["mesto", "city"],
    country: ["stat (skratka)", "stat", "krajina", "country"],
    registration_number: ["ico", "registration number", "ic"],
    tax_id: ["dic", "tax id"],
    vat_id: ["ic dph", "vat id", "dph"],
    email: ["e-mail", "email"],
    phone: ["telefon", "phone"],
    fax: ["fax"],
    delivery_name: ["postova adresa - nazov", "delivery name", "postal name"],
    delivery_street: ["postova adresa - ulica", "delivery street"],
    delivery_postal_code: ["postova adresa - psc", "delivery postal code"],
    delivery_city: ["postova adresa - mesto", "delivery city"],
    delivery_country: ["postova adresa - stat (skratka)", "postova adresa - stat", "delivery country"],
    web: ["web", "website"],
    notes: ["poznamka", "note", "notes"],
    default_payment_terms_days: ["splatnost (dni)", "payment terms (days)", "splatnost"],
    iban: ["bankovy ucet - iban", "iban"],
    swift: ["bankovy ucet - bic / swift", "bankovy ucet - bic/swift", "swift", "bic"],
};

export function normalizeHeader(header: string): string {
    return header
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ");
}

function buildAliasLookup(): Map<string, ContactImportFieldKey> {
    const lookup = new Map<string, ContactImportFieldKey>();
    for (const [field, aliases] of Object.entries(HEADER_ALIASES) as [ContactImportFieldKey, string[]][]) {
        for (const alias of aliases) {
            lookup.set(normalizeHeader(alias), field);
        }
    }
    return lookup;
}

const ALIAS_LOOKUP = buildAliasLookup();

export function suggestMapping(headers: string[]): ContactImportMapping {
    const mapping: ContactImportMapping = {};
    for (const field of CONTACT_IMPORT_FIELD_KEYS) {
        mapping[field] = null;
    }

    for (const [index, header] of headers.entries()) {
        if (!header.trim()) continue;
        const field = ALIAS_LOOKUP.get(normalizeHeader(header));
        if (field != null && mapping[field] == null) {
            mapping[field] = index;
        }
    }

    return mapping;
}

export function normalizeMapping(mapping: ContactImportMapping): ContactImportMapping {
    const normalized: ContactImportMapping = {};
    for (const field of CONTACT_IMPORT_FIELD_KEYS) {
        const value = mapping[field];
        normalized[field] = value === "" || value == null ? null : (() => {
            const index = Number(value);
            return Number.isInteger(index) && index >= 0 ? index : null;
        })();
    }

    if (normalized.name == null) {
        throw new Error("Missing required column mapping: name");
    }

    return normalized;
}

export function stripUtf8Bom(text: string): string {
    return text.charCodeAt(0) === 0xfeff ? text.slice(1) : text;
}

export function isContactImportCsvFile(file: File): boolean {
    const name = file.name.toLowerCase();
    return name.endsWith(".csv") || file.type === "text/csv";
}

export async function readContactImportCsvFile(file: File): Promise<string> {
    return stripUtf8Bom(await file.text());
}

function parseCsvRows(text: string): string[][] {
    const rows: string[][] = [];
    let row: string[] = [];
    let field = "";
    let inQuotes = false;

    for (let i = 0; i < text.length; i++) {
        const char = text[i];
        const next = text[i + 1];

        if (inQuotes) {
            if (char === '"') {
                if (next === '"') {
                    field += '"';
                    i++;
                } else {
                    inQuotes = false;
                }
            } else {
                field += char;
            }
            continue;
        }

        if (char === '"') {
            inQuotes = true;
        } else if (char === ",") {
            row.push(field);
            field = "";
        } else if (char === "\r" && next === "\n") {
            row.push(field);
            rows.push(row);
            row = [];
            field = "";
            i++;
        } else if (char === "\n" || char === "\r") {
            row.push(field);
            rows.push(row);
            row = [];
            field = "";
        } else {
            field += char;
        }
    }

    if (field.length > 0 || row.length > 0) {
        row.push(field);
        rows.push(row);
    }

    return rows;
}

export function parseContactImportCsv(text: string): { headers: string[]; rows: string[][] } {
    const cleaned = stripUtf8Bom(text);
    const allRows = parseCsvRows(cleaned);

    if (allRows.length < 2) {
        throw new Error("The spreadsheet has no data rows.");
    }

    const headerRow = allRows.shift() ?? [];
    const headers = headerRow.map((header) => header.trim());

    return { headers, rows: allRows };
}

function cellString(value: string | undefined): string | null {
    if (value == null) return null;
    const trimmed = value.trim();
    return trimmed === "" ? null : trimmed;
}

function isEmptyRow(row: string[]): boolean {
    return row.every((cell) => cellString(cell) == null);
}

function expandCountry(value: string): string {
    const code = value.trim().toUpperCase();
    switch (code) {
        case "SK":
            return "Slovensko";
        case "CZ":
            return "Česko";
        case "AT":
            return "Rakúsko";
        case "HU":
            return "Maďarsko";
        case "PL":
            return "Poľsko";
        case "DE":
            return "Nemecko";
        case "US":
        case "USA":
            return "US";
        default:
            return value;
    }
}

function isValidEmail(value: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

type ImportRowPayload = {
    name: string;
    registration_number: string | null;
    email: string | null;
    phone: string | null;
    fax: string | null;
    tax_id: string | null;
    vat_id: string | null;
    street: string | null;
    city: string | null;
    postal_code: string | null;
    country: string | null;
    iban: string | null;
    swift: string | null;
    delivery_street: string | null;
    delivery_postal_code: string | null;
    delivery_city: string | null;
    delivery_country: string | null;
    default_payment_terms_days: number;
    notes: string | null;
};

function rowToPayload(row: string[], mapping: ContactImportMapping): ImportRowPayload | null {
    const data: ImportRowPayload = {
        name: "",
        registration_number: null,
        email: null,
        phone: null,
        fax: null,
        tax_id: null,
        vat_id: null,
        street: null,
        city: null,
        postal_code: null,
        country: null,
        iban: null,
        swift: null,
        delivery_street: null,
        delivery_postal_code: null,
        delivery_city: null,
        delivery_country: null,
        default_payment_terms_days: 14,
        notes: null,
    };

    let postalName: string | null = null;
    let web: string | null = null;
    let nameValue: string | null = null;

    for (const field of CONTACT_IMPORT_FIELD_KEYS) {
        const index = mapping[field];
        if (index == null) continue;

        const value = cellString(row[index]);
        if (value == null) continue;

        if (field === "delivery_name") {
            postalName = value;
            continue;
        }

        if (field === "web") {
            web = value;
            continue;
        }

        if (field === "country" || field === "delivery_country") {
            data[field] = expandCountry(value);
            continue;
        }

        if (field === "default_payment_terms_days") {
            if (!/^-?\d+(\.\d+)?$/.test(value)) {
                throw new Error("Invalid payment terms (days).");
            }
            data.default_payment_terms_days = Number.parseInt(value, 10);
            continue;
        }

        if (field === "email") {
            if (!isValidEmail(value)) {
                throw new Error("Invalid e-mail address.");
            }
            data.email = value;
            continue;
        }

        if (field === "name") {
            nameValue = value;
            continue;
        }

        if (field in data) {
            (data as Record<string, string | number | null>)[field] = value;
        }
    }

    const name = (nameValue ?? "").trim();
    if (name === "") {
        return null;
    }

    let notes = (data.notes ?? "").trim();
    if (web) {
        notes = `${notes}\nWeb: ${web}`.trim();
    }
    if (postalName && !data.delivery_street) {
        notes = `${notes}\nPoštová adresa: ${postalName}`.trim();
    }

    data.notes = notes !== "" ? notes : null;
    data.name = name;

    return data;
}

function payloadToForm(payload: ImportRowPayload): ContactFormState {
    const form = emptyContactForm();
    form.name = payload.name;
    form.registration_number = payload.registration_number ?? "";
    form.email = payload.email ?? "";
    form.phone = payload.phone ?? "";
    form.fax = payload.fax ?? "";
    form.tax_id = payload.tax_id ?? "";
    form.vat_id = payload.vat_id ?? "";
    form.street = payload.street ?? "";
    form.city = payload.city ?? "";
    form.postal_code = payload.postal_code ?? "";
    form.country = payload.country ?? "";
    form.iban = payload.iban ?? "";
    form.swift = payload.swift ?? "";
    form.delivery_street = payload.delivery_street ?? "";
    form.delivery_postal_code = payload.delivery_postal_code ?? "";
    form.delivery_city = payload.delivery_city ?? "";
    form.delivery_country = payload.delivery_country ?? "";
    form.default_payment_terms_days = payload.default_payment_terms_days;
    form.notes = payload.notes ?? "";
    form.is_active = true;
    return form;
}

function validationErrorMessage(result: { ok: false; error?: unknown }): string {
    if (typeof result.error === "string" && result.error.trim() !== "") {
        return result.error;
    }
    return "Could not import contact.";
}

export function previewContactImportCsv(
    csvText: string,
    mapping?: ContactImportMapping,
): ContactImportPreviewResult {
    const parsed = parseContactImportCsv(csvText);
    const suggested = normalizeMapping(mapping ?? suggestMapping(parsed.headers));

    return {
        headers: parsed.headers,
        suggested_mapping: suggested,
        row_count: parsed.rows.length,
    };
}


export async function importContactsFromCsv(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    csvText: string,
    mapping: ContactImportMapping,
    onProgress?: (done: number, total: number) => void,
): Promise<ContactImportResult> {
    const parsed = parseContactImportCsv(csvText);
    const normalizedMapping = normalizeMapping(mapping);

    let imported = 0;
    let skipped = 0;
    const errors: ContactImportRowError[] = [];
    let rowNumber = 1;
    let processed = 0;

    for (const row of parsed.rows) {
        rowNumber++;
        processed++;
        if (processed % IMPORT_YIELD_EVERY_ROWS === 0) {
            onProgress?.(processed, parsed.rows.length);
            await yieldToEventLoop();
        }

        if (imported + skipped >= MAX_ROWS) {
            errors.push({
                row: rowNumber,
                message: `Row limit exceeded (${MAX_ROWS}).`,
            });
            break;
        }

        if (isEmptyRow(row)) {
            continue;
        }

        try {
            const payload = rowToPayload(row, normalizedMapping);
            if (!payload) {
                skipped++;
                errors.push({ row: rowNumber, message: "Client name is required." });
                continue;
            }

            const form = payloadToForm(payload);
            const includeDelivery = Boolean(
                form.delivery_street.trim()
                || form.delivery_postal_code.trim()
                || form.delivery_city.trim()
                || form.delivery_country.trim(),
            );

            const result = insertLocalContactFromForm(evolu, companyId, form, includeDelivery);
            if (!result.ok) {
                skipped++;
                errors.push({ row: rowNumber, message: validationErrorMessage(result) });
                continue;
            }

            imported++;
        } catch (error) {
            skipped++;
            errors.push({
                row: rowNumber,
                message: error instanceof Error ? error.message : "Import failed.",
            });
        }
    }

    onProgress?.(processed, parsed.rows.length);

    return { imported, skipped, errors };
}

function escapeCsvCell(value: string): string {
    return `"${value.replace(/"/g, '""')}"`;
}

export function buildContactImportExampleCsvBlob(): Blob {
    const exampleRows = [
        [
            "Vzorový klient s.r.o.",
            "Kvetná 1",
            "123 45",
            "Bratislava",
            "SK",
            "12345678",
            "2123456789",
            "SK2123456789",
            "vzory@superfaktura.sk",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "14",
            "",
            "EUR",
            "",
            "",
        ],
        [
            "Company Example",
            "Street 123",
            "08001",
            "Brno",
            "CZ",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "30",
            "",
            "EUR",
            "",
            "",
        ],
    ];

    const lines = [
        CONTACT_IMPORT_EXAMPLE_HEADERS.map(escapeCsvCell).join(","),
        ...exampleRows.map((row) => row.map(escapeCsvCell).join(",")),
    ];

    const bom = "\uFEFF";
    return new Blob([bom + lines.join("\r\n")], { type: "text/csv;charset=utf-8" });
}
