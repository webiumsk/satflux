import { kysely } from "@evolu/common";
import { createQuery, type Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import { loadEvoluQueryWithTimeout } from "./queryLoad";

const requiredColumns = {
    migrationSource: ["id", "ownerId", "companyId", "sourceKind", "relatedExportId", "originalFilename", "mimeType", "sizeBytes", "checksum", "chunkCount", "savedAt"],
    migrationChunk: ["id", "ownerId", "companyId", "sourceId", "chunkIndex", "contentBase64"],
    document: ["migrationSourceId", "migrationFingerprint", "migrationCompletedAt"],
} as const;

// SQLite returns no rows for an absent table. Inspect fixed metadata without
// querying an unavailable migration table or creating a worker error.
const schemaQuery = createQuery((db) => db.selectFrom(kysely.sql<{ tableName: string; name: string }>`(
    select 'migrationSource' as tableName, name from pragma_table_info('migrationSource')
    union all
    select 'migrationChunk' as tableName, name from pragma_table_info('migrationChunk')
    union all
    select 'document' as tableName, name from pragma_table_info('document')
)`.as("migrationColumns")).selectAll());

/** Check the existing worker before any migration write or archive query. */
export async function assertMigrationSchemaReady(evolu: Evolu<InvoicingLocalSchema>): Promise<void> {
    const columns = await loadEvoluQueryWithTimeout(evolu, schemaQuery);
    const ready = Object.entries(requiredColumns).every(([table, names]) =>
        names.every(name => columns.some(column => column.tableName === table && column.name === name)),
    );
    if (!ready) throw new Error("migration_schema_outdated");
}
