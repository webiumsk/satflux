import api from "@/services/api";

export async function previewNextDocumentNumberFromStore(
    linkedStoreId: string,
    documentType: string,
): Promise<string | null> {
    const { data } = await api.get(`/invoicing/stores/${linkedStoreId}/number-series/preview`, {
        params: { type: documentType },
    });
    return (data.data?.next_number as string | undefined) ?? null;
}

export async function reserveNextDocumentNumberFromStore(
    linkedStoreId: string,
    documentType: string,
): Promise<{ ok: true; value: { number: string } } | { ok: false; error: string }> {
    try {
        const { data } = await api.post(`/invoicing/stores/${linkedStoreId}/number-series/reserve`, {
            document_type: documentType,
        });
        const number = data.data?.number as string | undefined;
        if (!number) {
            return { ok: false, error: "reserve_failed" };
        }
        return { ok: true, value: { number } };
    } catch {
        return { ok: false, error: "reserve_failed" };
    }
}
