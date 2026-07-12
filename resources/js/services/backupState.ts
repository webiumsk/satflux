import { hasLegacyPersistedMnemonic } from "./accountSeed";

/**
 * Backup evidence for the data-loss guards (P1).
 *
 * Tracks when the user last exported their invoicing dataset (written by the
 * backup export in P1 phase 2) and whether the recovery phrase still sits
 * only in this browser (legacy plaintext localStorage copy - the strongest
 * "never confirmed a backup" signal).
 */

export type LastExportMeta = {
    exportedAt: string;
    sha256: string;
    tableCounts: Record<string, number>;
};

const LAST_EXPORT_KEY_PREFIX = "satflux.backup.last_export.v1.";

/** True when the plaintext recovery phrase still sits in localStorage (backup wizard never finished). */
export function hasLegacyPlaintextMnemonic(): boolean {
    return hasLegacyPersistedMnemonic();
}

export function lastExportStorageKey(ownerIdHash: string): string {
    return `${LAST_EXPORT_KEY_PREFIX}${ownerIdHash}`;
}

export function setLastExportMeta(ownerIdHash: string, meta: LastExportMeta): void {
    try {
        localStorage.setItem(lastExportStorageKey(ownerIdHash), JSON.stringify(meta));
    } catch {
        // Storage unavailable - the export itself still succeeded.
    }
}

export function getLastExportMeta(ownerIdHash: string): LastExportMeta | null {
    try {
        return parseMeta(localStorage.getItem(lastExportStorageKey(ownerIdHash)));
    } catch {
        return null;
    }
}

/**
 * Newest export meta across all owners on this device. The guards use it for
 * the informational "last export" line when the current owner hash is not
 * readily available (e.g. the logout guard outside the invoicing context).
 */
export function getAnyLastExportMeta(): LastExportMeta | null {
    try {
        let newest: LastExportMeta | null = null;
        for (let i = 0; i < localStorage.length; i += 1) {
            const key = localStorage.key(i);
            if (!key?.startsWith(LAST_EXPORT_KEY_PREFIX)) continue;
            const meta = parseMeta(localStorage.getItem(key));
            if (meta && (!newest || meta.exportedAt > newest.exportedAt)) {
                newest = meta;
            }
        }
        return newest;
    } catch {
        return null;
    }
}

function parseMeta(raw: string | null): LastExportMeta | null {
    if (!raw) return null;
    try {
        const parsed = JSON.parse(raw) as Partial<LastExportMeta>;
        if (typeof parsed.exportedAt !== "string" || !parsed.exportedAt) return null;
        return {
            exportedAt: parsed.exportedAt,
            sha256: typeof parsed.sha256 === "string" ? parsed.sha256 : "",
            tableCounts: parsed.tableCounts && typeof parsed.tableCounts === "object" ? parsed.tableCounts : {},
        };
    } catch {
        return null;
    }
}

export const BACKUP_STALE_AFTER_DAYS = 7;
const REMINDER_SNOOZE_KEY = "satflux.backup.reminder_snooze.v1";
const DAY_MS = 24 * 60 * 60 * 1000;

/** True when no export exists or the newest one is older than the staleness window. */
export function isBackupStale(
    meta: LastExportMeta | null,
    now: Date = new Date(),
    maxAgeDays: number = BACKUP_STALE_AFTER_DAYS,
): boolean {
    if (!meta) return true;
    const exportedAt = new Date(meta.exportedAt).getTime();
    if (Number.isNaN(exportedAt)) return true;
    return now.getTime() - exportedAt > maxAgeDays * DAY_MS;
}

export function snoozeBackupReminder(now: Date = new Date(), days: number = BACKUP_STALE_AFTER_DAYS): void {
    try {
        localStorage.setItem(REMINDER_SNOOZE_KEY, new Date(now.getTime() + days * DAY_MS).toISOString());
    } catch {
        // Storage unavailable - the banner just reappears.
    }
}

export function isBackupReminderSnoozed(now: Date = new Date()): boolean {
    try {
        const until = localStorage.getItem(REMINDER_SNOOZE_KEY);
        if (!until) return false;
        const untilTime = new Date(until).getTime();
        return !Number.isNaN(untilTime) && untilTime > now.getTime();
    } catch {
        return false;
    }
}

export type LogoutGuardDecision = "block" | "confirm" | "none";

/**
 * Logout guard policy (pure - unit tested):
 * - non-guest accounts sign out unchanged (password login recovers access),
 * - a guest whose plaintext phrase still sits only in this browser AND who
 *   has local data gets a BLOCKING typed-word confirm - logout deletes that
 *   only phrase copy, making the encrypted data unrecoverable,
 * - any other guest gets a lightweight confirm (session phrase is dropped,
 *   signing back in requires the phrase).
 */
export function shouldBlockLogout(input: {
    isGuest: boolean;
    hasLegacyPhrase: boolean;
    hasLocalData: boolean;
}): LogoutGuardDecision {
    if (!input.isGuest) return "none";
    if (input.hasLegacyPhrase && input.hasLocalData) return "block";
    return "confirm";
}
