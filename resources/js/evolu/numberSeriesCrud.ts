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
import { previewNextDocumentNumber } from "./documentNumber";
import {
    counterInPeriod,
    currentPeriodKey,
    formatHasCounterToken,
    previewNextNumber,
} from "./numberSeriesFormat";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import type { CompanyId, DocumentType, InvoicingLocalSchema, NumberSeriesId, ResetPeriod, DocumentId } from "./schema";

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
    { documentType: "invoice", nameKey: "invoicing.series_default_name_invoice", format: "INVYYYYNNNN", isDefault: true },
    { documentType: "credit_note", nameKey: "invoicing.series_default_name_credit_note", format: "CNYYYYNNNN", isDefault: true },
    { documentType: "proforma", nameKey: "invoicing.series_default_name_proforma", format: "PFYYYYNNNN", isDefault: true },
    { documentType: "delivery_note", nameKey: "invoicing.series_default_name_delivery_note", format: "DELYYYYNNNN", isDefault: true },
    { documentType: "quote", nameKey: "invoicing.series_default_name_quote", format: "QTYYYYNNNN", isDefault: true },
    { documentType: "order_received", nameKey: "invoicing.series_default_name_order_received", format: "POYYYYNNNN", isDefault: true },
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

/**
 * Highest counter among numbered documents of the series' CURRENT period
 * (issued, paid and cancelled - a cancelled number stays used). Parsed with
 * the full format, so numbers of an earlier year, an earlier format or a
 * foreign import do not count - the trailing-digits parse carried last
 * year's count into the new year and misread a widened counter.
 */
export function highestIssuedDocumentCounter(
    companyId: CompanyId,
    documentType: DocumentType,
    format: string,
    documents: EvoluDocumentRow[],
    resetPeriod: ResetPeriod | string = "yearly",
    date = new Date(),
): number {
    let max = 0;

    for (const doc of documents) {
        if (doc.companyId !== companyId || doc.documentType !== documentType || !doc.number) {
            continue;
        }
        if (doc.status === "draft") {
            continue;
        }
        const counter = counterInPeriod(format, doc.number, resetPeriod, date, doc.issueDate);
        if (counter !== null && counter > max) {
            max = counter;
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

/**
 * Series counter for the current period: the highest number used by
 * documents, never below the stored counter of the same period (the
 * "last number" a migrating user types into the series panel is a floor -
 * it used to be silently overwritten). `lowerTo` is the explicit lowering
 * path of a gapless delete (see syncNumberSeriesCounterFromDocuments).
 */
function ensureCounterSynced(
    series: EvoluNumberSeriesRow,
    documents: EvoluDocumentRow[],
    date = new Date(),
    lowerTo?: number,
): EvoluNumberSeriesRow {
    const synced = syncPeriodFields(series, date);
    const fromDocuments = highestIssuedDocumentCounter(
        synced.companyId,
        synced.documentType,
        synced.format,
        documents,
        synced.resetPeriod,
        date,
    );
    const stored = parseInt(synced.lastNumber || "0", 10) || 0;
    const floor = lowerTo !== undefined ? Math.min(stored, Math.max(0, lowerTo)) : stored;
    const next = Math.max(fromDocuments, floor);
    if (next !== stored) {
        return { ...synced, lastNumber: String(next) };
    }
    return synced;
}

/**
 * Counter the server allocator must not go below for this client: the
 * highest number of the current period among local documents and the
 * series' own counter (manual start value).
 */
export function localHighCounterForSeries(
    series: EvoluNumberSeriesRow | null,
    companyId: CompanyId,
    documentType: DocumentType,
    documents: EvoluDocumentRow[],
    date = new Date(),
): number {
    if (!series) {
        return highestIssuedDocumentCounter(companyId, documentType, "YYYYNNNN", documents, "yearly", date);
    }
    return parseInt(ensureCounterSynced(series, documents, date).lastNumber || "0", 10) || 0;
}

export function isIssuedNumberTaken(
    number: string,
    companyId: CompanyId,
    documentType: DocumentType,
    documents: EvoluDocumentRow[],
    excludeDocumentId?: DocumentId,
): boolean {
    return documents.some(
        (doc) =>
            doc.id !== excludeDocumentId
            && doc.companyId === companyId
            && doc.documentType === documentType
            && doc.status !== "draft"
            && doc.status !== "cancelled"
            && doc.number === number,
    );
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
    if (!formatHasCounterToken(format.value)) {
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
    _existing: EvoluNumberSeriesRow,
) {
    const name = NameType.from(form.name.trim());
    if (!name.ok) return name;
    const format = FormatType.from(form.format.trim().toUpperCase());
    if (!format.ok) return format;
    if (!formatHasCounterToken(format.value)) {
        return { ok: false as const, error: "format_missing_counter" };
    }

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
        // The form edits the counter of the current period (the panel shows
        // the period-effective value), so the period is always re-stamped.
        lastNumber: String(Math.max(0, Number(form.last_number) || 0)),
        periodKey: currentPeriodKey(form.reset_period as ResetPeriod),
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

// The local max+1 allocator (allocateNextNumberForIssue / nextNumberForIssue)
// was removed: issuing reserves through the server allocator (audit F3).

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

/** Preview next number, seeding default series when missing (matches issue flow). */
export function previewNextLocalDocumentNumber(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    documentType: DocumentType,
    allDocuments: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
): string {
    let workingSeries = allSeries;
    let series = resolveDefaultSeries(workingSeries, companyId, documentType);
    if (!series) {
        const seeded = seedDefaultNumberSeries(evolu, companyId, workingSeries);
        workingSeries = [...workingSeries, ...seeded];
        series = resolveDefaultSeries(workingSeries, companyId, documentType);
    }
    if (series) {
        const synced = ensureCounterSynced(series, allDocuments);
        return previewNextNumber(synced);
    }
    return previewNextDocumentNumber(
        allDocuments,
        companyId,
        documentType,
        null,
        workingSeries,
    );
}

/** Bump default number series counter when historical imports use higher numbers. */
export function syncNumberSeriesCounterFromDocuments(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    documentType: DocumentType,
    allDocuments: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
    options: { releasedNumbers?: string[] } = {},
): void {
    let workingSeries = allSeries;
    let series = resolveDefaultSeries(workingSeries, companyId, documentType);
    if (!series) {
        const seeded = seedDefaultNumberSeries(evolu, companyId, workingSeries);
        workingSeries = [...workingSeries, ...seeded];
        series = resolveDefaultSeries(workingSeries, companyId, documentType);
    }
    if (!series) return;

    // Gapless delete: the released top numbers go back to the pool, so the
    // counter drops below the smallest released one (never below what the
    // remaining documents use). Without released counters it only rises.
    const released = (options.releasedNumbers ?? [])
        .map((number) => counterInPeriod(series.format, number, series.resetPeriod))
        .filter((counter): counter is number => counter !== null);
    const lowerTo = released.length > 0 ? Math.min(...released) - 1 : undefined;
    const synced = ensureCounterSynced(series, allDocuments, new Date(), lowerTo);
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

    const counter = counterInPeriod(series.format, issuedNumber, series.resetPeriod);
    if (counter === null) return;
    const synced = syncPeriodFields(series);
    const current = parseInt(synced.lastNumber || "0", 10) || 0;
    if (counter <= current) return;

    evolu.update("numberSeries", {
        id: series.id,
        periodKey: synced.periodKey,
        lastNumber: String(counter),
    });
}
