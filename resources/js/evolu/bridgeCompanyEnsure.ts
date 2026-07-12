import { resolveOrCreateBridgeCompanyId } from "./numberAllocatorBridge";

/**
 * Resolves (or creates) the server bridge company for a LOCAL Evolu company
 * by its local id (P1 follow-up: per-company bank inbound address).
 *
 * The bridge is matched by legal identity and created on demand with the
 * local company's jurisdiction/country/currency as creation hints - the same
 * machinery the number allocator uses, so features like the bank inbound
 * e-mail work for every company, including ones that never had a server row.
 * The Evolu client is imported lazily.
 */
export async function ensureBridgeCompanyIdForLocalCompany(
    localCompanyId: string,
): Promise<string | null> {
    if (!localCompanyId) {
        return null;
    }
    try {
        const { evolu, allCompaniesDetailQuery } = await import("./client");
        const rows = await evolu.loadQuery(allCompaniesDetailQuery);
        const row = rows.find((company) => company.id === localCompanyId);
        if (!row?.legalName) {
            return null;
        }
        return await resolveOrCreateBridgeCompanyId({
            legal_name: row.legalName,
            registration_number: row.registrationNumber ?? null,
            jurisdiction: row.jurisdiction ?? null,
            country: row.country ?? null,
            default_currency: row.defaultCurrency ?? null,
        });
    } catch {
        return null;
    }
}
