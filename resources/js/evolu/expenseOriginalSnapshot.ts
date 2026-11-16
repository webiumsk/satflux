import {
    EXPENSE_ORIGINAL_SOURCE_KINDS, expenseOriginalMimeType, expenseOriginalSourceKind, isExpenseOriginalSourceKind,
    isExpenseOriginalReference,
    parseExpenseOriginalReference,
} from "./expenseOriginalReference";
import { verifyExpenseOriginalArchiveRows } from "./expenseOriginalArchive";
import { validateMigrationSnapshot } from "./migrationSnapshot";
import type { InvoicingDataSnapshot } from "./invoicingSnapshot";

type Row = Readonly<Record<string, unknown>>;

/** Detect even incomplete or unsupported representations before selecting a backup version. */
export function snapshotHasExpenseOriginals(snapshot: InvoicingDataSnapshot): boolean {
    return (snapshot.expenseAttachment ?? []).some(row => isExpenseOriginalReference(row.contentBase64))
        || (snapshot.migrationSource ?? []).some(row => typeof row.sourceKind === "string"
            && EXPENSE_ORIGINAL_SOURCE_KINDS.some(kind => String(row.sourceKind).startsWith(kind)));
}

/** Keep established migration validation unchanged for its own archive namespace. */
export async function validateRegularMigrationSnapshot(snapshot: InvoicingDataSnapshot): Promise<void> {
    const sources = snapshot.migrationSource ?? [];
    const chunks = snapshot.migrationChunk ?? [];
    if (!Array.isArray(sources) || !Array.isArray(chunks)) throw new Error("archive_unverified");
    const originalIds = new Set(sources.filter(row => isExpenseOriginalSourceKind(row.sourceKind)).map(row => row.id));
    await validateMigrationSnapshot({
        company: snapshot.company ?? [],
        migrationSource: sources.filter(row => !originalIds.has(row.id)),
        migrationChunk: chunks.filter(row => !originalIds.has(row.sourceId)),
    });
}

function one(rows: readonly Row[], id: unknown): Row {
    const matches = rows.filter(row => row.id === id);
    if (matches.length !== 1 || (matches[0].isDeleted != null && matches[0].isDeleted !== 0)) throw new Error("original_unverified");
    return matches[0];
}

/**
 * A snapshot is portable across owners, but each original must still belong to
 * exactly one company/expense/attachment and one consistent source owner.
 * Complete validation happens before backup output and before any restore write.
 */
export async function validateExpenseOriginalSnapshot(snapshot: InvoicingDataSnapshot): Promise<void> {
    if (!snapshotHasExpenseOriginals(snapshot)) return;
    for (const table of [snapshot.company, snapshot.expense, snapshot.expenseAttachment,
        snapshot.migrationSource, snapshot.migrationChunk]) {
        if (!Array.isArray(table)) throw new Error("original_unverified");
    }
    const sources = snapshot.migrationSource ?? [];
    const chunks = snapshot.migrationChunk ?? [];
    const originalSources = sources.filter(row => isExpenseOriginalSourceKind(row.sourceKind));
    if (sources.some(row => typeof row.sourceKind === "string" && EXPENSE_ORIGINAL_SOURCE_KINDS.some(kind => String(row.sourceKind).startsWith(kind))
        && !isExpenseOriginalSourceKind(row.sourceKind))) throw new Error("original_unverified");
    const referenced = new Set<string>();
    for (const attachment of snapshot.expenseAttachment) {
        if (!isExpenseOriginalReference(attachment.contentBase64)) continue;
        const ref = parseExpenseOriginalReference(attachment.contentBase64);
        if (!ref || ref.attachmentId !== attachment.id || ref.expenseId !== attachment.expenseId
            || String(ref.sizeBytes) !== attachment.sizeBytes
            || attachment.mimeType !== expenseOriginalMimeType(ref) || referenced.has(ref.sourceId)) throw new Error("original_unverified");
        one(snapshot.expenseAttachment, ref.attachmentId);
        const company = one(snapshot.company, ref.companyId);
        const expense = one(snapshot.expense, ref.expenseId);
        const source = one(sources, ref.sourceId);
        if (expense.companyId !== ref.companyId || source.sourceKind !== expenseOriginalSourceKind(ref)
            || attachment.originalFilename !== source.originalFilename || attachment.mimeType !== source.mimeType) throw new Error("original_unverified");
        const archiveChunks = chunks.filter(row => row.sourceId === ref.sourceId);
        const related = [company, expense, attachment, source, ...archiveChunks];
        const owner = attachment.ownerId;
        if (owner != null && (typeof owner !== "string" || owner === "")) throw new Error("original_unverified");
        if (related.some(row => (row.ownerId ?? undefined) !== (owner ?? undefined))) throw new Error("original_unverified");
        await verifyExpenseOriginalArchiveRows(ref, [source], archiveChunks, typeof owner === "string" ? owner : undefined);
        referenced.add(ref.sourceId);
    }
    // An unfinished, unbound or foreign archive is never advertised as a safe backup.
    if (originalSources.some(row => typeof row.id !== "string" || !referenced.has(row.id))) throw new Error("original_unverified");
}
