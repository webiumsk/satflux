/**
 * Drops repeated occurrences of the SAME row id, keeping the first. The
 * reactive Evolu query layer can surface one physical row multiple times
 * (observed with the relay sync owner subscribed) - such phantom repeats are
 * not duplicates to merge and must not trigger the duplicate-companies flow.
 */
export function dedupeRowsById<T extends { id: string }>(rows: readonly T[]): T[] {
    const seen = new Set<string>();
    const unique: T[] = [];
    for (const row of rows) {
        if (seen.has(row.id)) continue;
        seen.add(row.id);
        unique.push(row);
    }
    return unique;
}

export function normalizeCompanyIdentityKey(
    legalName: string,
    registrationNumber?: string | null,
): string {
    const name = legalName.trim().toLowerCase();
    const reg = (registrationNumber ?? "").trim().toLowerCase();
    return reg ? `${name}|${reg}` : name;
}

/**
 * Finds the server bridge company matching a local Evolu company identity.
 *
 * Exact (name + registration number) match wins. When one side is missing the
 * registration number - typical for legacy bridge rows created before the
 * field was filled locally - falls back to a name-only match, but only when
 * exactly one candidate carries that name, so a store link can never be
 * cross-wired between two same-named companies.
 */
export function matchCompanyByIdentity<
    T extends { id: string; legal_name: string; registration_number?: string | null },
>(
    companies: readonly T[],
    identity: { legal_name: string; registration_number?: string | null },
): T | null {
    const key = normalizeCompanyIdentityKey(identity.legal_name, identity.registration_number ?? null);
    const exact = companies.find(
        (row) => normalizeCompanyIdentityKey(row.legal_name, row.registration_number ?? null) === key,
    );
    if (exact) {
        return exact;
    }

    const name = identity.legal_name.trim().toLowerCase();
    if (!name) {
        return null;
    }
    const targetReg = (identity.registration_number ?? "").trim().toLowerCase();
    const relaxed = companies.filter((row) => {
        if (row.legal_name.trim().toLowerCase() !== name) {
            return false;
        }
        const rowReg = (row.registration_number ?? "").trim().toLowerCase();
        // Regs present on both sides but different = two distinct companies.
        return rowReg === "" || targetReg === "";
    });

    return relaxed.length === 1 ? relaxed[0] : null;
}

export function findDuplicateCompanyGroups<
    T extends { id: string; legal_name: string; registration_number?: string | null },
>(companies: readonly T[]): T[][] {
    const groups = new Map<string, T[]>();

    // Phantom same-id repeats are not mergeable duplicates.
    for (const company of dedupeRowsById(companies)) {
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
