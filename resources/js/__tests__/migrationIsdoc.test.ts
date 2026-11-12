import { describe, expect, it } from "vitest";
import { parseMigrationIsdoc, readMigrationIsdocXml } from "@/evolu/migrationIsdoc";
import invoice from "./fixtures/migration-isdoc/invoice.isdoc?raw";

const bytes = (xml: string) => new TextEncoder().encode(xml);
const read = (xml = invoice) => parseMigrationIsdoc(bytes(xml));

describe("migration ISDOC evidence", () => {
    it("preserves distinct tax rates, decimal quantities, descriptions and exact source totals", () => {
        expect(read()).toEqual({
            documentNumber: "TEST-2026-123", issueDate: "2026-08-15", currency: "EUR",
            supplierRegistrationNumber: "12345678", buyerRegistrationNumber: "87654321",
            buyerName: "Fixture buyer", subtotal: "110.00", taxTotal: "19.90", total: "129.90",
            lines: [
                { name: "Support plan", description: null, quantity: "2.0000", unit: "ks", unitPrice: "40.00",
                    lineDiscountPercent: "0.00", taxRate: "23.00", lineTotal: "98.40" },
                { name: "Consulting & setup", description: "From the source invoice", quantity: "1.5000", unit: "h",
                    unitPrice: "20.00", lineDiscountPercent: "0.00", taxRate: "5.00", lineTotal: "31.50" },
            ],
        });
    });

    it("classifies XML from other formats separately from malformed ISDOC", () => {
        expect(readMigrationIsdocXml(bytes('<Invoice xmlns="urn:some-other-format" />'))).toBeNull();
        expect(() => read(invoice.replace("</Invoice>", ""))).toThrow("migration_isdoc_invalid");
    });

    it.each(["0", "129.90"])("requires spreadsheet confirmation for a zero-payable export with deposits %s", deposits => {
        const xml = invoice.replace("<PayableAmount>129.90", "<PayableAmount>0.00")
            .replace("<PaidDepositsAmount>0", `<PaidDepositsAmount>${deposits}`);
        expect(read(xml)).toMatchObject({ requiresFullPayment: true, subtotal: "110.00", taxTotal: "19.90", total: "129.90" });
    });

    it("still rejects a zero payable amount with a deposit that differs from the gross total", () => {
        const xml = invoice.replace("<PayableAmount>129.90", "<PayableAmount>0.00")
            .replace("<PaidDepositsAmount>0", "<PaidDepositsAmount>50.00");
        expect(() => read(xml)).toThrow(expect.objectContaining({ reason: "adjustments" }));
    });

    it("reconciles explicit nontaxable deposit rows with the full-gross settlement field", () => {
        const xml = invoice.replace("<TaxTotal>", `<NonTaxedDeposits><NonTaxedDeposit><ID>1</ID>
            <VariableSymbol>001</VariableSymbol><DepositAmount>129.90</DepositAmount></NonTaxedDeposit></NonTaxedDeposits><TaxTotal>`)
            .replace("<PayableAmount>129.90", "<PayableAmount>0.00")
            .replace("<PaidDepositsAmount>0", "<PaidDepositsAmount>129.90");
        expect(read(xml)).toMatchObject({ requiresFullPayment: true, total: "129.90", taxTotal: "19.90" });
        expect(() => read(xml.replace("<DepositAmount>129.90", "<DepositAmount>129.89")))
            .toThrow(expect.objectContaining({ reason: "adjustments" }));
        expect(() => read(xml.replace("<DepositAmount>", "<DepositAmountCurr>1</DepositAmountCurr><DepositAmount>")))
            .toThrow(expect.objectContaining({ reason: "adjustments" }));
    });

    it("preserves a signed deduction with reconciled line, tax bucket and invoice amounts", () => {
        const deduction = `<InvoiceLine>
            <ID>3</ID><InvoicedQuantity>1</InvoicedQuantity>
            <LineExtensionAmount>-10.00</LineExtensionAmount>
            <LineExtensionAmountTaxInclusive>-12.30</LineExtensionAmountTaxInclusive>
            <LineExtensionTaxAmount>-2.30</LineExtensionTaxAmount>
            <UnitPrice>-10.00</UnitPrice><UnitPriceTaxInclusive>-12.30</UnitPriceTaxInclusive>
            <ClassifiedTaxCategory><Percent>23</Percent><VATCalculationMethod>0</VATCalculationMethod></ClassifiedTaxCategory>
            <Item><Description>Service deduction</Description></Item>
        </InvoiceLine>`;
        const xml = invoice
            .replace(/<(TaxableAmount|DifferenceTaxableAmount)>80\.00</g, "<$1>70.00<")
            .replace(/<(TaxInclusiveAmount|DifferenceTaxInclusiveAmount)>98\.40</g, "<$1>86.10<")
            .replace(/<(TaxAmount|DifferenceTaxAmount)>18\.40</g, "<$1>16.10<")
            .replace(/>110\.00</g, ">100.00<")
            .replace(/>19\.90</g, ">17.60<")
            .replace(/>129\.90</g, ">117.60<")
            .replace("</InvoiceLines>", `${deduction}</InvoiceLines>`);
        const imported = read(xml);
        expect(imported).toMatchObject({ subtotal: "100.00", taxTotal: "17.60", total: "117.60" });
        expect(imported.lines[2]).toMatchObject({
            name: "Service deduction", quantity: "1.0000", unitPrice: "-10.00", taxRate: "23.00", lineTotal: "-12.30",
        });
        expect(() => read(xml.replace("<LineExtensionTaxAmount>-2.30", "<LineExtensionTaxAmount>-2.29")))
            .toThrow(expect.objectContaining({ reason: "totals" }));
    });

    it.each([
        ["wrong total", invoice.replace("<TaxInclusiveAmount>129.90", "<TaxInclusiveAmount>120.00").replace("<PayableAmount>129.90", "<PayableAmount>120.00").replace("<DifferenceTaxInclusiveAmount>129.90", "<DifferenceTaxInclusiveAmount>120.00")],
        ["wrong per-rate subtotal", invoice.replace("<TaxableAmount>80.00", "<TaxableAmount>79.99")],
        ["inconsistent gross line", invoice.replace("<LineExtensionAmountTaxInclusive>98.40", "<LineExtensionAmountTaxInclusive>98.41")],
        ["duplicate line id", invoice.replace("<ID>2</ID>", "<ID>1</ID>")],
        ["conflicting total fields", invoice.replace("<PayableAmount>129.90</PayableAmount>", "<PayableAmount>1</PayableAmount><PayableAmount>129.90</PayableAmount>")],
        ["foreign namespace shadows a financial field", invoice.replace("<PayableAmount>129.90</PayableAmount>", '<PayableAmount xmlns="urn:other">1</PayableAmount><PayableAmount>129.90</PayableAmount>')],
        ["invalid issue date", invoice.replace("<IssueDate>2026-08-15", "<IssueDate>2026-02-30")],
        ["DTD and external entity", invoice.replace("<Invoice ", '<!DOCTYPE Invoice [<!ENTITY attack SYSTEM "https://example.invalid/blocked">]><Invoice ').replace("Support plan", "&attack;")],
        ["external processing instruction", invoice.replace("<Invoice ", '<?xml-stylesheet href="https://example.invalid/blocked" ?><Invoice ')],
    ])("rejects %s", (_name, xml) => {
        expect(() => read(xml)).toThrow("migration_isdoc_invalid");
    });

    it.each([
        ["credit note", invoice.replace("<DocumentType>1", "<DocumentType>2")],
        ["foreign currency", invoice.replace("<CurrRate>1", "<ForeignCurrencyCode>USD</ForeignCurrencyCode><CurrRate>1")],
        ["unit price with unsupported precision", invoice.replace("<UnitPrice>40.00", "<UnitPrice>40.00001")],
        ["quantity that does not reproduce the line", invoice.replace(">2.0000</InvoicedQuantity>", ">3.0000</InvoicedQuantity>")],
        ["effective line discount", invoice.replace("<LineExtensionAmount>80.00</LineExtensionAmount>", "<LineExtensionAmount>80.00</LineExtensionAmount><LineExtensionAmountBeforeDiscount>100.00</LineExtensionAmountBeforeDiscount>")],
        ["existing deposit", invoice.replace("<PaidDepositsAmount>0", "<PaidDepositsAmount>50")],
        ["payable rounding", invoice.replace("<PayableAmount>", "<PayableRoundingAmount>0.01</PayableRoundingAmount><PayableAmount>")],
    ])("reports unsupported financial semantics for %s", (_name, xml) => {
        expect(() => read(xml)).toThrow("migration_isdoc_unsupported");
    });

    it("rejects cent-net VAT that differs from VAT on Satflux's raw quantity times price", () => {
        const xml = invoice
            .replace(">2.0000</InvoicedQuantity>", ">1.0000</InvoicedQuantity>")
            .replace("<UnitPrice>40.00", "<UnitPrice>0.0246")
            .replace("<UnitPriceTaxInclusive>49.20", "<UnitPriceTaxInclusive>0.0303")
            .replace(/>80\.00</g, ">0.02<")
            .replace(/>98\.40</g, ">0.02<")
            .replace(/>18\.40</g, ">0.00<")
            .replace(/>110\.00</g, ">30.02<")
            .replace(/>19\.90</g, ">1.50<")
            .replace(/>129\.90</g, ">31.52<");
        expect(() => read(xml)).toThrow("migration_isdoc_unsupported");
    });

    it("rejects aggregate rounding that would change the invoice total", () => {
        const xml = invoice
            .replace(">2.0000</InvoicedQuantity>", ">1.0000</InvoicedQuantity>")
            .replace(">1.5</InvoicedQuantity>", ">1.0000</InvoicedQuantity>")
            .replace("<UnitPrice>40.00", "<UnitPrice>0.0149")
            .replace("<UnitPrice>20.00", "<UnitPrice>0.0149")
            .replace(/<UnitPriceTaxInclusive>(?:49\.20|21\.00)</g, "<UnitPriceTaxInclusive>0.0149<")
            .replace(/>(?:80\.00|30\.00|98\.40|31\.50)</g, ">0.01<")
            .replace(/>(?:18\.40|1\.50|19\.90)</g, ">0.00<")
            .replace(/>(?:110\.00|129\.90)</g, ">0.02<")
            .replace(/<Percent>(?:23|5)</g, "<Percent>0<")
            .replace(/<TaxSubTotal>[\s\S]*?<\/TaxSubTotal>/g, "");
        // Each line rounds to 0.01, while the sum of raw nets rounds to 0.03.
        expect(() => read(xml)).toThrow("migration_isdoc_unsupported");
    });
});
