import { MIGRATION_MAX_FILE_BYTES, MIGRATION_MAX_TOTAL_BYTES, MIGRATION_MAX_ORIGINALS, MIGRATION_CHUNK_BYTES, migrationSha256 } from "./migrationArchiveValues";
export { MIGRATION_MAX_FILE_BYTES, MIGRATION_MAX_TOTAL_BYTES, MIGRATION_MAX_ORIGINALS, MIGRATION_CHUNK_BYTES, migrationSha256 } from "./migrationArchiveValues";
import { createIdFromString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import { migrationChunkProofQuery, migrationSourceProofQuery } from "./client";
import { awaitBankMutation, awaitWorkerOutcome } from "./bankReconciliation";
import { assertExpenseCompanyOwner, checkpointExpenseMutations, type ExpenseReconciliationScope } from "./expenseReconciliation";
import { MigrationChunkId, MigrationSourceId, type CompanyId, type InvoicingLocalSchema } from "./schema";
import { toAppRows } from "./queryLoad";

export type MigrationSource = {
    id: MigrationSourceId; companyId: CompanyId; ownerId: string; sourceKind: string | null; relatedExportId: MigrationSourceId | null;
    originalFilename: string | null; mimeType: string | null; sizeBytes: string | null;
    checksum: string | null; chunkCount: string | null; savedAt: string | null; isDeleted?: number | null;
};
type MigrationChunk = { id: MigrationChunkId; companyId: string; ownerId: string; sourceId: MigrationSourceId;
    chunkIndex: string | null; contentBase64: string | null; isDeleted?: number | null };
type Client = Evolu<InvoicingLocalSchema>;

function encode(bytes: Uint8Array): string {
    let text = "";
    for (let offset = 0; offset < bytes.length; offset += 8192) text += String.fromCharCode(...bytes.subarray(offset, offset + 8192));
    return btoa(text);
}
function decode(value: string): Uint8Array {
    if (!/^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(value)) throw new Error("archive_unverified");
    return Uint8Array.from(atob(value), char => char.charCodeAt(0));
}
async function loadSourceProof(evolu: Client): Promise<MigrationSource[]> {
    return awaitWorkerOutcome(evolu, (complete, unknown) => {
        void evolu.loadQuery(migrationSourceProofQuery).then(rows => complete(toAppRows(rows)), unknown);
    });
}
export async function readMigrationOriginal(evolu: Client, companyId: string, ownerId: string, sourceId: string): Promise<{ source: MigrationSource; bytes: Uint8Array }> {
    const sources = (await loadSourceProof(evolu)).filter(source => source.id === sourceId && source.companyId === companyId && source.ownerId === ownerId && source.isDeleted !== 1);
    if (sources.length !== 1) throw new Error("archive_unverified");
    const source = sources[0];
    const count = Number(source.chunkCount); const size = Number(source.sizeBytes);
    if (!Number.isInteger(count) || count < 1 || count > Math.ceil(MIGRATION_MAX_FILE_BYTES / MIGRATION_CHUNK_BYTES)
        || !Number.isInteger(size) || size < 1 || size > MIGRATION_MAX_FILE_BYTES || !/^[a-f0-9]{64}$/.test(source.checksum ?? "")) throw new Error("archive_unverified");
    const proof = await awaitWorkerOutcome<MigrationChunk[]>(evolu, (complete, unknown) => {
        void evolu.loadQuery(migrationChunkProofQuery(source.companyId, source.id)).then(rows => complete(toAppRows(rows)), unknown);
    });
    const chunks = proof.filter(chunk => chunk.sourceId === source.id && chunk.companyId === companyId && chunk.ownerId === ownerId && chunk.isDeleted !== 1);
    if (chunks.length !== count) throw new Error("archive_unverified");
    const bytes = new Uint8Array(size);
    let offset = 0;
    for (let index = 0; index < count; index++) {
        const matches = chunks.filter(chunk => chunk.chunkIndex === String(index));
        if (matches.length !== 1 || !matches[0].contentBase64) throw new Error("archive_unverified");
        const content = decode(matches[0].contentBase64);
        const expectedLength = Math.min(MIGRATION_CHUNK_BYTES, size - offset);
        if (content.length !== expectedLength) throw new Error("archive_unverified");
        bytes.set(content, offset); offset += content.length;
    }
    if (offset !== size || await migrationSha256(bytes) !== source.checksum) throw new Error("archive_unverified");
    return { source, bytes };
}

/** Called within the same company/financial lease as document import. Original is committed and verified first. */
export async function retainMigrationOriginal(scope: ExpenseReconciliationScope, input: {
    companyId: CompanyId; ownerId: string; sourceKind: string; file: File; bytes: Uint8Array; assertCurrent: () => void;
    relatedExportId?: MigrationSourceId;
}): Promise<MigrationSource> {
    const { companyId, ownerId, file, bytes } = input;
    if (bytes.length < 1 || bytes.length > MIGRATION_MAX_FILE_BYTES || bytes.length !== file.size || !file.name || file.name.length > 255
        || (file.name.includes("/") || file.name.includes("\\") || Array.from(file.name).some(char => char.charCodeAt(0) < 32)) || file.type.length > 128 || !["excel", "superfaktura", "zaprite", "original"].includes(input.sourceKind)) throw new Error("invalid_original");
    if (input.sourceKind === "original" && !input.relatedExportId) throw new Error("invalid_original");
    const checksum = await migrationSha256(bytes);
    const chunkCount = Math.ceil(bytes.length / MIGRATION_CHUNK_BYTES);
    const sources = await loadSourceProof(scope.evolu);
    // A shared/rekeyed company's original rows keep their ids. Match only the
    // current authoritative partition and batch relation, never a former owner's copy.
    const candidates = sources.filter(source => source.ownerId === ownerId && source.companyId === companyId
        && source.checksum === checksum && source.sourceKind === input.sourceKind && source.isDeleted !== 1
        && (source.relatedExportId ?? null) === (input.relatedExportId ?? null));
    if (candidates.length > 1) throw new Error("archive_unverified");
    const id = candidates.length === 1 ? candidates[0].id
        : MigrationSourceId.orThrow(createIdFromString(`satflux.migration-source.v1.${ownerId}.${companyId}.${checksum}.${input.relatedExportId ?? "export"}`));
    const existing = sources.filter(source => source.id === id && source.ownerId === ownerId);
    if (existing.length > 1 || (existing.length === 1 && (candidates.length !== 1 || existing[0].isDeleted === 1))) throw new Error("archive_unverified");
    if (existing.some(source => source.savedAt)) {
        const retained = await readMigrationOriginal(scope.evolu, companyId, ownerId, id);
        if (retained.bytes.length !== bytes.length || !retained.bytes.every((byte, index) => byte === bytes[index])) throw new Error("archive_unverified");
        input.assertCurrent(); assertExpenseCompanyOwner(scope, companyId, ownerId);
        return retained.source;
    }
    input.assertCurrent(); assertExpenseCompanyOwner(scope, companyId, ownerId);
    const pending = await awaitBankMutation(scope.evolu, onComplete => scope.evolu.upsert("migrationSource", {
        id, companyId, sourceKind: input.sourceKind, relatedExportId: input.relatedExportId ?? null, originalFilename: file.name, mimeType: file.type || "application/octet-stream",
        sizeBytes: String(bytes.length), checksum, chunkCount: String(chunkCount), savedAt: null,
    }, { ownerId: ownerId as never, onComplete }));
    if (!pending) throw new Error("archive_unverified");
    await checkpointExpenseMutations(scope.evolu);
    for (let index = 0; index < chunkCount; index++) {
        input.assertCurrent(); assertExpenseCompanyOwner(scope, companyId, ownerId);
        const contentBase64 = encode(bytes.subarray(index * MIGRATION_CHUNK_BYTES, (index + 1) * MIGRATION_CHUNK_BYTES));
        const chunkId = MigrationChunkId.orThrow(createIdFromString(`satflux.migration-chunk.v1.${id}.${index}`));
        const saved = await awaitBankMutation(scope.evolu, onComplete => scope.evolu.upsert("migrationChunk", {
            id: chunkId, companyId, sourceId: id, chunkIndex: String(index), contentBase64,
        }, { ownerId: ownerId as never, onComplete }));
        if (!saved) throw new Error("archive_unverified");
        await checkpointExpenseMutations(scope.evolu);
    }
    await readMigrationOriginal(scope.evolu, companyId, ownerId, id);
    input.assertCurrent(); assertExpenseCompanyOwner(scope, companyId, ownerId);
    const saved = await awaitBankMutation(scope.evolu, onComplete => scope.evolu.upsert("migrationSource", {
        id, companyId, sourceKind: input.sourceKind, relatedExportId: input.relatedExportId ?? null, originalFilename: file.name, mimeType: file.type || "application/octet-stream",
        sizeBytes: String(bytes.length), checksum, chunkCount: String(chunkCount), savedAt: new Date().toISOString(),
    }, { ownerId: ownerId as never, onComplete }));
    if (!saved) throw new Error("archive_unverified");
    await checkpointExpenseMutations(scope.evolu);
    const verified = await readMigrationOriginal(scope.evolu, companyId, ownerId, id);
    input.assertCurrent(); assertExpenseCompanyOwner(scope, companyId, ownerId);
    return verified.source;
}

/** Optional original documents are retained as batch evidence, never guessed into invoice associations. */
export function validateMigrationOriginalFiles(source: File, originals: readonly File[]): void {
    if (originals.length > MIGRATION_MAX_ORIGINALS || source.size + originals.reduce((sum, file) => sum + file.size, 0) > MIGRATION_MAX_TOTAL_BYTES) throw new Error("originals_limit");
    for (const file of originals) {
        if (!/\.(pdf|xml|isdoc)$/i.test(file.name) || !Number.isInteger(file.size) || file.size < 1 || file.size > MIGRATION_MAX_FILE_BYTES) throw new Error("invalid_original");
    }
}
