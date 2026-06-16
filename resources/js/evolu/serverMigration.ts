import type { Evolu } from "@evolu/common/local-first";
import api from "@/services/api";
import { ensureEvoluBoundToAccountSeed } from "@/evolu/bootstrap";
import {
    restoreInvoicingSnapshot,
    snapshotHasInvoicingData,
    type InvoicingDataSnapshot,
} from "@/evolu/invoicingSnapshot";
import {
    markEvoluRelaySyncPending,
    waitForInvoicingRelaySync,
} from "@/evolu/relaySyncWait";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { sanitizeLocalStoreReferences } from "@/evolu/sanitizeStoreReferences";

const MIGRATION_COMPLETED_KEY = "satflux.server_migration.completed.v1";

export type ServerMigrationStatus = {
    available: boolean;
    companies_count: number;
    contacts_count: number;
    documents_count: number;
    expenses_count: number;
};

export type ServerMigrationExportMeta = {
    exported_at: string;
    warnings: string[];
    counts: Record<string, number>;
};

export type ServerMigrationImportResult = {
    upserted: number;
    warnings: string[];
    counts: Record<string, number>;
};

export function isServerMigrationCompleted(): boolean {
    return localStorage.getItem(MIGRATION_COMPLETED_KEY) === "1";
}

export function markServerMigrationCompleted(): void {
    localStorage.setItem(MIGRATION_COMPLETED_KEY, "1");
}

export async function fetchServerMigrationStatus(): Promise<ServerMigrationStatus> {
    const { data } = await api.get("/invoicing/migration/status");
    return data.data as ServerMigrationStatus;
}

export async function fetchServerMigrationExport(): Promise<{
    snapshot: InvoicingDataSnapshot;
    meta: ServerMigrationExportMeta;
}> {
    const { data } = await api.get("/invoicing/migration/export");
    return {
        snapshot: data.data as InvoicingDataSnapshot,
        meta: data.meta as ServerMigrationExportMeta,
    };
}

export async function importServerInvoicingToEvolu(
    evolu: Evolu<InvoicingLocalSchema>,
    validStoreIds: ReadonlySet<string>,
): Promise<ServerMigrationImportResult> {
    await ensureEvoluBoundToAccountSeed();

    const { snapshot, meta } = await fetchServerMigrationExport();
    if (!snapshotHasInvoicingData(snapshot)) {
        throw new Error("empty_snapshot");
    }

    const upserted = restoreInvoicingSnapshot(evolu, snapshot);
    markEvoluRelaySyncPending();
    await waitForInvoicingRelaySync(evolu);
    await sanitizeLocalStoreReferences(evolu, validStoreIds);
    markServerMigrationCompleted();

    return {
        upserted,
        warnings: meta.warnings ?? [],
        counts: meta.counts ?? {},
    };
}
