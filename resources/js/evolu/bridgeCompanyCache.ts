/**
 * Session-scoped memory of local companies known to have NO server bridge
 * company. Number-series preview/reserve consult it to skip server calls that
 * can only answer 422 store_not_linked - purely local companies fall back to
 * local numbering without hammering (and console-logging) the API on every
 * form open. Module-scoped on purpose: a page reload retries once.
 */

const knownUnbridged = new Set<string>();

export function markCompanyUnbridged(identityKey: string): void {
    knownUnbridged.add(identityKey);
}

/** Call when a bridge company turns out to exist after all (e.g. settings save synced the link). */
export function clearCompanyUnbridged(identityKey: string): void {
    knownUnbridged.delete(identityKey);
}

export function isCompanyKnownUnbridged(identityKey: string): boolean {
    return knownUnbridged.has(identityKey);
}
