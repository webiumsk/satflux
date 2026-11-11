import { createIdFromString } from "@evolu/common";
import { describe, expect, it } from "vitest";
import { buildMigrationPreview, migrationMinor, migrationDecimal, migrationFingerprint, parseMigrationInvoice, sameMigrationPreview } from "@/evolu/migrationValidation";
import type { DocumentImportMapping } from "@/composables/useDocumentImportFields";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { MigrationStructuredInvoice } from "@/evolu/migrationIsdoc";
const mapping: DocumentImportMapping = { invoice_number: 0, client_name: 1, issue_date: 2, due_date: 3, amount: 4,
    currency: 5, paid_at: 6, amount_paid: 7, payment_status: 8, payment_currency: 9 };
const row = (number = "A1", total = "100.00", paid = "", state = "", paidAt = "", currency = "EUR") =>
    [number, "Example Customer", "2025-01-01", "2025-01-15", total, currency, paidAt, paid, state, currency];
const options = { defaultCurrency: "EUR", dateFormat: "ymd_dash" as const, companyId: "company-a", ownerId: "owner-a", existing: [] };
const preview = (rows: string[][], existing: (EvoluDocumentRow & { ownerId?: string })[] = [], lines: never[] = []) => buildMigrationPreview(rows, mapping, { ...options, existing, lines });
const structured: MigrationStructuredInvoice = {
    documentNumber: "a1", issueDate: "2025-01-01", currency: "EUR", supplierRegistrationNumber: "00012345",
    buyerRegistrationNumber: null, buyerName: "Example Customer", subtotal: "100.00", taxTotal: "0.00", total: "100.00",
    lines: [{ name: "Source item", description: null, quantity: "2.0000", unit: "pcs", unitPrice: "50.00",
        lineDiscountPercent: "0.00", taxRate: "0.00", lineTotal: "100.00" }],
};
const withOriginals = (originals: readonly MigrationStructuredInvoice[], companyRegistrationNumber: string | null = "00012345") =>
    buildMigrationPreview([row()], mapping, { ...options, structuredInvoices: originals, companyRegistrationNumber });

describe("historical migration financial validation", () => {
    it.each([["1 234,50", "1234.50"], ["1.234,50", "1234.50"], ["1,234.50", "1234.50"], ["0.10", "0.10"], ["0001", "1.00"]])("normalizes exact source decimals %s", (input, expected) => {
        expect(migrationDecimal(migrationMinor(input))).toBe(expected);
    });
    it.each(["12abc", "1.234", "Infinity", "-1", "1e4", "1,2,3", "", "9999999999999", "1 2", "12\t34", "1 23 4.56", "1  234", "12 34", "1\u00a0234\u00a056"])('rejects ambiguous/unsupported amounts %s', input => expect(() => migrationMinor(input)).toThrow());
    it("computes exact totals per currency for the complete file, including rows after the first ten", () => {
        const rows = Array.from({ length: 15 }, (_, index) => row(`A${index}`, "0.10"));
        rows.push(row("USD1", "2.00", "0.50", "partial", "", "USD"));
        const result = preview(rows);
        expect(result.ready).toBe(16);
        expect(result.rows).toHaveLength(16);
        expect(result.totals).toEqual([
            { currency: "EUR", count: 15, total: "1.50", paid: "0.00", balance: "1.50" },
            { currency: "USD", count: 1, total: "2.00", paid: "0.50", balance: "1.50" },
        ]);
    });
    it.each([
        [row("A1"), "unpaid", "0.00", null],
        [row("A1", "100", "40", "partial"), "partial", "40.00", null],
        [row("A1", "100", "", "paid"), "paid", "100.00", null],
        [row("A1", "100", "100", "", "2025-01-10"), "paid", "100.00", "2025-01-10"],
        [row("A1", "100", "120", "paid", "2025-01-10"), "paid", "120.00", "2025-01-10"],
        [row("A1", "100", "0", "cancelled"), "cancelled", "0.00", null],
    ])("preserves supported source payment states and never invents a missing date", (input, state, paid, date) => {
        const result = parseMigrationInvoice(input, mapping, 2, options);
        expect(result).toMatchObject({ state, amount_paid: paid, paid_at: date });
    });
    it.each([
        row("A1", "100", "40", "paid"), row("A1", "100", "100", "unpaid"), row("A1", "100", "0", "partial"),
        row("A1", "100", "100", "cancelled"), row("A1", "100", "", "mystery"), row("A1", "100", "", "", "not-a-date"),
    ].map(input => [input]))("blocks contradictory or unknown payment evidence", input => expect(preview([input]).invalid).toBe(1));
    it.each(["constructor", "__proto__", "toString", "hasOwnProperty"])("rejects prototype property status %s", status => {
        expect(preview([row("A1", "100", "", status)]).rows[0].code).toBe("unsupported_status");
    });
    it("refuses to infer a full payment from a payment date alone", () => {
        expect(preview([row("A1", "100", "", "", "2025-01-10")]).rows[0].code).toBe("missing_payment_evidence");
    });
    it("rejects an unresolved payment in another currency", () => {
        const input = row("A1", "100", "40", "partial"); input[9] = "USD";
        expect(preview([input]).rows[0].code).toBe("payment_currency_mismatch");
    });
    it("marks exact in-file repeats as duplicates and all conflicting repeats as conflicts", () => {
        expect(preview([row(), row()])).toMatchObject({ ready: 1, duplicate: 1 });
        expect(preview([row(), row("A1", "200")])).toMatchObject({ ready: 0, conflict: 2 });
    });
    it("binds structured lines by document evidence and preserves the previous summary identity", () => {
        const summary = preview([row()]);
        expect(migrationFingerprint(summary.rows[0].invoice!)).toBe("bb5615fcb6875c7bfd1e31a1add95fd066a61186b2ee46812e2d550623367b13");
        const detail = withOriginals([structured, { ...structured, documentNumber: "A2" }]);
        expect(detail.rows[0]).toMatchObject({ kind: "ready", invoice: { structured, amount_paid: "0.00" } });
        expect(detail.totals).toEqual(summary.totals);
        expect(sameMigrationPreview(detail, withOriginals([structured]))).toBe(true);
        expect(sameMigrationPreview(detail, summary)).toBe(false);
        const changed = withOriginals([{ ...structured, lines: [{ ...structured.lines[0], name: "Different item" }] }]);
        expect(sameMigrationPreview(detail, changed)).toBe(false);
    });
    it("only binds zero-payable item evidence to an independently and exactly paid spreadsheet row", () => {
        const original = { ...structured, requiresFullPayment: true as const };
        const check = (input: string[]) => buildMigrationPreview([input], mapping, {
            ...options, companyRegistrationNumber: "00012345", structuredInvoices: [original],
        });
        expect(check(row("A1", "100", "100", "paid")).rows[0]).toMatchObject({
            kind: "ready", invoice: { structured: original, amount_paid: "100.00" },
        });
        for (const input of [
            row(), row("A1", "100", "40", "partial"), row("A1", "100", "110", "paid"),
            row("A1", "100", "0", "cancelled"),
        ]) {
            expect(check(input).rows[0]).toMatchObject({ kind: "conflict", code: "structured_conflict", structuredMismatch: "payment" });
        }
        expect(check(row("A1", "100", "", "", "2025-01-10")).rows[0].code).toBe("missing_payment_evidence");
    });
    it.each([
        [{ supplierRegistrationNumber: "12345" }, "supplier"],
        [{ issueDate: "2025-01-02" }, "date"],
        [{ currency: "USD" }, "currency"],
        [{ total: "99.99" }, "total"],
        [{ buyerName: "Another Customer" }, "buyer"],
    ] as const)("identifies a contradictory original even when the invoice number matches", (change, reason) => {
        expect(withOriginals([{ ...structured, ...change }]).rows[0]).toMatchObject({
            kind: "conflict", code: "structured_conflict", structuredMismatch: reason,
        });
    });
    it("requires a unique original and known target company identity", () => {
        expect(withOriginals([structured, structured]).rows[0].code).toBe("structured_conflict");
        expect(withOriginals([structured], null).rows[0].code).toBe("structured_conflict");
        expect(withOriginals([{ ...structured, documentNumber: "A2" }]).rows[0].invoice?.structured).toBeUndefined();
    });
    it("checks buyer registration when both sources contain it", () => {
        const mappedBuyer = { ...mapping, client_registration_number: 10 };
        const invoice = [...row(), "00045678"];
        const original = { ...structured, buyerRegistrationNumber: "45678" };
        const run = (buyer: MigrationStructuredInvoice) => buildMigrationPreview([invoice], mappedBuyer, {
            ...options, companyRegistrationNumber: "00012345", structuredInvoices: [buyer],
        });
        expect(run(original).rows[0].code).toBe("structured_conflict");
        expect(run({ ...original, buyerRegistrationNumber: "00045678" }).rows[0].invoice?.structured).toBeDefined();
    });
    it("matches grouped eight-digit registration numbers while preserving their digits", () => {
        const original = {
            ...structured,
            supplierRegistrationNumber: "00 012 345",
            buyerRegistrationNumber: "00\u00a0045\u202f678",
        };
        const input = [...row(), "00045678"];
        const mappedBuyer = { ...mapping, client_registration_number: 10 };
        const run = (companyRegistrationNumber: string, evidence = original) => buildMigrationPreview([input], mappedBuyer, {
            ...options, companyRegistrationNumber, structuredInvoices: [evidence],
        }).rows[0];
        expect(run("00012345")).toMatchObject({ kind: "ready", invoice: { structured: original } });
        expect(run("00 012 345", { ...original, supplierRegistrationNumber: "00012345" }).kind).toBe("ready");
        expect(run("12345").structuredMismatch).toBe("supplier");
        expect(run("00012346").structuredMismatch).toBe("supplier");
        expect(run("00012345", { ...original, buyerRegistrationNumber: "00 045 679" }).structuredMismatch).toBe("buyer");
    });
    it("matches duplicates only in the exact current company and owner", () => {
        const base = { id: "existing", companyId: "company-a", ownerId: "owner-a", documentType: "invoice", number: "A1",
            status: "issued", total: "100.00", currency: "EUR", amountPaid: "0.00", paidAt: null, issueDate: "2025-01-01", dueDate: "2025-01-15",
            deliveryDate: null, variableSymbol: null, constantSymbol: null, specificSymbol: null,
            migrationSourceId: "archive", migrationCompletedAt: "2025-01-01T00:00:00Z", migrationFingerprint: migrationFingerprint(parseMigrationInvoice(row(), mapping, 2, options)) };
        const line = { id: createIdFromString("satflux.migrated-line.v1.existing"), documentId: "existing", ownerId: "owner-a",
            name: "Imported item", quantity: "1.0000", unitPrice: "100.00", lineTotal: "100.00", taxRate: "0.00", lineDiscountPercent: "0.00" } as never;
        expect(preview([row()], [base as never], [line])).toMatchObject({ ready: 0, duplicate: 1 });
        expect(preview([row()], [base as never])).toMatchObject({ ready: 0, conflict: 1 });
        expect(preview([row()], [{ ...base, ownerId: "other-owner" } as never])).toMatchObject({ ready: 1 });
        expect(preview([row()], [{ ...base, companyId: "other-company" } as never])).toMatchObject({ ready: 1 });
        expect(preview([row()], [{ ...base, total: "200.00" } as never])).toMatchObject({ ready: 0, conflict: 1 });
        expect(preview([row()], [{ ...base, migrationFingerprint: null } as never])).toMatchObject({ ready: 0, conflict: 1 });
        const anotherBuyer = row(); anotherBuyer[1] = "Different Buyer";
        expect(preview([anotherBuyer], [base as never])).toMatchObject({ ready: 0, conflict: 1 });
    });
    it("detects an invalid final row and excludes cancellations from receivable totals", () => {
        const result = preview([...Array.from({ length: 12 }, (_, index) => row(`A${index}`)), row("C1", "300", "0", "cancelled"), row("BAD", "broken")]);
        expect(result.invalid).toBe(1); expect(result.totals[0].total).toBe("1200.00");
    });
    it("enforces the row cap before parsing", () => expect(() => preview(Array.from({ length: 2001 }, () => row()))).toThrow("too_many_rows"));
});
