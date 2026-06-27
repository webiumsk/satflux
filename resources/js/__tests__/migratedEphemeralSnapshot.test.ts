import { describe, expect, it } from "vitest";
import { prepareServerSnapshotForEvolu } from "@/evolu/serverSnapshotPrepare";

const emptySnapshot = {
    company: [] as Record<string, unknown>[],
    contact: [] as Record<string, unknown>[],
    numberSeries: [] as Record<string, unknown>[],
    document: [] as Record<string, unknown>[],
    documentLine: [] as Record<string, unknown>[],
    documentEvent: [] as Record<string, unknown>[],
    expense: [] as Record<string, unknown>[],
    expenseAttachment: [] as Record<string, unknown>[],
    recurringProfile: [] as Record<string, unknown>[],
    recurringProfileLine: [] as Record<string, unknown>[],
    companyWarehouse: [] as Record<string, unknown>[],
    companyStockItem: [] as Record<string, unknown>[],
    companyStockBalance: [] as Record<string, unknown>[],
    companyStockMovement: [] as Record<string, unknown>[],
    bankImportBatch: [] as Record<string, unknown>[],
    bankTransaction: [] as Record<string, unknown>[],
    bankTransactionMatch: [] as Record<string, unknown>[],
};

const SERVER_COMPANY_ID = "019ec83b-9368-7183-ab36-693391827ee6";
const SERVER_CONTACT_ID = "019ec83b-9368-7183-ab36-693391827f0";
const SERVER_DOCUMENT_ID = "019ec83b-9368-7183-ab36-693391827f1";
const SERVER_LINE_ID = "019ec83b-9368-7183-ab36-693391827f2";

describe("migrated server snapshot ephemeral PDF bridge", () => {
    it("preserves invoice fields and remaps server UUIDs for Evolu upsert", () => {
        const prepared = prepareServerSnapshotForEvolu({
            ...emptySnapshot,
            company: [{
                id: SERVER_COMPANY_ID,
                legalName: "Acme s.r.o.",
                tradeName: null,
                jurisdiction: "eu_sk",
                defaultCurrency: "EUR",
                registrationNumber: "36518999",
                taxId: "2020155808",
                vatNumber: "SK2020155808",
                commercialRegister: null,
                street: "Main 1",
                city: "Bratislava",
                postalCode: "81101",
                country: "SK",
                stateRegion: null,
                iban: "SK3112000000198742637541",
                bic: null,
                bankName: null,
                bankAccount: null,
                bankCode: null,
                vatPayer: 1,
                vatStatus: "payer",
                vatRateDefault: "21",
                legalFooterNote: null,
                issuerName: null,
                issuerPhone: null,
                issuerEmail: "issuer@acme.test",
                website: null,
                invoiceNumberPrefix: null,
                linkedStoreId: null,
                appSettingsJson: "{\"rounding_method\":\"per_line\",\"show_pay_by_square\":true}",
                emailSettingsJson: null,
                logoDataUrl: null,
                signatureDataUrl: null,
            }],
            contact: [{
                id: SERVER_CONTACT_ID,
                companyId: SERVER_COMPANY_ID,
                name: "Client Ltd",
                registrationNumber: null,
                peppolParticipantId: null,
                email: "billing@client.test",
                phone: null,
                taxId: null,
                vatId: null,
                street: null,
                city: null,
                postalCode: null,
                stateRegion: null,
                country: "SK",
                isActive: 1,
            }],
            document: [{
                id: SERVER_DOCUMENT_ID,
                companyId: SERVER_COMPANY_ID,
                contactId: SERVER_CONTACT_ID,
                documentType: "invoice",
                status: "issued",
                quoteStatus: null,
                number: "20260042",
                title: "Invoice",
                storeId: null,
                sourceDocumentId: null,
                issueDate: "2026-06-01",
                deliveryDate: null,
                dueDate: "2026-06-15",
                variableSymbol: "20260042",
                constantSymbol: null,
                specificSymbol: null,
                currency: "EUR",
                subtotal: "100",
                taxTotal: "21",
                discountPercent: "0",
                total: "121",
                noteAboveLines: null,
                noteFooter: null,
                internalNote: null,
                pdfLocale: "sk",
                pdfShowSignature: 1,
                pdfShowPaymentInfo: 1,
                paymentBankEnabled: 1,
                paymentBtcEnabled: 0,
                tagsJson: null,
                paidAt: null,
                amountPaid: null,
                emailSentAt: null,
            }],
            documentLine: [{
                id: SERVER_LINE_ID,
                documentId: SERVER_DOCUMENT_ID,
                sortOrder: 1,
                name: "Service",
                description: null,
                quantity: "1",
                unit: "ks",
                unitPrice: "100",
                lineDiscountPercent: "0",
                taxRate: "21",
                lineTotal: "121",
                companyStockItemId: null,
                companyWarehouseId: null,
            }],
        });

        const company = prepared.company[0]!;
        const contact = prepared.contact[0]!;
        const document = prepared.document[0]!;
        const line = prepared.documentLine[0]!;

        expect(company.legalName).toBe("Acme s.r.o.");
        expect(company.jurisdiction).toBe("eu_sk");
        expect(company.iban).toBe("SK3112000000198742637541");
        expect(document.number).toBe("20260042");
        expect(document.status).toBe("issued");
        expect(document.documentType).toBe("invoice");
        expect(line.name).toBe("Service");
        expect(line.taxRate).toBe("21");
        expect(line.quantity).toBe("1");

        expect(company.id).not.toBe(SERVER_COMPANY_ID);
        expect(String(company.id).length).toBeGreaterThan(10);
        expect(contact.companyId).toBe(company.id);
        expect(document.companyId).toBe(company.id);
        expect(document.contactId).toBe(contact.id);
        expect(line.documentId).toBe(document.id);
    });
});
