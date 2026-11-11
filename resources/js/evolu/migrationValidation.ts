import { createIdFromString } from "@evolu/common";
import { sha256 } from "@noble/hashes/sha2.js";
import type { DocumentImportFieldKey, DocumentImportMapping } from "@/composables/useDocumentImportFields";
import { parseImportDate, type ImportDateFormat } from "./importDateParse";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "./documentMap";
import type { MigrationStructuredInvoice } from "./migrationIsdoc";
import { migrationStructuredLinePatches, migrationStructuredLinesMatch } from "./migrationItemEvidence";

export type MigrationState = "unpaid" | "partial" | "paid" | "cancelled";
export type MigrationIssue = "invalid" | "duplicate" | "conflict";
export type MigrationInvoice = {
    row: number; invoice_number: string; client_name: string; issue_date: string; due_date: string;
    delivery_date: string | null; currency: string; total: string; amount_paid: string;
    balance: string; paid_at: string | null; state: MigrationState;
    variable_symbol: string | null; constant_symbol: string | null; specific_symbol: string | null;
    contact_attributes: {
        name: string; registration_number: string | null; tax_id: string | null; vat_id: string | null;
        street: string | null; city: string | null; postal_code: string | null; country: string | null;
        phone: string | null; email: string | null;
    };
    structured?: MigrationStructuredInvoice;
    sourceIdentity?: { provider: "zaprite"; organizationId: string; invoiceId: string; orderId: string }
        | { provider: "fakturoid"; issuerRegistrationNumber: string; invoiceId: string };
};
export type StructuredMismatch = "multiple" | "company_id" | "supplier" | "date" | "currency" | "total" | "items" | "payment" | "buyer";
export type MigrationPreviewRow = {
    row: number; invoice_number: string; kind: "ready" | MigrationIssue;
    code?: string; structuredMismatch?: StructuredMismatch; invoice?: MigrationInvoice;
};
export type MigrationTotals = { currency: string; count: number; total: string; paid: string; balance: string };
export type MigrationPreview = { rows: MigrationPreviewRow[]; totals: MigrationTotals[]; ready: number; duplicate: number; invalid: number; conflict: number };

/** Strict two-decimal source values; never round or parse a numeric prefix. */
export function migrationMinor(value: unknown): bigint {
    let text = String(value ?? "").trim();
    if (Array.from(text).some(char => char.charCodeAt(0) < 32)) throw new Error("invalid_amount");
    if (/[ \u00a0\u202f]/.test(text)) {
        if (!/^\d{1,3}(?:[ \u00a0\u202f]\d{3})+(?:[.,]\d{1,2})?$/.test(text)) throw new Error("invalid_amount");
        text = text.replace(/[ \u00a0\u202f]/g, "");
    }
    if (text.includes(",") && text.includes(".")) {
        const decimal = text.lastIndexOf(",") > text.lastIndexOf(".") ? "," : ".";
        const grouping = decimal === "," ? "." : ",";
        const parts = text.split(decimal);
        if (parts.length !== 2 || !new RegExp(`^\\d{1,3}(?:\\${grouping}\\d{3})+$`).test(parts[0])) throw new Error("invalid_amount");
        text = parts[0].split(grouping).join("") + "." + parts[1];
    } else text = text.replace(",", ".");
    if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(text)) throw new Error("invalid_amount");
    const [whole, fraction = ""] = text.split(".");
    return BigInt(whole) * 100n + BigInt(fraction.padEnd(2, "0"));
}
export function migrationDecimal(minor: bigint): string {
    const negative = minor < 0n;
    const value = negative ? -minor : minor;
    return `${negative ? "-" : ""}${value / 100n}.${String(value % 100n).padStart(2, "0")}`;
}
function normalized(value: string): string {
    return value.trim().toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}
const states: Record<string, MigrationState> = {
    unpaid: "unpaid", issued: "unpaid", sent: "unpaid", overdue: "unpaid", neuhradene: "unpaid", neuhradena: "unpaid", vystavena: "unpaid",
    partial: "partial", partially_paid: "partial", "partially paid": "partial", "ciastocne uhradene": "partial", "ciastocne uhradena": "partial",
    paid: "paid", uhradene: "paid", uhradena: "paid", zaplatene: "paid", zaplaceno: "paid",
    cancelled: "cancelled", canceled: "cancelled", void: "cancelled", stornovana: "cancelled", stornovane: "cancelled", storno: "cancelled",
};
export function migrationNumberKey(number: string): string { return number.trim().normalize("NFKC").toLowerCase(); }

function identityKey(value: string | null | undefined): string {
    return value?.normalize("NFKC").trim().replace(/\s+/g, " ").toLowerCase() ?? "";
}

function registrationKey(value: string | null | undefined): string {
    const key = identityKey(value);
    const digits = key.replace(/ /g, "");
    // SuperFaktura groups eight-digit registration numbers in ISDOC.
    return /^\d{8}$/.test(digits) ? digits : key;
}

function structuredMismatch(
    invoice: MigrationInvoice,
    candidates: readonly MigrationStructuredInvoice[],
    registrationNumber?: string | null,
): StructuredMismatch | null {
    const seller = registrationKey(registrationNumber);
    if (candidates.length !== 1) return "multiple";
    if (!seller) return "company_id";
    const original = candidates[0];
    if (registrationKey(original.supplierRegistrationNumber) !== seller) return "supplier";
    if (original.issueDate !== invoice.issue_date) return "date";
    if (original.currency !== invoice.currency) return "currency";
    if (original.total !== invoice.total) return "total";
    if (!original.lines.length) return "items";
    if (original.requiresFullPayment && (invoice.state !== "paid" || invoice.amount_paid !== invoice.total || invoice.balance !== "0.00")) return "payment";
    const buyerId = registrationKey(invoice.contact_attributes.registration_number);
    const originalBuyerId = registrationKey(original.buyerRegistrationNumber);
    const buyerMatches = buyerId && originalBuyerId
        ? buyerId === originalBuyerId
        : identityKey(invoice.client_name) === identityKey(original.buyerName);
    return buyerMatches ? null : "buyer";
}

export function parseMigrationInvoice(row: string[], mapping: DocumentImportMapping, rowNumber: number, options: { defaultCurrency: string; dateFormat: ImportDateFormat }): MigrationInvoice {
    const cell = (field: DocumentImportFieldKey): string | null => {
        const index = mapping[field];
        if (index == null) return null;
        if (!Number.isInteger(index) || index < 0 || index >= row.length) throw new Error("invalid_mapping");
        const value = String(row[index] ?? "").trim();
        return value || null;
    };
    const required = (field: DocumentImportFieldKey, max: number): string => {
        const value = cell(field);
        if (!value || value.length > max) throw new Error(`invalid_${field}`);
        return value;
    };
    const date = (field: DocumentImportFieldKey, requiredDate = false): string | null => {
        const value = cell(field);
        const parsed = value ? parseImportDate(value, options.dateFormat) : null;
        if ((value && !parsed) || (requiredDate && !parsed)) throw new Error(`invalid_${field}`);
        return parsed;
    };
    const number = required("invoice_number", 64);
    const name = required("client_name", 255);
    const issueDate = date("issue_date", true)!;
    const dueDate = date("due_date", true)!;
    const total = migrationMinor(cell("amount"));
    if (total <= 0n) throw new Error("invalid_amount");
    const currency = (cell("currency") || options.defaultCurrency).toUpperCase();
    if (!/^[A-Z]{3}$/.test(currency)) throw new Error("invalid_currency");
    const paidAt = date("paid_at");
    const statusText = cell("payment_status");
    const explicitState = statusText && Object.prototype.hasOwnProperty.call(states, normalized(statusText))
        ? states[normalized(statusText)] : undefined;
    if (statusText && !explicitState) throw new Error("unsupported_status");
    const amountText = cell("amount_paid");
    if (paidAt && amountText == null && !explicitState) throw new Error("missing_payment_evidence");
    const paid = amountText != null ? migrationMinor(amountText) : explicitState === "paid" ? total : 0n;
    const paymentCurrency = cell("payment_currency")?.toUpperCase();
    if (paid > 0n && paymentCurrency && paymentCurrency !== currency) throw new Error("payment_currency_mismatch");
    const inferred: MigrationState = paid === 0n ? "unpaid" : paid < total ? "partial" : "paid";
    const state = explicitState ?? inferred;
    if ((state === "unpaid" && (paid > 0n || paidAt)) || (state === "partial" && !(paid > 0n && paid < total))
        || (state === "paid" && paid < total) || (state === "cancelled" && (paid > 0n || paidAt))) throw new Error("contradictory_payment");
    const text = (field: DocumentImportFieldKey, max: number): string | null => {
        const value = cell(field); if (value && value.length > max) throw new Error(`invalid_${field}`); return value;
    };
    return {
        row: rowNumber, invoice_number: number, client_name: name, issue_date: issueDate, due_date: dueDate,
        delivery_date: date("delivery_date"), currency, total: migrationDecimal(total), amount_paid: migrationDecimal(paid),
        balance: migrationDecimal(total - paid), paid_at: paidAt, state,
        variable_symbol: text("variable_symbol", 32), constant_symbol: text("constant_symbol", 16), specific_symbol: text("specific_symbol", 16),
        contact_attributes: { name, registration_number: text("client_registration_number", 64), tax_id: text("client_tax_id", 64),
            vat_id: text("client_vat_id", 32), street: text("client_street", 255), city: text("client_city", 128),
            postal_code: text("client_postal_code", 32), country: text("client_country", 128), phone: text("client_phone", 64), email: text("client_email", 100) },
    };
}
/** Canonical source identity excludes row position and export timestamps, preserving buyer and payment evidence. */
export function migrationFingerprint(invoice: MigrationInvoice): string {
    const original = invoice.structured;
    // Preserve the historical summary fingerprint byte-for-byte. Structured
    // evidence is canonicalized explicitly so caller key order is irrelevant.
    const structured = original && {
        documentNumber: migrationNumberKey(original.documentNumber),
        issueDate: original.issueDate,
        currency: original.currency,
        supplierRegistrationNumber: identityKey(original.supplierRegistrationNumber),
        buyerRegistrationNumber: identityKey(original.buyerRegistrationNumber),
        buyerName: identityKey(original.buyerName),
        subtotal: original.subtotal,
        taxTotal: original.taxTotal,
        total: original.total,
        requiresFullPayment: original.requiresFullPayment,
        lines: original.lines.map(({ name, description, quantity, unit, unitPrice, lineDiscountPercent, taxRate, lineTotal }) => ({
            name, description, quantity, unit, unitPrice, lineDiscountPercent, taxRate, lineTotal,
        })),
    };
    const canonical = JSON.stringify({ ...invoice, row: 0, structured });
    return Array.from(sha256(new TextEncoder().encode(canonical)), byte => byte.toString(16).padStart(2, "0")).join("");
}
export function migrationLineMatches(invoice: MigrationInvoice, existing: EvoluDocumentRow, lines: readonly (EvoluDocumentLineRow & { ownerId?: string })[], ownerId: string): boolean {
    if (invoice.structured) {
        return migrationStructuredLinesMatch(existing.id, migrationStructuredLinePatches(existing.id, invoice.structured), lines, ownerId, true);
    }
    const id = createIdFromString(`satflux.migrated-line.v1.${existing.id}`);
    const matches = lines.filter(line => line.documentId === existing.id && line.ownerId === ownerId);
    if (matches.length !== 1 || matches[0].id !== id) return false;
    const line = matches[0];
    return Boolean(line.name) && line.quantity === "1.0000" && line.unitPrice === invoice.total
        && line.lineTotal === invoice.total && line.taxRate === "0.00" && line.lineDiscountPercent === "0.00";
}
function sameSource(invoice: MigrationInvoice, existing: EvoluDocumentRow): boolean {
    return Reflect.get(existing, "migrationFingerprint") === migrationFingerprint(invoice)
        && typeof Reflect.get(existing, "migrationSourceId") === "string"
        && existing.deliveryDate === invoice.delivery_date && existing.variableSymbol === invoice.variable_symbol
        && existing.constantSymbol === invoice.constant_symbol && existing.specificSymbol === invoice.specific_symbol
        && existing.currency === invoice.currency && existing.issueDate === invoice.issue_date && existing.dueDate === invoice.due_date
        && existing.total === invoice.total
        && (!invoice.structured || (existing.subtotal === invoice.structured.subtotal && existing.taxTotal === invoice.structured.taxTotal));
}
function resumable(invoice: MigrationInvoice, existing: EvoluDocumentRow, lines: readonly (EvoluDocumentLineRow & { ownerId?: string })[], ownerId: string): boolean {
    return sameSource(invoice, existing) && existing.status === "draft" && !Reflect.get(existing, "migrationCompletedAt")
        && existing.amountPaid === "0.00" && existing.paidAt == null
        && (!invoice.structured || migrationStructuredLinesMatch(existing.id, migrationStructuredLinePatches(existing.id, invoice.structured), lines, ownerId, false));
}
function sameExisting(invoice: MigrationInvoice, existing: EvoluDocumentRow, lines: readonly (EvoluDocumentLineRow & { ownerId?: string })[], ownerId: string): boolean {
    try {
        const status = invoice.state === "cancelled" ? "cancelled" : invoice.state === "paid" ? "paid" : "issued";
        return sameSource(invoice, existing) && Boolean(Reflect.get(existing, "migrationCompletedAt"))
            && migrationLineMatches(invoice, existing, lines, ownerId) && existing.status === status && existing.currency === invoice.currency && existing.issueDate === invoice.issue_date
            && existing.dueDate === invoice.due_date && migrationMinor(existing.total) === migrationMinor(invoice.total)
            && migrationMinor(existing.amountPaid ?? (existing.status === "paid" ? existing.total : "0")) === migrationMinor(invoice.amount_paid)
            && (existing.paidAt?.slice(0, 10) ?? null) === invoice.paid_at;
    } catch { return false; }
}
export function buildMigrationPreview(rows: string[][], mapping: DocumentImportMapping, options: {
    companyId: string; ownerId: string; defaultCurrency: string; dateFormat: ImportDateFormat;
    existing: readonly (EvoluDocumentRow & { ownerId?: string })[];
    lines?: readonly (EvoluDocumentLineRow & { ownerId?: string })[];
    structuredInvoices?: readonly MigrationStructuredInvoice[];
    companyRegistrationNumber?: string | null;
}): MigrationPreview {
    if (rows.length > 2000) throw new Error("too_many_rows");
    const entries: MigrationPreviewRow[] = [];
    const originalsByNumber = new Map<string, MigrationStructuredInvoice[]>();
    for (const original of options.structuredInvoices ?? []) {
        const key = migrationNumberKey(original.documentNumber);
        const group = originalsByNumber.get(key) ?? [];
        group.push(original);
        originalsByNumber.set(key, group);
    }
    rows.forEach((row, index) => {
        if (row.every(value => !value.trim())) return;
        const numberIndex = mapping.invoice_number;
        const number = numberIndex == null ? "" : String(row[numberIndex] ?? "").trim();
        let entry: MigrationPreviewRow;
        try {
            const invoice = parseMigrationInvoice(row, mapping, index + 2, options);
            entry = { row: index + 2, invoice_number: number, kind: "ready", invoice };
            const key = migrationNumberKey(invoice.invoice_number);
            const originals = originalsByNumber.get(key);
            if (originals) {
                const mismatch = structuredMismatch(invoice, originals, options.companyRegistrationNumber);
                if (mismatch === null) invoice.structured = originals[0];
                else { entry.kind = "conflict"; entry.code = "structured_conflict"; entry.structuredMismatch = mismatch; }
            }
        } catch (error) {
            entry = { row: index + 2, invoice_number: number, kind: "invalid", code: error instanceof Error ? error.message : "invalid_row" };
        }
        entries.push(entry);
    });
    return buildPreparedMigrationPreview(entries, options);
}

/** Apply the same tenant, duplicate, recovery and total checks to parsed provider evidence. */
export function buildPreparedMigrationPreview(entries: readonly MigrationPreviewRow[], options: {
    companyId: string; ownerId: string;
    existing: readonly (EvoluDocumentRow & { ownerId?: string })[];
    lines?: readonly (EvoluDocumentLineRow & { ownerId?: string })[];
}): MigrationPreview {
    if (entries.length > 2000) throw new Error("too_many_rows");
    const result: MigrationPreview = { rows: entries.map(entry => ({ ...entry })), totals: [], ready: 0, duplicate: 0, invalid: 0, conflict: 0 };
    const existing = options.existing.filter(doc => doc.companyId === options.companyId && doc.documentType === "invoice" && doc.ownerId === options.ownerId);
    const byNumber = new Map<string, MigrationPreviewRow[]>();
    for (const entry of result.rows) {
        const invoice = entry.invoice;
        if (!invoice) continue;
        const key = migrationNumberKey(invoice.invoice_number);
        if (entry.kind === "ready") {
            const matches = existing.filter(doc => doc.number && migrationNumberKey(doc.number) === key);
            if (matches.length) entry.kind = matches.length === 1 && resumable(invoice, matches[0], options.lines ?? [], options.ownerId) ? "ready"
                : matches.length === 1 && sameExisting(invoice, matches[0], options.lines ?? [], options.ownerId) ? "duplicate" : "conflict";
            if (entry.kind !== "ready") entry.code = entry.kind === "duplicate" ? "existing_duplicate" : "existing_conflict";
        }
        const group = byNumber.get(key) ?? [];
        group.push(entry); byNumber.set(key, group);
    }
    for (const group of byNumber.values()) {
        if (group.length < 2) continue;
        const signatures = new Set(group.map(entry => migrationFingerprint(entry.invoice!)));
        if (signatures.size > 1) group.forEach(entry => { entry.kind = "conflict"; entry.code = "in_file_conflict"; });
        else group.slice(1).forEach(entry => { if (entry.kind === "ready") { entry.kind = "duplicate"; entry.code = "in_file_duplicate"; } });
    }
    const totals = new Map<string, { count: number; total: bigint; paid: bigint; balance: bigint }>();
    for (const entry of result.rows) {
        result[entry.kind]++;
        if (entry.kind !== "ready" || !entry.invoice || entry.invoice.state === "cancelled") continue;
        const invoice = entry.invoice;
        const aggregate = totals.get(invoice.currency) ?? { count: 0, total: 0n, paid: 0n, balance: 0n };
        aggregate.count++; aggregate.total += migrationMinor(invoice.total); aggregate.paid += migrationMinor(invoice.amount_paid);
        aggregate.balance += migrationMinor(invoice.total) - migrationMinor(invoice.amount_paid); totals.set(invoice.currency, aggregate);
    }
    result.totals = [...totals].sort(([a], [b]) => a.localeCompare(b)).map(([currency, total]) => ({ currency, count: total.count,
        total: migrationDecimal(total.total), paid: migrationDecimal(total.paid), balance: migrationDecimal(total.balance) }));
    return result;
}

/** Re-parsing the selected bytes at commit must reproduce every displayed row. */
export function sameMigrationPreview(expected: MigrationPreview, current: MigrationPreview): boolean {
    return expected.rows.length === current.rows.length && expected.rows.every((before, index) => {
        const after = current.rows[index];
        return before.row === after.row && before.invoice_number === after.invoice_number && before.kind === after.kind
            && before.code === after.code && before.structuredMismatch === after.structuredMismatch
            && (before.invoice ? migrationFingerprint(before.invoice) : null) === (after.invoice ? migrationFingerprint(after.invoice) : null);
    });
}
