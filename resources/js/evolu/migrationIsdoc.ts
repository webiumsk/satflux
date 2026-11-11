export type MigrationStructuredLine = {
    name: string;
    description: string | null;
    quantity: string;
    unit: string | null;
    unitPrice: string;
    lineDiscountPercent: string;
    taxRate: string;
    lineTotal: string;
};

export type MigrationStructuredInvoice = {
    documentNumber: string;
    issueDate: string;
    currency: string;
    supplierRegistrationNumber: string;
    buyerRegistrationNumber: string | null;
    buyerName: string;
    subtotal: string;
    taxTotal: string;
    total: string;
    requiresFullPayment?: true;
    lines: readonly MigrationStructuredLine[];
};

type IsdocErrorCode = "migration_isdoc_invalid" | "migration_isdoc_unsupported"
    | "migration_isdoc_ambiguous" | "migration_isdoc_unreadable";

export type MigrationIsdocRejection = "format" | "document_type" | "currency" | "adjustments"
    | "line_values" | "discount" | "tax" | "rounding" | "precision" | "size" | "xml" | "totals";

export class MigrationIsdocError extends Error {
    constructor(code: IsdocErrorCode, readonly reason?: MigrationIsdocRejection) {
        super(code);
    }
}

export const MIGRATION_ISDOC_MAX_BYTES = 512 * 1024;
const ISDOC_NAMESPACE = "http://isdoc.cz/namespace/2013";
const SUPPORTED_VERSIONS = new Set(["6.0.0", "6.0.1", "6.0.2"]);
const MAX_LINES = 500;

function invalid(reason: "xml" | "totals" = "xml"): never {
    throw new MigrationIsdocError("migration_isdoc_invalid", reason);
}

function unsupported(reason: MigrationIsdocRejection = "format"): never {
    throw new MigrationIsdocError("migration_isdoc_unsupported", reason);
}

function children(parent: Element, name: string): Element[] {
    const matches = Array.from(parent.children).filter(child => child.localName === name);
    if (matches.some(child => child.namespaceURI !== ISDOC_NAMESPACE)) invalid();
    return matches;
}

function child(parent: Element, name: string, required: true): Element;
function child(parent: Element, name: string, required: false): Element | null;
function child(parent: Element, name: string, required: boolean): Element | null {
    const matches = children(parent, name);
    if (matches.length > 1 || (required && matches.length === 0)) invalid();
    return matches[0] ?? null;
}

function text(element: Element, limit: number): string {
    const value = element.textContent?.trim() ?? "";
    if (element.childElementCount || value.length > limit) invalid();
    return value;
}

function field(parent: Element, name: string, limit: number): string {
    const value = text(child(parent, name, true), limit);
    if (!value) invalid();
    return value;
}

function optionalField(parent: Element, name: string, limit: number): string | null {
    const element = child(parent, name, false);
    return element ? text(element, limit) || null : null;
}

/** Parse supported source decimals exactly, without rounding extra precision. */
function decimal(value: string, scale: number, integerDigits = 12): bigint {
    const match = /^(-?)(\d{1,12})(?:\.(\d{1,16}))?$/.exec(value);
    if (!match || match[2].length > integerDigits) invalid();
    const fractional = (match[3] ?? "").replace(/0+$/, "");
    if (fractional.length > scale) unsupported("precision");
    const absolute = BigInt(match[2]) * (10n ** BigInt(scale)) + BigInt(fractional.padEnd(scale, "0") || "0");
    return match[1] ? -absolute : absolute;
}

function format(value: bigint, scale: number): string {
    const factor = 10n ** BigInt(scale);
    const absolute = value < 0n ? -value : value;
    return `${value < 0n ? "-" : ""}${absolute / factor}.${String(absolute % factor).padStart(scale, "0")}`;
}

function amount(parent: Element, name: string): bigint {
    return decimal(field(parent, name, 29), 2);
}

function optionalAmount(parent: Element, name: string): bigint | null {
    const element = child(parent, name, false);
    return element ? decimal(text(element, 29), 2) : null;
}

function round(numerator: bigint, denominator: bigint): bigint {
    const absolute = numerator < 0n ? -numerator : numerator;
    const rounded = (absolute + denominator / 2n) / denominator;
    return numerator < 0n ? -rounded : rounded;
}

function flag(parent: Element, name: string): boolean | null {
    const value = optionalField(parent, name, 5);
    if (value === null) return null;
    if (value === "true" || value === "1") return true;
    if (value === "false" || value === "0") return false;
    return invalid();
}

type Amounts = { net: bigint; tax: bigint; gross: bigint };
type ParsedLine = Amounts & { id: string; rate: bigint; rawNet: bigint; rawTax: bigint; line: MigrationStructuredLine };

// A four-decimal quantity times a four-decimal price has eight places.
// Tax rates have a further four places after converting percent to a fraction.
const RAW_NET_PER_CENT = 1_000_000n;
const RAW_TAX_PER_CENT = 10_000_000_000n;

function readLine(element: Element, invoiceVatApplies: boolean | null): ParsedLine {
    const id = field(element, "ID", 36);
    const quantityElement = child(element, "InvoicedQuantity", true);
    const quantity = decimal(text(quantityElement, 29), 4, 8);
    const unit = quantityElement.getAttribute("unitCode")?.trim() || null;
    if (quantity <= 0n || (unit?.length ?? 0) > 32) unsupported("line_values");

    const unitPrice = decimal(field(element, "UnitPrice", 29), 4);
    const net = amount(element, "LineExtensionAmount");
    const tax = amount(element, "LineExtensionTaxAmount");
    const gross = amount(element, "LineExtensionAmountTaxInclusive");
    const beforeDiscount = optionalAmount(element, "LineExtensionAmountBeforeDiscount");
    const grossBeforeDiscount = optionalAmount(element, "LineExtensionAmountTaxInclusiveBeforeDiscount");
    if ((beforeDiscount !== null && beforeDiscount !== net)
        || (grossBeforeDiscount !== null && grossBeforeDiscount !== gross)) unsupported("discount");

    const category = child(element, "ClassifiedTaxCategory", true);
    const method = field(category, "VATCalculationMethod", 1);
    if (method !== "0" && method !== "1") unsupported("tax");
    if (child(category, "LocalReverseCharge", false) || child(element, "VATNote", false)) unsupported("tax");
    const rate = decimal(field(category, "Percent", 10), 2, 3);
    const vatApplies = flag(category, "VATApplicable");
    if (rate < 0n || rate > 10_000n || ((invoiceVatApplies === false || vatApplies === false) && rate !== 0n)) unsupported("tax");

    const rawNet = quantity * unitPrice;
    const rawTax = rawNet * rate;
    const expectedNet = round(rawNet, RAW_NET_PER_CENT);
    const expectedTax = round(net * rate, 10_000n);
    if (gross !== net + tax) invalid("totals");
    // Both source-style VAT on cent-rounded net and Satflux's VAT on raw net
    // must reproduce the source amounts, including a rounded raw gross.
    if (expectedNet !== net || expectedTax !== tax || round(rawTax, RAW_TAX_PER_CENT) !== tax
        || round(rawNet * 10_000n + rawTax, RAW_TAX_PER_CENT) !== gross) unsupported("rounding");
    const grossPrice = field(element, "UnitPriceTaxInclusive", 29);
    const grossPriceScale = Math.max(2, (grossPrice.split(".")[1] ?? "").replace(/0+$/, "").length);
    if (grossPriceScale > 4) unsupported("precision");
    const expectedGrossPrice = round(unitPrice * (10_000n + rate), 10n ** BigInt(8 - grossPriceScale));
    if (decimal(grossPrice, grossPriceScale) !== expectedGrossPrice) unsupported("rounding");

    const item = child(element, "Item", true);
    return {
        id, net, tax, gross, rate, rawNet, rawTax,
        line: {
            name: field(item, "Description", 255),
            description: optionalField(element, "Note", 4000),
            quantity: format(quantity, 4),
            unit,
            unitPrice: unitPrice % 100n === 0n ? format(unitPrice / 100n, 2) : format(unitPrice, 4),
            lineDiscountPercent: "0.00",
            taxRate: format(rate, 2),
            lineTotal: format(gross, 2),
        },
    };
}

function checkTaxSubtotals(taxTotal: Element, lines: readonly ParsedLine[]): void {
    const byRate = new Map<bigint, Amounts>();
    for (const { rate, net, tax, gross } of lines) {
        const sums = byRate.get(rate) ?? { net: 0n, tax: 0n, gross: 0n };
        byRate.set(rate, { net: sums.net + net, tax: sums.tax + tax, gross: sums.gross + gross });
    }
    if ([...byRate.values()].some(({ net, tax }) => net < 0n || tax < 0n)) unsupported("tax");
    const subtotals = children(taxTotal, "TaxSubTotal");
    if (!subtotals.length && byRate.size === 1 && byRate.has(0n)) return;
    for (const subtotal of subtotals) {
        const rate = decimal(field(child(subtotal, "TaxCategory", true), "Percent", 10), 2, 3);
        const expected = byRate.get(rate);
        if (!expected || amount(subtotal, "TaxableAmount") !== expected.net
            || amount(subtotal, "TaxAmount") !== expected.tax
            || amount(subtotal, "TaxInclusiveAmount") !== expected.gross) invalid("totals");
        for (const name of ["AlreadyClaimedTaxableAmount", "AlreadyClaimedTaxAmount", "AlreadyClaimedTaxInclusiveAmount"]) {
            if ((optionalAmount(subtotal, name) ?? 0n) !== 0n) unsupported("adjustments");
        }
        for (const [name, value] of [
            ["DifferenceTaxableAmount", expected.net], ["DifferenceTaxAmount", expected.tax], ["DifferenceTaxInclusiveAmount", expected.gross],
        ] as const) {
            const difference = optionalAmount(subtotal, name);
            if (difference !== null && difference !== value) invalid("totals");
        }
        byRate.delete(rate);
    }
    if (byRate.size) invalid("totals");
}

function checkNonTaxedDeposits(section: Element, expected: bigint): void {
    const deposits = children(section, "NonTaxedDeposit");
    if (expected <= 0n || !deposits.length || deposits.length > MAX_LINES
        || deposits.length !== section.childElementCount) unsupported("adjustments");
    const ids = new Set<string>();
    let sum = 0n;
    for (const deposit of deposits) {
        const id = field(deposit, "ID", 64);
        if (ids.has(id) || Array.from(deposit.children).some(node =>
            node.namespaceURI !== ISDOC_NAMESPACE || !["ID", "VariableSymbol", "DepositAmount"].includes(node.localName))) unsupported("adjustments");
        optionalField(deposit, "VariableSymbol", 64);
        ids.add(id);
        const value = amount(deposit, "DepositAmount");
        if (value <= 0n) unsupported("adjustments");
        sum += value;
    }
    if (sum !== expected) unsupported("adjustments");
}

function readInvoice(root: Element): MigrationStructuredInvoice {
    if (!SUPPORTED_VERSIONS.has(root.getAttribute("version") ?? "")) unsupported("format");
    if (field(root, "DocumentType", 2) !== "1") unsupported("document_type");
    const currency = field(root, "LocalCurrencyCode", 3);
    if (!/^[A-Z]{3}$/.test(currency) || child(root, "ForeignCurrencyCode", false)) unsupported("currency");
    if (child(root, "TaxedDeposits", false)) unsupported("adjustments");
    const nonTaxedDeposits = child(root, "NonTaxedDeposits", false);
    for (const name of ["CurrRate", "RefCurrRate"]) {
        const value = optionalField(root, name, 29);
        if (value !== null && decimal(value, 4) !== 10_000n) unsupported("currency");
    }
    const issueDate = field(root, "IssueDate", 10);
    const date = new Date(`${issueDate}T00:00:00.000Z`);
    if (!/^[1-9]\d{3}-\d{2}-\d{2}$/.test(issueDate)
        || !Number.isFinite(date.getTime()) || date.toISOString().slice(0, 10) !== issueDate) invalid();
    const party = (name: string) => child(child(root, name, true), "Party", true);
    const supplier = party("AccountingSupplierParty");
    const buyer = party("AccountingCustomerParty");
    const supplierRegistrationNumber = field(child(supplier, "PartyIdentification", true), "ID", 64);
    const buyerIdentification = child(buyer, "PartyIdentification", false);
    const buyerRegistrationNumber = buyerIdentification ? optionalField(buyerIdentification, "ID", 64) : null;
    const buyerName = field(child(buyer, "PartyName", true), "Name", 255);

    const elements = children(child(root, "InvoiceLines", true), "InvoiceLine");
    if (!elements.length || elements.length > MAX_LINES) unsupported("line_values");
    const invoiceVatApplies = flag(root, "VATApplicable");
    const lines = elements.map(element => readLine(element, invoiceVatApplies));
    if (new Set(lines.map(line => line.id)).size !== lines.length) invalid();
    const amounts = child(root, "LegalMonetaryTotal", true);
    const subtotal = amount(amounts, "TaxExclusiveAmount");
    const total = amount(amounts, "TaxInclusiveAmount");
    for (const name of ["PayableRoundingAmount", "AlreadyClaimedTaxExclusiveAmount", "AlreadyClaimedTaxInclusiveAmount"]) {
        if ((optionalAmount(amounts, name) ?? 0n) !== 0n) unsupported("adjustments");
    }
    const netDifference = optionalAmount(amounts, "DifferenceTaxExclusiveAmount");
    const grossDifference = optionalAmount(amounts, "DifferenceTaxInclusiveAmount");
    const payable = amount(amounts, "PayableAmount");
    const paidDeposits = optionalAmount(amounts, "PaidDepositsAmount") ?? 0n;
    // SuperFaktura can export an already settled invoice with zero payable.
    // Item evidence is conditional on independent full payment in the sheet.
    const requiresFullPayment = total > 0n && payable === 0n && (paidDeposits === 0n || paidDeposits === total);
    if ((!requiresFullPayment && (payable !== total || paidDeposits !== 0n)) || (netDifference !== null && netDifference !== subtotal)
        || (grossDifference !== null && grossDifference !== total)) unsupported("adjustments");
    if (nonTaxedDeposits) {
        if (!requiresFullPayment) unsupported("adjustments");
        checkNonTaxedDeposits(nonTaxedDeposits, paidDeposits);
    }
    const taxTotal = child(root, "TaxTotal", true);
    const tax = amount(taxTotal, "TaxAmount");
    if (total <= 0n || total !== subtotal + tax || lines.reduce((sum, line) => sum + line.net, 0n) !== subtotal
        || lines.reduce((sum, line) => sum + line.tax, 0n) !== tax || lines.reduce((sum, line) => sum + line.gross, 0n) !== total) invalid("totals");
    const rawNet = lines.reduce((sum, line) => sum + line.rawNet, 0n);
    const rawTax = lines.reduce((sum, line) => sum + line.rawTax, 0n);
    if (round(rawNet, RAW_NET_PER_CENT) !== subtotal || round(rawTax, RAW_TAX_PER_CENT) !== tax
        || round(rawNet * 10_000n + rawTax, RAW_TAX_PER_CENT) !== total) unsupported("rounding");
    checkTaxSubtotals(taxTotal, lines);

    return {
        documentNumber: field(root, "ID", 64), issueDate, currency, supplierRegistrationNumber,
        buyerRegistrationNumber, buyerName, subtotal: format(subtotal, 2), taxTotal: format(tax, 2),
        total: format(total, 2), ...(requiresFullPayment ? { requiresFullPayment: true as const } : {}),
        lines: lines.map(line => line.line),
    };
}

/** Return null for well-formed XML belonging to another document format. */
export function readMigrationIsdocXml(bytes: Uint8Array): MigrationStructuredInvoice | null {
    if (!bytes.length || bytes.length > MIGRATION_ISDOC_MAX_BYTES) unsupported("size");
    let xml: string;
    try {
        xml = new TextDecoder("utf-8", { fatal: true }).decode(bytes);
    } catch {
        return invalid();
    }
    const declaration = /^<\?xml\s[^?]*\?>/.exec(xml)?.[0] ?? "";
    const encoding = /\bencoding\s*=\s*["']([^"']+)["']/i.exec(declaration)?.[1];
    if (encoding && encoding.toLowerCase() !== "utf-8") unsupported();
    if (/<\?|<!DOCTYPE\b|<!ENTITY\b/i.test(xml.slice(declaration.length))) invalid();
    const doc = new DOMParser().parseFromString(xml, "application/xml");
    const root = doc.documentElement;
    if (!root || doc.doctype || doc.getElementsByTagNameNS("*", "parsererror").length) invalid();
    if (root.namespaceURI !== ISDOC_NAMESPACE) return null;
    if (root.localName !== "Invoice") unsupported();
    return readInvoice(root);
}

export function parseMigrationIsdoc(bytes: Uint8Array): MigrationStructuredInvoice {
    return readMigrationIsdocXml(bytes) ?? unsupported();
}
