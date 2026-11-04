import { companyShareInfo, companyShareRoutingKey, isCompanyShareRegistryReady } from "./companyShareRegistry";
import { withBankReadBudget } from "./bankReadBudget";
import { isServerUuid } from "@/utils/serverIds";
import { resolveOrCreateBridgeCompanyId, type BridgeCompanyRequestOptions } from "./numberAllocatorBridge";

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
 * The Evolu client is imported lazily. Optional transport controls are recomputed
 * before each server request; omitted controls preserve ordinary caller behavior.
 *
 * Failures are reported distinctly from "this company has no identity" so
 * callers do not present a transient error as a permanently missing bridge.
 * Bank callers can prefer the validated shared-company pin; unresolved shared
 * routing fails closed, while default numbering/other callers retain identity matching.
 */
export async function ensureBridgeCompanyIdForLocalCompany(
    localCompanyId: string,
    requestOptions?: BridgeCompanyRequestOptions,
    options: { preferRecordedBridgeId?: boolean } = {},
): Promise<EnsureBridgeCompanyResult> {
    if (!localCompanyId) {
        return { ok: true, bridgeCompanyId: null };
    }
    /** Resolve metadata and bridge HTTP inside the caller's read stage; no credential or financial write is included. */
    const resolve = async (controls = requestOptions): Promise<EnsureBridgeCompanyResult> => {
        const assertRead = () => { if (options.preferRecordedBridgeId) controls?.(); };
        if (options.preferRecordedBridgeId && !isCompanyShareRegistryReady()) return { ok: false };
        const routingKey = options.preferRecordedBridgeId ? companyShareRoutingKey(localCompanyId) : null;
        assertRead();
        const { evolu, allCompaniesDetailQuery } = await import("./client");
        assertRead();
        const rows = await evolu.loadQuery(allCompaniesDetailQuery);
        assertRead();
        const matchingRows = rows.filter((company) => company.id === localCompanyId);
        const row = matchingRows[0];
        if (options.preferRecordedBridgeId) {
            if (companyShareRoutingKey(localCompanyId) !== routingKey
                || matchingRows.length !== 1 || !("ownerId" in row)) return { ok: false };
            const share = companyShareInfo(localCompanyId);
            if (share) {
                if ((share.status !== "active" && share.status !== "migrating")
                    || row.ownerId !== share.ownerId || !isServerUuid(share.bridgeCompanyId)) {
                    return { ok: false };
                }
                return { ok: true, bridgeCompanyId: share.bridgeCompanyId.trim() };
            }
            const appOwner = await evolu.appOwner;
            assertRead();
            if (row.ownerId !== appOwner.id || companyShareRoutingKey(localCompanyId) !== routingKey) return { ok: false };
        }
        if (!row?.legalName) {
            return { ok: true, bridgeCompanyId: null };
        }
        const identity = {
            legal_name: row.legalName,
            registration_number: row.registrationNumber ?? null,
            jurisdiction: row.jurisdiction ?? null,
            country: row.country ?? null,
            default_currency: row.defaultCurrency ?? null,
        };
        const bridgeCompanyId = controls
            ? await resolveOrCreateBridgeCompanyId(identity, controls)
            : await resolveOrCreateBridgeCompanyId(identity);
        assertRead();
        if (options.preferRecordedBridgeId && companyShareRoutingKey(localCompanyId) !== routingKey) return { ok: false };
        return { ok: true, bridgeCompanyId };
    };
    try {
        return options.preferRecordedBridgeId
            ? await withBankReadBudget((controls) => resolve(controls), { parentOptions: requestOptions })
            : await resolve();
    } catch (error) {
        console.error(
            "[invoicing] bridge company resolution failed for local company",
            localCompanyId,
            error instanceof Error ? error.message : error,
        );
        return { ok: false };
    }
}
