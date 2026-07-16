import { describe, expect, it } from "vitest";
import {
    allowedVatRates,
    defaultPdfLocaleForJurisdiction,
    JURISDICTION_RULES,
    jurisdictionRules,
    standardVatRate,
    supportsStructuredDocumentExport,
    vatRateOutsideJurisdiction,
} from "@/config/jurisdictionRules";
import {
    COMPANY_JURISDICTION_VALUES,
    jurisdictionFromCountry,
} from "@/config/companyJurisdiction";

/**
 * Locks the TS side of the jurisdiction rules to the shared expectations -
 * tests/Unit/JurisdictionRulesTest.php asserts the SAME values against the
 * PHP source, so the two mirrors cannot drift silently.
 */
const SHARED_EXPECTATIONS = [
    {
        jurisdiction: "eu_sk",
        vat_name: "DPH",
        tax_id_label: "IČ DPH",
        vat_rates: [0, 5, 19, 23],
        archive_years: 10,
        eu_member: true,
        issue_from: "2027-01-01",
    },
    {
        jurisdiction: "eu_de",
        vat_name: "USt.",
        tax_id_label: "USt-IdNr.",
        vat_rates: [0, 7, 19],
        archive_years: 8,
        eu_member: true,
        receive_from: "2025-01-01",
        issue_from: "2027-01-01",
        issue_from_all: "2028-01-01",
        pdf_label_override: true,
    },
    {
        jurisdiction: "eu_at",
        vat_name: "USt.",
        tax_id_label: "UID-Nr.",
        vat_rates: [0, 10, 13, 20],
        archive_years: 7,
        eu_member: true,
        b2g_only: true,
        pdf_label_override: true,
    },
    {
        jurisdiction: "ch",
        vat_name: "MWST",
        tax_id_label: "UID (MWST)",
        vat_rates: [0, 2.6, 3.8, 8.1],
        archive_years: 10,
        eu_member: false,
        pdf_label_override: true,
    },
] as const;

describe("jurisdiction rules (TS mirror)", () => {
    it("matches the shared expectations asserted by the PHP test", () => {
        for (const expected of SHARED_EXPECTATIONS) {
            const rules = jurisdictionRules(expected.jurisdiction);
            expect(rules.vat_name, expected.jurisdiction).toBe(expected.vat_name);
            expect(rules.tax_id_label, expected.jurisdiction).toBe(expected.tax_id_label);
            expect(rules.vat_rates, expected.jurisdiction).toEqual([...expected.vat_rates]);
            expect(rules.archive_years, expected.jurisdiction).toBe(expected.archive_years);
            expect(rules.eu_member, expected.jurisdiction).toBe(expected.eu_member);
            if ("pdf_label_override" in expected) {
                expect(rules.pdf_label_override, expected.jurisdiction).toBe(true);
            }
            if ("receive_from" in expected) {
                expect(rules.e_invoicing?.receive_from).toBe(expected.receive_from);
            }
            if ("issue_from" in expected) {
                expect(rules.e_invoicing?.issue_from).toBe(expected.issue_from);
            }
            if ("issue_from_all" in expected) {
                expect(rules.e_invoicing?.issue_from_all).toBe(expected.issue_from_all);
            }
            if ("b2g_only" in expected) {
                expect(rules.e_invoicing?.b2g_only).toBe(true);
            }
        }
    });

    it("has a rules row for every dropdown value", () => {
        for (const value of COMPANY_JURISDICTION_VALUES) {
            expect(JURISDICTION_RULES[value], value).toBeDefined();
        }
    });

    it("maps DE/AT/CH/LI country codes to the new jurisdictions", () => {
        expect(jurisdictionFromCountry("DE")).toBe("eu_de");
        expect(jurisdictionFromCountry("AT")).toBe("eu_at");
        expect(jurisdictionFromCountry("CH")).toBe("ch");
        expect(jurisdictionFromCountry("LI")).toBe("ch");
        expect(jurisdictionFromCountry("SK")).toBe("eu_sk");
        expect(jurisdictionFromCountry("FR")).toBe("eu_other");
    });
});

describe("VAT rate validation", () => {
    it("flags rates outside the jurisdiction's known set", () => {
        expect(vatRateOutsideJurisdiction("eu_de", 19)).toBe(false);
        expect(vatRateOutsideJurisdiction("eu_de", 20)).toBe(true);
        expect(vatRateOutsideJurisdiction("ch", 8.1)).toBe(false);
        expect(vatRateOutsideJurisdiction("ch", 19)).toBe(true);
        expect(vatRateOutsideJurisdiction("eu_at", 13)).toBe(false);
    });

    it("skips validation where rates are unknown and for the US", () => {
        expect(vatRateOutsideJurisdiction("eu_other", 42)).toBe(false);
        expect(vatRateOutsideJurisdiction("us", 8.25)).toBe(false);
    });

    it("allows only zero for no-VAT jurisdictions", () => {
        expect(allowedVatRates("offshore")).toEqual([0]);
        expect(vatRateOutsideJurisdiction("offshore", 20)).toBe(true);
        expect(vatRateOutsideJurisdiction("offshore", 0)).toBe(false);
    });

    it("defaults the PDF language per jurisdiction (mirrors the PHP test)", () => {
        expect(defaultPdfLocaleForJurisdiction("eu_sk")).toBe("sk");
        expect(defaultPdfLocaleForJurisdiction("eu_other")).toBe("sk");
        expect(defaultPdfLocaleForJurisdiction("eu_cz")).toBe("cs");
        expect(defaultPdfLocaleForJurisdiction("eu_de")).toBe("de");
        expect(defaultPdfLocaleForJurisdiction("eu_at")).toBe("de");
        expect(defaultPdfLocaleForJurisdiction("ch")).toBe("de");
        expect(defaultPdfLocaleForJurisdiction("us")).toBe("en");
    });

    it("enables structured exports for jurisdictions with UBL support", () => {
        for (const jurisdiction of ["eu_sk", "eu_cz", "eu_de", "eu_at", "eu_other", "ch"]) {
            expect(supportsStructuredDocumentExport(jurisdiction), jurisdiction).toBe(true);
        }
        expect(supportsStructuredDocumentExport("us")).toBe(false);
    });

    it("derives the standard (highest) rate for prefill", () => {
        expect(standardVatRate("eu_de")).toBe(19);
        expect(standardVatRate("eu_at")).toBe(20);
        expect(standardVatRate("ch")).toBe(8.1);
        expect(standardVatRate("eu_other")).toBeNull();
        expect(standardVatRate("us")).toBeNull();
    });
});
