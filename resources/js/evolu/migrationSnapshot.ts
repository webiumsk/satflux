import { MIGRATION_CHUNK_BYTES, MIGRATION_MAX_FILE_BYTES, migrationSha256 } from "./migrationArchiveValues";

type Row = Readonly<Record<string, unknown>>;
/** Reject broken archive references before restore schedules its first mutation. Legacy backups have neither table. */
export async function validateMigrationSnapshot(snapshot: {
    company: readonly Row[]; migrationSource?: readonly Row[]; migrationChunk?: readonly Row[];
}): Promise<void> {
    const sources = snapshot.migrationSource ?? [];
    const chunks = snapshot.migrationChunk ?? [];
    if (!Array.isArray(sources) || !Array.isArray(chunks)) throw new Error("archive_unverified");
    const companies = new Set(snapshot.company.map(row => row.id));
    const seen = new Set<unknown>();
    for (const source of sources) {
        if (typeof source.id !== "string" || seen.has(source.id) || !companies.has(source.companyId)
            || typeof source.checksum !== "string" || !/^[a-f0-9]{64}$/.test(source.checksum)
            || typeof source.originalFilename !== "string" || source.originalFilename.length < 1 || source.originalFilename.length > 255
            || typeof source.mimeType !== "string" || source.mimeType.length > 128) throw new Error("archive_unverified");
        if (source.sourceKind === "original" && !sources.some(parent => parent.id === source.relatedExportId
            && parent.companyId === source.companyId && parent.sourceKind !== "original")) throw new Error("archive_unverified");
        seen.add(source.id);
        const count = Number(source.chunkCount); const size = Number(source.sizeBytes);
        if (!Number.isInteger(size) || size < 1 || size > MIGRATION_MAX_FILE_BYTES || count !== Math.ceil(size / MIGRATION_CHUNK_BYTES)) throw new Error("archive_unverified");
        const rows = chunks.filter(chunk => chunk.sourceId === source.id);
        if (rows.length > count || (source.savedAt && rows.length !== count)) throw new Error("archive_unverified");
        const indices = new Set<number>(); const ids = new Set<unknown>();
        const bytes = new Uint8Array(size);
        for (const chunk of rows) {
            const index = Number(chunk.chunkIndex);
            if (chunk.companyId !== source.companyId || !Number.isInteger(index) || index < 0 || index >= count
                || indices.has(index) || typeof chunk.id !== "string" || ids.has(chunk.id) || typeof chunk.contentBase64 !== "string"
                || !/^(?:[A-Za-z0-9+/]{4})*(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/.test(chunk.contentBase64)) throw new Error("archive_unverified");
            indices.add(index); ids.add(chunk.id);
            const decoded = Uint8Array.from(atob(chunk.contentBase64), char => char.charCodeAt(0));
            if (decoded.length !== Math.min(MIGRATION_CHUNK_BYTES, size - index * MIGRATION_CHUNK_BYTES)) throw new Error("archive_unverified");
            bytes.set(decoded, index * MIGRATION_CHUNK_BYTES);
        }
        if (source.savedAt && await migrationSha256(bytes) !== source.checksum) throw new Error("archive_unverified");
    }
    if (chunks.some(chunk => !seen.has(chunk.sourceId))) throw new Error("archive_unverified");
}
