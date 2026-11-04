import { shallowRef, watch } from "vue";
import type { OwnerId } from "@evolu/common/local-first";

/**
 * In-memory view of which local company is shared under which SharedOwner.
 * Fed from the `companyShare` Evolu table (see companyShareBootstrap.ts) and
 * consulted synchronously by the mutation scoping proxy (ownerScope.ts) -
 * mutations cannot await, so this must be a plain map.
 */

export type CompanyShareRole = "owner" | "accountant" | "member";
export type CompanyShareStatus = "migrating" | "active" | "revoked" | "rekeying";

export type CompanyShareInfo = {
    companyId: string;
    ownerId: OwnerId;
    role: CompanyShareRole;
    status: CompanyShareStatus;
    bridgeCompanyId: string | null;
};

const byCompany = new Map<string, CompanyShareInfo>();
const sharedOwnerIds = new Set<OwnerId>();
/** False until the first load from the `companyShare` table finished (see ownerScope.ts). */
let ready = false;
const routingRevision = shallowRef(0);

/** Observe only this company's validated routing metadata; unrelated company edits keep the key unchanged. */
export function companyShareRoutingKey(companyId: string): string {
    void routingRevision.value;
    const info = byCompany.get(companyId);
    return JSON.stringify([ready, info?.ownerId ?? null, info?.status ?? null, info?.role ?? null, info?.bridgeCompanyId ?? null]);
}

/** Publish semantic selected-route changes while retaining the existing synchronous ownership map. */
export function setCompanyShare(info: CompanyShareInfo): void {
    const previous = byCompany.get(info.companyId);
    const previousRouting = companyShareRoutingKey(info.companyId);
    if (previous && previous.ownerId !== info.ownerId) {
        sharedOwnerIds.delete(previous.ownerId);
    }
    byCompany.set(info.companyId, info);
    sharedOwnerIds.add(info.ownerId);
    if (companyShareRoutingKey(info.companyId) !== previousRouting) routingRevision.value++;
}

/** Invalidate selected bank routing when its trusted share binding disappears. */
export function removeCompanyShare(companyId: string): void {
    const previous = byCompany.get(companyId);
    if (previous) {
        sharedOwnerIds.delete(previous.ownerId);
    }
    byCompany.delete(companyId);
    if (previous) routingRevision.value++;
}

/** Clear bindings and readiness together so bank lookups cannot guess private ownership during bootstrap. */
export function clearCompanyShares(): void {
    const changed = byCompany.size > 0 || ready;
    byCompany.clear();
    sharedOwnerIds.clear();
    ready = false;
    if (changed) routingRevision.value++;
}

/** Publish initial registry readiness once its trusted bootstrap load has completed. */
export function markCompanyShareRegistryReady(): void {
    if (ready) return;
    ready = true;
    routingRevision.value++;
}

export function isCompanyShareRegistryReady(): boolean {
    return ready;
}

/** Await trusted bootstrap only for a bounded bank read; abort always removes the standalone observer. */
export function waitCompanyShareRegistryReady(signal: AbortSignal): Promise<void> {
    if (signal.aborted) return Promise.reject(new Error("company_share_registry_wait_aborted"));
    if (ready) return Promise.resolve();
    return new Promise<void>((resolve, reject) => {
        let stop = () => {};
        const finish = (error?: Error) => {
            stop();
            signal.removeEventListener("abort", abort);
            if (error) reject(error);
            else resolve();
        };
        const abort = () => finish(new Error("company_share_registry_wait_aborted"));
        stop = watch(routingRevision, () => { if (ready) finish(); }, { flush: "sync" });
        signal.addEventListener("abort", abort, { once: true });
    });
}

/** SharedOwner of a company, or undefined when the company is private (AppOwner). */
export function ownerIdForCompany(companyId: string | null | undefined): OwnerId | undefined {
    if (!companyId) return undefined;
    const info = byCompany.get(String(companyId));
    return info && info.status !== "revoked" ? info.ownerId : undefined;
}

export function companyShareInfo(companyId: string | null | undefined): CompanyShareInfo | undefined {
    return companyId ? byCompany.get(String(companyId)) : undefined;
}

export function isRegisteredSharedOwnerId(ownerId: string | null | undefined): boolean {
    return typeof ownerId === "string" && sharedOwnerIds.has(ownerId as OwnerId);
}

export function listCompanyShares(): CompanyShareInfo[] {
    return [...byCompany.values()];
}
