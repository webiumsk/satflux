import type { IdokladSpreadsheetResult } from "./migrationIdoklad";
import type { IdokladPdfInvoice } from "./migrationIdokladPdf";
import { migrationNumberKey, type MigrationPreviewRow } from "./migrationValidation";

function identity(value: string | null | undefined): string {
    return value?.normalize("NFKC").trim().replace(/\s+/g, " ").toLowerCase() ?? "";
}

function registration(value: string | null | undefined): string {
    const key = identity(value);
    const digits = key.replace(/ /g, "");
    return /^\d{8}$/.test(digits) ? digits : key;
}

/** Complete source/PDF proof is required before any iDoklad row becomes ready. */
export function joinIdokladMigrationEvidence(
    source: IdokladSpreadsheetResult,
    originals: readonly IdokladPdfInvoice[],
    options: { companyRegistrationNumber: string | null; originalErrors?: boolean },
): MigrationPreviewRow[] {
    const sourceCounts = new Map<string, number>();
    for (const record of source.records) {
        const key = migrationNumberKey(record.invoice_number);
        sourceCounts.set(key, (sourceCounts.get(key) ?? 0) + 1);
    }
    const byNumber = new Map<string, IdokladPdfInvoice[]>();
    for (const original of originals) {
        const key = migrationNumberKey(original.invoice.documentNumber);
        byNumber.set(key, [...(byNumber.get(key) ?? []), original]);
    }
    const extraOriginals = [...byNumber.keys()].some(key => !sourceCounts.has(key));
    const seller = registration(options.companyRegistrationNumber);
    return source.records.map((record): MigrationPreviewRow => {
        const base = { row: record.row, invoice_number: record.invoice_number };
        if (record.kind === "invalid") return { ...base, kind: "invalid", code: record.code };
        const invalid = (code: string): MigrationPreviewRow => ({ ...base, kind: "invalid", code });
        if (options.originalErrors) return invalid("idoklad_original_unreadable");
        if (extraOriginals || sourceCounts.get(migrationNumberKey(record.invoice_number)) !== 1) {
            return invalid("idoklad_original_mismatch");
        }
        const candidates = byNumber.get(migrationNumberKey(record.invoice_number)) ?? [];
        if (!candidates.length) return invalid("idoklad_original_missing");
        if (candidates.length !== 1) return invalid("idoklad_original_mismatch");
        const original = candidates[0];
        const detail = original.invoice;
        const invoice = record.invoice;
        if (!seller || registration(detail.supplierRegistrationNumber) !== seller) {
            return invalid("idoklad_company_mismatch");
        }
        const buyer = registration(invoice.contact_attributes.registration_number);
        const originalBuyer = registration(detail.buyerRegistrationNumber);
        const sameBuyer = buyer || originalBuyer
            ? buyer === originalBuyer
            : identity(invoice.client_name) === identity(detail.buyerName);
        if (!sameBuyer || detail.issueDate !== invoice.issue_date || original.dueDate !== invoice.due_date
            || original.deliveryDate !== invoice.delivery_date || detail.currency !== invoice.currency || !detail.lines.length) {
            return invalid("idoklad_original_mismatch");
        }
        if (detail.subtotal !== record.sourceSubtotal || detail.taxTotal !== record.sourceTax || detail.total !== invoice.total) {
            return invalid("idoklad_totals");
        }
        if ((original.paidAmount !== undefined && original.paidAmount !== invoice.amount_paid)
            || (original.payableAmount !== undefined && original.payableAmount !== invoice.balance)
            || ((original.settled || detail.requiresFullPayment) && (invoice.state !== "paid"
                || invoice.amount_paid !== invoice.total || invoice.balance !== "0.00"))) {
            return invalid("idoklad_payment_evidence");
        }
        return { ...base, kind: "ready", invoice: { ...invoice, structured: detail } };
    });
}
