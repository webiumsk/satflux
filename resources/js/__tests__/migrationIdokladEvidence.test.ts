import { describe, expect, it } from "vitest";
import type { IdokladSpreadsheetResult } from "@/evolu/migrationIdoklad";
import type { IdokladPdfInvoice } from "@/evolu/migrationIdokladPdf";
import { joinIdokladMigrationEvidence } from "@/evolu/migrationIdokladEvidence";
import type { MigrationInvoice } from "@/evolu/migrationValidation";

function fixture() {
    const invoice: MigrationInvoice = {
        row: 6, invoice_number: "ID-TEST-1", client_name: "Synthetic buyer",
        issue_date: "2026-10-01", due_date: "2026-10-15", delivery_date: "2026-10-01",
        currency: "EUR", total: "123.00", amount_paid: "50.00", balance: "73.00", paid_at: null, state: "partial",
        variable_symbol: "001", constant_symbol: null, specific_symbol: null,
        contact_attributes: { name: "Synthetic buyer", registration_number: "00054321", tax_id: null,
            vat_id: null, street: null, city: null, postal_code: null, country: null, phone: null, email: null },
    };
    const source: IdokladSpreadsheetResult = { invoiceCount: 1, records: [{ kind: "parsed", row: 6,
        invoice_number: invoice.invoice_number, invoice, sourceSubtotal: "100.00", sourceTax: "23.00" }] };
    const pdf: IdokladPdfInvoice = { dueDate: invoice.due_date, deliveryDate: invoice.delivery_date!, invoice: {
        documentNumber: invoice.invoice_number, issueDate: invoice.issue_date, currency: invoice.currency,
        supplierRegistrationNumber: "00012345", buyerRegistrationNumber: "00054321", buyerName: "Synthetic buyer",
        subtotal: "100.00", taxTotal: "23.00", total: "123.00", lines: [{ name: "Synthetic service", description: null,
            quantity: "1.0000", unit: "h", unitPrice: "100.0000", lineDiscountPercent: "0.00", taxRate: "23.00", lineTotal: "123.00" }],
    } };
    const join = (originals = [pdf], originalErrors = false, companyRegistrationNumber: string | null = "00012345") =>
        joinIdokladMigrationEvidence(source, originals, { companyRegistrationNumber, originalErrors });
    return { source, invoice, pdf, join };
}

describe("iDoklad complete spreadsheet and original evidence", () => {
    it("requires complete PDF proof and retains exact VAT, source row and unknown partial date", () => {
        const f = fixture(); const row = f.join()[0];
        expect(row).toMatchObject({ kind: "ready", row: 6, invoice: { amount_paid: "50.00", balance: "73.00", paid_at: null,
            structured: { subtotal: "100.00", taxTotal: "23.00", total: "123.00", lines: [{ lineTotal: "123.00" }] } } });
        expect(f.invoice.structured).toBeUndefined();
    });
    it("keeps existing source errors visible", () => {
        const f = fixture(); f.source.records = [{ kind: "invalid", row: 6, invoice_number: "ID-TEST-1", code: "idoklad_payment_evidence" }];
        expect(f.join()[0]).toMatchObject({ kind: "invalid", code: "idoklad_payment_evidence" });
    });
    it("never turns missing or unreadable details into a zero-tax summary", () => {
        const f = fixture();
        expect(f.join([])[0]).toMatchObject({ kind: "invalid", code: "idoklad_original_missing" });
        expect(f.join([f.pdf], true)[0]).toMatchObject({ kind: "invalid", code: "idoklad_original_unreadable" });
    });
    it("rejects duplicate originals, extra originals and duplicate source numbers", () => {
        const f = fixture();
        expect(f.join([f.pdf, f.pdf])[0]).toMatchObject({ kind: "invalid", code: "idoklad_original_mismatch" });
        const extra = { ...f.pdf, invoice: { ...f.pdf.invoice, documentNumber: "OTHER-INVOICE" } };
        expect(f.join([f.pdf, extra])[0]).toMatchObject({ kind: "invalid", code: "idoklad_original_mismatch" });
        f.source.records.push({ ...f.source.records[0], row: 7 });
        expect(f.join().every(row => row.kind === "invalid")).toBe(true);
    });
    it.each([null, "00012346"])("requires a matching fresh issuer %s", issuer => {
        expect(fixture().join(undefined, false, issuer)[0]).toMatchObject({ kind: "invalid", code: "idoklad_company_mismatch" });
    });
    it("preserves eight-digit registration padding while accepting grouping spaces", () => {
        const f = fixture(); f.pdf.invoice.supplierRegistrationNumber = "00 012 345";
        expect(f.join(undefined, false, "00\u00a0012\u202f345")[0].kind).toBe("ready");
    });
    it.each(["issue", "due", "delivery", "buyer", "currency", "items"])("rejects mismatched %s", field => {
        const f = fixture();
        if (field === "issue") f.pdf.invoice.issueDate = "2026-10-02";
        if (field === "due") f.pdf.dueDate = "2026-10-16";
        if (field === "delivery") f.pdf.deliveryDate = "2026-10-02";
        if (field === "buyer") f.pdf.invoice.buyerRegistrationNumber = "00054322";
        if (field === "currency") f.pdf.invoice.currency = "USD";
        if (field === "items") f.pdf.invoice.lines = [];
        expect(f.join()[0]).toMatchObject({ kind: "invalid", code: "idoklad_original_mismatch" });
    });
    it.each(["subtotal", "taxTotal", "total"])("checks source %s independently rather than gross only", field => {
        const f = fixture(); Reflect.set(f.pdf.invoice, field, "99.00");
        expect(f.join()[0]).toMatchObject({ kind: "invalid", code: "idoklad_totals" });
    });
    it.each(["paidAmount", "payableAmount", "settled"])("does not overwrite source payments from PDF %s", field => {
        const f = fixture(); Reflect.set(f.pdf, field, field === "settled" ? true : "0.00");
        expect(f.join()[0]).toMatchObject({ kind: "invalid", code: "idoklad_payment_evidence" });
        expect(f.invoice.amount_paid).toBe("50.00");
    });
});
