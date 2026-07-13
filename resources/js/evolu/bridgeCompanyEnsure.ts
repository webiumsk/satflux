import { resolveOrCreateBridgeCompanyId } from "./numberAllocatorBridge";

export type EnsureBridgeCompanyResult =
    /** Resolved (or created); null id means the local company has no usable identity. */
    | { ok: true; bridgeCompanyId: string | null }
    /** Lookup/creation failed (network, Evolu, server) - retryable, NOT "no bridge". */
    | { ok: false };

/**
 * Resolves (or creates) the server bridge company for a LOCAL Evolu company
 * by its local id (P1 follow-up: per-company bank inbound address).
 *
 * The bridge is matched by legal identity and created on demand with the
 * local company's jurisdiction/country/currency as creation hints - the same
 * machinery the number allocator uses, so features like the bank inbound
 * e-mail work for every company, including ones that never had a server row.
 * The Evolu client is imported lazily.
 *
 * Failures are reported distinctly from "this company has no identity" so
 * callers do not present a transient error as a permanently missing bridge.
 */
export async function ensureBridgeCompanyIdForLocalCompany(
    localCompanyId: string,
): Promise<EnsureBridgeCompanyResult> {
    if (!localCompanyId) {
        return { ok: true, bridgeCompanyId: null };
    }
    try {
        const { evolu, allCompaniesDetailQuery } = await import("./client");
        const rows = await evolu.loadQuery(allCompaniesDetailQuery);
        const row = rows.find((company) => company.id === localCompanyId);
        if (!row?.legalName) {
            return { ok: true, bridgeCompanyId: null };
        }
        const bridgeCompanyId = await resolveOrCreateBridgeCompanyId({
            legal_name: row.legalName,
            registration_number: row.registrationNumber ?? null,
            jurisdiction: row.jurisdiction ?? null,
            country: row.country ?? null,
            default_currency: row.defaultCurrency ?? null,
        });
        return { ok: true, bridgeCompanyId };
    } catch (error) {
        console.error(
            "[invoicing] bridge company resolution failed for local company",
            localCompanyId,
            error instanceof Error ? error.message : error,
        );
        return { ok: false };
    }
}
