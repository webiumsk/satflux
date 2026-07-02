import { maxLength, NonEmptyString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { CompanyAppSettingsState } from "@/composables/useCompanyAppSettings";
import type { CompanyEfakturaSettingsState } from "@/composables/useCompanyEfakturaSettings";
import { allCompaniesDetailQuery } from "./client";
import type { CompanyId, InvoicingLocalSchema } from "./schema";

const Opt4000 = maxLength(4000)(NonEmptyString);

function parseSettingsJson(json: string | null | undefined): Record<string, unknown> {
    if (!json?.trim()) return {};
    try {
        const parsed = JSON.parse(json);
        if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
            return parsed as Record<string, unknown>;
        }
    } catch {
        // ignore invalid JSON
    }
    return {};
}

function stringifySettings(merged: Record<string, unknown>) {
    if (Object.keys(merged).length === 0) {
        return { ok: true as const, value: null };
    }
    const json = JSON.stringify(merged);
    const validated = Opt4000.from(json);
    if (!validated.ok) return validated;
    return { ok: true as const, value: validated.value };
}

function findCompanyRow(evolu: Evolu<InvoicingLocalSchema>, companyId: CompanyId) {
    const rows = evolu.getQueryRows(allCompaniesDetailQuery);
    return rows.find((row) => row.id === companyId);
}

function mergeEmailSettings(
    existing: Record<string, unknown>,
    partial: Record<string, unknown>,
): Record<string, unknown> {
    const merged: Record<string, unknown> = { ...existing, ...partial };

    if (partial.templates && typeof partial.templates === "object") {
        merged.templates = {
            ...((existing.templates as Record<string, unknown> | undefined) ?? {}),
            ...(partial.templates as Record<string, unknown>),
        };
    }

    if (partial.smtp && typeof partial.smtp === "object") {
        const existingSmtp = (existing.smtp as Record<string, unknown> | undefined) ?? {};
        const partialSmtp = partial.smtp as Record<string, unknown>;
        const smtp: Record<string, unknown> = { ...existingSmtp, ...partialSmtp };

        if (!partialSmtp.password && existingSmtp.password) {
            smtp.password = existingSmtp.password;
        }
        if (smtp.password) {
            smtp.password_set = true;
        }

        merged.smtp = smtp;
    }

    return merged;
}

export function resolveLocalEmailSettingsForBridge(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    partial: Record<string, unknown>,
): Record<string, unknown> {
    const row = findCompanyRow(evolu, companyId);
    const existing = parseSettingsJson(row?.emailSettingsJson ?? null);

    return mergeEmailSettings(existing, partial);
}

export function updateLocalAppSettings(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    partial: Partial<CompanyAppSettingsState>,
) {
    const row = findCompanyRow(evolu, companyId);
    const existing = parseSettingsJson(row?.appSettingsJson ?? null);
    const merged = { ...existing, ...partial };
    const stringified = stringifySettings(merged);
    if (!stringified.ok) return stringified;

    return evolu.update("company", {
        id: companyId,
        appSettingsJson: stringified.value,
    });
}

export function updateLocalEmailSettings(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    partial: Record<string, unknown>,
) {
    const row = findCompanyRow(evolu, companyId);
    const existing = parseSettingsJson(row?.emailSettingsJson ?? null);
    const merged = mergeEmailSettings(existing, partial);
    const stringified = stringifySettings(merged);
    if (!stringified.ok) return stringified;

    return evolu.update("company", {
        id: companyId,
        emailSettingsJson: stringified.value,
    });
}

function mergeEfakturaSettings(
    existing: Record<string, unknown>,
    partial: Partial<CompanyEfakturaSettingsState>,
): Record<string, unknown> {
    const merged: Record<string, unknown> = { ...existing, ...partial };

    const incomingSecret = partial.efaktura_sapi_client_secret;
    if (incomingSecret === undefined || incomingSecret === "") {
        if (existing.efaktura_sapi_client_secret) {
            merged.efaktura_sapi_client_secret = existing.efaktura_sapi_client_secret;
        } else {
            delete merged.efaktura_sapi_client_secret;
        }
    }

    if (merged.efaktura_sapi_client_secret) {
        merged.efaktura_sapi_client_secret_set = true;
    }

    return merged;
}

export function updateLocalEfakturaSettings(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    partial: Partial<CompanyEfakturaSettingsState>,
) {
    const row = findCompanyRow(evolu, companyId);
    const existing = parseSettingsJson(row?.appSettingsJson ?? null);
    const merged = mergeEfakturaSettings(existing, partial);
    const stringified = stringifySettings(merged);
    if (!stringified.ok) return stringified;

    return evolu.update("company", {
        id: companyId,
        appSettingsJson: stringified.value,
    });
}
