import { invoicingApi } from "@/services/api";
import type { EvoluDocumentRow } from "./documentMap";
import {
    highestIssuedDocumentCounter,
    resolveDefaultSeries,
} from "./numberSeriesCrud";
import type { EvoluNumberSeriesRow } from "./numberSeriesMap";
import type { CompanyId, DocumentType } from "./schema";
import { isKnownStoreId } from "./storeIdUtils";
import { formatDocumentNumber } from "./numberSeriesFormat";

export type CompanyBridgeIdentity = {
    legal_name?: string | null;
    registration_number?: string | null;
};

function isStoreNotLinkedError(error: unknown): boolean {
    const response = (error as { response?: { status?: number; data?: { error?: string } } })?.response;
    return response?.status === 422 && response?.data?.error === "store_not_linked";
}

/**
 * Self-heal for the local-first split-brain: the Evolu company says it is
 * linked to the store, but the server-side stores.company_id is missing
 * (422 store_not_linked). Mirror the link to the bridge company once and
 * let the caller retry.
 */
async function healStoreLink(
    identity: CompanyBridgeIdentity | undefined,
    linkedStoreId: string,
): Promise<boolean> {
    if (!identity?.legal_name) {
        return false;
    }
    // Dynamic import: ephemeralBridge transitively pulls the Evolu client,
    // which this module must not load eagerly (tests, bundle weight).
    const { syncLinkedStoreToServerBridge } = await import("./ephemeralBridge");
    return (await syncLinkedStoreToServerBridge(identity, linkedStoreId)) === "synced";
}

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
    companyIdentity?: CompanyBridgeIdentity,
    isRetryAfterHeal = false,
): Promise<string | null> {
    if (validStoreIds && !isKnownStoreId(linkedStoreId, validStoreIds)) {
        return null;
    }

    try {
        const params: Record<string, string | number> = { type: documentType };
        if (localHighCounter != null && localHighCounter > 0) {
            params.local_high_counter = localHighCounter;
        }
        const data = await invoicingApi.storeNumberSeries.preview(linkedStoreId, params);
        if (data?.error === "store_not_linked") {
            return null;
        }
        if (!data?.data) {
            throw new Error("number_series_preview_invalid_response");
        }
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
        if (!isRetryAfterHeal && isStoreNotLinkedError(error) && await healStoreLink(companyIdentity, linkedStoreId)) {
            return previewNextDocumentNumberFromStore(
                linkedStoreId,
                documentType,
                validStoreIds,
                localHighCounter,
                allSeries,
                companyId,
                companyIdentity,
                true,
            );
        }
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
    companyIdentity?: CompanyBridgeIdentity,
    isRetryAfterHeal = false,
): Promise<{ ok: true; value: { number: string; counter: number } } | { ok: false; error: string }> {
    if (validStoreIds && !isKnownStoreId(linkedStoreId, validStoreIds)) {
        return { ok: false, error: "store_not_found" };
    }

    try {
        const body: Record<string, string | number> = { document_type: documentType };
        if (localHighCounter != null && localHighCounter > 0) {
            body.local_high_counter = localHighCounter;
        }
        const data = await invoicingApi.storeNumberSeries.reserve(linkedStoreId, body);
        if (data?.error === "store_not_linked" || !data?.data) {
            return { ok: false, error: "store_not_found" };
        }
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
        if (!isRetryAfterHeal && isStoreNotLinkedError(error) && await healStoreLink(companyIdentity, linkedStoreId)) {
            return reserveNextDocumentNumberFromStore(
                linkedStoreId,
                documentType,
                validStoreIds,
                localHighCounter,
                allSeries,
                companyId,
                companyIdentity,
                true,
            );
        }
        const status = (error as { response?: { status?: number } })?.response?.status;
        if (status === 404 || status === 422) {
            return { ok: false, error: "store_not_found" };
        }
        return { ok: false, error: "reserve_failed" };
    }
}
