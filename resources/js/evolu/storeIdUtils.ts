export function isKnownStoreId(storeId: string | null | undefined, validStoreIds: ReadonlySet<string>): boolean {
    if (!storeId?.trim()) {
        return false;
    }
    return validStoreIds.has(storeId.trim());
}
