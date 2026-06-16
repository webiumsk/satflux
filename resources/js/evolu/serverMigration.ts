import type { Evolu } from "@evolu/common/local-first";
import api from "@/services/api";
import { getStoredAccountMnemonic, initEvoluFromAccountSeedIfNeeded } from "@/services/accountSeed";
import { allCompaniesQuery } from "@/evolu/client";
import {
    restoreInvoicingSnapshotDetailedAsync,
    snapshotHasInvoicingData,
    type InvoicingDataSnapshot,
} from "@/evolu/invoicingSnapshot";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { sanitizeLocalStoreReferences } from "@/evolu/sanitizeStoreReferences";
import { prepareServerSnapshotForEvolu } from "@/evolu/serverSnapshotPrepare";
import { dropRowsWithoutId, sanitizeSnapshotRowsForUpsert } from "@/evolu/serverSnapshotSanitize";

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
        readonly errorId?: string,
    ) {
        super(message);
        this.name = "ServerMigrationError";
    }
}

const INFORMATIONAL_MIGRATION_WARNINGS = new Set([
    "attachments_metadata_only",
    "branding_skipped",
]);

/** True when export warnings mean real data loss the user should know about. */
export function migrationWarningsNeedUserAttention(warnings: string[]): boolean {
    return warnings.some((warning) => {
        if (INFORMATIONAL_MIGRATION_WARNINGS.has(warning)) {
            return false;
        }

        return (
            warning.startsWith("attachment_")
            || warning.startsWith("branding_")
            || warning.startsWith("section_failed:")
            || warning.startsWith("row_export_failed:")
        );
    });
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

/** Show server import when Evolu is empty but PostgreSQL still has legacy rows. */
export function shouldOfferServerMigration(
    localCompanyCount: number,
    serverStatus: ServerMigrationStatus | null,
): boolean {
    return localCompanyCount === 0 && serverStatus?.available === true;
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
            params: { include_attachments: 0, include_branding: 0 },
        });
        return {
            snapshot: data.data as InvoicingDataSnapshot,
            meta: data.meta as ServerMigrationExportMeta,
        };
    } catch (error: unknown) {
        const axiosError = error as {
            response?: { status?: number; data?: { message?: string; debug?: string; error_id?: string } };
            message?: string;
        };
        const status = axiosError.response?.status;
        const body = axiosError.response?.data;
        const debug = body?.debug;
        const errorId = body?.error_id;
        console.error("Server migration export failed", status ?? "no_status", body?.message ?? axiosError.message);
        if (errorId) {
            console.error("Server migration export error_id:", errorId);
        }
        if (debug) {
            console.error("Server migration export debug:", debug);
        }
        if (!axiosError.response) {
            console.error("Server migration export network error:", axiosError.message);
        }
        throw new ServerMigrationError(
            debug ?? errorId ?? "export_failed",
            "export_failed",
            status,
            errorId,
        );
    }
}

export async function importServerInvoicingToEvolu(
    evolu: Evolu<InvoicingLocalSchema>,
    validStoreIds: ReadonlySet<string>,
): Promise<ServerMigrationImportResult> {
    const mnemonic = getStoredAccountMnemonic();
    if (!mnemonic) {
        throw new ServerMigrationError("recovery_required", "upsert_failed");
    }

    const seedResult = await initEvoluFromAccountSeedIfNeeded(mnemonic);
    if (seedResult === "restored" || seedResult === "migrated_legacy_owner") {
        evolu.reloadApp();
        await new Promise((resolve) => {
            setTimeout(resolve, 300);
        });
    }

    const { snapshot, meta } = await fetchServerMigrationExport();
    if (!snapshotHasInvoicingData(snapshot)) {
        throw new ServerMigrationError("empty_snapshot", "empty_snapshot");
    }

    const prepared = dropRowsWithoutId(
        sanitizeSnapshotRowsForUpsert(
            prepareServerSnapshotForEvolu(snapshot),
        ),
    );
    const { upserted, failed } = await restoreInvoicingSnapshotDetailedAsync(evolu, prepared);
    const companyFailures = failed.filter((entry) => entry.table === "company");

    if (prepared.company.length > 0 && companyFailures.length >= prepared.company.length) {
        console.error("Server migration company upsert failures", companyFailures.slice(0, 20));
        clearServerMigrationCompleted();
        throw new ServerMigrationError("companies_missing", "companies_missing");
    }

    if (upserted === 0) {
        console.error("Server migration upsert failures", failed.slice(0, 20));
        clearServerMigrationCompleted();
        throw new ServerMigrationError("upsert_failed", "upsert_failed");
    }

    await sanitizeLocalStoreReferences(evolu, validStoreIds);

    const companies = await evolu.loadQuery(allCompaniesQuery);
    if (prepared.company.length > 0 && companies.length === 0) {
        console.error("Server migration wrote rows but company table is empty", {
            companyFailures: failed.filter((entry) => entry.table === "company"),
            otherFailures: failed.filter((entry) => entry.table !== "company").slice(0, 10),
        });
        clearServerMigrationCompleted();
        throw new ServerMigrationError("companies_missing", "companies_missing");
    }

    if (failed.length > 0) {
        console.warn("Server migration partial upsert failures", failed.slice(0, 30));
    }

    markServerMigrationCompleted();

    return {
        upserted,
        warnings: meta.warnings ?? [],
        counts: {
            ...(meta.counts ?? {}),
            company: companies.length,
        },
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
                if (error.message && error.message !== "export_failed") {
                    if (error.errorId) {
                        return `${error.message} (${error.errorId})`;
                    }
                    return error.message;
                }
                if (error.errorId) {
                    return t("invoicing.server_migration_error_server_id", { id: error.errorId });
                }
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
