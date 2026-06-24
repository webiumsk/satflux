import { createIdFromString } from "@evolu/common";
import { normalizeCompanyIdentityKey } from "./duplicateCompanies";
import { CompanyId } from "./schema";

/** Same legal identity → same Evolu company id on every device (upsert, not duplicate insert). */
export function stableCompanyIdFromIdentity(
    legalName: string,
    registrationNumber?: string | null,
): CompanyId | null {
    const key = normalizeCompanyIdentityKey(legalName, registrationNumber ?? null);
    const stable = createIdFromString(`satflux.company.v1.${key}`);
    const parsed = CompanyId.from(stable);
    return parsed.ok ? parsed.value : null;
}
