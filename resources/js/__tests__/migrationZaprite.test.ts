import { describe, expect, it } from "vitest";
import { parseZapriteMigrationExport } from "@/evolu/migrationZaprite";
import { buildPreparedMigrationPreview } from "@/evolu/migrationValidation";
import partialFixture from "./fixtures/migration-zaprite/zaprite-partial.json";

function contact() {
    return {
        id: "contact-one", legalName: "Synthetic Buyer", contactName: null, displayName: "Buyer",
        email: "buyer@example.invalid", taxId: "SOURCE-TAX", defaultCurrency: "BTC",
        billingAddress: { line1: "Example street 1", line2: "Unit 2", zipCode: "12345", city: "Example town", state: null, countryCode: "SK" },
        paymentProfiles: [],
    };
}

function line() {
    return {
        id: "line-one", name: "Consulting", description: null, unitPrice: 10000, quantity: 2,
        taxRateBips: 0, taxAmount: 0, discountRateBips: 0, discountAmount: 0,
    };
}

function transaction() {
    return {
        id: "transaction-one", date: "2026-11-11T14:25:00.000Z", amount: 5000, currency: "EUR",
        amountInOrderCurrency: null, discountsInOrderCurrency: [], orderCurrency: null,
        amountInAccountingCurrency: null, accountingCurrency: null, method: "BANK", status: "CONFIRMED",
        externalRef: null,
    };
}

function order(id = "order-one", currency = "EUR") {
    const buyer = contact();
    return {
        id, orgId: "organization-one", orderType: "invoice", totalAmount: 29900, currency,
        status: "PENDING", paidAt: null as string | null, promo: null as unknown, coupons: [] as unknown[],
        contact: buyer, transactions: [] as Record<string, unknown>[],
        invoice: {
            id: "invoice-" + id, number: "INV-" + id, orgId: "organization-one", status: "SENT", date: "2026-11-01",
            daysToPay: 14 as number | null, customer: buyer,
            lineItems: [line(), { ...line(), id: "line-two", name: "Hosting", unitPrice: 9900, quantity: 1 }],
        },
    };
}

function envelope(orders = [order()]) {
    return {
        version: 1, provider: "zaprite",
        pages: [{ items: orders, meta: { itemsCount: orders.length, pagesCount: 1, page: 1, perPage: 100 } }],
    };
}

function bytes(value: unknown): Uint8Array {
    return new TextEncoder().encode(JSON.stringify(value));
}

function parse(orders = [order()], excludeUnsupportedCurrencies = false) {
    return parseZapriteMigrationExport(bytes(envelope(orders)), { excludeUnsupportedCurrencies });
}

function errorCode(source: ReturnType<typeof order>): string | undefined {
    return parse([source]).rows[0]?.code;
}

describe("Zaprite source and payment preservation", () => {
    it("imports source invoice identity, items, buyer and strict UTC due dates", () => {
        const result = parse();
        expect(result.organizationId).toBe("organization-one");
        expect(result.invoiceCount).toBe(1);
        expect(result.ignoredOrders).toBe(0);
        expect(result.rows[0]).toMatchObject({
            row: 2, invoice_number: "INV-order-one", kind: "ready",
            invoice: {
                issue_date: "2026-11-01", due_date: "2026-11-15", currency: "EUR", total: "299.00",
                amount_paid: "0.00", balance: "299.00", paid_at: null, state: "unpaid",
                contact_attributes: { name: "Synthetic Buyer", registration_number: null, tax_id: "SOURCE-TAX", vat_id: null, street: "Example street 1\nUnit 2" },
                sourceIdentity: { provider: "zaprite", organizationId: "organization-one", invoiceId: "invoice-order-one", orderId: "order-one" },
                structured: { supplierRegistrationNumber: "", subtotal: "299.00", taxTotal: "0.00", total: "299.00" },
            },
        });
        expect(result.rows[0].invoice?.structured?.lines.map(item => [item.quantity, item.unitPrice, item.lineTotal]))
            .toEqual([["2.0000", "100.00", "200.00"], ["1.0000", "99.00", "99.00"]]);
    });

    it("preserves the actual confirmed partial payment with the API's same-currency fallback", () => {
        expect(parseZapriteMigrationExport(bytes(partialFixture)).rows[0].invoice).toMatchObject({
            amount_paid: "50.00", total: "299.00", balance: "249.00", state: "partial", paid_at: "2026-11-11",
        });
    });

    it("uses an explicitly converted order-currency amount without inferring a crypto exchange rate", () => {
        const source = order();
        source.status = source.invoice.status = "UNDERPAID";
        source.transactions = [{ ...transaction(), currency: "BTC", amount: 1000, amountInOrderCurrency: 5000, orderCurrency: "EUR" }];
        expect(parse([source]).rows[0].invoice?.amount_paid).toBe("50.00");
        source.transactions[0].amountInOrderCurrency = null;
        source.transactions[0].orderCurrency = null;
        expect(errorCode(source)).toBe("zaprite_payment_evidence");
    });

    it.each(["PENDING", "CANCELED"])("does not count %s receipts or require an invented conversion", status => {
        const source = order();
        source.status = "PROCESSING";
        source.transactions = [{ ...transaction(), status, currency: "BTC" }];
        expect(parse([source]).rows[0].invoice).toMatchObject({ state: "unpaid", amount_paid: "0.00", paid_at: null });
    });

    it.each(["PAID", "OVERPAID"])("rejects %s and manual paid timestamps without confirmed payment evidence", status => {
        const source = order();
        source.status = source.invoice.status = status;
        expect(errorCode(source)).toBe("zaprite_payment_evidence");
        source.status = "PENDING";
        source.invoice.status = "SENT";
        source.paidAt = "2026-11-11T14:25:00.000Z";
        expect(errorCode(source)).toBe("zaprite_payment_evidence");
    });

    it("retains overpayment and the actual last confirmed payment date", () => {
        const source = order();
        source.status = source.invoice.status = "OVERPAID";
        source.transactions = [
            { ...transaction(), amount: 20000 },
            { ...transaction(), id: "transaction-two", amount: 15000, date: "2026-11-12T10:00:00Z" },
            { ...transaction(), id: "transaction-pending", amount: 1000, status: "PENDING", date: "2026-11-13T10:00:00Z" },
        ];
        expect(parse([source]).rows[0].invoice).toMatchObject({
            state: "paid", amount_paid: "350.00", balance: "-51.00", paid_at: "2026-11-12",
        });
    });

    it("preserves VOID only when no payment was received", () => {
        const source = order();
        source.invoice.status = "VOID";
        expect(parse([source]).rows[0].invoice?.state).toBe("cancelled");
        source.transactions = [transaction()];
        expect(errorCode(source)).toBe("zaprite_payment_evidence");
    });

    it.each(["DRAFT", "CANCELED", "NEW"])("keeps unsupported invoice status %s invalid", status => {
        const source = order();
        source.invoice.status = status;
        expect(errorCode(source)).toBe("zaprite_unsupported_status");
    });

    it.each([
        { ...transaction(), status: "SETTLED" },
        { ...transaction(), method: "UNRECOGNIZED" },
        { ...transaction(), id: "" },
        { ...transaction(), amount: -5000 },
        { ...transaction(), amount: 0 },
        { ...transaction(), amountInOrderCurrency: 5000, orderCurrency: "USD" },
        { ...transaction(), discountsInOrderCurrency: [{ amountOff: 1, rateBipsOff: 0, paymentType: "FIAT" }] },
        { ...transaction(), date: "2026-11-11T14:25:00+01:00" },
    ])("rejects ambiguous, negative or unconfirmed payment data %#", payment => {
        const source = order();
        source.status = source.invoice.status = "UNDERPAID";
        source.transactions = [payment];
        expect(errorCode(source)).toBe("zaprite_payment_evidence");
    });

    it("detects repeated transaction IDs both within an invoice and across source orders", () => {
        const first = order();
        first.status = first.invoice.status = "UNDERPAID";
        first.transactions = [transaction(), transaction()];
        expect(errorCode(first)).toBe("zaprite_payment_evidence");
        first.transactions = [transaction()];
        const second = order("order-two");
        second.status = second.invoice.status = "UNDERPAID";
        second.transactions = [transaction()];
        expect(parse([first, second]).rows.map(row => row.code))
            .toEqual(["zaprite_payment_evidence", "zaprite_payment_evidence"]);
    });
});

describe("Zaprite exact line amounts", () => {
    it("reconciles percentage discounts and VAT using source minor units", () => {
        const source = order();
        source.totalAmount = 11115;
        source.invoice.lineItems = [{ ...line(), quantity: 1, discountRateBips: 1000, discountAmount: 1000, taxRateBips: 2350, taxAmount: 2115 }];
        expect(parse([source]).rows[0].invoice?.structured).toMatchObject({
            subtotal: "90.00", taxTotal: "21.15", total: "111.15",
            lines: [{ quantity: "1.0000", unitPrice: "100.00", lineDiscountPercent: "10.00", taxRate: "23.50", lineTotal: "111.15" }],
        });
    });

    it("preserves supported two-decimal quantities and signed deductions", () => {
        const source = order();
        source.totalAmount = 1845;
        source.invoice.lineItems = [{ ...line(), quantity: 1.25, unitPrice: 1200, taxRateBips: 2300, taxAmount: 345 }];
        expect(parse([source]).rows[0].invoice?.structured?.lines[0]).toMatchObject({ quantity: "1.2500", lineTotal: "18.45" });
        source.totalAmount = 10000;
        source.invoice.lineItems = [{ ...line(), quantity: 1, unitPrice: 12000 }, { ...line(), id: "deduction", quantity: 1, unitPrice: -2000 }];
        expect(parse([source]).rows[0].invoice?.structured?.lines[1]).toMatchObject({ unitPrice: "-20.00", lineTotal: "-20.00" });
    });

    it("rejects fractional-cent gross rounding that would change the transferred total", () => {
        const source = order();
        source.totalAmount = 1;
        source.invoice.lineItems = [
            { ...line(), quantity: 0.4, unitPrice: 1, taxRateBips: 3000, taxAmount: 0 },
            { ...line(), id: "second", quantity: 1, unitPrice: 1 },
        ];
        expect(errorCode(source)).toBe("zaprite_totals");
    });

    it("rejects inaccurate discount, VAT and document totals instead of rounding them to fit", () => {
        const source = order();
        source.invoice.lineItems[0].taxAmount = 1;
        expect(errorCode(source)).toBe("zaprite_totals");
        source.invoice.lineItems[0].taxAmount = 0;
        source.invoice.lineItems[0].discountAmount = 1;
        expect(errorCode(source)).toBe("zaprite_totals");
        source.invoice.lineItems[0].discountAmount = 0;
        source.totalAmount++;
        expect(errorCode(source)).toBe("zaprite_totals");
    });

    it.each([0.001, 0, -1, Number.MAX_SAFE_INTEGER])("rejects unsupported quantity %s", quantity => {
        const source = order();
        source.invoice.lineItems[0].quantity = quantity;
        expect(errorCode(source)).toBe("zaprite_invalid_invoice");
    });

    it.each([1.1, Number.MAX_SAFE_INTEGER, Number.MAX_SAFE_INTEGER + 1])("rejects unrepresentable unit prices %s", price => {
        const source = order();
        source.invoice.lineItems[0].unitPrice = price;
        expect(errorCode(source)).toBe("zaprite_invalid_invoice");
    });

    it("rejects unrepresented order-level adjustments", () => {
        const source = order();
        source.promo = { amountOff: 1, rateBipsOff: 0 };
        expect(errorCode(source)).toBe("zaprite_totals");
        source.promo = null;
        source.coupons = [{ amountOff: 1 }];
        expect(errorCode(source)).toBe("zaprite_totals");
    });
});

describe("Zaprite export completeness and currency selection", () => {
    it("keeps unsupported currencies invalid until explicitly excluded", () => {
        const sources = [order("eur", "EUR"), order("btc", "BTC"), order("usd", "USD")];
        const result = parse(sources);
        expect(result.rows.map(row => row.kind)).toEqual(["ready", "invalid", "ready"]);
        expect(result.rows[1]).toMatchObject({ row: 3, invoice_number: "INV-btc", code: "zaprite_unsupported_currency" });
        const selected = parse(sources, true);
        expect(selected.invoiceCount).toBe(3);
        expect(selected.rows.map(row => row.row)).toEqual([2, 4]);
        expect(selected.excludedCurrencies).toEqual([{ currency: "BTC", count: 1 }]);
    });

    it.each(["LBTC", "USDC", "USDT", "JPY", "BHD", "KWD"])("does not interpret %s native units as cents", currency => {
        expect(errorCode(order("unsupported", currency))).toBe("zaprite_unsupported_currency");
    });

    it("does not hide other invalid rows when unsupported currencies are excluded", () => {
        const source = order("bad-btc", "BTC");
        source.invoice.status = "DRAFT";
        const result = parse([source], true);
        expect(result.rows[0].code).toBe("zaprite_unsupported_status");
        expect(result.excludedCurrencies).toEqual([]);
    });

    it("uses the existing preview to flag duplicate invoice numbers without renaming", () => {
        const first = order("one");
        const second = order("two");
        second.invoice.number = first.invoice.number;
        second.contact = second.invoice.customer = { ...contact(), id: "different-buyer", legalName: "Other Buyer" };
        const result = parse([first, second]);
        const preview = buildPreparedMigrationPreview(result.rows, { companyId: "company", ownerId: "owner", existing: [] });
        expect(preview.conflict).toBe(2);
        expect(preview.rows.map(row => row.invoice_number)).toEqual([first.invoice.number, first.invoice.number]);
    });

    it("accepts contiguous complete pages and skips non-invoice orders without changing invoice row ordinals", () => {
        const first = order("one");
        first.orderType = "apiInvoice";
        const ignored = order("ignored");
        ignored.orderType = "paymentLink";
        const third = order("three");
        const source = {
            version: 1, provider: "zaprite",
            pages: [
                { items: [first, ignored], meta: { itemsCount: 3, pagesCount: 2, page: 1, perPage: 2 } },
                { items: [third], meta: { itemsCount: 3, pagesCount: 2, page: 2, perPage: 2 } },
            ],
        };
        const result = parseZapriteMigrationExport(bytes(source));
        expect(result.rows.map(row => row.row)).toEqual([2, 3]);
        expect(result.ignoredOrders).toBe(1);
        expect(result.invoiceCount).toBe(2);
        source.pages.pop();
        expect(() => parseZapriteMigrationExport(bytes(source))).toThrow("zaprite_incomplete_export");
    });

    it.each(["page", "pagesCount", "itemsCount", "perPage"] as const)("rejects inconsistent %s pagination", field => {
        const source = envelope();
        source.pages[0].meta[field] = field === "perPage" ? 0 : source.pages[0].meta[field] + 1;
        expect(() => parseZapriteMigrationExport(bytes(source))).toThrow("zaprite_incomplete_export");
    });

    it("rejects repeated order or invoice identity and mixed organizations", () => {
        const source = order();
        expect(() => parse([source, source])).toThrow("zaprite_incomplete_export");
        const other = order("other");
        other.invoice.id = source.invoice.id;
        expect(() => parse([source, other])).toThrow("zaprite_incomplete_export");
        other.invoice.id = "another-invoice";
        other.orgId = "another-organization";
        expect(() => parse([source, other])).toThrow("zaprite_incomplete_export");
    });

    it("accepts an empty complete export without inventing an organization", () => {
        expect(parse([])).toMatchObject({ rows: [], invoiceCount: 0, ignoredOrders: 0, organizationId: "" });
    });

    it("rejects object-valued discriminators through finite source error codes", () => {
        const source = envelope();
        Reflect.set(source.pages[0].items[0], "orderType", { toString: null });
        expect(() => parseZapriteMigrationExport(bytes(source))).toThrow("zaprite_invalid_export");
        const invoice = order();
        invoice.transactions = [{ ...transaction(), status: { toString: null } }];
        expect(errorCode(invoice)).toBe("zaprite_payment_evidence");
    });

    it("rejects malformed, oversized and incompatible envelopes", () => {
        expect(() => parseZapriteMigrationExport(new Uint8Array())).toThrow("zaprite_invalid_export");
        expect(() => parseZapriteMigrationExport(new Uint8Array(10 * 1024 * 1024 + 1))).toThrow("zaprite_invalid_export");
        expect(() => parseZapriteMigrationExport(bytes({ version: 2, provider: "zaprite", pages: [] }))).toThrow("zaprite_invalid_export");
        expect(() => parseZapriteMigrationExport(new Uint8Array([255]))).toThrow("zaprite_invalid_export");
    });

    it("rejects due-on-receipt terms without inventing a receipt date", () => {
        const source = order();
        source.invoice.daysToPay = null;
        expect(parse([source]).rows[0]).toMatchObject({
            kind: "invalid",
            code: "zaprite_invalid_invoice",
        });
        expect(parse([source]).rows[0].invoice).toBeUndefined();
    });

    it("uses UTC calendar dates and validates leap days", () => {
        const source = order();
        source.invoice.date = "2024-02-28";
        source.invoice.daysToPay = 2;
        expect(parse([source]).rows[0].invoice?.due_date).toBe("2024-03-01");
        source.invoice.daysToPay = 0;
        expect(parse([source]).rows[0].invoice?.due_date).toBe("2024-02-28");
        source.invoice.date = "2026-02-30";
        expect(errorCode(source)).toBe("zaprite_invalid_invoice");
    });
});
