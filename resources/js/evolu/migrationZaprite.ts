import type { MigrationInvoice, MigrationPreviewRow, MigrationState } from "./migrationValidation";
import { migrationDecimal } from "./migrationValidation";
import type { MigrationStructuredInvoice, MigrationStructuredLine } from "./migrationIsdoc";

export type ZapriteMigrationErrorCode = "zaprite_invalid_export" | "zaprite_incomplete_export"
    | "zaprite_unsupported_currency" | "zaprite_payment_evidence" | "zaprite_totals"
    | "zaprite_unsupported_status" | "zaprite_invalid_invoice";

export class ZapriteMigrationError extends Error {
    constructor(readonly code: ZapriteMigrationErrorCode) {
        super(code);
        this.name = "ZapriteMigrationError";
    }
}

export type ZapriteMigrationResult = {
    rows: MigrationPreviewRow[];
    excludedCurrencies: { currency: string; count: number }[];
    invoiceCount: number;
    ignoredOrders: number;
    organizationId: string;
};

const MAX_BYTES = 10 * 1024 * 1024;
const MAX_ORDERS = 2000;
const MAX_PAGES = 20;
const MAX_LINES = 500;
const MAX_TRANSACTIONS = 500;
const PAYMENT_METHODS = new Set([
    "BITCOIN", "LIQUID", "LIGHTNING", "BANK", "CARD", "CASH", "CHECK", "GIFTCARD", "COUPON",
    "APPLEPAY", "GOOGLEPAY", "CASHAPP_PAY", "INTERAC", "ACH", "SEPA", "UNKNOWN", "WIRE_TRANSFER",
    "VENMO", "PAYPAL", "REVOLUT_PAY", "NO_CHARGE", "TETHER", "USDCIRCLE",
]);
const MAX_MINOR = 99_999_999_999_999n;
const TWO_DECIMAL_CURRENCIES = new Set([
    "AUD", "CAD", "CHF", "CNY", "CZK", "DKK", "EUR", "GBP", "HKD", "HUF",
    "ILS", "INR", "MXN", "NOK", "NZD", "PLN", "SEK", "SGD", "USD", "ZAR",
]);
const ORDER_TYPES = new Set(["invoice", "apiInvoice", "paymentLink", "pos", "event", "wooCommerce", "paymentRequest", "apiOrder", "other"]);
type JsonRecord = Record<string, unknown>;

function fail(code: ZapriteMigrationErrorCode): never {
    throw new ZapriteMigrationError(code);
}

function record(value: unknown, code: ZapriteMigrationErrorCode): JsonRecord {
    if (!value || typeof value !== "object" || Array.isArray(value)) fail(code);
    return value as JsonRecord;
}

function text(value: unknown, max: number, code: ZapriteMigrationErrorCode): string {
    if (typeof value !== "string" || !value.trim() || value.length > max) fail(code);
    return value.trim();
}

function optionalText(value: unknown, max: number, code: ZapriteMigrationErrorCode): string | null {
    if (value === null || value === undefined || value === "") return null;
    return text(value, max, code);
}

function identifier(value: unknown, code: ZapriteMigrationErrorCode): string {
    const id = text(value, 128, code);
    if (id !== value || Array.from(id).some(char => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127)) fail(code);
    return id;
}

function integer(value: unknown, min: number, max: number, code: ZapriteMigrationErrorCode): number {
    if (typeof value !== "number" || !Number.isSafeInteger(value) || value < min || value > max) fail(code);
    return value;
}

function amount(value: unknown, code: ZapriteMigrationErrorCode): bigint {
    const result = BigInt(integer(value, -Number(MAX_MINOR), Number(MAX_MINOR), code));
    return result;
}

function round(numerator: bigint, denominator: bigint): bigint {
    const magnitude = numerator < 0n ? -numerator : numerator;
    const rounded = (magnitude + denominator / 2n) / denominator;
    return numerator < 0n ? -rounded : rounded;
}

function bounded(value: bigint, code: ZapriteMigrationErrorCode): bigint {
    if (value < -MAX_MINOR || value > MAX_MINOR) fail(code);
    return value;
}

function day(value: unknown): string {
    const valueText = text(value, 10, "zaprite_invalid_invoice");
    if (!/^\d{4}-\d{2}-\d{2}$/.test(valueText)) fail("zaprite_invalid_invoice");
    const parsed = new Date(valueText + "T00:00:00.000Z");
    if (!Number.isFinite(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== valueText) fail("zaprite_invalid_invoice");
    return valueText;
}

function paymentDate(value: unknown): string {
    const valueText = text(value, 24, "zaprite_payment_evidence");
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?Z$/.test(valueText)) fail("zaprite_payment_evidence");
    const parsed = new Date(valueText);
    const parts = valueText.slice(0, -1).split(".");
    const expected = parts[0] + "." + (parts[1] ?? "").padEnd(3, "0") + "Z";
    if (!Number.isFinite(parsed.getTime()) || parsed.toISOString() !== expected) fail("zaprite_payment_evidence");
    return expected;
}

function dueDate(invoice: JsonRecord, issued: string): string {
    const days = integer(invoice.daysToPay, 0, 3_652_058, "zaprite_invalid_invoice");
    const due = new Date(Date.parse(issued + "T00:00:00.000Z") + days * 86_400_000);
    if (!Number.isFinite(due.getTime()) || due.getUTCFullYear() > 9999) fail("zaprite_invalid_invoice");
    return due.toISOString().slice(0, 10);
}

function quantity(value: unknown): bigint {
    if (typeof value !== "number" || !Number.isFinite(value) || value <= 0) fail("zaprite_invalid_invoice");
    const decimal = String(value);
    if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(decimal)) fail("zaprite_invalid_invoice");
    const [whole, fraction = ""] = decimal.split(".");
    return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
}

function readLines(value: unknown, total: bigint): Pick<MigrationStructuredInvoice, "subtotal" | "taxTotal" | "lines"> {
    if (!Array.isArray(value) || !value.length || value.length > MAX_LINES) fail("zaprite_invalid_invoice");
    let subtotal = 0n;
    let taxTotal = 0n;
    let rawNetTotal = 0n;
    let rawTaxTotal = 0n;
    const ids = new Set<string>();
    const buckets = new Map<number, { net: bigint; tax: bigint; rawNet: bigint; rawTax: bigint }>();
    const lines: MigrationStructuredLine[] = value.map(item => {
        const line = record(item, "zaprite_invalid_invoice");
        const id = identifier(line.id, "zaprite_invalid_invoice");
        if (ids.has(id)) fail("zaprite_invalid_invoice");
        ids.add(id);
        const units = quantity(line.quantity);
        const price = amount(line.unitPrice, "zaprite_invalid_invoice");
        const rate = integer(line.taxRateBips, 0, 10_000, "zaprite_invalid_invoice");
        const discount = integer(line.discountRateBips, 0, 10_000, "zaprite_invalid_invoice");
        const base = price * units;
        const rawNet = base * BigInt(10_000 - discount);
        const rawTax = rawNet * BigInt(rate);
        const discountAmount = amount(line.discountAmount, "zaprite_totals");
        const tax = amount(line.taxAmount, "zaprite_totals");
        const net = bounded(round(rawNet, 1_000_000n), "zaprite_totals");
        if (discountAmount !== round(base * BigInt(discount), 1_000_000n)
            || net !== round(base, 100n) - discountAmount
            || tax !== round(rawTax, 10_000_000_000n)) fail("zaprite_totals");
        const gross = bounded(net + tax, "zaprite_totals");
        if (round(rawNet * 10_000n + rawTax, 10_000_000_000n) !== gross) fail("zaprite_totals");
        subtotal = bounded(subtotal + net, "zaprite_totals");
        taxTotal = bounded(taxTotal + tax, "zaprite_totals");
        rawNetTotal += rawNet;
        rawTaxTotal += rawTax;
        const bucket = buckets.get(rate) ?? { net: 0n, tax: 0n, rawNet: 0n, rawTax: 0n };
        bucket.net += net;
        bucket.tax += tax;
        bucket.rawNet += rawNet;
        bucket.rawTax += rawTax;
        buckets.set(rate, bucket);
        return {
            name: text(line.name, 255, "zaprite_invalid_invoice"),
            description: optionalText(line.description, 4000, "zaprite_invalid_invoice"),
            quantity: migrationDecimal(units) + "00",
            unit: null,
            unitPrice: migrationDecimal(price),
            lineDiscountPercent: migrationDecimal(BigInt(discount)),
            taxRate: migrationDecimal(BigInt(rate)),
            lineTotal: migrationDecimal(gross),
        };
    });
    if (subtotal <= 0n || taxTotal < 0n || subtotal + taxTotal !== total
        || round(rawNetTotal, 1_000_000n) !== subtotal || round(rawTaxTotal, 10_000_000_000n) !== taxTotal
        || round(rawNetTotal * 10_000n + rawTaxTotal, 10_000_000_000n) !== total
        || [...buckets.values()].some(bucket => bucket.net < 0n || bucket.tax < 0n
            || round(bucket.rawNet, 1_000_000n) !== bucket.net
            || round(bucket.rawTax, 10_000_000_000n) !== bucket.tax)) fail("zaprite_totals");
    return { subtotal: migrationDecimal(subtotal), taxTotal: migrationDecimal(taxTotal), lines };
}

function checkAdjustments(order: JsonRecord): void {
    if (order.promo !== null && order.promo !== undefined) {
        const promo = record(order.promo, "zaprite_totals");
        if (amount(promo.amountOff, "zaprite_totals") !== 0n
            || integer(promo.rateBipsOff, 0, 10_000, "zaprite_totals") !== 0) fail("zaprite_totals");
    }
    if (order.coupons !== undefined && (!Array.isArray(order.coupons) || order.coupons.length)) fail("zaprite_totals");
}

function readPayments(order: JsonRecord, currency: string, transactionCounts: ReadonlyMap<string, number>): { paid: bigint; paidAt: string | null } {
    if (!Array.isArray(order.transactions) || order.transactions.length > MAX_TRANSACTIONS) fail("zaprite_payment_evidence");
    let paid = 0n;
    let lastPayment: string | null = null;
    for (const value of order.transactions) {
        const transaction = record(value, "zaprite_payment_evidence");
        const id = identifier(transaction.id, "zaprite_payment_evidence");
        if (transactionCounts.get(id) !== 1) fail("zaprite_payment_evidence");
        const status = transaction.status;
        if (typeof status !== "string" || !["CONFIRMED", "PENDING", "CANCELED"].includes(status)) fail("zaprite_payment_evidence");
        const date = paymentDate(transaction.date);
        const nativeAmount = amount(transaction.amount, "zaprite_payment_evidence");
        if (nativeAmount <= 0n) fail("zaprite_payment_evidence");
        const transactionCurrency = text(transaction.currency, 8, "zaprite_payment_evidence");
        if (!/^[A-Z]{3,8}$/.test(transactionCurrency)
            || !PAYMENT_METHODS.has(text(transaction.method, 64, "zaprite_payment_evidence"))) fail("zaprite_payment_evidence");
        if (!Array.isArray(transaction.discountsInOrderCurrency)) fail("zaprite_payment_evidence");
        for (const value of transaction.discountsInOrderCurrency) {
            const discount = record(value, "zaprite_payment_evidence");
            if (amount(discount.amountOff, "zaprite_payment_evidence") !== 0n
                || integer(discount.rateBipsOff, -10_000, 10_000, "zaprite_payment_evidence") !== 0) fail("zaprite_payment_evidence");
        }
        if (status !== "CONFIRMED") continue;
        let orderAmount: bigint;
        if (transaction.amountInOrderCurrency === null) {
            if (transactionCurrency !== currency || (transaction.orderCurrency !== null && transaction.orderCurrency !== currency)) fail("zaprite_payment_evidence");
            orderAmount = nativeAmount;
        } else {
            if (transaction.orderCurrency !== currency) fail("zaprite_payment_evidence");
            orderAmount = amount(transaction.amountInOrderCurrency, "zaprite_payment_evidence");
        }
        if (orderAmount <= 0n) fail("zaprite_payment_evidence");
        paid = bounded(paid + orderAmount, "zaprite_payment_evidence");
        if (lastPayment === null || date > lastPayment) lastPayment = date;
    }
    if (order.paidAt !== null && order.paidAt !== undefined) {
        paymentDate(order.paidAt);
        if (paid === 0n) fail("zaprite_payment_evidence");
    }
    return { paid, paidAt: lastPayment?.slice(0, 10) ?? null };
}

function readState(invoiceStatus: unknown, orderStatus: unknown, total: bigint, paid: bigint): MigrationState {
    if (typeof invoiceStatus !== "string" || !["SENT", "OVERDUE", "PENDING", "UNDERPAID", "PAID", "OVERPAID", "VOID"].includes(invoiceStatus)) fail("zaprite_unsupported_status");
    if (typeof orderStatus !== "string" || !["PENDING", "PROCESSING", "UNDERPAID", "PAID", "OVERPAID", "COMPLETE"].includes(orderStatus)) fail("zaprite_unsupported_status");
    if (invoiceStatus === "VOID") {
        if (paid !== 0n || !["PENDING", "PROCESSING"].includes(orderStatus)) fail("zaprite_payment_evidence");
        return "cancelled";
    }
    if (["SENT", "OVERDUE", "PENDING"].includes(invoiceStatus)) {
        if (paid !== 0n || !["PENDING", "PROCESSING"].includes(orderStatus)) fail("zaprite_payment_evidence");
        return "unpaid";
    }
    if (invoiceStatus === "UNDERPAID") {
        if (paid <= 0n || paid >= total || orderStatus !== "UNDERPAID") fail("zaprite_payment_evidence");
        return "partial";
    }
    if (invoiceStatus === "PAID") {
        if (paid !== total || !["PAID", "COMPLETE"].includes(orderStatus)) fail("zaprite_payment_evidence");
        return "paid";
    }
    if (paid <= total || !["OVERPAID", "COMPLETE"].includes(orderStatus)) fail("zaprite_payment_evidence");
    return "paid";
}

function readContact(order: JsonRecord, invoice: JsonRecord): MigrationInvoice["contact_attributes"] {
    const contact = record(invoice.customer ?? order.contact, "zaprite_invalid_invoice");
    identifier(contact.id, "zaprite_invalid_invoice");
    if (order.contact && invoice.customer) {
        const customer = record(order.contact, "zaprite_invalid_invoice");
        if (contact.id !== customer.id || contact.legalName !== customer.legalName) fail("zaprite_invalid_invoice");
    }
    const address = contact.billingAddress === null || contact.billingAddress === undefined
        ? null : record(contact.billingAddress, "zaprite_invalid_invoice");
    const street = address ? [optionalText(address.line1, 255, "zaprite_invalid_invoice"), optionalText(address.line2, 255, "zaprite_invalid_invoice")].filter(Boolean).join("\n") : null;
    if (street && street.length > 255) fail("zaprite_invalid_invoice");
    const country = address ? optionalText(address.countryCode, 2, "zaprite_invalid_invoice") : null;
    if (country && !/^[A-Z]{2}$/.test(country)) fail("zaprite_invalid_invoice");
    return {
        name: text(contact.legalName, 255, "zaprite_invalid_invoice"),
        registration_number: null,
        tax_id: optionalText(contact.taxId, 64, "zaprite_invalid_invoice"),
        vat_id: null,
        street: street || null,
        city: address ? optionalText(address.city, 128, "zaprite_invalid_invoice") : null,
        postal_code: address ? optionalText(address.zipCode, 32, "zaprite_invalid_invoice") : null,
        country,
        phone: null,
        email: optionalText(contact.email, 100, "zaprite_invalid_invoice"),
    };
}

function convertOrder(order: JsonRecord, row: number, transactionCounts: ReadonlyMap<string, number>): MigrationInvoice {
    const invoice = record(order.invoice, "zaprite_invalid_invoice");
    const invoiceId = identifier(invoice.id, "zaprite_invalid_invoice");
    if (invoice.orgId !== order.orgId) fail("zaprite_invalid_invoice");
    const number = text(invoice.number, 64, "zaprite_invalid_invoice");
    if (Array.from(number).some(char => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127)) fail("zaprite_invalid_invoice");
    const issued = day(invoice.date);
    const due = dueDate(invoice, issued);
    const currency = text(order.currency, 8, "zaprite_invalid_invoice");
    if (!/^[A-Z]{3,8}$/.test(currency)) fail("zaprite_invalid_invoice");
    const total = amount(order.totalAmount, "zaprite_totals");
    if (total <= 0n) fail("zaprite_totals");
    checkAdjustments(order);
    const lines = readLines(invoice.lineItems, total);
    const contact = readContact(order, invoice);
    const payment = readPayments(order, currency, transactionCounts);
    const state = readState(invoice.status, order.status, total, payment.paid);
    if (!TWO_DECIMAL_CURRENCIES.has(currency)) fail("zaprite_unsupported_currency");
    return {
        row, invoice_number: number, client_name: contact.name,
        issue_date: issued, due_date: due, delivery_date: null, currency,
        total: migrationDecimal(total), amount_paid: migrationDecimal(payment.paid),
        balance: migrationDecimal(total - payment.paid), paid_at: payment.paidAt, state,
        variable_symbol: null, constant_symbol: null, specific_symbol: null,
        contact_attributes: contact,
        sourceIdentity: {
            provider: "zaprite",
            organizationId: String(order.orgId),
            invoiceId,
            orderId: String(order.id),
        },
        structured: {
            documentNumber: number, issueDate: issued, currency,
            supplierRegistrationNumber: "", buyerRegistrationNumber: null, buyerName: contact.name,
            subtotal: lines.subtotal, taxTotal: lines.taxTotal, total: migrationDecimal(total), lines: lines.lines,
        },
    };
}

function readOrders(bytes: Uint8Array): JsonRecord[] {
    if (!bytes.length || bytes.length > MAX_BYTES) fail("zaprite_invalid_export");
    let source: unknown;
    try {
        source = JSON.parse(new TextDecoder("utf-8", { fatal: true }).decode(bytes));
    } catch {
        fail("zaprite_invalid_export");
    }
    const envelope = record(source, "zaprite_invalid_export");
    if (envelope.version !== 1 || envelope.provider !== "zaprite" || !Array.isArray(envelope.pages)
        || !envelope.pages.length || envelope.pages.length > MAX_PAGES) fail("zaprite_invalid_export");
    const orders: JsonRecord[] = [];
    let declaredCount = -1;
    let perPage = -1;
    for (const [index, value] of envelope.pages.entries()) {
        const page = record(value, "zaprite_invalid_export");
        const meta = record(page.meta, "zaprite_invalid_export");
        const count = integer(meta.itemsCount, 0, MAX_ORDERS, "zaprite_incomplete_export");
        const size = integer(meta.perPage, 1, 100, "zaprite_incomplete_export");
        const number = integer(meta.page, 1, MAX_PAGES, "zaprite_incomplete_export");
        const pagesCount = integer(meta.pagesCount, 0, MAX_PAGES, "zaprite_incomplete_export");
        if (index === 0) { declaredCount = count; perPage = size; }
        const expectedPages = Math.max(1, Math.ceil(count / size));
        if (!Array.isArray(page.items) || number !== index + 1 || count !== declaredCount || size !== perPage
            || expectedPages !== envelope.pages.length
            || (pagesCount !== expectedPages && !(count === 0 && pagesCount === 0))
            || page.items.length !== Math.min(size, Math.max(0, count - index * size))) fail("zaprite_incomplete_export");
        for (const item of page.items) orders.push(record(item, "zaprite_invalid_export"));
    }
    if (orders.length !== declaredCount) fail("zaprite_incomplete_export");
    return orders;
}

/** Convert a complete user-exported API page set without fetching any provider URL. */
export function parseZapriteMigrationExport(
    bytes: Uint8Array,
    options?: { excludeUnsupportedCurrencies?: boolean },
): ZapriteMigrationResult {
    const orders = readOrders(bytes);
    const orderIds = new Set<string>();
    const invoiceIds = new Set<string>();
    const transactionCounts = new Map<string, number>();
    let organizationId = "";
    for (const order of orders) {
        const id = identifier(order.id, "zaprite_invalid_export");
        const organization = identifier(order.orgId, "zaprite_invalid_export");
        if (orderIds.has(id) || (organizationId && organizationId !== organization)) fail("zaprite_incomplete_export");
        orderIds.add(id);
        organizationId = organization;
        if (typeof order.orderType !== "string" || !ORDER_TYPES.has(order.orderType)) fail("zaprite_invalid_export");
        if ((order.orderType === "invoice" || order.orderType === "apiInvoice") && order.invoice && typeof order.invoice === "object" && !Array.isArray(order.invoice)) {
            const invoice = order.invoice as JsonRecord;
            if (typeof invoice.id === "string" && invoice.id) {
                const invoiceId = invoice.id.trim();
                if (invoiceIds.has(invoiceId)) fail("zaprite_incomplete_export");
                invoiceIds.add(invoiceId);
            }
        }
        if (Array.isArray(order.transactions)) {
            for (const value of order.transactions) {
                if (!value || typeof value !== "object" || Array.isArray(value)) continue;
                const transaction = value as JsonRecord;
                if (typeof transaction.id !== "string") continue;
                const transactionId = transaction.id.trim();
                transactionCounts.set(transactionId, (transactionCounts.get(transactionId) ?? 0) + 1);
            }
        }
    }
    const rows: MigrationPreviewRow[] = [];
    const excluded = new Map<string, number>();
    let invoiceCount = 0;
    let ignoredOrders = 0;
    for (const order of orders) {
        if (order.orderType !== "invoice" && order.orderType !== "apiInvoice") { ignoredOrders++; continue; }
        const row = ++invoiceCount + 1;
        try {
            const invoice = convertOrder(order, row, transactionCounts);
            rows.push({ row, invoice_number: invoice.invoice_number, kind: "ready", invoice });
        } catch (error) {
            if (!(error instanceof ZapriteMigrationError)) throw error;
            const currency = typeof order.currency === "string" ? order.currency : "";
            if (error.code === "zaprite_unsupported_currency" && options?.excludeUnsupportedCurrencies) {
                excluded.set(currency, (excluded.get(currency) ?? 0) + 1);
                continue;
            }
            const invoice = order.invoice && typeof order.invoice === "object" ? order.invoice as JsonRecord : null;
            const number = invoice && typeof invoice.number === "string" && invoice.number.length <= 64 ? invoice.number.trim() : "";
            rows.push({ row, invoice_number: number, kind: "invalid", code: error.code });
        }
    }
    return {
        rows,
        excludedCurrencies: [...excluded].sort(([a], [b]) => a.localeCompare(b)).map(([currency, count]) => ({ currency, count })),
        invoiceCount, ignoredOrders, organizationId,
    };
}
