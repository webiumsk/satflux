import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import {
    snapshotInvoicingData,
    type InvoicingDataSnapshot,
} from "./invoicingSnapshot";
import { deterministicStringify } from "./documentSnapshotCrud";
import { sha256Hex } from "@/utils/sha256";
import { setLastExportMeta, type LastExportMeta } from "@/services/backupState";

/**
 * Client-side full-dataset backup (P1 phase 2).
 *
 * The envelope wraps the complete 18-table invoicing snapshot with a SHA-256
 * integrity hash over its deterministic serialization, so restore can detect
 * corruption or tampering. The file is PLAINTEXT by design (v1, user
 * decision): a backup must survive the loss of the recovery phrase, so it
 * cannot be keyed to it - the UI warns that the file contains readable
 * invoices. It contains the owner id only as a SHA-256 hash (used to pair a
 * backup with an account) - never the id itself or the mnemonic.
 */

export const INVOICING_BACKUP_FORMAT = "satflux-invoicing-backup";
export const INVOICING_BACKUP_VERSION = 1;

export type InvoicingBackupEnvelope = {
    format: typeof INVOICING_BACKUP_FORMAT;
    version: typeof INVOICING_BACKUP_VERSION;
    created_at: string;
    app_version: string | null;
    /** Null only when the backup was made without a known owner id. */
    owner_id_hash: string | null;
    table_counts: Record<string, number>;
    sha256: string;
    data: InvoicingDataSnapshot;
};

export function backupTableCounts(snapshot: InvoicingDataSnapshot): Record<string, number> {
    const counts: Record<string, number> = {};
    for (const [table, rows] of Object.entries(snapshot)) {
        counts[table] = Array.isArray(rows) ? rows.length : 0;
    }
    return counts;
}

function appVersion(): string | null {
    const fromEnv = import.meta.env.VITE_APP_VERSION;
    return typeof fromEnv === "string" && fromEnv.trim() !== "" ? fromEnv.trim() : null;
}

/**
 * Pure envelope builder - unit tested without an Evolu instance. Throws when
 * the WebCrypto digest is unavailable: a backup without a verifiable
 * integrity hash (or with a silently missing owner pairing) must not be
 * produced at all.
 */
export async function buildBackupEnvelopeFromSnapshot(
    snapshot: InvoicingDataSnapshot,
    ownerId: string | null,
    now: Date = new Date(),
): Promise<InvoicingBackupEnvelope> {
    const sha256 = await sha256Hex(deterministicStringify(snapshot));
    if (!sha256) {
        throw new Error("backup_hash_unavailable");
    }
    const ownerIdHash = ownerId ? await sha256Hex(ownerId) : null;
    if (ownerId && !ownerIdHash) {
        throw new Error("backup_hash_unavailable");
    }
    return {
        format: INVOICING_BACKUP_FORMAT,
        version: INVOICING_BACKUP_VERSION,
        created_at: now.toISOString(),
        app_version: appVersion(),
        owner_id_hash: ownerIdHash,
        table_counts: backupTableCounts(snapshot),
        sha256,
        data: snapshot,
    };
}

export async function buildBackupEnvelope(
    evolu: Evolu<InvoicingLocalSchema>,
    ownerId: string | null,
): Promise<InvoicingBackupEnvelope> {
    const snapshot = await snapshotInvoicingData(evolu);
    return buildBackupEnvelopeFromSnapshot(snapshot, ownerId);
}

export function backupFilename(date: Date = new Date()): string {
    const yyyy = date.getFullYear();
    const mm = String(date.getMonth() + 1).padStart(2, "0");
    const dd = String(date.getDate()).padStart(2, "0");
    return `satflux-backup-${yyyy}-${mm}-${dd}.json`;
}

function triggerDownload(content: string, filename: string): void {
    const blob = new Blob([content], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
}

/**
 * Builds the envelope, downloads it as a JSON file and records the export
 * evidence (read by the data-loss guards and the Profile card).
 */
export async function exportInvoicingBackup(
    evolu: Evolu<InvoicingLocalSchema>,
    ownerId: string | null,
): Promise<LastExportMeta> {
    const envelope = await buildBackupEnvelope(evolu, ownerId);
    triggerDownload(JSON.stringify(envelope), backupFilename());

    const meta: LastExportMeta = {
        exportedAt: envelope.created_at,
        sha256: envelope.sha256,
        tableCounts: envelope.table_counts,
    };
    if (envelope.owner_id_hash) {
        setLastExportMeta(envelope.owner_id_hash, meta);
    }
    return meta;
}
