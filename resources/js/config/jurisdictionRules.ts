import type { CompanyJurisdictionValue } from "./companyJurisdiction";

/**
 * Legal invoicing rules per jurisdiction - browser-side mirror of
 * app/Support/Invoicing/JurisdictionRules.php (local-first: the client must
 * know the rules offline). Both sides are locked to shared expectations by
 * tests - update PHP + TS + tests together.
 *
 * Snapshot verified July 2026; re-verify any e-invoicing mandate within
 * ~6 months of its effective date.
 */

export type JurisdictionEInvoicing = {
    network: "peppol" | "xrechnung_zugferd";
    /** ISO date the jurisdiction requires businesses to RECEIVE e-invoices. */
    receive_from: string | null;
    /** ISO date issuing becomes mandatory (first wave, e.g. by turnover). */
    issue_from: string | null;
    /** ISO date issuing becomes mandatory for ALL businesses. */
    issue_from_all: string | null;
    /** Mandate covers business-to-government supplies only. */
    b2g_only: boolean;
};

export type JurisdictionRules = {
    has_vat: boolean;
    /** Printed VAT column/breakdown label: DPH, USt., MWST, VAT, Sales Tax. */
    vat_name: string;
    /** Label of the VAT/tax identifier: IČ DPH, USt-IdNr., UID-Nr., ... */
    tax_id_label: string;
    /** Allowed VAT rates incl. 0; empty = unknown, skip rate validation. */
    vat_rates: number[];
    eu_member: boolean;
    sequential_numbering: boolean;
    archive_years: number | null;
    e_invoicing: JurisdictionEInvoicing | null;
    /** PDF prints vat_name/tax_id_label instead of localized generic terms. */
    pdf_label_override: boolean;
    is_generic_bucket: boolean;
    requires_manual_review: boolean;
    legal_basis_note: string;
};

const DEFAULTS: Omit<JurisdictionRules, "vat_name" | "tax_id_label"> = {
    has_vat: true,
    vat_rates: [],
    eu_member: true,
    sequential_numbering: true,
    archive_years: null,
    e_invoicing: null,
    pdf_label_override: false,
    is_generic_bucket: false,
    requires_manual_review: false,
    legal_basis_note: "",
};

export const JURISDICTION_RULES: Record<CompanyJurisdictionValue, JurisdictionRules> = {
    eu_sk: {
        ...DEFAULTS,
        vat_name: "DPH",
        tax_id_label: "IČ DPH",
        vat_rates: [0, 5, 19, 23],
        archive_years: 10,
        e_invoicing: {
            network: "peppol",
            receive_from: "2027-01-01",
            issue_from: "2027-01-01",
            issue_from_all: null,
            b2g_only: false,
        },
        legal_basis_note: "zákon č. 385/2025 Z.z.; §76a zákona č. 222/2004 Z.z. o DPH",
    },
    eu_cz: {
        ...DEFAULTS,
        vat_name: "DPH",
        tax_id_label: "DIČ",
        vat_rates: [0, 12, 21],
        archive_years: 5,
        legal_basis_note: "zákon č. 235/2004 Sb. §29; občanský zákoník §435",
    },
    eu_de: {
        ...DEFAULTS,
        vat_name: "USt.",
        tax_id_label: "USt-IdNr.",
        pdf_label_override: true,
        vat_rates: [0, 7, 19],
        archive_years: 8,
        e_invoicing: {
            network: "xrechnung_zugferd",
            receive_from: "2025-01-01",
            issue_from: "2027-01-01",
            issue_from_all: "2028-01-01",
            b2g_only: false,
        },
        legal_basis_note: "Wachstumschancengesetz; §14, §14a UStG; GoBD (8 Jahre - BEG IV)",
    },
    eu_at: {
        ...DEFAULTS,
        vat_name: "USt.",
        tax_id_label: "UID-Nr.",
        pdf_label_override: true,
        vat_rates: [0, 10, 13, 20],
        archive_years: 7,
        e_invoicing: {
            network: "peppol",
            receive_from: null,
            issue_from: null,
            issue_from_all: null,
            b2g_only: true,
        },
        legal_basis_note: "§11 UStG (AT); §132 BAO; IKT-Konsolidierungsgesetz (B2G)",
    },
    ch: {
        ...DEFAULTS,
        vat_name: "MWST",
        tax_id_label: "UID (MWST)",
        pdf_label_override: true,
        vat_rates: [0, 2.6, 3.8, 8.1],
        eu_member: false,
        archive_years: 10,
        legal_basis_note: "MWSTG Art. 26; OR Art. 958f (Aufbewahrung)",
    },
    uk: {
        ...DEFAULTS,
        vat_name: "VAT",
        tax_id_label: "VAT Reg No",
        vat_rates: [0, 5, 20],
        eu_member: false,
        archive_years: 6,
        e_invoicing: {
            network: "peppol",
            receive_from: null,
            issue_from: "2029-04-01",
            issue_from_all: null,
            b2g_only: false,
        },
        legal_basis_note: "VAT Regulations 1995 (HMRC); B2B e-invoicing mandate confirmed for 1 Apr 2029",
    },
    us: {
        ...DEFAULTS,
        has_vat: false,
        vat_name: "Sales Tax",
        tax_id_label: "EIN",
        eu_member: false,
        sequential_numbering: false,
        legal_basis_note: "No federal VAT/e-invoicing; state sales tax applies only with US nexus",
    },
    eu_other: {
        ...DEFAULTS,
        vat_name: "VAT",
        tax_id_label: "VAT ID",
        is_generic_bucket: true,
        requires_manual_review: true,
        legal_basis_note:
            "Generic EU bucket - country-specific rules not configured; merchant must verify local obligations",
    },
    offshore: {
        ...DEFAULTS,
        has_vat: false,
        vat_name: "VAT",
        tax_id_label: "Tax ID",
        vat_rates: [0],
        eu_member: false,
        is_generic_bucket: true,
        requires_manual_review: true,
        legal_basis_note:
            "Generic offshore bucket - jurisdictions vary too much to default any VAT rules",
    },
    asia: {
        ...DEFAULTS,
        has_vat: false,
        vat_name: "VAT",
        tax_id_label: "Tax ID",
        vat_rates: [0],
        eu_member: false,
        is_generic_bucket: true,
        requires_manual_review: true,
        legal_basis_note:
            "Generic Asia bucket (SG GST 9%, JP 10%, AE 5%, ...) - split into real countries as demand appears",
    },
};

export function jurisdictionRules(jurisdiction: string): JurisdictionRules {
    return (
        JURISDICTION_RULES[jurisdiction as CompanyJurisdictionValue]
        ?? JURISDICTION_RULES.eu_other
    );
}

/**
 * Allowed VAT rates for line validation; empty array means "unknown - do not
 * validate". has_vat=false jurisdictions allow only 0.
 */
export function allowedVatRates(jurisdiction: string): number[] {
    const rules = jurisdictionRules(jurisdiction);
    if (!rules.has_vat) {
        return [0];
    }
    return rules.vat_rates;
}

/** True when the rate is outside the jurisdiction's known set (warn, never block). */
export function vatRateOutsideJurisdiction(jurisdiction: string, rate: number): boolean {
    if (jurisdiction === "us") {
        // US sales tax is state-dependent - any rate the merchant enters is
        // plausible; existing US behavior stays untouched.
        return false;
    }
    const allowed = allowedVatRates(jurisdiction);
    if (allowed.length === 0) {
        return false;
    }
    return !allowed.some((value) => Math.abs(value - rate) < 0.001);
}

export function standardVatRate(jurisdiction: string): number | null {
    const rules = jurisdictionRules(jurisdiction);
    if (!rules.has_vat || rules.vat_rates.length === 0) {
        return null;
    }
    return Math.max(...rules.vat_rates);
}

/** Default PDF language for new documents; SK behavior stays unchanged. */
export function defaultPdfLocaleForJurisdiction(jurisdiction: string): "sk" | "cs" | "de" | "en" {
    switch (jurisdiction) {
        case "eu_sk":
        case "eu_other":
            return "sk";
        case "eu_cz":
            return "cs";
        case "eu_de":
        case "eu_at":
        case "ch":
            return "de";
        default:
            return "en";
    }
}

export function supportsStructuredDocumentExport(jurisdiction: string | null | undefined): boolean {
    switch (jurisdiction) {
        case "eu_sk":
        case "eu_cz":
        case "eu_de":
        case "eu_at":
        case "eu_other":
        case "ch":
            return true;
        default:
            return false;
    }
}
