import { booleanToSqliteBoolean, maxLength, NonEmptyString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import { emptyContactForm, type ContactFormState } from "@/composables/useCompanyContact";
import {
    DOCUMENT_IMPORT_FIELD_KEYS,
    REQUIRED_DOCUMENT_IMPORT_FIELDS,
    type DocumentImportFieldKey,
    type DocumentImportMapping,
} from "@/composables/useDocumentImportFields";
import { useCompanyVatPolicy, type VatPolicyCompany, type VatPolicyContact } from "@/composables/useCompanyVatPolicy";
import {
    allContactsQuery,
    allDocumentsQuery,
    allNumberSeriesQuery,
} from "./client";
import { insertLocalContactFromForm, updateLocalContactFromForm } from "./contactCrud";
import { evoluContactToApi, type EvoluContactRow } from "./contactMap";
import {
    calcDocumentTotals,
    type DocumentLinePayload,
} from "./documentCrud";
import { downloadCsvBlob } from "./documentBulkLocal";
import { logDocumentEvent } from "./documentEventLog";
import { variableSymbolFromNumber } from "./documentNumber";
import type { EvoluDocumentRow } from "./documentMap";
import { syncNumberSeriesCounterFromDocuments } from "./numberSeriesCrud";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import type { CompanyId, ContactId, DocumentId, InvoicingLocalSchema } from "./schema";
import { stripUtf8Bom } from "./contactImportLocal";

export { downloadCsvBlob };

const MAX_ROWS = 2000;

const LineNameType = maxLength(255)(NonEmptyString);
const TitleType = maxLength(1000)(NonEmptyString);
const CurrencyType = maxLength(3)(NonEmptyString);

/** SuperFaktura-compatible example headers (matches server example). */
export const DOCUMENT_IMPORT_EXAMPLE_HEADERS = [
    "Č. faktúry",
    "Variabilný symbol",
    "Vytvorené",
    "Dátum splatnosti",
    "IČO klienta",
    "Názov / Meno",
    "Adresa klienta",
    "Mesto klienta",
    "PSČ",
    "Krajina klienta (kód ISO)",
    "E-mail",
    "Suma",
    "Fakturačná mena (kód ISO)",
    "Dátum úhrady",
] as const;

export type DocumentImportPreviewRow = {
    row: number;
    invoice_number?: string;
    client_name?: string;
    issue_date?: string;
    due_date?: string;
    amount?: number;
    currency?: string;
    paid?: boolean;
    error?: string;
};

export type DocumentImportPreviewResult = {
    headers: string[];
    suggested_mapping: DocumentImportMapping;
    row_count: number;
    preview?: DocumentImportPreviewRow[];
};

export type DocumentImportRowError = {
    row: number;
    invoice_number?: string | null;
    message: string;
};

export type DocumentImportResult = {
    imported: number;
    skipped: number;
    contacts_created: number;
    contacts_linked: number;
    errors: DocumentImportRowError[];
};

export type DocumentImportOptions = {
    lineName: string;
    lineDescription: string;
    createContacts: boolean;
    defaultCurrency: string;
    noteFooter: string | null;
    company: VatPolicyCompany;
};

function vatOptionsForContact(company: VatPolicyCompany, contact: VatPolicyContact | null) {
    const vatPolicy = useCompanyVatPolicy();
    const defaultVat = vatPolicy.defaultTaxRate(company, contact ?? undefined);
    return {
        defaultVat,
        lineTaxApplies: (_line: DocumentLinePayload) =>
            vatPolicy.calculatesVatAmounts(company, contact),
        lineTaxRate: (line: DocumentLinePayload) =>
            vatPolicy.resolveLineTaxRate(company, contact, line.tax_rate ?? defaultVat),
    };
}

const HEADER_ALIASES: Record<DocumentImportFieldKey, string[]> = {
    invoice_number: ["c. faktury", "cislo faktury", "cislo dokladu", "invoice number", "number", "cislo"],
    variable_symbol: ["variabilny symbol", "variable symbol", "vs"],
    constant_symbol: ["konstantny symbol", "constant symbol", "ks"],
    specific_symbol: ["specificky symbol", "specific symbol", "ss"],
    issue_date: ["vytvorene", "datum vystavenia", "issue date", "created", "datum vytvorenia"],
    delivery_date: ["datum dodania", "delivery date"],
    due_date: ["datum splatnosti", "due date", "splatnost"],
    client_registration_number: ["ico klienta", "ico", "registration number", "ic"],
    client_tax_id: ["dic klienta", "dic", "tax id"],
    client_vat_id: ["ic dph klienta", "ic dph", "vat id", "dph"],
    client_name: ["nazov / meno", "nazov klienta", "meno", "client name", "name", "odberatel"],
    client_street: ["adresa klienta", "ulica", "street", "address"],
    client_city: ["mesto klienta", "mesto", "city"],
    client_postal_code: ["psc", "postal code", "zip"],
    client_country: ["krajina klienta (kod iso)", "krajina klienta", "krajina", "country"],
    client_phone: ["telefon", "phone"],
    client_email: ["email", "e-mail"],
    amount: ["suma", "amount", "total", "cena"],
    currency: ["fakturacna mena (kod iso)", "mena", "currency"],
    paid_at: ["datum uhrady", "paid at", "payment date"],
    payment_method: ["forma uhrady", "payment method"],
};

export function normalizeHeader(header: string): string {
    return header
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/\s+/g, " ");
}

function buildAliasLookup(): Map<string, DocumentImportFieldKey> {
    const lookup = new Map<string, DocumentImportFieldKey>();
    for (const [field, aliases] of Object.entries(HEADER_ALIASES) as [DocumentImportFieldKey, string[]][]) {
        for (const alias of aliases) {
            lookup.set(normalizeHeader(alias), field);
        }
    }
    return lookup;
}

const ALIAS_LOOKUP = buildAliasLookup();

export function suggestDocumentMapping(headers: string[]): DocumentImportMapping {
    const mapping: DocumentImportMapping = {};
    for (const field of DOCUMENT_IMPORT_FIELD_KEYS) {
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

export function normalizeDocumentMapping(mapping: DocumentImportMapping): DocumentImportMapping {
    const normalized: DocumentImportMapping = {};
    for (const field of DOCUMENT_IMPORT_FIELD_KEYS) {
        const value = mapping[field];
        normalized[field] = value === "" || value == null ? null : Number(value);
    }

    for (const field of REQUIRED_DOCUMENT_IMPORT_FIELDS) {
        if (normalized[field] == null) {
            throw new Error(`Missing required column mapping: ${field}`);
        }
    }

    return normalized;
}

export function isDocumentImportCsvFile(file: File): boolean {
    const name = file.name.toLowerCase();
    return name.endsWith(".csv") || file.type === "text/csv";
}

export async function readDocumentImportCsvFile(file: File): Promise<string> {
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

export function parseDocumentImportCsv(text: string): { headers: string[]; rows: string[][] } {
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

function parseDate(value: string | null): string | null {
    if (value == null || value === "") return null;

    if (/^\d+(\.\d+)?$/.test(value)) {
        const serial = Number.parseInt(value, 10);
        const utcMs = (serial - 25569) * 86400 * 1000;
        return new Date(utcMs).toISOString().slice(0, 10);
    }

    const parsed = Date.parse(value);
    if (Number.isNaN(parsed)) return null;
    return new Date(parsed).toISOString().slice(0, 10);
}

function parseAmount(value: string | null): number | null {
    if (value == null || value === "") return null;

    let normalized = value.replace(/[\s\u00a0]/g, "");
    normalized = normalized.replace(",", ".");
    normalized = normalized.replace(/[^0-9.-]/g, "");

    if (normalized === "" || !/^-?\d+(\.\d+)?$/.test(normalized)) {
        return null;
    }

    return Math.round(Number.parseFloat(normalized) * 100) / 100;
}

type ParsedInvoiceRow = {
    invoice_number: string;
    variable_symbol: string;
    constant_symbol: string | null;
    specific_symbol: string | null;
    client_name: string;
    issue_date: string;
    delivery_date: string | null;
    due_date: string;
    amount: number;
    currency: string;
    paid: boolean;
    paid_at: string | null;
    contact_attributes: ContactAttributes;
};

type ContactAttributes = {
    name: string;
    registration_number: string | null;
    tax_id: string | null;
    vat_id: string | null;
    street: string | null;
    city: string | null;
    postal_code: string | null;
    country: string | null;
    phone: string | null;
    email: string | null;
};

function cell(row: string[], mapping: DocumentImportMapping, field: DocumentImportFieldKey): string | null {
    const index = mapping[field];
    if (index == null) return null;
    return cellString(row[index]);
}

function contactAttributesFromRow(row: string[], mapping: DocumentImportMapping, name: string): ContactAttributes {
    const countryRaw = cell(row, mapping, "client_country");
    return {
        name,
        registration_number: cell(row, mapping, "client_registration_number"),
        tax_id: cell(row, mapping, "client_tax_id"),
        vat_id: cell(row, mapping, "client_vat_id"),
        street: cell(row, mapping, "client_street"),
        city: cell(row, mapping, "client_city"),
        postal_code: cell(row, mapping, "client_postal_code"),
        country: countryRaw ? expandCountry(countryRaw) : null,
        phone: cell(row, mapping, "client_phone"),
        email: cell(row, mapping, "client_email"),
    };
}

function rowToInvoiceData(
    row: string[],
    mapping: DocumentImportMapping,
    defaultCurrency: string,
    existingNumbers: Set<string>,
    validateOnly: boolean,
): ParsedInvoiceRow {
    const invoiceNumber = cell(row, mapping, "invoice_number");
    const clientName = cell(row, mapping, "client_name");
    const issueDate = parseDate(cell(row, mapping, "issue_date"));
    const dueDate = parseDate(cell(row, mapping, "due_date"));
    const amount = parseAmount(cell(row, mapping, "amount"));

    if (invoiceNumber == null || invoiceNumber === "") {
        throw new Error("Invoice number is required.");
    }
    if (clientName == null || clientName.trim() === "") {
        throw new Error("Client name is required.");
    }
    if (!issueDate) {
        throw new Error("Issue date is invalid or missing.");
    }
    if (!dueDate) {
        throw new Error("Due date is invalid or missing.");
    }
    if (amount == null || amount <= 0) {
        throw new Error("Amount is invalid or missing.");
    }

    if (!validateOnly && existingNumbers.has(invoiceNumber)) {
        throw new Error(`Invoice number already exists: ${invoiceNumber}`);
    }

    const paidAt = parseDate(cell(row, mapping, "paid_at"));
    const variableSymbol =
        cell(row, mapping, "variable_symbol") ?? variableSymbolFromNumber(invoiceNumber);

    return {
        invoice_number: invoiceNumber,
        variable_symbol: variableSymbol,
        constant_symbol: cell(row, mapping, "constant_symbol"),
        specific_symbol: cell(row, mapping, "specific_symbol"),
        client_name: clientName.trim(),
        issue_date: issueDate,
        delivery_date: parseDate(cell(row, mapping, "delivery_date")),
        due_date: dueDate,
        amount,
        currency: (cell(row, mapping, "currency") ?? defaultCurrency).toUpperCase(),
        paid: paidAt != null,
        paid_at: paidAt,
        contact_attributes: contactAttributesFromRow(row, mapping, clientName.trim()),
    };
}

function contactFormFromRow(row: EvoluContactRow): ContactFormState {
    const api = evoluContactToApi(row);
    const form = emptyContactForm();
    form.name = api.name;
    form.registration_number = api.registration_number ?? "";
    form.peppol_participant_id = api.peppol_participant_id ?? "";
    form.email = api.email ?? "";
    form.phone = api.phone ?? "";
    form.fax = api.fax ?? "";
    form.tax_id = api.tax_id ?? "";
    form.vat_id = api.vat_id ?? "";
    form.street = api.street ?? "";
    form.city = api.city ?? "";
    form.postal_code = api.postal_code ?? "";
    form.state_region = api.state_region ?? "";
    form.country = api.country ?? "";
    form.bank_account = api.bank_account ?? "";
    form.bank_code = api.bank_code ?? "";
    form.iban = api.iban ?? "";
    form.swift = api.swift ?? "";
    form.delivery_street = api.delivery_street ?? "";
    form.delivery_postal_code = api.delivery_postal_code ?? "";
    form.delivery_city = api.delivery_city ?? "";
    form.delivery_country = api.delivery_country ?? "";
    form.default_payment_terms_days = api.default_payment_terms_days ?? 14;
    form.notes = api.notes ?? "";
    form.is_active = api.is_active !== false;
    form.contact_persons = api.contact_persons ?? [{ name: "", phone: "", email: "" }];
    return form;
}

function contactFormFromAttributes(attrs: ContactAttributes): ContactFormState {
    const form = emptyContactForm();
    form.name = attrs.name;
    form.registration_number = attrs.registration_number ?? "";
    form.tax_id = attrs.tax_id ?? "";
    form.vat_id = attrs.vat_id ?? "";
    form.street = attrs.street ?? "";
    form.city = attrs.city ?? "";
    form.postal_code = attrs.postal_code ?? "";
    form.country = attrs.country ?? "";
    form.phone = attrs.phone ?? "";
    form.email = attrs.email ?? "";
    form.is_active = true;
    return form;
}

function fillMissingContactFields(
    form: ContactFormState,
    attrs: ContactAttributes,
): ContactFormState {
    const next = { ...form };
    if (!next.registration_number.trim() && attrs.registration_number) {
        next.registration_number = attrs.registration_number;
    }
    if (!next.tax_id.trim() && attrs.tax_id) next.tax_id = attrs.tax_id;
    if (!next.vat_id.trim() && attrs.vat_id) next.vat_id = attrs.vat_id;
    if (!next.street.trim() && attrs.street) next.street = attrs.street;
    if (!next.city.trim() && attrs.city) next.city = attrs.city;
    if (!next.postal_code.trim() && attrs.postal_code) next.postal_code = attrs.postal_code;
    if (!next.country.trim() && attrs.country) next.country = attrs.country;
    if (!next.phone.trim() && attrs.phone) next.phone = attrs.phone;
    if (!next.email.trim() && attrs.email) next.email = attrs.email;
    return next;
}

function findLocalContact(
    contacts: EvoluContactRow[],
    companyId: CompanyId,
    name: string,
    ico: string | null,
): EvoluContactRow | null {
    const companyContacts = contacts.filter((c) => c.companyId === companyId);
    if (ico) {
        const byIco = companyContacts.find((c) => c.registrationNumber === ico);
        if (byIco) return byIco;
    }
    const normalizedName = name.trim().toLowerCase();
    return companyContacts.find((c) => c.name.trim().toLowerCase() === normalizedName) ?? null;
}

type ResolveContactResult = {
    contactId: ContactId | null;
    created: boolean;
    linked: boolean;
};

function resolveLocalContact(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    contacts: EvoluContactRow[],
    attrs: ContactAttributes,
    createContacts: boolean,
): ResolveContactResult {
    const existing = findLocalContact(
        contacts,
        companyId,
        attrs.name,
        attrs.registration_number,
    );

    if (existing) {
        const merged = fillMissingContactFields(contactFormFromRow(existing), attrs);
        const includeDelivery = Boolean(
            merged.delivery_street.trim()
            || merged.delivery_postal_code.trim()
            || merged.delivery_city.trim()
            || merged.delivery_country.trim(),
        );
        updateLocalContactFromForm(evolu, existing.id, merged, includeDelivery);
        return { contactId: existing.id, created: false, linked: true };
    }

    if (!createContacts) {
        return { contactId: null, created: false, linked: false };
    }

    const form = contactFormFromAttributes(attrs);
    const result = insertLocalContactFromForm(evolu, companyId, form, false);
    if (!result.ok) {
        throw new Error(typeof result.error === "string" ? result.error : "Could not create contact.");
    }

    return { contactId: result.value.id, created: true, linked: false };
}

export function importLocalHistoricalDocument(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    invoice: ParsedInvoiceRow,
    contactId: ContactId | null,
    options: Pick<
        DocumentImportOptions,
        "lineName" | "lineDescription" | "defaultVat" | "lineTaxApplies" | "lineTaxRate" | "noteFooter"
    >,
): { ok: true; value: { id: DocumentId } } | { ok: false; error: string } {
    const linePayload: DocumentLinePayload = {
        name: options.lineName.trim() || "Imported item",
        description: options.lineDescription.trim() || null,
        quantity: 1,
        unit: "ks.",
        unit_price: invoice.amount,
        line_discount_percent: 0,
        tax_rate: 0,
        company_stock_item_id: null,
        company_warehouse_id: null,
    };

    linePayload.tax_rate = options.lineTaxRate(linePayload);

    const totals = calcDocumentTotals(
        [linePayload],
        0,
        options.defaultVat,
        options.lineTaxApplies,
        options.lineTaxRate,
    );

    const title = TitleType.from(`Faktúra ${invoice.invoice_number}`);
    if (!title.ok) return title;
    const currency = CurrencyType.from(invoice.currency || "EUR");
    if (!currency.ok) return currency;
    const lineName = LineNameType.from(linePayload.name);
    if (!lineName.ok) return lineName;

    const status = invoice.paid ? "paid" : "issued";
    const paidAtIso = invoice.paid
        ? new Date(`${invoice.paid_at ?? invoice.issue_date}T12:00:00`).toISOString()
        : null;

    const docResult = evolu.insert("document", {
        companyId,
        contactId,
        documentType: "invoice",
        status,
        quoteStatus: null,
        title: title.value,
        number: invoice.invoice_number,
        sourceDocumentId: null,
        issueDate: invoice.issue_date,
        deliveryDate: invoice.delivery_date,
        dueDate: invoice.due_date,
        variableSymbol: invoice.variable_symbol || null,
        constantSymbol: invoice.constant_symbol,
        specificSymbol: invoice.specific_symbol,
        currency: currency.value,
        subtotal: totals.subtotal,
        taxTotal: totals.taxTotal,
        discountPercent: "0.00",
        total: totals.total,
        noteAboveLines: null,
        noteFooter: options.noteFooter,
        internalNote: null,
        pdfLocale: "sk",
        pdfShowSignature: booleanToSqliteBoolean(true),
        pdfShowPaymentInfo: booleanToSqliteBoolean(true),
        paymentBankEnabled: booleanToSqliteBoolean(false),
        paymentBtcEnabled: booleanToSqliteBoolean(false),
        storeId: null,
        tagsJson: JSON.stringify(["import"]),
        paidAt: paidAtIso,
        amountPaid: invoice.paid ? totals.total : null,
    });

    if (!docResult.ok) {
        return { ok: false, error: "Could not import invoice." };
    }

    const docId = docResult.value.id;
    const lineResult = evolu.insert("documentLine", {
        documentId: docId,
        sortOrder: "0",
        name: lineName.value,
        description: linePayload.description,
        quantity: "1.0000",
        unit: linePayload.unit,
        unitPrice: linePayload.unit_price.toFixed(4),
        lineDiscountPercent: "0.00",
        taxRate: linePayload.tax_rate.toFixed(2),
        lineTotal: totals.lineTotals[0] || totals.total,
        companyStockItemId: null,
        companyWarehouseId: null,
    });

    if (!lineResult.ok) {
        return { ok: false, error: "Could not import invoice line." };
    }

    logDocumentEvent(evolu, docId, "business_document.imported", {
        number: invoice.invoice_number,
    });

    return { ok: true, value: { id: docId } };
}

function existingInvoiceNumbers(
    documents: EvoluDocumentRow[],
    companyId: CompanyId,
): Set<string> {
    const numbers = new Set<string>();
    for (const doc of documents) {
        if (doc.companyId !== companyId || doc.documentType !== "invoice" || !doc.number) {
            continue;
        }
        numbers.add(doc.number);
    }
    return numbers;
}

export function previewDocumentImportCsv(
    csvText: string,
    mapping?: DocumentImportMapping,
    options?: { defaultCurrency?: string; includePreview?: boolean },
): DocumentImportPreviewResult {
    const parsed = parseDocumentImportCsv(csvText);
    const suggested = normalizeDocumentMapping(mapping ?? suggestDocumentMapping(parsed.headers));
    const defaultCurrency = options?.defaultCurrency ?? "EUR";

    const result: DocumentImportPreviewResult = {
        headers: parsed.headers,
        suggested_mapping: suggested,
        row_count: parsed.rows.length,
    };

    if (options?.includePreview) {
        const preview: DocumentImportPreviewRow[] = [];
        for (const [index, row] of parsed.rows.slice(0, 10).entries()) {
            const rowNumber = index + 2;
            if (isEmptyRow(row)) continue;
            try {
                const data = rowToInvoiceData(row, suggested, defaultCurrency, new Set(), true);
                preview.push({
                    row: rowNumber,
                    invoice_number: data.invoice_number,
                    client_name: data.client_name,
                    issue_date: data.issue_date,
                    due_date: data.due_date,
                    amount: data.amount,
                    currency: data.currency,
                    paid: data.paid,
                });
            } catch (error) {
                preview.push({
                    row: rowNumber,
                    error: error instanceof Error ? error.message : "Import failed.",
                });
            }
        }
        result.preview = preview;
    }

    return result;
}

export function importDocumentImportCsv(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    rows: string[][],
    mapping: DocumentImportMapping,
    options: DocumentImportOptions,
): DocumentImportResult {
    const normalizedMapping = normalizeDocumentMapping(mapping);
    const documents = evolu.getQueryRows(allDocumentsQuery) as EvoluDocumentRow[];
    let contacts = evolu.getQueryRows(allContactsQuery) as EvoluContactRow[];
    const series = evolu.getQueryRows(allNumberSeriesQuery) as EvoluNumberSeriesRow[];

    const knownNumbers = existingInvoiceNumbers(documents, companyId);
    let imported = 0;
    let skipped = 0;
    let contactsCreated = 0;
    let contactsLinked = 0;
    const errors: DocumentImportRowError[] = [];
    let rowNumber = 1;
    const workingDocuments = [...documents];

    for (const row of rows) {
        rowNumber++;

        if (imported + skipped >= MAX_ROWS) {
            errors.push({
                row: rowNumber,
                invoice_number: cell(row, normalizedMapping, "invoice_number"),
                message: `Row limit exceeded (${MAX_ROWS}).`,
            });
            break;
        }

        if (isEmptyRow(row)) {
            continue;
        }

        const invoiceNumber = cell(row, normalizedMapping, "invoice_number");

        try {
            const invoice = rowToInvoiceData(
                row,
                normalizedMapping,
                options.defaultCurrency,
                knownNumbers,
                false,
            );

            const contactResult = resolveLocalContact(
                evolu,
                companyId,
                contacts,
                invoice.contact_attributes,
                options.createContacts,
            );

            if (contactResult.created) contactsCreated++;
            else if (contactResult.linked) contactsLinked++;

            if (contactResult.created && contactResult.contactId) {
                contacts = [
                    ...contacts,
                    {
                        id: contactResult.contactId,
                        companyId,
                        name: invoice.contact_attributes.name,
                        registrationNumber: invoice.contact_attributes.registration_number,
                        peppolParticipantId: null,
                        email: invoice.contact_attributes.email,
                        phone: invoice.contact_attributes.phone,
                        fax: null,
                        taxId: invoice.contact_attributes.tax_id,
                        vatId: invoice.contact_attributes.vat_id,
                        street: invoice.contact_attributes.street,
                        city: invoice.contact_attributes.city,
                        postalCode: invoice.contact_attributes.postal_code,
                        stateRegion: null,
                        country: invoice.contact_attributes.country,
                        bankAccount: null,
                        bankCode: null,
                        iban: null,
                        swift: null,
                        deliveryStreet: null,
                        deliveryPostalCode: null,
                        deliveryCity: null,
                        deliveryCountry: null,
                        defaultPaymentTermsDays: null,
                        notes: null,
                        contactPersonsJson: null,
                        isActive: 1,
                    },
                ];
            }

            const vatContact: VatPolicyContact = invoice.contact_attributes.country
                ? { country: invoice.contact_attributes.country }
                : null;
            const vat = vatOptionsForContact(options.company, vatContact);

            const importResult = importLocalHistoricalDocument(
                evolu,
                companyId,
                invoice,
                contactResult.contactId,
                {
                    lineName: options.lineName,
                    lineDescription: options.lineDescription,
                    defaultVat: vat.defaultVat,
                    lineTaxApplies: vat.lineTaxApplies,
                    lineTaxRate: vat.lineTaxRate,
                    noteFooter: options.noteFooter,
                },
            );

            if (!importResult.ok) {
                skipped++;
                errors.push({
                    row: rowNumber,
                    invoice_number: invoiceNumber,
                    message: typeof importResult.error === "string"
                        ? importResult.error
                        : "Import failed.",
                });
                continue;
            }

            knownNumbers.add(invoice.invoice_number);
            workingDocuments.push({
                id: importResult.value.id,
                companyId,
                contactId: contactResult.contactId,
                documentType: "invoice",
                status: invoice.paid ? "paid" : "issued",
                quoteStatus: null,
                title: `Faktúra ${invoice.invoice_number}`,
                number: invoice.invoice_number,
                sourceDocumentId: null,
                issueDate: invoice.issue_date,
                deliveryDate: invoice.delivery_date,
                dueDate: invoice.due_date,
                variableSymbol: invoice.variable_symbol,
                constantSymbol: invoice.constant_symbol,
                specificSymbol: invoice.specific_symbol,
                currency: invoice.currency,
                subtotal: null,
                taxTotal: null,
                discountPercent: null,
                total: null,
                noteAboveLines: null,
                noteFooter: options.noteFooter,
                internalNote: null,
                pdfLocale: "sk",
                pdfShowSignature: 1,
                pdfShowPaymentInfo: 1,
                paymentBankEnabled: 0,
                paymentBtcEnabled: 0,
                storeId: null,
                tagsJson: JSON.stringify(["import"]),
                paidAt: invoice.paid ? invoice.paid_at : null,
                amountPaid: null,
                emailSentAt: null,
            });

            imported++;
        } catch (error) {
            skipped++;
            errors.push({
                row: rowNumber,
                invoice_number: invoiceNumber,
                message: error instanceof Error ? error.message : "Import failed.",
            });
        }
    }

    if (imported > 0) {
        syncNumberSeriesCounterFromDocuments(
            evolu,
            companyId,
            "invoice",
            workingDocuments,
            series,
        );
    }

    return {
        imported,
        skipped,
        contacts_created: contactsCreated,
        contacts_linked: contactsLinked,
        errors,
    };
}


function escapeCsvCell(value: string): string {
    return `"${value.replace(/"/g, '""')}"`;
}

export function buildDocumentImportExampleCsvBlob(): Blob {
    const exampleRows = [
        [
            "20240001",
            "20240001",
            "2024-01-15",
            "2024-01-29",
            "12345678",
            "Vzorový klient s.r.o.",
            "Hlavná 1",
            "Bratislava",
            "81101",
            "SK",
            "klient@example.sk",
            "120.00",
            "EUR",
            "",
        ],
        [
            "20240002",
            "20240002",
            "2024-02-01",
            "2024-02-15",
            "",
            "John Doe",
            "Street 5",
            "Prague",
            "11000",
            "CZ",
            "",
            "250.50",
            "EUR",
            "2024-02-10",
        ],
    ];

    const lines = [
        DOCUMENT_IMPORT_EXAMPLE_HEADERS.map(escapeCsvCell).join(","),
        ...exampleRows.map((row) => row.map(escapeCsvCell).join(",")),
    ];

    const bom = "\uFEFF";
    return new Blob([bom + lines.join("\r\n")], { type: "text/csv;charset=utf-8" });
}
