export function normalizeCompanyIdentityKey(
    legalName: string,
    registrationNumber?: string | null,
): string {
    const name = legalName.trim().toLowerCase();
    const reg = (registrationNumber ?? "").trim().toLowerCase();
    return reg ? `${name}|${reg}` : name;
}

export function findDuplicateCompanyGroups<
    T extends { id: string; legal_name: string; registration_number?: string | null },
>(companies: readonly T[]): T[][] {
    const groups = new Map<string, T[]>();

    for (const company of companies) {
        const key = normalizeCompanyIdentityKey(
            company.legal_name,
            company.registration_number ?? null,
        );
        const bucket = groups.get(key) ?? [];
        bucket.push(company);
        groups.set(key, bucket);
    }

    return [...groups.values()].filter((group) => group.length > 1);
}
