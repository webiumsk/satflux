import { maxLength, NonEmptyString, SqliteBoolean } from "@evolu/common";
import { describe, expect, it } from "vitest";
import { prepareServerSnapshotForEvolu } from "@/evolu/serverSnapshotPrepare";
import { CompanyJurisdiction, DocumentType, VatStatus, BankImportSource } from "@/evolu/schema";

const sampleCompany = {
    id: "019ec83b-9368-7183-ab36-693391827ee6",
    legalName: "TECHNOLIM, spol. s r.o.",
    tradeName: null,
    jurisdiction: "eu_sk",
    defaultCurrency: "EUR",
    registrationNumber: "36518999",
    taxId: "2020155808",
    vatNumber: "SK2020155808",
    commercialRegister: "Mestský súd Bratislava III, Sro/62779/B",
    street: "Blagoevova 9",
    city: "Bratislava",
    postalCode: "851 04",
    country: "SK",
    stateRegion: null,
    iban: null,
    bic: null,
    bankName: null,
    bankAccount: null,
    bankCode: null,
    vatPayer: 1,
    vatStatus: "payer",
    vatRateDefault: null,
    legalFooterNote: null,
    issuerName: null,
    issuerPhone: null,
    issuerEmail: null,
    website: null,
    invoiceNumberPrefix: null,
    linkedStoreId: "019d34df-271f-7004-9e3c-7e27aa1d57e5",
    appSettingsJson: "{\"rounding_method\":\"per_line\"}",
    emailSettingsJson: "{\"delivery_method\":\"system\"}",
    logoDataUrl: null,
    signatureDataUrl: null,
};

const LegalName = maxLength(255)(NonEmptyString);
const CurrencyCode = maxLength(3)(NonEmptyString);
const Opt64 = maxLength(64)(NonEmptyString);

describe("server snapshot field validation", () => {
    it("prepared company row passes Evolu validators", () => {
        const prepared = prepareServerSnapshotForEvolu({
            company: [sampleCompany],
            contact: [],
            numberSeries: [],
            document: [],
            documentLine: [],
            documentEvent: [],
            expense: [],
            expenseAttachment: [],
            recurringProfile: [],
            recurringProfileLine: [],
            companyWarehouse: [],
            companyStockItem: [],
            companyStockBalance: [],
            companyStockMovement: [],
            bankImportBatch: [],
            bankTransaction: [],
            bankTransactionMatch: [],
        }).company[0];

        expect(LegalName.from(prepared.legalName as string).ok).toBe(true);
        expect(CurrencyCode.from(prepared.defaultCurrency as string).ok).toBe(true);
        expect(CompanyJurisdiction.from(prepared.jurisdiction).ok).toBe(true);
        expect(VatStatus.from(prepared.vatStatus).ok).toBe(true);
        expect(SqliteBoolean.from(prepared.vatPayer).ok).toBe(true);
        expect(Opt64.from(prepared.linkedStoreId as string).ok).toBe(true);
    });

    it("accepts number series document types used on the server", () => {
        for (const documentType of ["expense", "order_issued", "invoice"]) {
            expect(DocumentType.from(documentType).ok).toBe(true);
        }
    });

    it("accepts bank import sources from PostgreSQL export", () => {
        for (const source of ["csv", "camt053", "inbound_email", "manual", "wise"]) {
            expect(BankImportSource.from(source).ok).toBe(true);
        }
        expect(BankImportSource.from("email").ok).toBe(false);
    });
});
