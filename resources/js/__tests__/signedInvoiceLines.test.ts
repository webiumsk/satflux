import { describe, expect, it } from "vitest";
import { hasHistoricalSignedSource, signedInvoiceError, signedLineAmounts, signedMoneyRound, supportsNegativeQuantity } from "@/evolu/signedInvoiceLines";

const lines = [
    { quantity: 1, unitPrice: 100, discount: 0, taxRate: 23 },
    { quantity: 1, unitPrice: -20, discount: 0, taxRate: 23 },
];
const amounts = { subtotal: "80.00", taxTotal: "18.40", total: "98.40" };
describe("signed invoice eligibility", () => {
    it.each([0, 8.875, 8.8751, 100])("retains supported percentage precision for rate %s", taxRate => {
        expect(signedInvoiceError("invoice", lines.map(line => ({ ...line, taxRate })), { jurisdiction: "us" })).toBeNull();
    });
    it.each([-0.01, 100.0001, 8.87501, Infinity, NaN])("rejects unsupported percentage rate %s", taxRate => {
        expect(signedInvoiceError("invoice", lines.map(line => ({ ...line, taxRate })), { jurisdiction: "us" })).toBe("unsupported_negative_lines");
    });
    it.each(["stockItemId", "warehouseId"])("rejects negative-price financial deductions linked through %s", field => {
        expect(signedInvoiceError("invoice", [lines[0], { ...lines[1], [field]: "stock-link" }], { jurisdiction: "eu_sk" })).toBe("deduction_stock_not_supported");
    });
    it.each(["eu_sk", "eu_cz", "us"])("permits a positive deduction invoice for %s", jurisdiction => {
        expect(signedInvoiceError("invoice", lines, { jurisdiction }, amounts)).toBeNull();
    });
    it.each(["quote", "proforma", "credit_note"])("preserves unsupported signed %s boundary", type => {
        expect(signedInvoiceError(type, lines, { jurisdiction: "eu_sk" }, amounts)).toBe("unsupported_negative_lines");
    });
    it("rejects unsupported jurisdiction and external sales-tax provider", () => {
        expect(signedInvoiceError("invoice", lines, { jurisdiction: "eu_de" }, amounts)).toBe("unsupported_negative_lines");
        expect(signedInvoiceError("invoice", lines, { jurisdiction: "us", app_settings: { us_sales_tax_provider: "stripe" } }, amounts)).toBe("unsupported_negative_lines");
    });
    it("rejects a negative tax bucket despite a positive overall invoice", () => {
        const mixedRates = [{ ...lines[0], taxRate: 0 }, lines[1]];
        expect(signedInvoiceError("invoice", mixedRates, { jurisdiction: "eu_sk" }, { subtotal: 80, taxTotal: -4.60, total: 75.40 })).toBe("positive_total_required");
        expect(signedInvoiceError("invoice", [...mixedRates, { quantity: 1, unitPrice: 200, discount: 0, taxRate: 10 }], { jurisdiction: "eu_sk" }, { subtotal: 280, taxTotal: 15.40, total: 295.40 })).toBe("positive_total_required");
    });
    it("rejects lossy price precision only when using signed lines", () => {
        expect(signedInvoiceError("invoice", [{ ...lines[0], unitPrice: 100.001 }, lines[1]], null, amounts)).toBe("unsupported_negative_lines");
        expect(signedInvoiceError("invoice", [{ ...lines[0], unitPrice: 100.001 }], null, amounts)).toBeNull();
    });
    it("allows invalid draft totals to be edited but rejects issuing them", () => {
        expect(signedInvoiceError("invoice", lines, { jurisdiction: "eu_sk" })).toBeNull();
        expect(signedInvoiceError("invoice", lines, { jurisdiction: "eu_sk" }, { subtotal: -1, taxTotal: 0, total: -1 })).toBe("positive_total_required");
    });
});

describe("PHP-compatible signed money rounding", () => {
    it.each([
        [0.5 * 20.15, 10.08], [1.005, 1.01], [2.675, 2.68],
        [1234.565, 1234.57], [100000000.015, 100000000.02],
        [999999999999.985, 999999999999.99],
        [10.074999999, 10.07], [10.075000001, 10.08],
        [0.00499999999, 0], [0.004999999999999999, 0.01], [0.005, 0.01],
    ])("rounds %s and its opposite away from a half cent", (value, expected) => {
        expect(signedMoneyRound(value)).toBe(expected);
        expect(signedMoneyRound(-value)).toBe(-expected);
    });
    it("rounds signed net, percentage tax and gross independently", () => {
        const positive = { quantity: 0.5, unitPrice: 20.15, discount: 0, taxRate: 23 };
        expect(signedLineAmounts(positive, "per_line")).toEqual({ net: 10.08, tax: 2.32, gross: 12.4 });
        expect(signedLineAmounts({ ...positive, quantity: -0.5 }, "per_document")).toEqual({ net: -10.08, tax: -2.32, gross: -12.39 });
    });
});

it("ordinary saved signed invoices keep live editor totals while migrated sources preserve history", () => {
    expect(hasHistoricalSignedSource({ id: "ordinary", total: "98.40" }, true)).toBe(false);
    expect(hasHistoricalSignedSource({ id: "imported", migrationFingerprint: "source-digest" }, true)).toBe(true);
    expect(hasHistoricalSignedSource({ migrationFingerprint: "source-digest" }, false)).toBe(false);
});


describe("negative quantity constraints", () => {
    const quantityLines = [{ ...lines[0] }, { ...lines[1], quantity: -1, unitPrice: 20 }];
    it.each(["eu_sk", "eu_cz", "us"])("supports negative quantity with positive price for %s", jurisdiction => {
        expect(signedInvoiceError("invoice", quantityLines, { jurisdiction }, amounts)).toBeNull();
    });
    it.each(["eu_de", "eu_at"])("preserves unsupported jurisdiction %s", jurisdiction => {
        expect(signedInvoiceError("invoice", quantityLines, { jurisdiction }, amounts)).toBe("unsupported_negative_lines");
    });
    it("rejects Stripe tax and double negative lines", () => {
        expect(signedInvoiceError("invoice", quantityLines, { jurisdiction: "us", app_settings: { us_sales_tax_provider: "stripe_tax" } })).toBe("unsupported_negative_lines");
        expect(signedInvoiceError("invoice", [{ ...quantityLines[1], unitPrice: -20 }], { jurisdiction: "eu_sk" })).toBe("unsupported_negative_lines");
    });
    it.each([[-1e-20, 20], [-1, 1e-20], [-0.00001, 20], [-1, 0.001]])("rejects lossy signed quantity %s and price %s", (quantity, unitPrice) => {
        expect(signedInvoiceError("invoice", [{ ...quantityLines[1], quantity, unitPrice }], { jurisdiction: "eu_sk" })).toBe("unsupported_negative_lines");
    });
});


it("preserves credit notes with negative quantities but keeps negative prices invoice-only", () => {
    const quantityLines = [{ ...lines[0] }, { ...lines[1], quantity: -1, unitPrice: 20 }];
    expect(supportsNegativeQuantity("invoice")).toBe(true);
    expect(supportsNegativeQuantity("credit_note")).toBe(true);
    expect(supportsNegativeQuantity("proforma")).toBe(false);
    expect(supportsNegativeQuantity("quote")).toBe(false);
    expect(signedInvoiceError("credit_note", quantityLines, { jurisdiction: "eu_sk" }, amounts)).toBeNull();
    expect(signedInvoiceError("credit_note", lines, { jurisdiction: "eu_sk" }, amounts)).toBe("unsupported_negative_lines");
    expect(signedInvoiceError("credit_note", quantityLines, { jurisdiction: "eu_sk" }, { subtotal: -1, taxTotal: 0, total: -1 })).toBe("positive_total_required");
});
