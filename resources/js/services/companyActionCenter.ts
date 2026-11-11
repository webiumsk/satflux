import api, { invoicingApi, type ApiEnvelope } from "./api";
import { isServerUuid } from "@/utils/serverIds";

export const ACTION_CENTER_KEYS = ["unmatched_payments", "failed_sends", "synchronization", "incoming_documents"] as const;
export type ActionCenterKey = typeof ACTION_CENTER_KEYS[number];
export type ActionCenterState = "ready" | "unavailable" | "not_configured";
export type ActionCenterItem = {
    id: string;
    kind: string;
    reference: string | null;
    document_id: string | null;
    occurred_at: string | null;
    amount: string | null;
    currency: string | null;
    /** Opaque matching evidence only; never displayed or used as payment confirmation. */
    bank_dedupe_hash?: string | null;
    document_type?: string | null;
};
export type ActionCenterCategory = {
    key: ActionCenterKey;
    state: ActionCenterState;
    count: number | null;
    items: ActionCenterItem[];
    has_more: boolean;
};
export type CompanyActionCenterPayload = {
    checked_at: string;
    categories: ActionCenterCategory[];
    restricted_sources: string[];
};

export function emptyActionCenterCategories(state: ActionCenterState): ActionCenterCategory[] {
    return ACTION_CENTER_KEYS.map((key) => ({ key, state, count: state === "ready" ? 0 : null, items: [], has_more: false }));
}

/** Reject incomplete payloads instead of rendering a malformed/old response as an all-clear. */
export function validateActionCenterPayload(raw: unknown): CompanyActionCenterPayload {
    if (!raw || typeof raw !== "object") throw new Error("action_center_invalid_response");
    const value = raw as Partial<CompanyActionCenterPayload>;
    if (typeof value.checked_at !== "string" || !Number.isFinite(Date.parse(value.checked_at))
        || !Array.isArray(value.categories) || value.categories.length !== ACTION_CENTER_KEYS.length
        || !Array.isArray(value.restricted_sources) || value.restricted_sources.some((key) => typeof key !== "string")) {
        throw new Error("action_center_invalid_response");
    }
    const seen = new Set<string>();
    for (const category of value.categories) {
        if (!category || !ACTION_CENTER_KEYS.includes(category.key) || seen.has(category.key)
            || !["ready", "unavailable", "not_configured"].includes(category.state)
            || !Array.isArray(category.items) || category.items.length > 25 || typeof category.has_more !== "boolean"
            || (category.state === "ready" && (!Number.isSafeInteger(category.count) || (category.count ?? -1) < category.items.length))
            || (category.state !== "ready" && (category.count !== null || category.items.length > 0 || category.has_more))) {
            throw new Error("action_center_invalid_response");
        }
        for (const item of category.items) {
            if (!item || typeof item.id !== "string" || !item.id || typeof item.kind !== "string"
                || [item.reference, item.document_id, item.occurred_at, item.amount, item.currency].some((field) => field !== null && typeof field !== "string")
                || (item.bank_dedupe_hash !== undefined && item.bank_dedupe_hash !== null && typeof item.bank_dedupe_hash !== "string")) {
                throw new Error("action_center_invalid_response");
            }
        }
        seen.add(category.key);
    }
    return value as CompanyActionCenterPayload;
}

export async function fetchCompanyActionCenter(companyId: string, signal: AbortSignal): Promise<CompanyActionCenterPayload> {
    if (!isServerUuid(companyId)) throw new Error("action_center_invalid_company");
    const response = await api.get<ApiEnvelope<CompanyActionCenterPayload>>(
        `/invoicing/companies/${encodeURIComponent(companyId)}/action-center`,
        { signal, timeout: 15_000, skipErrorFlash: true },
    );
    return validateActionCenterPayload(response.data.data);
}

/** Find an existing bridge with the same canonical identity rules; viewing this page never creates one. */
export async function findActionCenterBridgeCompany(companyId: string, signal: AbortSignal): Promise<string | null> {
    const [{ evolu, allCompaniesDetailQuery }, shares, { matchCompanyByIdentity }, { loadEvoluQueryWithTimeout }] = await Promise.all([
        import("@/evolu/client"), import("@/evolu/companyShareRegistry"),
        import("@/evolu/duplicateCompanies"), import("@/evolu/queryLoad"),
    ]);
    await shares.waitCompanyShareRegistryReady(signal);
    const routing = shares.companyShareRoutingKey(companyId);
    const current = () => !signal.aborted && routing === shares.companyShareRoutingKey(companyId);
    const rows = await loadEvoluQueryWithTimeout(evolu, allCompaniesDetailQuery);
    if (!current()) throw new Error("action_center_context_changed");
    const matches = rows.filter((row) => row.id === companyId);
    const company = matches[0];
    if (matches.length !== 1 || !company || !("ownerId" in company)) throw new Error("action_center_company_unavailable");
    const share = shares.companyShareInfo(companyId);
    if (share) {
        if ((share.status !== "active" && share.status !== "migrating") || share.ownerId !== company.ownerId
            || !isServerUuid(share.bridgeCompanyId)) throw new Error("action_center_company_unavailable");
        return share.bridgeCompanyId;
    }
    const owner = await evolu.appOwner;
    if (!current() || company.ownerId !== owner.id) throw new Error("action_center_company_unavailable");
    if (!company.legalName?.trim()) return null;
    const bridgeRows = await invoicingApi.companies.list<{ id: string; legal_name: string; registration_number?: string | null }>({
        signal, timeout: 15_000, skipErrorFlash: true,
    });
    if (!current()) throw new Error("action_center_context_changed");
    const found = matchCompanyByIdentity([...bridgeRows].sort((a, b) => a.id.localeCompare(b.id)), {
        legal_name: company.legalName, registration_number: company.registrationNumber,
    });
    return found && isServerUuid(found.id) ? found.id : null;
}
