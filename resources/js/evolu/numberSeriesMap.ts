import type { NumberSeriesRow } from "@/composables/useCompanyNumberSeries";
import { previewNextNumber } from "./numberSeriesFormat";
import type { CompanyId, DocumentType, NumberSeriesId, ResetPeriod } from "./schema";

export type EvoluNumberSeriesRow = {
    id: NumberSeriesId;
    companyId: CompanyId;
    name: string;
    documentType: DocumentType;
    format: string;
    resetPeriod: ResetPeriod;
    isDefault: 0 | 1 | null;
    periodKey: string | null;
    lastNumber: string | null;
};

function sqliteBoolToBoolean(value: 0 | 1 | null | undefined): boolean {
    return value !== 0;
}

export function evoluNumberSeriesToApi(row: EvoluNumberSeriesRow): NumberSeriesRow {
    return {
        id: row.id,
        company_id: row.companyId,
        name: row.name,
        document_type: row.documentType,
        format: row.format,
        reset_period: row.resetPeriod,
        is_default: sqliteBoolToBoolean(row.isDefault),
        period_key: row.periodKey,
        last_number: parseInt(row.lastNumber || "0", 10) || 0,
        next_number_preview: previewNextNumber(row),
    };
}

export function listNumberSeriesForCompany(
    rows: EvoluNumberSeriesRow[],
    companyId: CompanyId,
): NumberSeriesRow[] {
    return rows
        .filter((row) => row.companyId === companyId)
        .map(evoluNumberSeriesToApi)
        .sort((a, b) => {
            const typeCmp = a.document_type.localeCompare(b.document_type);
            if (typeCmp !== 0) return typeCmp;
            if (a.is_default !== b.is_default) return a.is_default ? -1 : 1;
            return a.name.localeCompare(b.name, "sk");
        });
}
