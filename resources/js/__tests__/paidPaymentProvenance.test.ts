import { describe, expect, it, vi } from "vitest";
import { paidPaymentMethodTranslationKey, wooCommercePaidPaymentMethod } from "@/evolu/paidPaymentMethod";
import { resolveImportSettleActions } from "@/evolu/integrationInboxPaid";
import { resolveInvoicePreviewContact } from "@/composables/invoicePreviewContact";
import { preserveWooBuyerTaxIdentity } from "@/evolu/wooBuyerTaxIdentity";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { DocumentId, InvoicingLocalSchema } from "@/evolu/schema";
import type { Evolu } from "@evolu/common/local-first";

vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "companies", allContactsQuery: "contacts", allDocumentLinesQuery: "lines",
    allDocumentsQuery: "documents", allNumberSeriesQuery: "series", allCompanyStockBalancesQuery: "balances",
    allCompanyStockItemsQuery: "items", allCompanyStockMovementsQuery: "movements", allCompanyWarehousesQuery: "warehouses",
}));
import { calcDocumentTotals, markLocalDocumentPaidWithAmount, unmarkLocalDocumentPaid } from "@/evolu/documentCrud";

describe("recorded payment provenance", () => {
    it.each([
        ["bacs", "bank_transfer"], ["stripe", "card"], ["woocommerce_payments", "card"],
        ["cod", "cash"], ["btcpaygf_satoshi_tickets", "btcpay"], ["btcpay", "btcpay"],
        ["custom_satflux_card", "other"], ["custom_gateway", "other"], ["", null],
    ])("maps Woo gateway %s without guessing settlement", (gateway, expected) => {
        expect(wooCommercePaidPaymentMethod(gateway)).toBe(expected);
    });

    it("does not settle unpaid bank or card orders to obtain a method", () => {
        for (const method of ["bacs", "stripe", "cod"]) {
            expect(resolveImportSettleActions({ payment_method: method, is_paid: false }).markPaid).toBe(false);
        }
    });

    it.each(["manual", "bank_transfer", "card", "cash", "btcpay", "other"] as const)("persists %s only at full payment", (method) => {
        const update = vi.fn((_table: string, _row: Record<string, unknown>) => ({ ok: true, value: { id: "doc" } }));
        const evolu = { update, insert: vi.fn() } as unknown as Evolu<InvoicingLocalSchema>;
        const doc = { id: "doc", status: "issued", total: "100", paidAt: null } as EvoluDocumentRow;
        markLocalDocumentPaidWithAmount(evolu, doc.id, [doc], 50, 0.01, method);
        expect(update.mock.calls[0][1]).toMatchObject({ status: "issued", paidPaymentMethod: null });
        markLocalDocumentPaidWithAmount(evolu, doc.id, [doc], 100, 0.01, method);
        expect(update.mock.calls[1][1]).toMatchObject({ status: "paid", paidPaymentMethod: method });
        update.mockClear();
        markLocalDocumentPaidWithAmount(evolu, doc.id, [{ ...doc, status: "paid", paidPaymentMethod: method }], 100, 0.01, "manual");
        expect(update).not.toHaveBeenCalled();
        unmarkLocalDocumentPaid(evolu, "doc" as DocumentId);
        expect(update.mock.calls[0][1]).toMatchObject({ paidPaymentMethod: null, paidAt: null });
    });

    it("uses unspecified for historical missing or unknown provenance", () => {
        expect(paidPaymentMethodTranslationKey(null)).toBe("invoicing.payment_method_unspecified");
        expect(paidPaymentMethodTranslationKey("invented")).toBe("invoicing.payment_method_unspecified");
    });

    it("preserves a newer payment when a caller holds a stale issued row", () => {
        const update = vi.fn();
        const stale = { id: "doc", status: "issued", total: "100" } as EvoluDocumentRow;
        const current = { ...stale, status: "paid", paidPaymentMethod: "bank_transfer" };
        const evolu = { update, getQueryRows: () => [current] } as unknown as Evolu<InvoicingLocalSchema>;
        expect(markLocalDocumentPaidWithAmount(evolu, stale.id, [stale], 100, 0.01, "manual").ok).toBe(true);
        expect(update).not.toHaveBeenCalled();
    });

    it("checks the current cancellation and total before recording payment", () => {
        const update = vi.fn(() => ({ ok: true, value: { id: "doc" } }));
        const stale = { id: "doc", status: "issued", total: "100" } as EvoluDocumentRow;
        let current = { ...stale, status: "cancelled" };
        const evolu = { update, insert: vi.fn(), getQueryRows: () => [current] } as unknown as Evolu<InvoicingLocalSchema>;
        expect(markLocalDocumentPaidWithAmount(evolu, stale.id, [stale], 100).ok).toBe(false);
        expect(update).not.toHaveBeenCalled();
        current = { ...stale, status: "issued", total: "200" };
        markLocalDocumentPaidWithAmount(evolu, stale.id, [stale], 100);
        expect(update).toHaveBeenCalledWith("document", expect.objectContaining({ status: "issued", amountPaid: "100.00" }));
    });
});

describe("buyer identity in preview", () => {
    const live = { registration_number: "NEW", tax_id: "NEW-TAX", vat_id: "NEW-VAT" };
    const frozen = { registration_number: "OLD", tax_id: "OLD-TAX", vat_id: "OLD-VAT" };
    it("uses live draft data and issued snapshot data", () => {
        expect(resolveInvoicePreviewContact("draft", live, { contact: frozen }, null)).toEqual(live);
        expect(resolveInvoicePreviewContact("paid", live, { contact: frozen }, null)).toEqual(frozen);
        expect(resolveInvoicePreviewContact("issued", live, null, frozen)).toEqual(frozen);
    });
    it("preserves explicit empty buyer in a frozen snapshot", () => {
        expect(resolveInvoicePreviewContact("paid", live, { contact: null }, null)).toBeNull();
    });
    it("retains saved tax identity when Woo sends empty identifiers", () => {
        const existing = { registrationNumber: "12345678", taxId: "1234567890", vatId: "SK1234567890" };
        expect(preserveWooBuyerTaxIdentity({ registration_number: "", tax_id: "  ", vat_id: "" }, existing))
            .toEqual({ registration_number: "12345678", tax_id: "1234567890", vat_id: "SK1234567890" });
        expect(preserveWooBuyerTaxIdentity({ registration_number: "NEW", tax_id: "NEW-TAX", vat_id: "NEW-VAT" }, existing)).toEqual(live);
    });
});

describe("signed invoice canonical amounts", () => {
    it("keeps canonical line amounts before the document discount", () => {
        const lines = [100, -20].map(unit_price => ({
            name: "Synthetic item", description: null, quantity: 1, unit: "pcs", unit_price,
            line_discount_percent: 0, tax_rate: 23, company_stock_item_id: null, company_warehouse_id: null,
        }));
        expect(calcDocumentTotals(lines, 10, 23, () => true, () => 23)).toEqual({
            subtotal: "72.00", taxTotal: "16.56", total: "88.56", lineTotals: ["123.00", "-24.60"],
        });
    });
    it("rounds each signed net and tax according to the configured method", () => {
        const lines = [1, -0.1].map(unit_price => ({
            name: "Synthetic item", description: null, quantity: 0.5, unit: "pcs", unit_price,
            line_discount_percent: 0, tax_rate: 23, company_stock_item_id: null, company_warehouse_id: null,
        }));
        expect(calcDocumentTotals(lines, 0, 23, () => true, () => 23)).toMatchObject({ subtotal: "0.45", taxTotal: "0.11", total: "0.56" });
        expect(calcDocumentTotals(lines, 0, 23, () => true, () => 23, "per_document")).toMatchObject({ subtotal: "0.45", taxTotal: "0.11", total: "0.56" });
    });
});

it.each(["per_line", "per_document", "none"])("quantizes signed canonical net and tax before aggregate for %s", method => {
    const lines = [0.03, 0.03, -0.01].map(unit_price => ({
        name: "Synthetic item", description: null, quantity: 1, unit: "pcs", unit_price,
        line_discount_percent: 0, tax_rate: 23, company_stock_item_id: null, company_warehouse_id: null,
    }));
    expect(calcDocumentTotals(lines, 0, 23, () => true, () => 23, method)).toMatchObject({ subtotal: "0.05", taxTotal: "0.02", total: "0.07" });
});
