import {
    id,
    maxLength,
    NonEmptyString,
    nullOr,
    SqliteBoolean,
    union,
    type EvoluSchema,
} from "@evolu/common";

export const CompanyId = id("Company");
export type CompanyId = typeof CompanyId.Type;

export const ContactId = id("Contact");
export type ContactId = typeof ContactId.Type;

export const DocumentId = id("Document");
export type DocumentId = typeof DocumentId.Type;

export const DocumentLineId = id("DocumentLine");
export type DocumentLineId = typeof DocumentLineId.Type;

export const NumberSeriesId = id("NumberSeries");
export type NumberSeriesId = typeof NumberSeriesId.Type;

export const DocumentEventId = id("DocumentEvent");
export type DocumentEventId = typeof DocumentEventId.Type;

const LegalName = maxLength(255)(NonEmptyString);
const OptionalString16 = nullOr(maxLength(16)(NonEmptyString));
const OptionalString32 = nullOr(maxLength(32)(NonEmptyString));
const OptionalString64 = nullOr(maxLength(64)(NonEmptyString));
const OptionalString128 = nullOr(maxLength(128)(NonEmptyString));
const OptionalString255 = nullOr(maxLength(255)(NonEmptyString));
const OptionalString512 = nullOr(maxLength(512)(NonEmptyString));
const OptionalString1000 = nullOr(maxLength(1000)(NonEmptyString));
const OptionalString4000 = nullOr(maxLength(4000)(NonEmptyString));
/** Compressed image data URLs (logo / signature stamp), max ~128 KB each. */
const OptionalImageDataUrl = nullOr(maxLength(131072)(NonEmptyString));
const CountryCode = maxLength(2)(NonEmptyString);
const CurrencyCode = maxLength(3)(NonEmptyString);
const ContactName = maxLength(255)(NonEmptyString);
const ContactEmail = nullOr(maxLength(100)(NonEmptyString));
const LineName = maxLength(255)(NonEmptyString);
const DocumentTitle = maxLength(1000)(NonEmptyString);
const NumberSeriesName = maxLength(255)(NonEmptyString);
const NumberSeriesFormat = maxLength(64)(NonEmptyString);
const DocumentAction = maxLength(128)(NonEmptyString);

export const CompanyJurisdiction = union(
    "eu_sk",
    "eu_cz",
    "eu_other",
    "us",
    "uk",
    "offshore",
    "asia",
);
export type CompanyJurisdiction = typeof CompanyJurisdiction.Type;

export const VatStatus = union("none", "payer", "partial");
export type VatStatus = typeof VatStatus.Type;

export const DocumentStatus = union("draft", "issued", "paid", "cancelled");
export type DocumentStatus = typeof DocumentStatus.Type;

export const QuoteStatus = union("pending", "approved", "rejected", "expired");
export type QuoteStatus = typeof QuoteStatus.Type;

export const DocumentType = union(
    "invoice",
    "proforma",
    "quote",
    "credit_note",
    "delivery_note",
    "order_received",
);
export type DocumentType = typeof DocumentType.Type;

export const ResetPeriod = union("yearly", "monthly", "never");
export type ResetPeriod = typeof ResetPeriod.Type;

/** Local-first invoicing schema. Data lives in browser SQLite, synced E2EE via relay. */
export const InvoicingLocalSchema = {
    company: {
        id: CompanyId,
        legalName: LegalName,
        tradeName: OptionalString255,
        jurisdiction: CompanyJurisdiction,
        defaultCurrency: CurrencyCode,
        registrationNumber: OptionalString64,
        taxId: OptionalString64,
        vatNumber: OptionalString32,
        commercialRegister: OptionalString512,
        street: OptionalString255,
        city: OptionalString128,
        postalCode: OptionalString32,
        country: nullOr(CountryCode),
        stateRegion: OptionalString64,
        iban: OptionalString64,
        bic: OptionalString16,
        bankName: OptionalString128,
        bankAccount: OptionalString64,
        bankCode: OptionalString16,
        vatPayer: nullOr(SqliteBoolean),
        vatStatus: VatStatus,
        vatRateDefault: OptionalString16,
        legalFooterNote: OptionalString512,
        issuerName: OptionalString255,
        issuerPhone: OptionalString64,
        issuerEmail: OptionalString255,
        website: OptionalString255,
        invoiceNumberPrefix: OptionalString32,
        /** Satflux store UUID (BTCPay link stored locally until server bridge). */
        linkedStoreId: OptionalString64,
        /** JSON blob for app settings (rounding, PDF patterns, display flags). */
        appSettingsJson: OptionalString4000,
        /** JSON blob for email delivery + templates (+ SMTP credentials locally). */
        emailSettingsJson: OptionalString4000,
        /** Data URL for company logo (JPEG/WebP, resized locally). */
        logoDataUrl: OptionalImageDataUrl,
        /** Data URL for signature stamp image (JPEG/WebP, resized locally). */
        signatureDataUrl: OptionalImageDataUrl,
    },
    contact: {
        id: ContactId,
        companyId: CompanyId,
        name: ContactName,
        registrationNumber: OptionalString64,
        peppolParticipantId: OptionalString64,
        email: ContactEmail,
        phone: OptionalString64,
        fax: OptionalString64,
        taxId: OptionalString64,
        vatId: OptionalString32,
        street: OptionalString255,
        city: OptionalString128,
        postalCode: OptionalString32,
        stateRegion: OptionalString64,
        country: OptionalString128,
        bankAccount: OptionalString64,
        bankCode: OptionalString16,
        iban: OptionalString64,
        swift: OptionalString16,
        deliveryStreet: OptionalString255,
        deliveryPostalCode: OptionalString32,
        deliveryCity: OptionalString128,
        deliveryCountry: OptionalString128,
        defaultPaymentTermsDays: OptionalString16,
        notes: OptionalString1000,
        contactPersonsJson: OptionalString4000,
        isActive: nullOr(SqliteBoolean),
    },
    document: {
        id: DocumentId,
        companyId: CompanyId,
        contactId: nullOr(ContactId),
        documentType: DocumentType,
        status: DocumentStatus,
        quoteStatus: nullOr(QuoteStatus),
        title: DocumentTitle,
        number: OptionalString64,
        sourceDocumentId: nullOr(DocumentId),
        issueDate: OptionalString32,
        deliveryDate: OptionalString32,
        dueDate: OptionalString32,
        variableSymbol: OptionalString32,
        constantSymbol: OptionalString16,
        specificSymbol: OptionalString16,
        currency: nullOr(CurrencyCode),
        subtotal: OptionalString32,
        taxTotal: OptionalString32,
        discountPercent: OptionalString16,
        total: OptionalString32,
        noteAboveLines: OptionalString4000,
        noteFooter: OptionalString4000,
        internalNote: OptionalString4000,
        pdfLocale: OptionalString16,
        pdfShowSignature: nullOr(SqliteBoolean),
        pdfShowPaymentInfo: nullOr(SqliteBoolean),
        paymentBankEnabled: nullOr(SqliteBoolean),
        paymentBtcEnabled: nullOr(SqliteBoolean),
        storeId: OptionalString64,
        tagsJson: OptionalString1000,
        paidAt: OptionalString32,
        amountPaid: OptionalString32,
        emailSentAt: OptionalString32,
    },
    documentLine: {
        id: DocumentLineId,
        documentId: DocumentId,
        sortOrder: OptionalString16,
        name: LineName,
        description: OptionalString4000,
        quantity: OptionalString32,
        unit: OptionalString32,
        unitPrice: OptionalString32,
        lineDiscountPercent: OptionalString16,
        taxRate: OptionalString16,
        lineTotal: OptionalString32,
        companyStockItemId: OptionalString64,
        companyWarehouseId: OptionalString64,
    },
    numberSeries: {
        id: NumberSeriesId,
        companyId: CompanyId,
        name: NumberSeriesName,
        documentType: DocumentType,
        format: NumberSeriesFormat,
        resetPeriod: ResetPeriod,
        isDefault: nullOr(SqliteBoolean),
        periodKey: OptionalString32,
        lastNumber: OptionalString16,
    },
    documentEvent: {
        id: DocumentEventId,
        documentId: DocumentId,
        action: DocumentAction,
        metadataJson: OptionalString4000,
    },
} satisfies EvoluSchema;

export type InvoicingLocalSchema = typeof InvoicingLocalSchema;

export const EVOLU_APP_NAME = "satflux-invoicing" as const;
