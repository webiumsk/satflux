import { isServerUuid } from "@/utils/serverIds";

export class IntegrationInboxPathError extends Error {
    constructor(readonly code: "store_required" | "invalid_company_id") {
        super(code);
        this.name = "IntegrationInboxPathError";
    }
}

export function resolveIntegrationInboxBasePath(
    companyId: string,
    linkedStoreId?: string | null,
): string {
    const storeId = typeof linkedStoreId === "string" ? linkedStoreId.trim() : "";
    if (storeId) {
        return `/invoicing/stores/${encodeURIComponent(storeId)}/integration-inbox`;
    }
    if (isServerUuid(companyId)) {
        return `/invoicing/companies/${companyId}/integration-inbox`;
    }
    throw new IntegrationInboxPathError("store_required");
}
