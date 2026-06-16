import api from "@/services/api";
import { isKnownStoreId } from "./sanitizeStoreReferences";

export async function previewNextDocumentNumberFromStore(
    linkedStoreId: string,
    documentType: string,
    validStoreIds?: ReadonlySet<string>,
): Promise<string | null> {
    if (validStoreIds && !isKnownStoreId(linkedStoreId, validStoreIds)) {
        return null;
    }

    try {
        const { data } = await api.get(`/invoicing/stores/${linkedStoreId}/number-series/preview`, {
            params: { type: documentType },
        });
        return (data.data?.next_number as string | undefined) ?? null;
    } catch (error: unknown) {
        const status = (error as { response?: { status?: number } })?.response?.status;
        if (status === 404) {
            return null;
        }
        throw error;
    }
}

export async function reserveNextDocumentNumberFromStore(
    linkedStoreId: string,
    documentType: string,
    validStoreIds?: ReadonlySet<string>,
): Promise<{ ok: true; value: { number: string } } | { ok: false; error: string }> {
    if (validStoreIds && !isKnownStoreId(linkedStoreId, validStoreIds)) {
        return { ok: false, error: "store_not_found" };
    }

    try {
        const { data } = await api.post(`/invoicing/stores/${linkedStoreId}/number-series/reserve`, {
            document_type: documentType,
        });
        const number = data.data?.number as string | undefined;
        if (!number) {
            return { ok: false, error: "reserve_failed" };
        }
        return { ok: true, value: { number } };
    } catch (error: unknown) {
        const status = (error as { response?: { status?: number } })?.response?.status;
        if (status === 404) {
            return { ok: false, error: "store_not_found" };
        }
        return { ok: false, error: "reserve_failed" };
    }
}
