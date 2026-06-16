import type { Evolu } from "@evolu/common/local-first";
import api from "@/services/api";
import { ensureEvoluBoundToAccountSeed } from "@/evolu/bootstrap";
import {
    restoreInvoicingSnapshotDetailed,
    snapshotHasInvoicingData,
    type InvoicingDataSnapshot,
} from "@/evolu/invoicingSnapshot";
import {
    markEvoluRelaySyncPending,
    waitForInvoicingRelaySync,
} from "@/evolu/relaySyncWait";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { sanitizeLocalStoreReferences } from "@/evolu/sanitizeStoreReferences";
import { prepareServerSnapshotForEvolu } from "@/evolu/serverSnapshotPrepare";

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

export class ServerMigrationError extends Error {
    constructor(
        message: string,
        readonly code:
            | "export_failed"
            | "empty_snapshot"
            | "upsert_failed"
            | "companies_missing",
        readonly status?: number,
    ) {
        super(message);
        this.name = "ServerMigrationError";
    }
}

export function isServerMigrationCompleted(): boolean {
    return localStorage.getItem(MIGRATION_COMPLETED_KEY) === "1";
}

export function markServerMigrationCompleted(): void {
    localStorage.setItem(MIGRATION_COMPLETED_KEY, "1");
}

export function clearServerMigrationCompleted(): void {
    localStorage.removeItem(MIGRATION_COMPLETED_KEY);
}

export async function fetchServerMigrationStatus(): Promise<ServerMigrationStatus> {
    const { data } = await api.get("/invoicing/migration/status");
    return data.data as ServerMigrationStatus;
}

export async function fetchServerMigrationExport(): Promise<{
    snapshot: InvoicingDataSnapshot;
    meta: ServerMigrationExportMeta;
}> {
    try {
        const { data } = await api.get("/invoicing/migration/export", {
            timeout: 180_000,
            params: { include_attachments: 0 },
        });
        return {
            snapshot: data.data as InvoicingDataSnapshot,
            meta: data.meta as ServerMigrationExportMeta,
        };
    } catch (error: unknown) {
        const axiosError = error as {
            response?: { status?: number; data?: { message?: string } };
            message?: string;
        };
        const status = axiosError.response?.status;
        console.error("Server migration export failed", {
            status,
            message: axiosError.response?.data?.message ?? axiosError.message,
        });
        throw new ServerMigrationError("export_failed", "export_failed", status);
    }
}

export async function importServerInvoicingToEvolu(
    evolu: Evolu<InvoicingLocalSchema>,
    validStoreIds: ReadonlySet<string>,
): Promise<ServerMigrationImportResult> {
    await ensureEvoluBoundToAccountSeed();

    const { snapshot, meta } = await fetchServerMigrationExport();
    if (!snapshotHasInvoicingData(snapshot)) {
        throw new ServerMigrationError("empty_snapshot", "empty_snapshot");
    }

    const prepared = prepareServerSnapshotForEvolu(snapshot);
    const { upserted, failed } = restoreInvoicingSnapshotDetailed(evolu, prepared);
    const companyFailures = failed.filter((entry) => entry.table === "company").length;

    if (prepared.company.length > 0 && companyFailures >= prepared.company.length) {
        console.error("Server migration upsert failures", failed.slice(0, 20));
        throw new ServerMigrationError("companies_missing", "companies_missing");
    }

    if (upserted === 0) {
        console.error("Server migration upsert failures", failed.slice(0, 20));
        throw new ServerMigrationError("upsert_failed", "upsert_failed");
    }

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

export function serverMigrationErrorMessage(
    error: unknown,
    t: (key: string, params?: Record<string, unknown>) => string,
): string {
    if (error instanceof ServerMigrationError) {
        if (error.code === "export_failed") {
            if (error.status === 429) {
                return t("invoicing.server_migration_error_throttled");
            }
            if (error.status === 404) {
                return t("invoicing.server_migration_error_empty");
            }
            if (error.status === 500 || error.status === 502 || error.status === 503) {
                return t("invoicing.server_migration_error_server");
            }
            return t("invoicing.server_migration_error_export");
        }
        if (error.code === "empty_snapshot") {
            return t("invoicing.server_migration_error_empty");
        }
        if (error.code === "companies_missing" || error.code === "upsert_failed") {
            return t("invoicing.server_migration_error_upsert");
        }
    }
    return t("invoicing.server_migration_error");
}
