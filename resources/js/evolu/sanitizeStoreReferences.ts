import type { Evolu } from "@evolu/common/local-first";
import {
    allCompaniesQuery,
    allDocumentsQuery,
    allRecurringProfilesQuery,
} from "./client";
import type { InvoicingLocalSchema } from "./schema";

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
        evolu.loadQuery(allCompaniesQuery),
        evolu.loadQuery(allDocumentsQuery),
        evolu.loadQuery(allRecurringProfilesQuery),
    ]);

    let clearedCompanyLinks = 0;
    let clearedDocumentStores = 0;
    let clearedRecurringStores = 0;

    for (const company of companies) {
        // Lightweight company rows may omit this optional detail column.
        const linked = "linkedStoreId" in company && typeof company.linkedStoreId === "string"
            ? company.linkedStoreId.trim()
            : undefined;
        if (linked && !validStoreIds.has(linked)) {
            evolu.update("company", {
                id: company.id,
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
