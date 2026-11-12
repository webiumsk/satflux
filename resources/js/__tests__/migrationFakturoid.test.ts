import { describe, expect, it } from "vitest";
import fixture from "./fixtures/migration-fakturoid/export-unpaid.xml?raw";
import paymentsFixture from "./fixtures/migration-fakturoid/export-payments.xml?raw";
import { FakturoidMigrationError, parseFakturoidMigrationExport } from "@/evolu/migrationFakturoid";
import { buildPreparedMigrationPreview, migrationFingerprint } from "@/evolu/migrationValidation";

const companyRegistrationNumber = "00012345";
const bytes = (xml: string) => new TextEncoder().encode(xml);
const parse = (xml = fixture, company: string | null = companyRegistrationNumber) =>
    parseFakturoidMigrationExport(bytes(xml), { companyRegistrationNumber: company });

function change(edit: (invoice: Element, document: Document) => void, index = 0): string {
    const document = new DOMParser().parseFromString(fixture, "application/xml");
    edit(document.querySelectorAll("invoice")[index], document);
    return new XMLSerializer().serializeToString(document);
}

function set(invoice: Element, name: string, value: string): void {
    const element = Array.from(invoice.children).find(child => child.localName === name);
    if (!element) throw new Error("Synthetic fixture field missing");
    element.textContent = value;
}

function append(parent: Element, name: string, value: string): void {
    const element = parent.ownerDocument.createElement(name);
    element.textContent = value;
    parent.appendChild(element);
}

function setLines(invoice: Element, lines: { id: string; quantity: string; price: string; grossPrice: string; rate: string }[]): void {
    const section = invoice.querySelector("lines")!;
    section.replaceChildren();
    for (const line of lines) {
        const element = invoice.ownerDocument.createElement("line");
        for (const [name, value] of Object.entries({ id: line.id, name: "Synthetic service", quantity: line.quantity,
            unit_name: "h", unit_price: line.price, vat_rate: line.rate,
            unit_price_without_vat: line.price, unit_price_with_vat: line.grossPrice })) append(element, name, value);
        section.appendChild(element);
    }
}

function totals(invoice: Element, net: string, gross: string): void {
    for (const field of ["subtotal", "native_subtotal"]) set(invoice, field, net);
    for (const field of ["total", "native_total", "remaining_amount", "remaining_native_amount"]) set(invoice, field, gross);
}

function expectInvalid(xml: string, code: string): void {
    const result = parse(xml);
    expect(result.rows[0]).toMatchObject({ row: 2, kind: "invalid", code });
    expect(result.rows[0].invoice).toBeUndefined();
    expect(result.rows[1].kind).toBe("ready");
}

describe("Fakturoid verified native XML subset", () => {
    it("preserves both complete invoice records, three items, buyer identifiers, due and taxable dates", () => {
        const result = parse();
        expect(result.invoiceCount).toBe(2);
        expect(result.issuerRegistrationNumber).toBe(companyRegistrationNumber);
        expect(result.rows.map(row => row.kind)).toEqual(["ready", "ready"]);
        const first = result.rows[0].invoice!;
        expect(first).toMatchObject({ row: 2, invoice_number: "FKT-TEST-1", issue_date: "2026-10-01",
            due_date: "2026-10-15", delivery_date: "2026-10-01", currency: "EUR", total: "123.00",
            amount_paid: "0.00", balance: "123.00", paid_at: null, state: "unpaid", variable_symbol: "2026001",
            sourceIdentity: { provider: "fakturoid", issuerRegistrationNumber: companyRegistrationNumber, invoiceId: "1001" },
            contact_attributes: { registration_number: "00054321", vat_id: "SK0000000001", tax_id: null },
            structured: { subtotal: "100.00", taxTotal: "23.00", total: "123.00", supplierRegistrationNumber: companyRegistrationNumber } });
        expect(first.structured!.lines[0]).toEqual({ name: "Synthetic service 1", description: null,
            quantity: "1.0000", unit: "h", unitPrice: "100.0000", lineDiscountPercent: "0.00", taxRate: "23.00", lineTotal: "123.00" });
        expect(result.rows[1].invoice!.structured).toMatchObject({ subtotal: "120.00", taxTotal: "25.00", total: "145.00" });
        expect(result.rows[1].invoice!.structured!.lines).toHaveLength(2);
        const preview = buildPreparedMigrationPreview(result.rows, { companyId: "company", ownerId: "owner", existing: [] });
        expect(preview.totals).toEqual([{ currency: "EUR", count: 2, total: "268.00", paid: "0.00", balance: "268.00" }]);
    });

    it("retains historical fields and optional local VAT ID without treating VAT ID as registration", () => {
        const result = parse(change(invoice => {
            append(invoice, "client_local_vat_no", "2020000001");
            set(invoice, "client_street2", "Unit 2");
            append(invoice.querySelector("line")!, "description", "Historical description");
            set(invoice, "client_registration_no", "");
        }));
        expect(result.rows[0].invoice!.contact_attributes).toMatchObject({ registration_number: null,
            tax_id: "2020000001", vat_id: "SK0000000001", street: "Example lane 2\nUnit 2" });
        expect(result.rows[0].invoice!.structured!.lines[0].description).toBe("Historical description");
    });

    it("normalizes only eight-digit issuer whitespace grouping", () => {
        const xml = fixture.split("<your_registration_no>00012345</your_registration_no>").join("<your_registration_no>00 012 345</your_registration_no>");
        expect(parse(xml, "00\u00a0012\u202f345").rows.every(row => row.kind === "ready")).toBe(true);
        expect(parse(xml).issuerRegistrationNumber).toBe("00012345");
        expect(parse(fixture, "0012345").rows.every(row => row.code === "fakturoid_company_mismatch")).toBe(true);
    });

    it.each([null, "00099999", "０００１２３４５"])("refuses missing or different selected company identity %s", company => {
        expect(parse(fixture, company).rows.map(row => row.code)).toEqual(["fakturoid_company_mismatch", "fakturoid_company_mismatch"]);
    });

    it("does not fingerprint row position, export timestamps, notes or scalar element order", () => {
        const original = parse().rows[0].invoice!;
        const xml = change(invoice => {
            set(invoice, "updated_at", "2026-11-11T10:00:00+02:00");
            set(invoice, "private_note", "Synthetic private note must stay in retained bytes only");
            invoice.appendChild(invoice.querySelector("number")!);
            invoice.parentElement!.appendChild(invoice);
        });
        expect(migrationFingerprint(parse(xml).rows[1].invoice!)).toBe(migrationFingerprint(original));
        expect(JSON.stringify(parse(xml))).not.toContain("private note");
    });

    it("leaves duplicate invoice numbers visible to the existing shared conflict checks", () => {
        const xml = change(invoice => set(invoice, "number", "FKT-TEST-1"), 1);
        const result = buildPreparedMigrationPreview(parse(xml).rows, { companyId: "company", ownerId: "owner", existing: [] });
        expect(result.conflict).toBe(2);
        expect(result.rows.every(row => row.code === "in_file_conflict")).toBe(true);
    });
});

describe("Fakturoid payment proof boundaries", () => {
    it("preserves four source invoices and five lines with independent partial and full payment evidence", () => {
        const result = parse(paymentsFixture);
        expect(result.invoiceCount).toBe(4);
        expect(result.rows.map(row => row.kind)).toEqual(["ready", "ready", "ready", "ready"]);
        expect(result.rows.map(row => row.invoice!.structured!.lines.length)).toEqual([1, 2, 1, 1]);
        expect(result.rows[2].invoice).toMatchObject({ invoice_number: "FKT-TEST-3", state: "partial",
            total: "123.00", amount_paid: "50.00", balance: "73.00", paid_at: null });
        expect(result.rows[3].invoice).toMatchObject({ invoice_number: "FKT-TEST-4", state: "paid",
            total: "123.00", amount_paid: "123.00", balance: "0.00", paid_at: "2026-10-16" });
        expect(buildPreparedMigrationPreview(result.rows, { companyId: "company", ownerId: "owner", existing: [] }).totals)
            .toEqual([{ currency: "EUR", count: 4, total: "514.00", paid: "173.00", balance: "341.00" }]);
    });

    it.each(["open", "sent", "overdue"])("supports explicit zero paid for %s without inventing a payment date", status => {
        const invoice = parse(change(invoice => set(invoice, "status", status))).rows[0].invoice!;
        expect(invoice.state).toBe("unpaid");
        expect(invoice.amount_paid).toBe("0.00");
        expect(invoice.balance).toBe("123.00");
        expect(invoice.paid_at).toBeNull();
    });

    it("supports cancellation only with explicit zero payment and no payment date", () => {
        const result = parse(change(invoice => set(invoice, "status", "cancelled")));
        expect(result.rows[0].invoice!.state).toBe("cancelled");
        expect(buildPreparedMigrationPreview(result.rows, { companyId: "company", ownerId: "owner", existing: [] }).totals[0].total).toBe("145.00");
    });

    it.each(["open", "sent", "overdue"])("preserves exact partial payment with %s status and an unknown date", status => {
        const invoice = parse(change(invoice => {
            set(invoice, "status", status); set(invoice, "paid_amount", "50");
        })).rows[0].invoice!;
        expect(invoice).toMatchObject({ state: "partial", amount_paid: "50.00", balance: "73.00", paid_at: null });
    });

    it("keeps payment changes in source fingerprints without changing source identity", () => {
        const unpaid = parse().rows[0].invoice!;
        const partial = parse(change(invoice => set(invoice, "paid_amount", "50"))).rows[0].invoice!;
        expect(partial.sourceIdentity).toEqual(unpaid.sourceIdentity);
        expect(migrationFingerprint(partial)).not.toBe(migrationFingerprint(unpaid));
    });

    it("reports a conflict when a later export changes payment on an already completed unpaid import", () => {
        const unpaid = parse().rows[0].invoice!;
        const destination = { id: "document", companyId: "company", ownerId: "owner", documentType: "invoice",
            number: unpaid.invoice_number, status: "issued", currency: unpaid.currency, issueDate: unpaid.issue_date,
            dueDate: unpaid.due_date, deliveryDate: unpaid.delivery_date, variableSymbol: unpaid.variable_symbol,
            constantSymbol: null, specificSymbol: null, total: unpaid.total, amountPaid: "0.00", paidAt: null,
            subtotal: unpaid.structured!.subtotal, taxTotal: unpaid.structured!.taxTotal,
            migrationSourceId: "archive", migrationCompletedAt: "2026-10-02T10:00:00.000Z",
            migrationFingerprint: migrationFingerprint(unpaid) };
        const changed = parse(change(invoice => set(invoice, "paid_amount", "50")));
        const preview = buildPreparedMigrationPreview(changed.rows, {
            companyId: "company", ownerId: "owner", existing: [destination as never],
        });
        expect(preview.rows[0]).toMatchObject({ kind: "conflict", code: "existing_conflict" });
        expect(destination.amountPaid).toBe("0.00");
    });

    it.each([
        ["open", "123", ""], ["paid", "50", "2026-10-15"], ["paid", "150", "2026-10-15"],
        ["paid", "123", ""], ["open", "50", "2026-10-15"], ["open", "150", ""],
        ["paid", "0", ""], ["open", "0", "2026-10-15"], ["cancelled", "1", ""],
        ["cancelled", "0", "2026-10-15"], ["open", "-1", ""], ["open", "0", "2026-02-30"],
        ["paid", "123", "2026-02-30"], ["paid", "123", "2026-10-15T12:00:00Z"],
    ])("blocks contradictory or unsupported payment evidence %s/%s/%s", (status, paid, paidOn) => {
        expectInvalid(change(invoice => {
            set(invoice, "status", status); set(invoice, "paid_amount", paid); set(invoice, "paid_on", paidOn);
        }), "fakturoid_payment_evidence");
    });

    it("requires actual paid_amount even for an unpaid status", () => {
        expectInvalid(change(invoice => invoice.querySelector("paid_amount")!.remove()), "fakturoid_payment_evidence");
    });

    it("requires full-payment date rather than deriving one from due date or timestamps", () => {
        expectInvalid(change(invoice => {
            set(invoice, "status", "paid"); set(invoice, "paid_amount", "123");
            invoice.querySelector("paid_on")!.remove();
        }), "fakturoid_payment_evidence");
    });

    it("does not treat remaining document-link coverage as an unpaid balance", () => {
        expectInvalid(change(invoice => {
            set(invoice, "remaining_amount", "73"); set(invoice, "remaining_native_amount", "73");
        }), "fakturoid_unsupported");
    });

    it.each(["draft", "accepted", "uncollectible", "unknown"])("refuses unsupported status %s", status => {
        expectInvalid(change(invoice => set(invoice, "status", status)), "fakturoid_unsupported");
    });
});

describe("Fakturoid exact arithmetic", () => {
    it("preserves an exact signed-price deduction when the VAT bucket and document reconcile", () => {
        const invoice = parse(change(invoice => {
            setLines(invoice, [{ id: "1", quantity: "1", price: "100", grossPrice: "123", rate: "23" },
                { id: "2", quantity: "1", price: "-10", grossPrice: "-12.3", rate: "23" }]);
            totals(invoice, "90", "110.7");
        })).rows[0].invoice!;
        expect(invoice.total).toBe("110.70");
        expect(invoice.structured!.taxTotal).toBe("20.70");
        expect(invoice.structured!.lines[1]).toMatchObject({ unitPrice: "-10.0000", lineTotal: "-12.30" });
    });

    it("preserves exact fractional quantity and four-decimal unit price", () => {
        const result = parse(change(invoice => {
            setLines(invoice, [{ id: "1", quantity: "2.5", price: "1.2344", grossPrice: "1.2344", rate: "0" }]);
            totals(invoice, "3.09", "3.09");
        }));
        expect(result.rows[0].invoice!.structured!.lines[0]).toMatchObject({ quantity: "2.5000", unitPrice: "1.2344", lineTotal: "3.09" });
    });

    it("calculates line VAT from the entered price and quantity before rounding", () => {
        const result = parse(change(invoice => {
            setLines(invoice, [{ id: "1", quantity: "0.4", price: "0.06", grossPrice: "0.0738", rate: "23" }]);
            totals(invoice, "0.02", "0.03");
        }));
        expect(result.rows[0].kind).toBe("ready");
        expect(result.rows[0].invoice!.structured).toMatchObject({ subtotal: "0.02", taxTotal: "0.01" });
        expect(result.rows[0].invoice!.structured!.lines[0]).toMatchObject({
            quantity: "0.4000", unitPrice: "0.0600", taxRate: "23.00", lineTotal: "0.03",
        });
    });

    it("rejects raw-gross rounding that differs from net plus VAT", () => {
        expectInvalid(change(invoice => {
            setLines(invoice, [{ id: "1", quantity: "0.4", price: "0.01", grossPrice: "0.013", rate: "30" },
                { id: "2", quantity: "1", price: "0.01", grossPrice: "0.01", rate: "0" }]);
            totals(invoice, "0.01", "0.01");
        }), "fakturoid_totals");
    });

    it("rejects aggregate rounding even when each line rounds individually", () => {
        expectInvalid(change(invoice => {
            setLines(invoice, [{ id: "1", quantity: "0.4", price: "0.01", grossPrice: "0.01", rate: "0" },
                { id: "2", quantity: "0.4", price: "0.01", grossPrice: "0.01", rate: "0" },
                { id: "3", quantity: "1", price: "1", grossPrice: "1", rate: "0" }]);
            totals(invoice, "1", "1");
        }), "fakturoid_totals");
    });

    it.each([
        ["subtotal", "99.99", "fakturoid_unsupported"], ["total", "122.99", "fakturoid_unsupported"],
        ["native_total", "123.01", "fakturoid_unsupported"], ["exchange_rate", "0.99", "fakturoid_unsupported"],
        ["rounding_adjustment", "0.01", "fakturoid_unsupported"], ["subtotal", "100.001", "fakturoid_totals"],
    ])("rejects changed monetary evidence %s", (name, value, code) => {
        expectInvalid(change(invoice => set(invoice, name, value)), code);
    });

    it.each(["BTC", "LBTC", "JPY", "KWD", "XXX"])("explicitly rejects unsupported currency %s", currency => {
        expectInvalid(change(invoice => set(invoice, "currency", currency)), "fakturoid_unsupported_currency");
    });

    it.each([
        ["quantity", "0", "fakturoid_unsupported"], ["quantity", "-1", "fakturoid_unsupported"],
        ["quantity", "0.00001", "fakturoid_invalid_invoice"], ["unit_price", "1e2", "fakturoid_invalid_invoice"],
        ["unit_price_with_vat", "122.99", "fakturoid_totals"], ["vat_rate", "-1", "fakturoid_unsupported"],
        ["vat_rate", "101", "fakturoid_unsupported"], ["vat_rate", "23.001", "fakturoid_unsupported"],
    ])("rejects unsupported or contradictory line %s=%s", (field, value, code) => {
        expectInvalid(change(invoice => set(invoice.querySelector("line")!, field, value)), code);
    });

    it("bounds monetary overflow before emitting an invoice", () => {
        expectInvalid(change(invoice => {
            setLines(invoice, [{ id: "1", quantity: "9999999999", price: "999999999999", grossPrice: "999999999999", rate: "0" }]);
        }), "fakturoid_totals");
    });

    it("refuses negative VAT buckets even if another bucket makes the document positive", () => {
        expectInvalid(change(invoice => {
            setLines(invoice, [{ id: "1", quantity: "1", price: "100", grossPrice: "123", rate: "23" },
                { id: "2", quantity: "1", price: "-10", grossPrice: "-11", rate: "10" }]);
            totals(invoice, "90", "112");
        }), "fakturoid_totals");
    });
});

describe("Fakturoid structural and unsupported-source boundaries", () => {
    it.each(["proforma", "partial_proforma", "tax_document", "correction", "transferred_tax_liability", "eu_electronic_service"])("refuses unsupported flag %s", field => {
        expectInvalid(change(invoice => set(invoice, field, "true")), "fakturoid_unsupported");
    });

    it.each(["related_id", "correction_id", "supply_code"])("refuses linked/advance/tax metadata %s", field => {
        expectInvalid(change(invoice => set(invoice, field, "1")), "fakturoid_unsupported");
    });

    it("rejects an unrecognized financial field instead of ignoring it", () => {
        expectInvalid(change(invoice => append(invoice, "discount", "10")), "fakturoid_unsupported");
        expectInvalid(change(invoice => append(invoice.querySelector("line")!, "discount", "10")), "fakturoid_unsupported");
    });

    it("rejects inclusive VAT mode instead of treating gross prices as net", () => {
        expectInvalid(change(invoice => set(invoice, "vat_price_mode", "with_vat")), "fakturoid_unsupported");
    });

    it.each(["due_on", "issued_on", "taxable_fulfillment_due"])("validates exact historical date %s without guessing", field => {
        expectInvalid(change(invoice => set(invoice, field, "2026-02-30")), "fakturoid_invalid_invoice");
    });

    it("never derives missing due_on from the relative due scalar", () => {
        expectInvalid(change(invoice => invoice.querySelector("due_on")!.remove()), "fakturoid_invalid_invoice");
    });

    it.each([
        "", "{}", "<invoice/>", "<invoices/>", "<invoices><invoice></invoices>",
        "<!DOCTYPE invoices [<!ENTITY source 'x'>]><invoices>&source;</invoices>",
        "<!DOCTYPE invoices SYSTEM 'https://example.invalid/source'><invoices/>",
        "<?xml version='1.0' encoding='iso-8859-1'?><invoices/>",
        "<invoices xmlns='https://example.invalid'/>", "<invoices version='1'/>",
        "<invoices><?unexpected processing?></invoices>",
        "<invoices><![CDATA[unexpected]]></invoices>",
    ])("rejects malformed or alternate XML without source data in errors %#", xml => {
        expect(() => parse(xml)).toThrow("fakturoid_invalid_export");
    });

    it("strictly rejects malformed UTF8", () => {
        expect(() => parseFakturoidMigrationExport(new Uint8Array([0xc3, 0x28]), { companyRegistrationNumber })).toThrow("fakturoid_invalid_export");
    });

    it("rejects duplicate invoice IDs and mixed issuers globally", () => {
        expect(() => parse(change(invoice => set(invoice, "id", "1001"), 1))).toThrow("fakturoid_invalid_export");
        expect(() => parse(change(invoice => set(invoice, "your_registration_no", "00099999"), 1))).toThrow("fakturoid_invalid_export");
    });

    it("rejects missing issuer identity and provider IDs globally", () => {
        expect(() => parse(change(invoice => invoice.querySelector("your_registration_no")!.remove()))).toThrow("fakturoid_invalid_export");
        expect(() => parse(change(invoice => invoice.querySelector("id")!.remove()))).toThrow("fakturoid_invalid_export");
    });

    it("rejects duplicate scalar fields and line IDs globally even in unsupported records", () => {
        expect(() => parse(change(invoice => append(invoice, "total", "123")))).toThrow("fakturoid_invalid_export");
        expect(() => parse(change(invoice => {
            set(invoice, "proforma", "true"); append(invoice.querySelector("line")!, "quantity", "1");
        }))).toThrow("fakturoid_invalid_export");
        expect(() => parse(change(invoice => {
            const line = invoice.querySelector("line")!;
            line.parentElement!.appendChild(line.cloneNode(true));
        }))).toThrow("fakturoid_invalid_export");
    });

    it("rejects namespaces or attributes on nested nodes", () => {
        expect(() => parse(change(invoice => invoice.querySelector("total")!.setAttribute("currency", "EUR")))).toThrow("fakturoid_invalid_export");
        expect(() => parse(fixture.replace("<total>123.0</total>", "<x:total xmlns:x='https://example.invalid'>123.0</x:total>"))).toThrow("fakturoid_invalid_export");
    });

    it("bounds bytes, invoice count, line count, XML depth and tags", () => {
        expect(() => parse(" ".repeat(10 * 1024 * 1024 + 1))).toThrow("fakturoid_limit_exceeded");
        expect(() => parse("<invoices>" + "<invoice/>".repeat(2001) + "</invoices>")).toThrow("fakturoid_limit_exceeded");
        expect(() => parse(change(invoice => {
            const section = invoice.querySelector("lines")!;
            for (let i = 1; i <= 500; i++) section.appendChild(section.firstElementChild!.cloneNode(true));
        }))).toThrow("fakturoid_limit_exceeded");
        expect(() => parse("<invoices><invoice><a><b><c><d/></c></b></a></invoice></invoices>")).toThrow("fakturoid_limit_exceeded");
        expect(() => parse("<invoices>" + "<a/>".repeat(200_000) + "</invoices>")).toThrow("fakturoid_limit_exceeded");
    });

    it("uses a finite controlled error class and never returns raw XML parser errors", () => {
        const malformed = "<invoices><private_note>synthetic-secret</invoices>";
        expect(() => parse(malformed)).toThrow(FakturoidMigrationError);
        try { parse(malformed); }
        catch (error) {
            expect(error).toBeInstanceOf(FakturoidMigrationError);
            expect((error as Error).message).toBe("fakturoid_invalid_export");
            expect(String(error)).not.toContain("synthetic-secret");
        }
    });
});
