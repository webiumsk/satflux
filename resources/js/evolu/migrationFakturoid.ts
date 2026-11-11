import type { MigrationInvoice, MigrationPreviewRow, MigrationState } from "./migrationValidation";
import { migrationDecimal } from "./migrationValidation";
import type { MigrationStructuredInvoice, MigrationStructuredLine } from "./migrationIsdoc";
import { MIGRATION_MAX_FILE_BYTES } from "./migrationArchiveValues";

export type FakturoidMigrationErrorCode = "fakturoid_invalid_export" | "fakturoid_limit_exceeded"
    | "fakturoid_invalid_invoice" | "fakturoid_totals" | "fakturoid_payment_evidence"
    | "fakturoid_unsupported" | "fakturoid_company_mismatch" | "fakturoid_unsupported_currency";

/** Controlled codes only: never include source values, notes, tokens or XML errors. */
export class FakturoidMigrationError extends Error {
    constructor(readonly code: FakturoidMigrationErrorCode) {
        super(code);
        this.name = "FakturoidMigrationError";
    }
}

export type FakturoidMigrationResult = {
    rows: MigrationPreviewRow[];
    invoiceCount: number;
    issuerRegistrationNumber: string;
};

const MAX_INVOICES = 2000;
const MAX_LINES = 500;
const MAX_NODES = 200_000;
const MAX_MINOR = 99_999_999_999_999n;
const RAW_NET_PER_CENT = 1_000_000n;
const RAW_TAX_PER_CENT = 10_000_000_000n;
const TWO_DECIMAL_CURRENCIES = new Set([
    "AUD", "CAD", "CHF", "CNY", "CZK", "DKK", "EUR", "GBP", "HKD", "HUF",
    "ILS", "INR", "MXN", "NOK", "NZD", "PLN", "SEK", "SGD", "USD", "ZAR",
]);
// This is the verified native XML export, not the separately versioned API JSON.
const INVOICE_FIELDS = new Set([
    "id", "proforma", "partial_proforma", "tax_document", "number", "variable_symbol",
    "your_name", "your_street", "your_street2", "your_city", "your_zip", "your_country",
    "your_registration_no", "your_vat_no", "client_name", "client_street", "client_street2",
    "client_city", "client_zip", "client_country", "client_registration_no", "client_vat_no",
    "client_local_vat_no", "subject_custom_id", "subject_id", "generator_id", "related_id",
    "correction", "correction_id", "token", "status", "order_number", "issued_on",
    "taxable_fulfillment_due", "due", "due_on", "sent_at", "paid_on", "reminder_sent_at",
    "accepted_at", "cancelled_at", "note", "footer_note", "private_note", "tags", "bank_account",
    "iban", "swift_bic", "payment_method", "payment_method_human", "currency", "exchange_rate",
    "language", "transferred_tax_liability", "eu_electronic_service", "vat_price_mode", "supply_code",
    "subtotal", "total", "rounding_adjustment", "native_subtotal", "native_total", "remaining_amount",
    "remaining_native_amount", "paid_amount", "lines", "created_at", "updated_at",
]);
const LINE_FIELDS = new Set([
    "id", "name", "description", "quantity", "unit_name", "unit_price", "vat_rate",
    "unit_price_without_vat", "unit_price_with_vat",
]);
type Fields = Map<string, Element>;

function fail(code: FakturoidMigrationErrorCode): never { throw new FakturoidMigrationError(code); }

function fields(element: Element): Fields {
    const result: Fields = new Map();
    for (const child of Array.from(element.children)) {
        if (result.has(child.localName)) fail("fakturoid_invalid_export");
        result.set(child.localName, child);
    }
    return result;
}

function scalar(source: Fields, name: string, max = 255, required = true): string | null {
    const element = source.get(name);
    if (!element) {
        if (required) fail("fakturoid_invalid_invoice");
        return null;
    }
    const value = element.textContent?.trim() ?? "";
    if (element.childElementCount || value.length > max
        || Array.from(value).some(char => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127)) fail("fakturoid_invalid_invoice");
    if (!value && required) fail("fakturoid_invalid_invoice");
    return value || null;
}

function required(source: Fields, name: string, max = 255): string { return scalar(source, name, max)!; }

function identifier(source: Fields, name: string): string {
    const element = source.get(name);
    const value = element?.textContent?.trim() ?? "";
    if (element?.childElementCount || !/^[1-9]\d{0,19}$/.test(value)) fail("fakturoid_invalid_export");
    return value;
}

/** Only eight-digit registration numbers have a known whitespace grouping convention. */
function registration(value: string | null): string {
    const trimmed = value?.trim() ?? "";
    const digits = trimmed.replace(/\s/g, "");
    return /^\d{8}$/.test(digits) ? digits : trimmed;
}

function decimal(value: string, scale: number, code: FakturoidMigrationErrorCode): bigint {
    const match = /^(-?)(\d{1,12})(?:\.(\d{1,16}))?$/.exec(value);
    if (!match) fail(code);
    const fraction = (match[3] ?? "").replace(/0+$/, "");
    if (fraction.length > scale) fail(code);
    const absolute = BigInt(match[2]) * 10n ** BigInt(scale) + BigInt(fraction.padEnd(scale, "0") || "0");
    return match[1] ? -absolute : absolute;
}

function amount(source: Fields, name: string, code: FakturoidMigrationErrorCode = "fakturoid_totals"): bigint {
    return bounded(decimal(required(source, name, 32), 2, code), code);
}

function bounded(value: bigint, code: FakturoidMigrationErrorCode = "fakturoid_totals"): bigint {
    if (value < -MAX_MINOR || value > MAX_MINOR) fail(code);
    return value;
}

function round(numerator: bigint, denominator: bigint): bigint {
    const absolute = numerator < 0n ? -numerator : numerator;
    const result = (absolute + denominator / 2n) / denominator;
    return numerator < 0n ? -result : result;
}

function fixed(value: bigint, scale: number): string {
    const absolute = value < 0n ? -value : value;
    const divisor = 10n ** BigInt(scale);
    return `${value < 0n ? "-" : ""}${absolute / divisor}.${String(absolute % divisor).padStart(scale, "0")}`;
}

function day(value: string, code: FakturoidMigrationErrorCode = "fakturoid_invalid_invoice"): string {
    if (!/^[1-9]\d{3}-\d{2}-\d{2}$/.test(value)) fail(code);
    const date = new Date(value + "T00:00:00.000Z");
    if (!Number.isFinite(date.getTime()) || date.toISOString().slice(0, 10) !== value) fail(code);
    return value;
}

function checkSupported(source: Fields): void {
    if ([...source.keys()].some(name => !INVOICE_FIELDS.has(name))) fail("fakturoid_unsupported");
    for (const name of ["proforma", "partial_proforma", "tax_document", "correction", "transferred_tax_liability", "eu_electronic_service"]) {
        if (required(source, name, 5) !== "false") fail("fakturoid_unsupported");
    }
    if (["related_id", "correction_id", "supply_code"].some(name => scalar(source, name, 64, false))
        || required(source, "vat_price_mode", 32) !== "without_vat"
        || decimal(required(source, "exchange_rate", 32), 4, "fakturoid_unsupported") !== 10_000n
        || amount(source, "rounding_adjustment") !== 0n) fail("fakturoid_unsupported");
}

function readLines(source: Fields, subtotal: bigint, total: bigint): Pick<MigrationStructuredInvoice, "subtotal" | "taxTotal" | "lines"> {
    const section = source.get("lines");
    if (!section || !section.childElementCount) fail("fakturoid_invalid_invoice");
    if (section.childElementCount > MAX_LINES) fail("fakturoid_limit_exceeded");
    const ids = new Set<string>();
    let netSum = 0n, taxSum = 0n, grossSum = 0n, rawNetSum = 0n, rawTaxSum = 0n;
    const buckets = new Map<bigint, { net: bigint; tax: bigint; rawNet: bigint; rawTax: bigint }>();
    const lines: MigrationStructuredLine[] = Array.from(section.children).map(element => {
        if (element.localName !== "line") fail("fakturoid_invalid_export");
        const line = fields(element);
        if ([...line.keys()].some(name => !LINE_FIELDS.has(name))) fail("fakturoid_unsupported");
        const id = identifier(line, "id");
        if (ids.has(id)) fail("fakturoid_invalid_export");
        ids.add(id);
        const quantity = decimal(required(line, "quantity", 32), 4, "fakturoid_invalid_invoice");
        const price = decimal(required(line, "unit_price", 32), 4, "fakturoid_invalid_invoice");
        const netPrice = decimal(required(line, "unit_price_without_vat", 32), 4, "fakturoid_invalid_invoice");
        const grossPrice = decimal(required(line, "unit_price_with_vat", 32), 4, "fakturoid_invalid_invoice");
        const rate = decimal(required(line, "vat_rate", 16), 2, "fakturoid_unsupported");
        if (quantity <= 0n || quantity >= 100_000_000_000_000n || rate < 0n || rate > 10_000n) fail("fakturoid_unsupported");
        if (price !== netPrice || round(price * (10_000n + rate), 10_000n) !== grossPrice) fail("fakturoid_totals");
        const rawNet = quantity * price;
        const rawTax = rawNet * rate;
        const net = bounded(round(rawNet, RAW_NET_PER_CENT));
        const tax = bounded(round(rawTax, RAW_TAX_PER_CENT));
        const gross = bounded(net + tax);
        if (round(net * rate, 10_000n) !== tax
            || round(rawNet * 10_000n + rawTax, RAW_TAX_PER_CENT) !== gross) fail("fakturoid_totals");
        netSum = bounded(netSum + net); taxSum = bounded(taxSum + tax); grossSum = bounded(grossSum + gross);
        rawNetSum += rawNet; rawTaxSum += rawTax;
        const bucket = buckets.get(rate) ?? { net: 0n, tax: 0n, rawNet: 0n, rawTax: 0n };
        bucket.net += net; bucket.tax += tax; bucket.rawNet += rawNet; bucket.rawTax += rawTax;
        buckets.set(rate, bucket);
        return {
            name: required(line, "name"), description: scalar(line, "description", 4000, false),
            quantity: fixed(quantity, 4), unit: scalar(line, "unit_name", 32, false), unitPrice: fixed(price, 4),
            lineDiscountPercent: "0.00", taxRate: fixed(rate, 2), lineTotal: migrationDecimal(gross),
        };
    });
    if (subtotal <= 0n || total <= 0n || total < subtotal || netSum !== subtotal || grossSum !== total
        || taxSum !== total - subtotal || round(rawNetSum, RAW_NET_PER_CENT) !== subtotal
        || round(rawTaxSum, RAW_TAX_PER_CENT) !== taxSum
        || round(rawNetSum * 10_000n + rawTaxSum, RAW_TAX_PER_CENT) !== total
        || [...buckets.values()].some(bucket => bucket.net < 0n || bucket.tax < 0n
            || round(bucket.rawNet, RAW_NET_PER_CENT) !== bucket.net
            || round(bucket.rawTax, RAW_TAX_PER_CENT) !== bucket.tax
            || round(bucket.rawNet * 10_000n + bucket.rawTax, RAW_TAX_PER_CENT) !== bucket.net + bucket.tax)) fail("fakturoid_totals");
    return { subtotal: migrationDecimal(subtotal), taxTotal: migrationDecimal(taxSum), lines };
}

function payment(source: Fields, total: bigint): { state: MigrationState; paid: bigint; paidAt: string | null } {
    let paid: bigint;
    let rawDate: string | null;
    try {
        paid = amount(source, "paid_amount", "fakturoid_payment_evidence");
        rawDate = scalar(source, "paid_on", 10, false);
    } catch { return fail("fakturoid_payment_evidence"); }
    const status = required(source, "status", 32);
    const paidAt = rawDate ? day(rawDate, "fakturoid_payment_evidence") : null;
    if (paid < 0n || paid > total || (status !== "paid" && paidAt)) fail("fakturoid_payment_evidence");
    if (status === "cancelled") {
        if (paid !== 0n || paidAt) fail("fakturoid_payment_evidence");
        return { state: "cancelled", paid, paidAt };
    }
    if (status === "paid") {
        if (paid !== total || !paidAt) fail("fakturoid_payment_evidence");
        return { state: "paid", paid, paidAt };
    }
    if (!["open", "sent", "overdue"].includes(status)) fail("fakturoid_unsupported");
    if (paid >= total) fail("fakturoid_payment_evidence");
    return { state: paid === 0n ? "unpaid" : "partial", paid, paidAt };
}

function readInvoice(source: Fields, row: number, issuer: string, company: string): MigrationInvoice {
    if (!company || issuer !== company) fail("fakturoid_company_mismatch");
    checkSupported(source);
    const currency = required(source, "currency", 8);
    if (!TWO_DECIMAL_CURRENCIES.has(currency)) fail("fakturoid_unsupported_currency");
    const subtotal = amount(source, "subtotal"), total = amount(source, "total");
    // remaining_amount measures document-link coverage, never unpaid balance.
    if (amount(source, "native_subtotal") !== subtotal || amount(source, "native_total") !== total
        || amount(source, "remaining_amount") !== total || amount(source, "remaining_native_amount") !== total) fail("fakturoid_unsupported");
    const structured = readLines(source, subtotal, total);
    const { state, paid, paidAt } = payment(source, total);
    const number = required(source, "number", 64), name = required(source, "client_name");
    const issueDate = day(required(source, "issued_on", 10));
    const dueDate = day(required(source, "due_on", 10));
    const delivery = scalar(source, "taxable_fulfillment_due", 10, false);
    const street = [scalar(source, "client_street", 255, false), scalar(source, "client_street2", 255, false)].filter(Boolean).join("\n") || null;
    if (street && street.length > 255) fail("fakturoid_invalid_invoice");
    const buyerRegistration = scalar(source, "client_registration_no", 64, false);
    return {
        row, invoice_number: number, client_name: name, issue_date: issueDate, due_date: dueDate,
        delivery_date: delivery ? day(delivery) : null, currency, total: migrationDecimal(total),
        amount_paid: migrationDecimal(paid), balance: migrationDecimal(total - paid), paid_at: paidAt, state,
        variable_symbol: scalar(source, "variable_symbol", 32, false), constant_symbol: null, specific_symbol: null,
        contact_attributes: { name, registration_number: buyerRegistration,
            tax_id: scalar(source, "client_local_vat_no", 64, false), vat_id: scalar(source, "client_vat_no", 32, false),
            street, city: scalar(source, "client_city", 128, false), postal_code: scalar(source, "client_zip", 32, false),
            country: scalar(source, "client_country", 128, false), phone: null, email: null },
        structured: { ...structured, documentNumber: number, issueDate, currency, total: migrationDecimal(total),
            supplierRegistrationNumber: issuer, buyerRegistrationNumber: buyerRegistration, buyerName: name },
        sourceIdentity: { provider: "fakturoid", issuerRegistrationNumber: issuer, invoiceId: identifier(source, "id") },
    };
}

function readXml(bytes: Uint8Array): Element {
    if (!bytes.length) fail("fakturoid_invalid_export");
    if (bytes.length > MIGRATION_MAX_FILE_BYTES) fail("fakturoid_limit_exceeded");
    let xml: string;
    try { xml = new TextDecoder("utf-8", { fatal: true }).decode(bytes); }
    catch { return fail("fakturoid_invalid_export"); }
    const declaration = /^<\?xml\s+version=(["'])1\.0\1(?:\s+encoding=(["'])utf-8\2)?\s*\?>/i.exec(xml)?.[0] ?? "";
    const body = xml.slice(declaration.length);
    if (/<[!?]/.test(body)) fail("fakturoid_invalid_export");
    let tags = 0;
    for (const character of body) if (character === "<" && ++tags > MAX_NODES) fail("fakturoid_limit_exceeded");
    let document: Document;
    try { document = new DOMParser().parseFromString(xml, "application/xml"); }
    catch { return fail("fakturoid_invalid_export"); }
    if (document.getElementsByTagName("parsererror").length || !document.documentElement) fail("fakturoid_invalid_export");
    const root = document.documentElement;
    if (root.localName !== "invoices") fail("fakturoid_invalid_export");
    const stack: { node: Node; depth: number }[] = [{ node: root, depth: 1 }];
    let nodes = 0;
    while (stack.length) {
        const { node, depth } = stack.pop()!;
        if (++nodes > MAX_NODES || depth > 5) fail("fakturoid_limit_exceeded");
        if (node.nodeType === 1) {
            const element = node as Element;
            if (element.namespaceURI || element.attributes.length || element.prefix) fail("fakturoid_invalid_export");
            for (const child of Array.from(node.childNodes)) {
                if (child.nodeType === 1) stack.push({ node: child, depth: depth + 1 });
                else if (child.nodeType !== 3 || (element.childElementCount && child.textContent?.trim())) fail("fakturoid_invalid_export");
                else if (++nodes > MAX_NODES) fail("fakturoid_limit_exceeded");
            }
        }
    }
    return root;
}

/** Parse only the verified native-export subset, without IO or destination writes. */
export function parseFakturoidMigrationExport(bytes: Uint8Array, options: { companyRegistrationNumber: string | null }): FakturoidMigrationResult {
    const root = readXml(bytes);
    const invoices = Array.from(root.children);
    if (!invoices.length) fail("fakturoid_invalid_export");
    if (invoices.length > MAX_INVOICES) fail("fakturoid_limit_exceeded");
    const ids = new Set<string>();
    let issuerRegistrationNumber = "";
    // Structural ambiguity is an export error even if another row is unsupported.
    const records = invoices.map(element => {
        if (element.localName !== "invoice") fail("fakturoid_invalid_export");
        const source = fields(element);
        const id = identifier(source, "id");
        if (ids.has(id)) fail("fakturoid_invalid_export");
        ids.add(id);
        const issuerElement = source.get("your_registration_no");
        const issuerText = issuerElement?.textContent?.trim() ?? "";
        if (issuerText.length > 64 || issuerElement?.childElementCount) fail("fakturoid_invalid_export");
        const issuer = registration(issuerText);
        if (!issuer || (issuerRegistrationNumber && issuerRegistrationNumber !== issuer)) fail("fakturoid_invalid_export");
        issuerRegistrationNumber = issuer;
        for (const [name, field] of source) {
            if (name !== "lines" && field.childElementCount) fail("fakturoid_invalid_export");
        }
        const lines = source.get("lines");
        if (lines) {
            if (lines.childElementCount > MAX_LINES) fail("fakturoid_limit_exceeded");
            const lineIds = new Set<string>();
            for (const line of Array.from(lines.children)) {
                if (line.localName !== "line") fail("fakturoid_invalid_export");
                const lineSource = fields(line);
                const lineId = identifier(lineSource, "id");
                if (lineIds.has(lineId) || Array.from(line.children).some(field => field.childElementCount)) fail("fakturoid_invalid_export");
                lineIds.add(lineId);
            }
        }
        return source;
    });
    const rows = records.map((source, index): MigrationPreviewRow => {
        const row = index + 2;
        let number = "";
        try {
            number = required(source, "number", 64);
            const invoice = readInvoice(source, row, issuerRegistrationNumber, registration(options.companyRegistrationNumber));
            return { row, invoice_number: number, kind: "ready", invoice };
        } catch (error) {
            if (!(error instanceof FakturoidMigrationError)) throw error;
            if (["fakturoid_invalid_export", "fakturoid_limit_exceeded"].includes(error.code)) throw error;
            return { row, invoice_number: number, kind: "invalid", code: error.code };
        }
    });
    return { rows, invoiceCount: records.length, issuerRegistrationNumber };
}
