import api from "@/services/api";
import type { EvoluDocumentRow } from "./documentMap";
import {
    highestIssuedDocumentCounter,
    resolveDefaultSeries,
} from "./numberSeriesCrud";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import type { CompanyId, DocumentType } from "./schema";
import { isKnownStoreId } from "./storeIdUtils";
import { formatDocumentNumber } from "./numberSeriesFormat";

export function localHighCounterForStoreBridge(
    companyId: CompanyId,
    documentType: DocumentType,
    documents: EvoluDocumentRow[],
    allSeries: EvoluNumberSeriesRow[],
): number {
    const series = resolveDefaultSeries(allSeries, companyId, documentType);
    const format = series?.format ?? "YYYYNNNN";
    return highestIssuedDocumentCounter(companyId, documentType, format, documents);
}

export function formatNumberFromStoreCounter(
    companyId: CompanyId,
    documentType: DocumentType,
    counter: number,
    allSeries: EvoluNumberSeriesRow[],
    date = new Date(),
): string {
    const series = resolveDefaultSeries(allSeries, companyId, documentType);
    const format = series?.format ?? "YYYYNNNN";
    return formatDocumentNumber(format, counter, date);
}

export async function previewNextDocumentNumberFromStore(
    linkedStoreId: string,
    documentType: string,
    validStoreIds?: ReadonlySet<string>,
    localHighCounter?: number,
    allSeries?: EvoluNumberSeriesRow[],
    companyId?: CompanyId,
): Promise<string | null> {
    if (validStoreIds && !isKnownStoreId(linkedStoreId, validStoreIds)) {
        return null;
    }

    try {
        const params: Record<string, string | number> = { type: documentType };
        if (localHighCounter != null && localHighCounter > 0) {
            params.local_high_counter = localHighCounter;
        }
        const { data } = await api.get(`/invoicing/stores/${linkedStoreId}/number-series/preview`, {
            params,
        });
        const nextCounter = data.data?.next_counter as number | undefined;
        if (
            nextCounter != null
            && allSeries
            && companyId
            && Number.isFinite(nextCounter)
        ) {
            return formatNumberFromStoreCounter(
                companyId,
                documentType as DocumentType,
                nextCounter,
                allSeries,
            );
        }
        return (data.data?.next_number as string | undefined) ?? null;
    } catch (error: unknown) {
        const status = (error as { response?: { status?: number } })?.response?.status;
        if (status === 404 || status === 422) {
            return null;
        }
        throw error;
    }
}

export async function reserveNextDocumentNumberFromStore(
    linkedStoreId: string,
    documentType: string,
    validStoreIds?: ReadonlySet<string>,
    localHighCounter?: number,
    allSeries?: EvoluNumberSeriesRow[],
    companyId?: CompanyId,
): Promise<{ ok: true; value: { number: string; counter: number } } | { ok: false; error: string }> {
    if (validStoreIds && !isKnownStoreId(linkedStoreId, validStoreIds)) {
        return { ok: false, error: "store_not_found" };
    }

    try {
        const body: Record<string, string | number> = { document_type: documentType };
        if (localHighCounter != null && localHighCounter > 0) {
            body.local_high_counter = localHighCounter;
        }
        const { data } = await api.post(`/invoicing/stores/${linkedStoreId}/number-series/reserve`, body);
        const counter = data.data?.counter as number | undefined;
        const serverNumber = data.data?.number as string | undefined;
        if (counter == null || !serverNumber) {
            return { ok: false, error: "reserve_failed" };
        }
        const number =
            allSeries && companyId
                ? formatNumberFromStoreCounter(
                      companyId,
                      documentType as DocumentType,
                      counter,
                      allSeries,
                  )
                : serverNumber;
        return { ok: true, value: { number, counter } };
    } catch (error: unknown) {
        const status = (error as { response?: { status?: number } })?.response?.status;
        if (status === 404 || status === 422) {
            return { ok: false, error: "store_not_found" };
        }
        return { ok: false, error: "reserve_failed" };
    }
}
