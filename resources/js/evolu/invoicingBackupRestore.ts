import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import {
    EMPTY_INVOICING_SNAPSHOT,
    restoreInvoicingSnapshotDetailedAsync,
    type InvoicingDataSnapshot,
    type SnapshotRestoreReport,
} from "./invoicingSnapshot";
import {
    INVOICING_BACKUP_FORMAT,
    INVOICING_BACKUP_VERSION,
    backupTableCounts,
    type InvoicingBackupEnvelope,
} from "./invoicingBackup";
import { deterministicStringify } from "./documentSnapshotCrud";
import { sha256Hex } from "@/utils/sha256";
import type { EncryptedBackupEnvelope } from "./invoicingBackupCrypto";

/**
 * Backup restore (P1 phase 3): parse + validate a backup file, verify its
 * SHA-256 integrity hash, then upsert the rows into the CURRENT owner. This
 * is a MERGE - rows with the same id are overwritten by the backup version,
 * new rows are added, nothing is deleted. The "test restore" is deliberately
 * validate-parse-only: schema + hash verification covers the realistic
 * failure modes without a second Evolu instance.
 */

export type BackupValidationError =
    | "invalid_json"
    | "invalid_format"
    | "unsupported_version"
    | "hash_missing"
    | "hash_mismatch"
    | "hash_unavailable"
    | "decrypt_failed";

/**
 * First-pass classification of a picked file (P2 phase 2): an encrypted
 * backup needs a passphrase before any validation can run, a plaintext one
 * goes straight to parseAndValidateBackup. "invalid" covers non-JSON too -
 * the caller surfaces it via the plaintext path for the precise error code.
 */
export async function classifyBackupText(
    text: string,
): Promise<{ kind: "plaintext" } | { kind: "encrypted"; envelope: EncryptedBackupEnvelope } | { kind: "invalid" }> {
    let parsed: unknown;
    try {
        parsed = JSON.parse(text);
    } catch {
        return { kind: "invalid" };
    }
    const { isEncryptedBackupEnvelope } = await import("./invoicingBackupCrypto");
    if (isEncryptedBackupEnvelope(parsed)) {
        return { kind: "encrypted", envelope: parsed };
    }
    const candidate = parsed as { format?: unknown } | null;
    if (candidate && typeof candidate === "object" && candidate.format === INVOICING_BACKUP_FORMAT) {
        return { kind: "plaintext" };
    }
    return { kind: "invalid" };
}

/**
 * Decrypt an encrypted backup and validate the inner plaintext envelope with
 * the exact same schema + SHA-256 pipeline as an unencrypted file. A wrong
 * passphrase and a tampered file are indistinguishable by design (GCM).
 */
export async function decryptAndValidateBackup(
    encrypted: EncryptedBackupEnvelope,
    passphrase: string,
    currentOwnerIdHash: string | null,
): Promise<{ ok: true; value: ValidatedBackup } | { ok: false; error: BackupValidationError }> {
    let innerText: string;
    try {
        const { decryptBackupEnvelopeText } = await import("./invoicingBackupCrypto");
        innerText = await decryptBackupEnvelopeText(encrypted, passphrase);
    } catch {
        return { ok: false, error: "decrypt_failed" };
    }
    return parseAndValidateBackup(innerText, currentOwnerIdHash);
}

export type ValidatedBackup = {
    envelope: InvoicingBackupEnvelope;
    tableCounts: Record<string, number>;
    /** Null when the backup carries no owner pairing or the current owner is unknown. */
    ownerMatches: boolean | null;
};

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

/**
 * Merges the validated backup into the current owner. Tables the backup does
 * not know (older exports vs newer schema) default to empty; unknown extra
 * keys are ignored by the upsert order.
 */
export async function restoreInvoicingBackup(
    evolu: Evolu<InvoicingLocalSchema>,
    validated: ValidatedBackup,
): Promise<SnapshotRestoreReport> {
    const snapshot: InvoicingDataSnapshot = {
        ...EMPTY_INVOICING_SNAPSHOT,
        ...validated.envelope.data,
    };
    return restoreInvoicingSnapshotDetailedAsync(evolu, snapshot);
}
