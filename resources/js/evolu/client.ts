import { createEvolu, kysely, SimpleName, sqliteTrue } from "@evolu/common";
import { createUseEvolu } from "@evolu/vue";
import { evoluWebDeps } from "@evolu/web";
import { evoluTransports } from "./config";
import { EVOLU_APP_NAME, InvoicingLocalSchema } from "./schema";

export const evolu = createEvolu(evoluWebDeps)(InvoicingLocalSchema, {
    name: SimpleName.orThrow(EVOLU_APP_NAME),
    transports: evoluTransports(),
});

export const useInvoicingEvolu = createUseEvolu(evolu);

const companyListColumns = ["id", "legalName", "tradeName"] as const;

const companyDetailColumns = [
    "id",
    "legalName",
    "tradeName",
    "jurisdiction",
    "defaultCurrency",
    "registrationNumber",
    "taxId",
    "vatNumber",
    "commercialRegister",
    "street",
    "city",
    "postalCode",
    "country",
    "stateRegion",
    "iban",
    "bic",
    "bankName",
    "bankAccount",
    "bankCode",
    "vatPayer",
    "vatStatus",
    "vatRateDefault",
    "legalFooterNote",
    "issuerName",
    "issuerPhone",
    "issuerEmail",
    "website",
    "invoiceNumberPrefix",
    "linkedStoreId",
    "appSettingsJson",
    "emailSettingsJson",
    "logoDataUrl",
    "signatureDataUrl",
] as const;

const contactColumns = [
    "id",
    "companyId",
    "name",
    "registrationNumber",
    "peppolParticipantId",
    "email",
    "phone",
    "fax",
    "taxId",
    "vatId",
    "street",
    "city",
    "postalCode",
    "stateRegion",
    "country",
    "bankAccount",
    "bankCode",
    "iban",
    "swift",
    "deliveryStreet",
    "deliveryPostalCode",
    "deliveryCity",
    "deliveryCountry",
    "defaultPaymentTermsDays",
    "notes",
    "contactPersonsJson",
    "isActive",
] as const;

const documentColumns = [
    "id",
    "companyId",
    "contactId",
    "documentType",
    "status",
    "quoteStatus",
    "title",
    "number",
    "sourceDocumentId",
    "issueDate",
    "deliveryDate",
    "dueDate",
    "variableSymbol",
    "constantSymbol",
    "specificSymbol",
    "currency",
    "subtotal",
    "taxTotal",
    "discountPercent",
    "total",
    "noteAboveLines",
    "noteFooter",
    "internalNote",
    "pdfLocale",
    "pdfShowSignature",
    "pdfShowPaymentInfo",
    "paymentBankEnabled",
    "paymentBtcEnabled",
    "storeId",
    "tagsJson",
    "paidAt",
    "amountPaid",
] as const;

const documentLineColumns = [
    "id",
    "documentId",
    "sortOrder",
    "name",
    "description",
    "quantity",
    "unit",
    "unitPrice",
    "lineDiscountPercent",
    "taxRate",
    "lineTotal",
    "companyStockItemId",
    "companyWarehouseId",
] as const;

const numberSeriesColumns = [
    "id",
    "companyId",
    "name",
    "documentType",
    "format",
    "resetPeriod",
    "isDefault",
    "periodKey",
    "lastNumber",
] as const;

const documentEventColumns = [
    "id",
    "documentId",
    "action",
    "metadataJson",
    "createdAt",
] as const;

export const allCompaniesQuery = evolu.createQuery((db) =>
    db
        .selectFrom("company")
        .select(companyListColumns)
        .where("isDeleted", "is not", sqliteTrue)
        .where("legalName", "is not", null)
        .$narrowType<{ legalName: kysely.NotNull }>()
        .orderBy("createdAt"),
);

export const allCompaniesDetailQuery = evolu.createQuery((db) =>
    db
        .selectFrom("company")
        .select(companyDetailColumns)
        .where("isDeleted", "is not", sqliteTrue)
        .where("legalName", "is not", null)
        .$narrowType<{ legalName: kysely.NotNull }>()
        .orderBy("createdAt"),
);

export const allContactsQuery = evolu.createQuery((db) =>
    db
        .selectFrom("contact")
        .select(contactColumns)
        .where("isDeleted", "is not", sqliteTrue)
        .where("name", "is not", null)
        .$narrowType<{ name: kysely.NotNull }>()
        .orderBy("createdAt"),
);

export const allDocumentsQuery = evolu.createQuery((db) =>
    db
        .selectFrom("document")
        .select(documentColumns)
        .where("isDeleted", "is not", sqliteTrue)
        .where("title", "is not", null)
        .$narrowType<{ title: kysely.NotNull }>()
        .orderBy("createdAt"),
);

export const allDocumentLinesQuery = evolu.createQuery((db) =>
    db
        .selectFrom("documentLine")
        .select(documentLineColumns)
        .where("isDeleted", "is not", sqliteTrue)
        .where("name", "is not", null)
        .$narrowType<{ name: kysely.NotNull }>()
        .orderBy("createdAt"),
);

export const allNumberSeriesQuery = evolu.createQuery((db) =>
    db
        .selectFrom("numberSeries")
        .select(numberSeriesColumns)
        .where("isDeleted", "is not", sqliteTrue)
        .where("name", "is not", null)
        .$narrowType<{ name: kysely.NotNull }>()
        .orderBy("createdAt"),
);

export const allDocumentEventsQuery = evolu.createQuery((db) =>
    db
        .selectFrom("documentEvent")
        .select(documentEventColumns)
        .where("isDeleted", "is not", sqliteTrue)
        .where("action", "is not", null)
        .$narrowType<{ action: kysely.NotNull }>()
        .orderBy("createdAt"),
);

export { EVOLU_RELAY_URL } from "./config";
