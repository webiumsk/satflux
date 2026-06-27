import type { DocumentType, CompanyId } from "./schema";
import type { EvoluDocumentRow } from "./documentMap";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import { normalizeVariableSymbol } from "./bankSymbolNormalizer";
import { previewNextDocumentNumberFromSeries } from "./numberSeriesCrud";

const DEFAULT_PREFIX: Record<string, string> = {
    invoice: "",
    proforma: "ZF",
    quote: "CP",
    credit_note: "DB",
    delivery_note: "DL",
    order_received: "OB",
};

function issuedOfType(documents: EvoluDocumentRow[], companyId: CompanyId, type: DocumentType) {
    return documents.filter(
        (d) =>
            d.companyId === companyId
            && d.documentType === type
            && d.status !== "draft"
            && d.number,
    );
}

function parseTrailingNumber(number: string): number {
    const match = number.match(/(\d+)\s*$/);
    return match ? Number(match[1]) : 0;
}

export function previewNextDocumentNumber(
    documents: EvoluDocumentRow[],
    companyId: CompanyId,
    documentType: DocumentType,
    _companyPrefix?: string | null,
    allSeries?: EvoluNumberSeriesRow[],
): string {
    if (allSeries?.length) {
        const fromSeries = previewNextDocumentNumberFromSeries(
            allSeries,
            documents,
            companyId,
            documentType,
        );
        if (fromSeries) return fromSeries;
    }

    const prefix = _companyPrefix?.trim() || DEFAULT_PREFIX[documentType] || "";
    const issued = issuedOfType(documents, companyId, documentType);
    let maxSeq = 0;
    for (const doc of issued) {
        if (!doc.number) continue;
        const withoutPrefix = prefix && doc.number.startsWith(prefix)
            ? doc.number.slice(prefix.length)
            : doc.number;
        maxSeq = Math.max(maxSeq, parseTrailingNumber(withoutPrefix));
    }
    const next = maxSeq + 1;
    const padded = String(next).padStart(4, "0");
    return `${prefix}${padded}`;
}

export function variableSymbolFromNumber(number: string): string {
    const digits = number.replace(/\D/g, "");
    return digits.slice(-10);
}

/** Normalize explicit VS or derive digits-only VS from a document number. */
export function documentVariableSymbol(
    value: string | null | undefined,
    fallbackNumber?: string | null,
): string | null {
    return normalizeVariableSymbol(value) ?? normalizeVariableSymbol(fallbackNumber);
}
