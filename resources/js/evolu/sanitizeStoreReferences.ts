import type { Evolu } from "@evolu/common/local-first";
import {
    allCompaniesQuery,
    allDocumentsQuery,
    allRecurringProfilesQuery,
} from "./client";
import type { CompanyId, DocumentId, InvoicingLocalSchema, RecurringProfileId } from "./schema";
import type { EvoluCompanyRow } from "./companyMap";

export { isKnownStoreId } from "./storeIdUtils";

export type SanitizeStoreReferencesResult = {
    clearedCompanyLinks: number;
    clearedDocumentStores: number;
    clearedRecurringStores: number;
};

/**
 * Clear Evolu store UUIDs that do not belong to the current user's server stores.
 */
export async function sanitizeLocalStoreReferences(
    evolu: Evolu<InvoicingLocalSchema>,
    validStoreIds: ReadonlySet<string>,
): Promise<SanitizeStoreReferencesResult> {
    const [companies, documents, recurringProfiles] = await Promise.all([
        evolu.loadQuery(allCompaniesQuery) as Promise<EvoluCompanyRow[]>,
        evolu.loadQuery(allDocumentsQuery) as Promise<Array<{ id: DocumentId; storeId: string | null }>>,
        evolu.loadQuery(allRecurringProfilesQuery) as Promise<Array<{ id: RecurringProfileId; storeId: string | null }>>,
    ]);

    let clearedCompanyLinks = 0;
    let clearedDocumentStores = 0;
    let clearedRecurringStores = 0;

    for (const company of companies) {
        const linked = company.linkedStoreId?.trim();
        if (linked && !validStoreIds.has(linked)) {
            evolu.update("company", {
                id: company.id as CompanyId,
                linkedStoreId: null,
            });
            clearedCompanyLinks += 1;
        }
    }

    for (const document of documents) {
        const storeId = document.storeId?.trim();
        if (storeId && !validStoreIds.has(storeId)) {
            evolu.update("document", {
                id: document.id,
                storeId: null,
            });
            clearedDocumentStores += 1;
        }
    }

    for (const profile of recurringProfiles) {
        const storeId = profile.storeId?.trim();
        if (storeId && !validStoreIds.has(storeId)) {
            evolu.update("recurringProfile", {
                id: profile.id,
                storeId: null,
            });
            clearedRecurringStores += 1;
        }
    }

    return { clearedCompanyLinks, clearedDocumentStores, clearedRecurringStores };
}
