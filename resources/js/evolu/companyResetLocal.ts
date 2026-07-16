import { sqliteTrue } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import {
    allContactsQuery,
    allDocumentEventsQuery,
    allDocumentsQuery,
    allNumberSeriesQuery,
} from "./client";
import type { EvoluContactRow } from "./contactMap";
import type { EvoluDocumentEventRow } from "./documentEventLog";
import type { EvoluDocumentRow } from "./documentMap";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { toAppRows } from "./queryLoad";
import type {
    CompanyId,
    ContactId,
    DocumentEventId,
    DocumentId,
    InvoicingLocalSchema,
    NumberSeriesId,
} from "./schema";

export function resetLocalCompanyData(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
): void {
    const documents = toAppRows<EvoluDocumentRow>(evolu.getQueryRows(allDocumentsQuery));
    const contacts = toAppRows<EvoluContactRow>(evolu.getQueryRows(allContactsQuery));
    const numberSeries = toAppRows<EvoluNumberSeriesRow>(evolu.getQueryRows(allNumberSeriesQuery));
    const documentEvents = toAppRows<EvoluDocumentEventRow>(evolu.getQueryRows(allDocumentEventsQuery));

    const companyDocIds = new Set(
        documents.filter((doc) => doc.companyId === companyId).map((doc) => doc.id),
    );

    for (const doc of documents) {
        if (doc.companyId !== companyId) continue;
        evolu.update("document", { id: doc.id as DocumentId, isDeleted: sqliteTrue });
    }

    for (const contact of contacts) {
        if (contact.companyId !== companyId) continue;
        evolu.update("contact", { id: contact.id as ContactId, isDeleted: sqliteTrue });
    }

    for (const series of numberSeries) {
        if (series.companyId !== companyId) continue;
        evolu.update("numberSeries", { id: series.id as NumberSeriesId, isDeleted: sqliteTrue });
    }

    for (const event of documentEvents) {
        if (!companyDocIds.has(event.documentId as DocumentId)) continue;
        evolu.update("documentEvent", { id: event.id as DocumentEventId, isDeleted: sqliteTrue });
    }
}
