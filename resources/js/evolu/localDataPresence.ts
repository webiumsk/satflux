/**
 * Lightweight probe: does any local-first invoicing data exist on this
 * device? Used by data-loss guards (logout, owner switch). The Evolu client
 * is imported lazily inside the function so callers in the main bundle
 * (AppHeader, auth modals) never pull the sqlite WASM eagerly.
 */
export type LocalInvoicingDataCounts = {
    companies: number;
    contacts: number;
    documents: number;
    hasData: boolean;
};

export async function countLocalInvoicingData(): Promise<LocalInvoicingDataCounts> {
    const { evolu, allCompaniesQuery, allContactsQuery, allDocumentsQuery } = await import("./client");
    const [companies, contacts, documents] = await Promise.all([
        evolu.loadQuery(allCompaniesQuery),
        evolu.loadQuery(allContactsQuery),
        evolu.loadQuery(allDocumentsQuery),
    ]);
    const counts = {
        companies: companies.length,
        contacts: contacts.length,
        documents: documents.length,
    };
    return { ...counts, hasData: counts.companies + counts.contacts + counts.documents > 0 };
}
