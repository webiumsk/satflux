import { invoicingApi } from "@/services/api";
import { normalizeIsoCountryCode } from "@/utils/isoCountryCode";
import { emailSettingsForEphemeralSnapshot } from "./ephemeralBridge";
import { ensureBridgeCompanyIdForLocalCompany } from "./bridgeCompanyEnsure";
import { localHighCounterForStoreBridge } from "./numberSequenceBridge";
import { resolveDefaultSeries } from "./numberSeriesCrud";
import type { CompanyId, DocumentType } from "./schema";
import type { EvoluDocumentRow } from "./documentMap";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";

/**
 * Auto-issue profile sync (P3): pushes everything the server needs to issue
 * and email WooCommerce invoices HEADLESSLY for a local-first company:
 *
 * 1. invoice-header snapshot + local high counters → PUT auto-issue-profile,
 * 2. e-mail settings (SMTP/templates live only in Evolu for local-first
 *    companies) → PATCH email-settings on the bridge company,
 * 3. the LOCAL invoice series format → server default series (without this
 *    the server would issue with its INVYYYYNNNN default - the F3 bug).
 *
 * Opt-in: enabling deliberately persists this company's invoicing header and
 * SMTP settings server-side - the UI says so explicitly.
 */

export type AutoIssueProfileStatus = {
    autoEmail: boolean;
    syncedAt: string | null;
};

type ServerProfile = { company_id: string; auto_email: boolean; synced_at: string | null };

type ServerSeriesRow = {
    id: string;
    document_type: string;
    name: string;
    format: string;
    reset_period: string;
    is_default: boolean;
};

const SYNCED_DOCUMENT_TYPES: DocumentType[] = ["invoice" as DocumentType];

/** Company snapshot in the shape the server-side EphemeralDocumentFactory consumes. */
export function buildAutoIssueCompanyPayload(
    company: Record<string, unknown>,
): Record<string, unknown> {
    const rawAppSettings = (company.app_settings ?? {}) as Record<string, unknown>;

    return {
        legal_name: company.legal_name,
        trade_name: company.trade_name ?? null,
        registration_number: company.registration_number ?? null,
        tax_id: company.tax_id ?? null,
        vat_number: company.vat_number ?? null,
        street: company.street ?? null,
        city: company.city ?? null,
        postal_code: company.postal_code ?? null,
        country: normalizeIsoCountryCode(company.country as string | null | undefined),
        state_region: company.state_region ?? null,
        iban: company.iban ?? null,
        bic: company.bic ?? null,
        bank_name: company.bank_name ?? null,
        bank_account: company.bank_account ?? null,
        bank_code: company.bank_code ?? null,
        default_currency: company.default_currency ?? null,
        jurisdiction: company.jurisdiction ?? undefined,
        vat_payer: Boolean(company.vat_payer),
        vat_rate_default: company.vat_rate_default ?? null,
        legal_footer_note: company.legal_footer_note ?? null,
        issuer_name: company.issuer_name ?? null,
        issuer_phone: company.issuer_phone ?? null,
        issuer_email: company.issuer_email ?? null,
        website: company.website ?? null,
        // Only the render-relevant flag - efaktura credentials never leave
        // the device through this profile.
        app_settings: { show_pay_by_square: rawAppSettings.show_pay_by_square ?? true },
        ...(company.logo_url ? { logo_url: company.logo_url } : {}),
        ...(company.signature_stamp_url ? { signature_stamp_url: company.signature_stamp_url } : {}),
    };
}

/** Highest locally issued counter per synced document type. */
export function buildLocalHighCounters(
    companyId: CompanyId,
    documents: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
): Record<string, number> {
    const counters: Record<string, number> = {};
    for (const type of SYNCED_DOCUMENT_TYPES) {
        counters[type] = localHighCounterForStoreBridge(companyId, type, documents, allSeries);
    }
    return counters;
}

export async function fetchAutoIssueProfileStatus(
    localCompanyId: string,
): Promise<{ ok: true; status: AutoIssueProfileStatus | null } | { ok: false }> {
    const bridge = await ensureBridgeCompanyIdForLocalCompany(localCompanyId);
    if (!bridge.ok || !bridge.bridgeCompanyId) {
        return { ok: false };
    }

    try {
        const profile = await invoicingApi.companies.getAutoIssueProfile<ServerProfile>(
            bridge.bridgeCompanyId,
        );
        return {
            ok: true,
            status: profile
                ? { autoEmail: Boolean(profile.auto_email), syncedAt: profile.synced_at ?? null }
                : null,
        };
    } catch {
        return { ok: false };
    }
}

async function syncServerSeriesFormats(
    bridgeCompanyId: string,
    companyId: CompanyId,
    allSeries: EvoluNumberSeriesRow[],
): Promise<void> {
    const serverRows = await invoicingApi.numberSeries.list<ServerSeriesRow>(bridgeCompanyId);

    for (const type of SYNCED_DOCUMENT_TYPES) {
        const local = resolveDefaultSeries(allSeries, companyId, type);
        if (!local?.format) {
            continue;
        }

        const server = serverRows.find((row) => row.document_type === type && row.is_default);
        const payload = {
            document_type: type,
            name: String(local.name ?? server?.name ?? type),
            format: String(local.format),
            reset_period: String(local.resetPeriod ?? server?.reset_period ?? "yearly"),
            is_default: true,
        };

        if (!server) {
            await invoicingApi.numberSeries.create(bridgeCompanyId, payload);
        } else if (server.format !== payload.format || server.reset_period !== payload.reset_period) {
            await invoicingApi.numberSeries.update(bridgeCompanyId, server.id, payload);
        }
    }
}

/**
 * Full sync: profile + email settings + series formats. Loads the local
 * dataset through the lazily imported Evolu client (keeps this module
 * importable without WASM in tests).
 */
export async function syncAutoIssueProfile(
    localCompany: Record<string, unknown>,
    autoEmail: boolean,
): Promise<{ ok: true; status: AutoIssueProfileStatus } | { ok: false; error: string }> {
    const localCompanyId = String(localCompany.id ?? "");
    if (!localCompanyId) {
        return { ok: false, error: "company_missing" };
    }

    const bridge = await ensureBridgeCompanyIdForLocalCompany(localCompanyId);
    if (!bridge.ok || !bridge.bridgeCompanyId) {
        return { ok: false, error: "bridge_unavailable" };
    }
    const bridgeCompanyId = bridge.bridgeCompanyId;

    try {
        const { evolu, allDocumentsQuery, allNumberSeriesQuery } = await import("./client");
        const [documentRows, seriesRows] = await Promise.all([
            evolu.loadQuery(allDocumentsQuery),
            evolu.loadQuery(allNumberSeriesQuery),
        ]);
        const documents = documentRows as unknown as EvoluDocumentRow[];
        const allSeries = seriesRows as unknown as EvoluNumberSeriesRow[];
        const typedCompanyId = localCompanyId as CompanyId;

        const profile = await invoicingApi.companies.putAutoIssueProfile<ServerProfile>(
            bridgeCompanyId,
            {
                auto_email: autoEmail,
                company: buildAutoIssueCompanyPayload(localCompany),
                local_high_counters: buildLocalHighCounters(typedCompanyId, documents, allSeries),
            },
        );

        const emailSettings = emailSettingsForEphemeralSnapshot(localCompany);
        if (emailSettings) {
            await invoicingApi.companies.updateEmailSettings(bridgeCompanyId, emailSettings);
        }

        await syncServerSeriesFormats(bridgeCompanyId, typedCompanyId, allSeries);

        return {
            ok: true,
            status: {
                autoEmail: Boolean(profile.auto_email),
                syncedAt: profile.synced_at ?? null,
            },
        };
    } catch (error) {
        console.error("Auto-issue profile sync failed:", error);
        return { ok: false, error: "sync_failed" };
    }
}

export async function disableAutoIssueProfile(
    localCompanyId: string,
): Promise<{ ok: boolean }> {
    const bridge = await ensureBridgeCompanyIdForLocalCompany(localCompanyId);
    if (!bridge.ok || !bridge.bridgeCompanyId) {
        return { ok: false };
    }

    try {
        await invoicingApi.companies.deleteAutoIssueProfile(bridge.bridgeCompanyId);
        return { ok: true };
    } catch (error) {
        console.error("Auto-issue profile disable failed:", error);
        return { ok: false };
    }
}
