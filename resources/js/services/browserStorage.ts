/**
 * Browser storage quota awareness (P1 phase 5).
 *
 * The local-first invoicing database lives in browser storage the browser may
 * evict under pressure. requestPersistence() asks for durable storage once
 * per page load; getStorageEstimate() feeds the Profile usage card and the
 * 80% warning banner. Everything degrades to "unknown" on browsers without
 * the StorageManager API - never to a false claim.
 */

export type StorageEstimateInfo = {
    usageBytes: number | null;
    quotaBytes: number | null;
    /** 0-100, null when usage or quota is unknown. */
    usagePercent: number | null;
};

export const STORAGE_WARNING_PERCENT = 80;

let persistenceRequested = false;
let persistedResult: boolean | null = null;

function storageManager(): StorageManager | null {
    if (typeof navigator === "undefined" || !navigator.storage) return null;
    return navigator.storage;
}

/**
 * Ask the browser to protect this origin's storage from eviction. Safe to
 * call repeatedly - the browser prompt (if any) fires at most once per load.
 * Returns null when the API is unavailable.
 */
export async function requestPersistence(): Promise<boolean | null> {
    const storage = storageManager();
    if (!storage?.persist) return null;
    if (persistenceRequested) return persistedResult;
    persistenceRequested = true;
    try {
        persistedResult = await storage.persist();
    } catch {
        persistedResult = null;
    }
    return persistedResult;
}

/** Current persistence grant without prompting. Null when unknown. */
export async function isStoragePersisted(): Promise<boolean | null> {
    const storage = storageManager();
    if (!storage?.persisted) return null;
    try {
        return await storage.persisted();
    } catch {
        return null;
    }
}

export async function getStorageEstimate(): Promise<StorageEstimateInfo> {
    const storage = storageManager();
    if (!storage?.estimate) {
        return { usageBytes: null, quotaBytes: null, usagePercent: null };
    }
    try {
        const estimate = await storage.estimate();
        const usageBytes = typeof estimate.usage === "number" ? estimate.usage : null;
        const quotaBytes = typeof estimate.quota === "number" ? estimate.quota : null;
        return {
            usageBytes,
            quotaBytes,
            usagePercent: storageUsagePercent(usageBytes, quotaBytes),
        };
    } catch {
        return { usageBytes: null, quotaBytes: null, usagePercent: null };
    }
}

export function storageUsagePercent(
    usageBytes: number | null,
    quotaBytes: number | null,
): number | null {
    if (usageBytes == null || quotaBytes == null || quotaBytes <= 0) return null;
    return Math.min(100, Math.round((usageBytes / quotaBytes) * 100));
}

export function isStorageUsageCritical(info: StorageEstimateInfo): boolean {
    return info.usagePercent != null && info.usagePercent >= STORAGE_WARNING_PERCENT;
}

/** Test hook. */
export function resetBrowserStorageStateForTests(): void {
    persistenceRequested = false;
    persistedResult = null;
}
