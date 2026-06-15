import {
    booleanToSqliteBoolean,
    maxLength,
    NonEmptyString,
    sqliteTrue,
} from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { NumberSeriesFormState } from "@/composables/useCompanyNumberSeries";
import i18n from "@/i18n";
import type { EvoluDocumentRow } from "./documentMap";
import {
    counterDigitsInFormat,
    currentPeriodKey,
    previewNextNumber,
} from "./numberSeriesFormat";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import type { CompanyId, DocumentType, InvoicingLocalSchema, NumberSeriesId, ResetPeriod } from "./schema";

export type LocalizedDefaultSeriesDef = {
    documentType: DocumentType;
    name: string;
    format: string;
    isDefault: boolean;
};

const DEFAULT_SERIES_DEFS: ReadonlyArray<{
    documentType: DocumentType;
    nameKey: string;
    format: string;
    isDefault: boolean;
}> = [
    { documentType: "invoice", nameKey: "invoicing.series_default_name_invoice", format: "INVRRRRCCCC", isDefault: true },
    { documentType: "credit_note", nameKey: "invoicing.series_default_name_credit_note", format: "CNRRRRCCCC", isDefault: true },
    { documentType: "proforma", nameKey: "invoicing.series_default_name_proforma", format: "PFRRRRCCCC", isDefault: true },
    { documentType: "delivery_note", nameKey: "invoicing.series_default_name_delivery_note", format: "DELRRRRCCCC", isDefault: true },
    { documentType: "quote", nameKey: "invoicing.series_default_name_quote", format: "QTRRRRCCCC", isDefault: true },
    { documentType: "order_received", nameKey: "invoicing.series_default_name_order_received", format: "PORRRRCCCC", isDefault: true },
];

export function localizedDefaultSeries(
    translate: (key: string) => string = (key) => String(i18n.global.t(key)),
): LocalizedDefaultSeriesDef[] {
    return DEFAULT_SERIES_DEFS.map((def) => ({
        documentType: def.documentType,
        name: translate(def.nameKey),
        format: def.format,
        isDefault: def.isDefault,
    }));
}

const NameType = maxLength(255)(NonEmptyString);
const FormatType = maxLength(64)(NonEmptyString);

function highestUsedCounter(
    companyId: CompanyId,
    documentType: DocumentType,
    format: string,
    documents: EvoluDocumentRow[],
): number {
    const digitLen = counterDigitsInFormat(format);
    let max = 0;

    for (const doc of documents) {
        if (doc.companyId !== companyId || doc.documentType !== documentType || !doc.number) {
            continue;
        }
        if (doc.number.length < digitLen) continue;
        const suffix = doc.number.slice(-digitLen);
        if (/^\d+$/.test(suffix)) {
            max = Math.max(max, parseInt(suffix, 10));
        }
    }

    return max;
}

function syncPeriodFields(series: EvoluNumberSeriesRow, date = new Date()) {
    const key = currentPeriodKey(series.resetPeriod, date);
    if (series.periodKey !== key) {
        return {
            ...series,
            periodKey: key,
            lastNumber: "0",
        };
    }
    return series;
}

function ensureCounterSynced(
    series: EvoluNumberSeriesRow,
    documents: EvoluDocumentRow[],
    date = new Date(),
): EvoluNumberSeriesRow {
    const synced = syncPeriodFields(series, date);
    const fromDocuments = highestUsedCounter(
        synced.companyId,
        synced.documentType,
        synced.format,
        documents,
    );
    const current = parseInt(synced.lastNumber || "0", 10) || 0;
    if (fromDocuments !== current) {
        return { ...synced, lastNumber: String(fromDocuments) };
    }
    return synced;
}

export function resolveDefaultSeries(
    allSeries: EvoluNumberSeriesRow[],
    companyId: CompanyId,
    documentType: DocumentType,
): EvoluNumberSeriesRow | null {
    return (
        allSeries.find(
            (row) =>
                row.companyId === companyId
                && row.documentType === documentType
                && row.isDefault !== 0,
        ) ?? null
    );
}

export { previewNextNumber };

export function listNumberSeries(
    allSeries: EvoluNumberSeriesRow[],
    companyId: CompanyId,
): EvoluNumberSeriesRow[] {
    return allSeries.filter((row) => row.companyId === companyId);
}

export function seedDefaultNumberSeries(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    existingSeries: EvoluNumberSeriesRow[] = [],
    seriesDefs: LocalizedDefaultSeriesDef[] = localizedDefaultSeries(),
): EvoluNumberSeriesRow[] {
    const companyRows = existingSeries.filter((row) => row.companyId === companyId);
    const created: EvoluNumberSeriesRow[] = [];

    for (const def of seriesDefs) {
        const exists = companyRows.some(
            (row) => row.documentType === def.documentType && row.isDefault !== 0,
        );
        if (exists) continue;

        const name = NameType.from(def.name);
        if (!name.ok) continue;
        const format = FormatType.from(def.format);
        if (!format.ok) continue;

        const result = evolu.insert("numberSeries", {
            companyId,
            name: name.value,
            documentType: def.documentType,
            format: format.value,
            resetPeriod: "yearly",
            isDefault: booleanToSqliteBoolean(def.isDefault),
            periodKey: currentPeriodKey("yearly"),
            lastNumber: "0",
        });
        if (!result.ok) continue;

        created.push({
            id: result.value.id,
            companyId,
            name: def.name,
            documentType: def.documentType,
            format: def.format,
            resetPeriod: "yearly",
            isDefault: booleanToSqliteBoolean(def.isDefault),
            periodKey: currentPeriodKey("yearly"),
            lastNumber: "0",
        });
    }

    return created;
}

function clearDefaultForType(
    evolu: Evolu<InvoicingLocalSchema>,
    allSeries: EvoluNumberSeriesRow[],
    companyId: CompanyId,
    documentType: DocumentType,
    exceptId?: NumberSeriesId,
) {
    for (const row of allSeries) {
        if (row.companyId !== companyId || row.documentType !== documentType) continue;
        if (exceptId && row.id === exceptId) continue;
        if (row.isDefault === 0) continue;
        evolu.update("numberSeries", { id: row.id, isDefault: booleanToSqliteBoolean(false) });
    }
}

export function createNumberSeries(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    allSeries: EvoluNumberSeriesRow[],
    form: NumberSeriesFormState,
) {
    const name = NameType.from(form.name.trim());
    if (!name.ok) return name;
    const format = FormatType.from(form.format.trim().toUpperCase());
    if (!format.ok) return format;
    if (!format.value.includes("C")) {
        return { ok: false as const, error: "format_missing_counter" };
    }

    if (form.is_default) {
        clearDefaultForType(evolu, allSeries, companyId, form.document_type as DocumentType);
    }

    return evolu.insert("numberSeries", {
        companyId,
        name: name.value,
        documentType: form.document_type as DocumentType,
        format: format.value,
        resetPeriod: form.reset_period as ResetPeriod,
        isDefault: booleanToSqliteBoolean(form.is_default),
        periodKey: currentPeriodKey(form.reset_period as ResetPeriod),
        lastNumber: String(Math.max(0, Number(form.last_number) || 0)),
    });
}

export function updateNumberSeries(
    evolu: Evolu<InvoicingLocalSchema>,
    seriesId: NumberSeriesId,
    companyId: CompanyId,
    allSeries: EvoluNumberSeriesRow[],
    form: NumberSeriesFormState,
    existing: EvoluNumberSeriesRow,
) {
    const name = NameType.from(form.name.trim());
    if (!name.ok) return name;
    const format = FormatType.from(form.format.trim().toUpperCase());
    if (!format.ok) return format;
    if (!format.value.includes("C")) {
        return { ok: false as const, error: "format_missing_counter" };
    }

    const periodChanged = existing.resetPeriod !== form.reset_period;
    const isDefault = form.is_default;

    if (isDefault) {
        clearDefaultForType(
            evolu,
            allSeries,
            companyId,
            form.document_type as DocumentType,
            seriesId,
        );
    }

    return evolu.update("numberSeries", {
        id: seriesId,
        name: name.value,
        documentType: form.document_type as DocumentType,
        format: format.value,
        resetPeriod: form.reset_period as ResetPeriod,
        isDefault: booleanToSqliteBoolean(isDefault),
        lastNumber: String(Math.max(0, Number(form.last_number) || 0)),
        ...(periodChanged
            ? { periodKey: currentPeriodKey(form.reset_period as ResetPeriod) }
            : {}),
    });
}

export function deleteNumberSeries(
    evolu: Evolu<InvoicingLocalSchema>,
    seriesId: NumberSeriesId,
    companyId: CompanyId,
    allSeries: EvoluNumberSeriesRow[],
) {
    const series = allSeries.find((row) => row.id === seriesId);
    if (!series || series.companyId !== companyId) {
        return { ok: false as const, error: "not_found" };
    }

    if (series.isDefault !== 0) {
        const others = allSeries.filter(
            (row) =>
                row.companyId === companyId
                && row.documentType === series.documentType
                && row.id !== seriesId,
        );
        if (others.length === 0) {
            return { ok: false as const, error: "only_series_for_type" };
        }
        evolu.update("numberSeries", { id: others[0].id, isDefault: booleanToSqliteBoolean(true) });
    }

    return evolu.update("numberSeries", { id: seriesId, isDeleted: sqliteTrue });
}

export function nextNumberForIssue(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    documentType: DocumentType,
    allDocuments: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
): { ok: true; value: string } | { ok: false; error: string } {
    let workingSeries = allSeries;
    let series = resolveDefaultSeries(workingSeries, companyId, documentType);
    if (!series) {
        const seeded = seedDefaultNumberSeries(evolu, companyId, workingSeries);
        workingSeries = [...workingSeries, ...seeded];
        series = resolveDefaultSeries(workingSeries, companyId, documentType);
    }
    if (!series) {
        return { ok: false, error: "no_default_series" };
    }

    const synced = ensureCounterSynced(series, allDocuments);
    const nextCounter = (parseInt(synced.lastNumber || "0", 10) || 0) + 1;
    const number = previewNextNumber(synced, nextCounter);

    const updateResult = evolu.update("numberSeries", {
        id: series.id,
        periodKey: synced.periodKey,
        lastNumber: String(nextCounter),
    });
    if (!updateResult.ok) {
        return { ok: false, error: "series_update_failed" };
    }

    return { ok: true, value: number };
}

export function previewNextDocumentNumberFromSeries(
    allSeries: EvoluNumberSeriesRow[],
    allDocuments: EvoluDocumentRow[],
    companyId: CompanyId,
    documentType: DocumentType,
): string | null {
    const series = resolveDefaultSeries(allSeries, companyId, documentType);
    if (!series) return null;
    const synced = ensureCounterSynced(series, allDocuments);
    return previewNextNumber(synced);
}

/** Bump default number series counter when historical imports use higher numbers. */
export function syncNumberSeriesCounterFromDocuments(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    documentType: DocumentType,
    allDocuments: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
): void {
    let workingSeries = allSeries;
    let series = resolveDefaultSeries(workingSeries, companyId, documentType);
    if (!series) {
        const seeded = seedDefaultNumberSeries(evolu, companyId, workingSeries);
        workingSeries = [...workingSeries, ...seeded];
        series = resolveDefaultSeries(workingSeries, companyId, documentType);
    }
    if (!series) return;

    const synced = ensureCounterSynced(series, allDocuments);
    const currentLast = parseInt(series.lastNumber || "0", 10) || 0;
    const syncedLast = parseInt(synced.lastNumber || "0", 10) || 0;
    if (synced.periodKey !== series.periodKey || syncedLast !== currentLast) {
        evolu.update("numberSeries", {
            id: series.id,
            periodKey: synced.periodKey,
            lastNumber: synced.lastNumber,
        });
    }
}

/** Align local Evolu counter with a server-reserved document number (Woo / store bridge). */
export function syncLocalSeriesCounterFromIssuedNumber(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    documentType: DocumentType,
    issuedNumber: string,
    allSeries: EvoluNumberSeriesRow[],
): void {
    let workingSeries = allSeries;
    let series = resolveDefaultSeries(workingSeries, companyId, documentType);
    if (!series) {
        const seeded = seedDefaultNumberSeries(evolu, companyId, workingSeries);
        workingSeries = [...workingSeries, ...seeded];
        series = resolveDefaultSeries(workingSeries, companyId, documentType);
    }
    if (!series) return;

    const digitLen = counterDigitsInFormat(series.format);
    if (issuedNumber.length < digitLen) return;
    const suffix = issuedNumber.slice(-digitLen);
    if (!/^\d+$/.test(suffix)) return;

    const counter = parseInt(suffix, 10);
    const synced = syncPeriodFields(series);
    const current = parseInt(synced.lastNumber || "0", 10) || 0;
    if (counter <= current) return;

    evolu.update("numberSeries", {
        id: series.id,
        periodKey: synced.periodKey,
        lastNumber: String(counter),
    });
}
