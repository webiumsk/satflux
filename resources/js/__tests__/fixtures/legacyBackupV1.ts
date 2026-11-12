/** Historical reader from Satflux base 6e6c0587; parser function kept verbatim for cross-version tests. */
import { INVOICING_BACKUP_FORMAT, INVOICING_BACKUP_VERSION, backupTableCounts, type InvoicingBackupEnvelope } from '@/evolu/invoicingBackup';
import type { BackupValidationError, ValidatedBackup } from '@/evolu/invoicingBackupRestore';
import type { InvoicingDataSnapshot } from '@/evolu/invoicingSnapshot';
import { deterministicStringify } from '@/evolu/documentSnapshotCrud';
import { sha256Hex } from '@/utils/sha256';

export async function parseAndValidateBackup(
    text: string,
    currentOwnerIdHash: string | null,
): Promise<{ ok: true; value: ValidatedBackup } | { ok: false; error: BackupValidationError }> {
    let parsed: unknown;
    try {
        parsed = JSON.parse(text);
    } catch {
        return { ok: false, error: "invalid_json" };
    }

    const envelope = parsed as Partial<InvoicingBackupEnvelope> | null;
    if (
        !envelope
        || typeof envelope !== "object"
        || envelope.format !== INVOICING_BACKUP_FORMAT
        || !envelope.data
        || typeof envelope.data !== "object"
    ) {
        return { ok: false, error: "invalid_format" };
    }
    if (envelope.version !== INVOICING_BACKUP_VERSION) {
        return { ok: false, error: "unsupported_version" };
    }
    if (typeof envelope.sha256 !== "string" || envelope.sha256 === "") {
        return { ok: false, error: "hash_missing" };
    }

    // Verify integrity over the data exactly as stored in the file.
    const computed = await sha256Hex(deterministicStringify(envelope.data));
    if (computed === null) {
        return { ok: false, error: "hash_unavailable" };
    }
    if (computed !== envelope.sha256) {
        return { ok: false, error: "hash_mismatch" };
    }

    const ownerMatches =
        envelope.owner_id_hash && currentOwnerIdHash
            ? envelope.owner_id_hash === currentOwnerIdHash
            : null;

    return {
        ok: true,
        value: {
            envelope: envelope as InvoicingBackupEnvelope,
            tableCounts: backupTableCounts(envelope.data as InvoicingDataSnapshot),
            ownerMatches,
        },
    };
}
