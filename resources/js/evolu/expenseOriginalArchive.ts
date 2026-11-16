import { sqliteTrue } from "@evolu/common";
import { createQuery, type Evolu } from "@evolu/common/local-first";
import { awaitBankMutation, awaitWorkerOutcome } from "./bankReconciliation";
import { expenseOriginalAttachmentProofQuery, expenseOriginalChunksProofQuery, migrationSourceProofQuery } from "./client";
import { insertLocalExpenseOriginalReference, LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES, type EvoluExpenseAttachmentRow } from "./expenseAttachmentCrud";
import { assertExpenseCompanyOwner, checkpointExpenseMutations, currentExpenseTarget, withExpenseReconciliation,
    type ExpenseReconciliationScope } from "./expenseReconciliation";
import { migrationSha256 } from "./migrationArchiveValues";
import { CompanyId, MigrationChunkId, MigrationSourceId, type InvoicingLocalSchema } from "./schema";
import { toAppRows } from "./queryLoad";
import { createExpenseOriginalReference, createManualExpenseOriginalReference, encodeExpenseOriginalReference, expenseOriginalChunkId,
    expenseOriginalMimeType, expenseOriginalSourceKind, EXPENSE_ORIGINAL_CHUNK_BYTES, EXPENSE_ORIGINAL_MAX_BYTES,
    parseExpenseOriginalReference, type ExpenseOriginalReference, type ManualExpenseOriginalReference,
    type ReceivedExpenseOriginalReference } from "./expenseOriginalReference";

type Client = Evolu<InvoicingLocalSchema>;
type Row = Readonly<Record<string, unknown>>;
const fail = (): never => { throw new Error("original_unverified"); };

function encode(bytes: Uint8Array): string {
    let binary = "";
    for (let offset = 0; offset < bytes.length; offset += 8192) binary += String.fromCharCode(...bytes.subarray(offset, offset + 8192));
    return btoa(binary);
}
function decode(value: unknown, maxBytes: number): Uint8Array {
    if (typeof value !== "string" || value.length > Math.ceil(maxBytes / 3) * 4 || value.length % 4 !== 0
        || !/^[A-Za-z0-9+/]*={0,2}$/.test(value)) return fail();
    const bytes = Uint8Array.from(atob(value), char => char.charCodeAt(0));
    if (bytes.length > maxBytes || encode(bytes) !== value) return fail();
    return bytes;
}
function owned(row: Row, ownerId: string | undefined): boolean {
    return ownerId === undefined ? row.ownerId == null : row.ownerId === ownerId;
}
function live(row: Row): boolean {
    return row.isDeleted == null || row.isDeleted === 0;
}
function sourceMatches(source: Row, ref: ExpenseOriginalReference): boolean {
    return source.id === ref.sourceId && source.companyId === ref.companyId && source.sourceKind === expenseOriginalSourceKind(ref)
        && source.relatedExportId == null && source.checksum === ref.sha256 && source.sizeBytes === String(ref.sizeBytes)
        && source.chunkCount === String(ref.chunkCount) && typeof source.originalFilename === "string"
        && source.originalFilename.length > 0 && source.originalFilename.length <= 255 && source.mimeType === expenseOriginalMimeType(ref);
}
async function verifyRows(ref: ExpenseOriginalReference, sources: readonly Row[], chunks: readonly Row[], ownerId: string | undefined, ready: boolean): Promise<Uint8Array> {
    // Recreate all derived bindings even when the caller supplies a typed object.
    encodeExpenseOriginalReference(ref);
    const candidates = sources.filter(row => row.id === ref.sourceId && owned(row, ownerId));
    if (candidates.length !== 1 || !live(candidates[0]) || !sourceMatches(candidates[0], ref)) return fail();
    if (ready && (typeof candidates[0].savedAt !== "string" || !Number.isFinite(Date.parse(candidates[0].savedAt as string)))) return fail();
    const rows = chunks.filter(row => row.sourceId === ref.sourceId && owned(row, ownerId));
    if (rows.length !== ref.chunkCount) return fail();
    const bytes = new Uint8Array(ref.sizeBytes);
    for (let index = 0; index < ref.chunkCount; index++) {
        const matches = rows.filter(row => row.chunkIndex === String(index));
        if (matches.length !== 1 || !live(matches[0]) || matches[0].companyId !== ref.companyId
            || matches[0].id !== expenseOriginalChunkId(ref.sourceId, index)) return fail();
        const length = Math.min(EXPENSE_ORIGINAL_CHUNK_BYTES, ref.sizeBytes - index * EXPENSE_ORIGINAL_CHUNK_BYTES);
        const content = decode(matches[0].contentBase64, length);
        if (content.length !== length) return fail();
        bytes.set(content, index * EXPENSE_ORIGINAL_CHUNK_BYTES);
    }
    if (await migrationSha256(bytes) !== ref.sha256) return fail();
    return bytes;
}

/** Pure snapshot/read-back proof. Undefined owner is allowed only for ownerless exported snapshots. */
export function verifyExpenseOriginalArchiveRows(ref: ExpenseOriginalReference, sources: readonly Row[], chunks: readonly Row[], ownerId?: string): Promise<Uint8Array> {
    return verifyRows(ref, sources, chunks, ownerId, true);
}
async function loadRows(evolu: Client, query: Parameters<Client["loadQuery"]>[0]): Promise<Row[]> {
    return awaitWorkerOutcome(evolu, (complete, unknown) => {
        void evolu.loadQuery(query).then(rows => complete(toAppRows(rows)), unknown);
    });
}
async function archiveRows(evolu: Client, ref: ExpenseOriginalReference) {
    const sources = await loadRows(evolu, migrationSourceProofQuery);
    const chunks = await loadRows(evolu, expenseOriginalChunksProofQuery(CompanyId.orThrow(ref.companyId), MigrationSourceId.orThrow(ref.sourceId)));
    return { sources, chunks };
}

type ArchiveInput = {
    companyId: string; ownerId: string; expenseId: string; attachmentId: string; filename: string;
    bytes: Uint8Array; assertCurrent: () => void | Promise<void>;
};
/** Exact-byte writer under the caller's existing financial lease. Pending retries never clear a ready cell. */
export function retainExpenseOriginalArchive(scope: ExpenseReconciliationScope, input: ArchiveInput & { receiptId: string; mimeType?: never }): Promise<ReceivedExpenseOriginalReference>;
export function retainExpenseOriginalArchive(scope: ExpenseReconciliationScope, input: ArchiveInput & { receiptId?: never; mimeType: string }): Promise<ManualExpenseOriginalReference>;
export async function retainExpenseOriginalArchive(scope: ExpenseReconciliationScope,
    input: ArchiveInput & ({ receiptId: string; mimeType?: never } | { receiptId?: never; mimeType: string })): Promise<ExpenseOriginalReference> {
    if (input.bytes.length < 1 || input.bytes.length > EXPENSE_ORIGINAL_MAX_BYTES || !input.filename || input.filename.length > 255
        || /[/\\]/.test(input.filename) || Array.from(input.filename).some(char => char.charCodeAt(0) < 32)) return fail();
    const digest = { sha256: await migrationSha256(input.bytes), sizeBytes: input.bytes.length };
    const ref = input.receiptId !== undefined ? createExpenseOriginalReference({ ...input, receiptId: input.receiptId, ...digest })
        : createManualExpenseOriginalReference({ ...input, mimeType: input.mimeType, ...digest });
    const companyId = CompanyId.orThrow(input.companyId);
    const id = MigrationSourceId.orThrow(ref.sourceId);
    const attachments = await loadRows(scope.evolu, expenseOriginalAttachmentProofQuery);
    if (attachments.some(row => row.id === input.attachmentId && row.ownerId === input.ownerId
        && (!live(row) || row.expenseId !== input.expenseId))) return fail();
    if (ref.version === 2) {
        const current = attachments.filter(row => row.id === input.attachmentId && row.ownerId === input.ownerId);
        if (current.length > 1 || current.some(row => row.contentBase64 !== encodeExpenseOriginalReference(ref)
            || row.originalFilename !== input.filename || row.mimeType !== ref.mimeType || row.sizeBytes !== String(ref.sizeBytes))) return fail();
    }
    const assertCurrent = async () => {
        await input.assertCurrent();
        assertExpenseCompanyOwner(scope, input.companyId, input.ownerId);
    };
    const metadata = { id, companyId, sourceKind: expenseOriginalSourceKind(ref), relatedExportId: null,
        originalFilename: input.filename, mimeType: expenseOriginalMimeType(ref), sizeBytes: String(ref.sizeBytes),
        checksum: ref.sha256, chunkCount: String(ref.chunkCount) };
    let proof = await archiveRows(scope.evolu, ref);
    const existing = proof.sources.filter(row => row.id === id && row.ownerId === input.ownerId);
    if (existing.length > 1 || existing.some(row => !live(row) || !sourceMatches(row, ref))) return fail();
    if (existing[0]?.savedAt) {
        await verifyExpenseOriginalArchiveRows(ref, proof.sources, proof.chunks, input.ownerId);
        await assertCurrent();
        return ref;
    }
    await assertCurrent();
    if (!await awaitBankMutation(scope.evolu, onComplete => {
        const source = scope.evolu.upsert("migrationSource", metadata,
            { ownerId: input.ownerId as never, ...(ref.version === 1 ? { onComplete } : {}) });
        if (!source.ok || ref.version === 1) return source;
        // A manual File has no durable inbox receipt. Link its pending source in
        // this same atomic batch so interruption always leaves a deletable row.
        const attachment = insertLocalExpenseOriginalReference(scope.evolu, input.expenseId as never,
            input.attachmentId as never, ref, input.filename, { ownerId: input.ownerId, onComplete });
        return attachment;
    })) return fail();
    await checkpointExpenseMutations(scope.evolu);
    for (let index = 0; index < ref.chunkCount; index++) {
        await assertCurrent();
        proof = await archiveRows(scope.evolu, ref);
        const source = proof.sources.filter(row => row.id === id && row.ownerId === input.ownerId);
        if (source.length !== 1 || !live(source[0]) || !sourceMatches(source[0], ref)) return fail();
        const ownedChunks = proof.chunks.filter(row => row.ownerId === input.ownerId);
        if (ownedChunks.some(row => row.sourceId !== id || row.companyId !== companyId || !live(row)
            || !Number.isInteger(Number(row.chunkIndex)) || String(Number(row.chunkIndex)) !== row.chunkIndex
            || Number(row.chunkIndex) < 0 || Number(row.chunkIndex) >= ref.chunkCount
            || row.id !== expenseOriginalChunkId(id, Number(row.chunkIndex)))) return fail();
        const matches = ownedChunks.filter(row => row.chunkIndex === String(index));
        const contentBase64 = encode(input.bytes.subarray(index * EXPENSE_ORIGINAL_CHUNK_BYTES, (index + 1) * EXPENSE_ORIGINAL_CHUNK_BYTES));
        if (matches.length > 1 || matches.some(row => row.contentBase64 !== contentBase64)) return fail();
        if (matches.length === 1) continue;
        if (!await awaitBankMutation(scope.evolu, onComplete => scope.evolu.upsert("migrationChunk", {
            id: MigrationChunkId.orThrow(expenseOriginalChunkId(id, index)), companyId, sourceId: id, chunkIndex: String(index), contentBase64,
        }, { ownerId: input.ownerId as never, onComplete }))) return fail();
        await checkpointExpenseMutations(scope.evolu);
    }
    proof = await archiveRows(scope.evolu, ref);
    await verifyRows(ref, proof.sources, proof.chunks, input.ownerId, false);
    await assertCurrent();
    if (!await awaitBankMutation(scope.evolu, onComplete => scope.evolu.upsert("migrationSource", {
        ...metadata, savedAt: new Date().toISOString(),
    }, { ownerId: input.ownerId as never, onComplete }))) return fail();
    await checkpointExpenseMutations(scope.evolu);
    proof = await archiveRows(scope.evolu, ref);
    await verifyExpenseOriginalArchiveRows(ref, proof.sources, proof.chunks, input.ownerId);
    await assertCurrent();
    return ref;
}

async function currentAttachment(scope: ExpenseReconciliationScope, companyId: string, attachment: EvoluExpenseAttachmentRow, expectedOwnerId?: string) {
    assertExpenseCompanyOwner(scope, companyId, expectedOwnerId);
    const expense = currentExpenseTarget(scope, attachment.expenseId);
    const ownerId = expense && Reflect.get(expense, "ownerId");
    if (!expense || expense.isDeleted === sqliteTrue || expense.companyId !== companyId || typeof ownerId !== "string") return fail();
    assertExpenseCompanyOwner(scope, companyId, ownerId);
    const rows = await loadRows(scope.evolu, expenseOriginalAttachmentProofQuery);
    const matches = rows.filter(row => row.id === attachment.id && row.ownerId === ownerId);
    if (matches.length !== 1 || !live(matches[0]) || matches[0].expenseId !== expense.id
        || matches[0].contentBase64 !== attachment.contentBase64 || matches[0].sizeBytes !== attachment.sizeBytes
        || matches[0].mimeType !== attachment.mimeType || matches[0].originalFilename !== attachment.originalFilename) return fail();
    return { ownerId, row: matches[0] };
}
async function readInScope(scope: ExpenseReconciliationScope, companyId: string, attachment: EvoluExpenseAttachmentRow, options: { maxBytes?: number; expectedOwnerId?: string }) {
    const { ownerId } = await currentAttachment(scope, companyId, attachment, options.expectedOwnerId);
    const ref = parseExpenseOriginalReference(attachment.contentBase64);
    const maxBytes = options.maxBytes ?? EXPENSE_ORIGINAL_MAX_BYTES;
    if (!Number.isSafeInteger(maxBytes) || maxBytes < 1 || maxBytes > EXPENSE_ORIGINAL_MAX_BYTES) return fail();
    let bytes: Uint8Array;
    if (ref) {
        assertReferenceBinding(ref, companyId, attachment);
        if (ref.sizeBytes > maxBytes) throw new Error("original_too_large");
        const proof = await archiveRows(scope.evolu, ref);
        bytes = await verifyExpenseOriginalArchiveRows(ref, proof.sources, proof.chunks, ownerId);
    } else {
        const content = attachment.contentBase64;
        if (typeof content !== "string") return fail();
        if (content.length > Math.ceil(Math.min(maxBytes, LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES) / 3) * 4) throw new Error("original_too_large");
        bytes = decode(content, Math.min(maxBytes, LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES));
        if (attachment.sizeBytes != null && attachment.sizeBytes !== String(bytes.length)) return fail();
    }
    await currentAttachment(scope, companyId, attachment, ownerId);
    return { bytes, blob: new Blob([new Uint8Array(bytes)], { type: attachment.mimeType || "application/octet-stream" }) };
}

function assertReferenceBinding(ref: ExpenseOriginalReference, companyId: string, attachment: EvoluExpenseAttachmentRow): void {
    if (ref.companyId !== companyId || ref.expenseId !== attachment.expenseId || ref.attachmentId !== attachment.id
        || attachment.sizeBytes !== String(ref.sizeBytes) || attachment.mimeType !== expenseOriginalMimeType(ref)) return fail();
}

/** Include intended chunk ids even if a damaged row points at another source/company. */
async function deletionArchiveRows(evolu: Client, ref: ExpenseOriginalReference) {
    const chunkIds = Array.from({ length: ref.chunkCount }, (_, index) => MigrationChunkId.orThrow(expenseOriginalChunkId(ref.sourceId, index)));
    const query = createQuery(db => db.selectFrom("migrationChunk").selectAll().where(eb => eb.or([
        eb("sourceId", "=", MigrationSourceId.orThrow(ref.sourceId)), eb("id", "in", chunkIds),
    ])));
    const sources = await loadRows(evolu, migrationSourceProofQuery);
    const chunks = (await loadRows(evolu, query)).filter(row => row.sourceId === ref.sourceId || chunkIds.includes(row.id as MigrationChunkId));
    return { sources, chunks };
}

/** Fresh authorization and exact content verification for UI/export consumers; no external fetching. */
export function readVerifiedExpenseAttachment(evolu: Client, companyId: string, attachment: EvoluExpenseAttachmentRow,
    options: { maxBytes?: number; expectedOwnerId?: string } = {}): Promise<{ bytes: Uint8Array; blob: Blob }> {
    return withExpenseReconciliation(evolu, scope => readInScope(scope, companyId, attachment, options), undefined, ["allExpenseAttachmentsQuery"]);
}

/** Delete a freshly authorized attachment and its own archive in one small atomic worker batch. */
export function deleteVerifiedExpenseAttachment(evolu: Client, companyId: string, attachment: EvoluExpenseAttachmentRow,
    options: { expectedOwnerId?: string } = {}): Promise<void> {
    return withExpenseReconciliation(evolu, async scope => {
        const { ownerId } = await currentAttachment(scope, companyId, attachment, options.expectedOwnerId);
        const ref = parseExpenseOriginalReference(attachment.contentBase64);
        const targets: { table: "expenseAttachment" | "migrationSource" | "migrationChunk"; id: string }[] = [
            { table: "expenseAttachment", id: attachment.id },
        ];
        if (ref) {
            assertReferenceBinding(ref, companyId, attachment);
            const proof = await deletionArchiveRows(scope.evolu, ref);
            const sources = proof.sources.filter(row => row.id === ref.sourceId && row.ownerId === ownerId);
            if (sources.length > 1 || sources.some(row => !sourceMatches(row, ref) || (!live(row) && row.isDeleted !== sqliteTrue))) return fail();
            const chunks = proof.chunks.filter(row => row.ownerId === ownerId);
            const seen = new Set<unknown>();
            for (const chunk of chunks) {
                const index = Number(chunk.chunkIndex);
                if (chunk.companyId !== companyId || chunk.sourceId !== ref.sourceId || !Number.isInteger(index) || index < 0 || index >= ref.chunkCount
                    || chunk.chunkIndex !== String(index) || chunk.id !== expenseOriginalChunkId(ref.sourceId, index) || seen.has(chunk.id)
                    || (!live(chunk) && chunk.isDeleted !== sqliteTrue)) return fail();
                seen.add(chunk.id);
            }
            // Canonical absent ids need deletion history too: older replica
            // updates may fill their metadata later but must remain deleted.
            targets.push({ table: "migrationSource", id: ref.sourceId });
            for (let index = 0; index < ref.chunkCount; index++) targets.push({ table: "migrationChunk", id: expenseOriginalChunkId(ref.sourceId, index) });
        }
        // Deletion authorizes identities and bindings, not recoverability of the bytes.
        await currentAttachment(scope, companyId, attachment, ownerId);
        assertExpenseCompanyOwner(scope, companyId, ownerId);
        const removed = await awaitBankMutation(scope.evolu, onComplete => {
            for (const [index, target] of targets.entries()) {
                const result = scope.evolu.update(target.table, { id: target.id as never, isDeleted: sqliteTrue },
                    { ownerId: ownerId as never, ...(index === targets.length - 1 ? { onComplete } : {}) });
                if (!result.ok) return result;
            }
            return { ok: true };
        });
        if (!removed) return fail();
        await checkpointExpenseMutations(scope.evolu);
        const attachmentRows = await loadRows(scope.evolu, expenseOriginalAttachmentProofQuery);
        if (!attachmentRows.some(row => row.id === attachment.id && row.ownerId === ownerId && row.isDeleted === sqliteTrue)) return fail();
        if (ref) {
            const proof = await deletionArchiveRows(scope.evolu, ref);
            for (const target of targets.filter(target => target.table !== "expenseAttachment")) {
                const rows = (target.table === "migrationSource" ? proof.sources : proof.chunks).filter(row => row.id === target.id && row.ownerId === ownerId);
                if (rows.length !== 1 || rows[0].isDeleted !== sqliteTrue) return fail();
            }
        }
        assertExpenseCompanyOwner(scope, companyId, ownerId);
    }, undefined, ["allExpenseAttachmentsQuery"]);
}
