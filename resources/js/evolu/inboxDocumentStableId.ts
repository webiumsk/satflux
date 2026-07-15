import { createIdFromString } from "@evolu/common";
import { DocumentId } from "./schema";
import type { EvoluDocumentRow } from "./documentMap";
import type { CompanyId } from "./schema";

export type InboxEntryMatchInput = {
    evolu_document_id: string;
    woocommerce_order_id: number | null;
    payload: Record<string, unknown>;
};

/** Same inbox row → same Evolu document id on every device (WooCommerce import + relay). */
export function stableDocumentIdFromInboxUuid(inboxEvoluUuid: string): DocumentId | null {
    const normalized = inboxEvoluUuid.trim().toLowerCase();
    if (!normalized) {
        return null;
    }
    const stable = createIdFromString(`satflux.inbox-document.v1.${normalized}`);
    const parsed = DocumentId.from(stable);
    return parsed.ok ? parsed.value : null;
}

/** Local id of the linked source document (the proforma) referenced by a final-invoice payload. */
export function resolveLinkedSourceDocumentId(
    payload: Record<string, unknown>,
): DocumentId | null {
    const sourceUuid = String(payload?.source_evolu_document_id ?? "").trim();
    return sourceUuid ? stableDocumentIdFromInboxUuid(sourceUuid) : null;
}

export function findLocalDocumentForInboxEntry(
    documents: ReadonlyArray<EvoluDocumentRow>,
    companyId: CompanyId,
    entry: InboxEntryMatchInput,
): EvoluDocumentRow | null {
    const stableId = stableDocumentIdFromInboxUuid(entry.evolu_document_id);
    if (stableId) {
        const byStableId = documents.find((row) => row.id === stableId);
        if (byStableId) {
            return byStableId;
        }
    }

    // One order may carry BOTH a proforma and its final invoice, so the
    // order-scoped fallbacks below must never match across document types
    // (an invoice entry matching the imported proforma would be silently
    // cleared as already-imported). Rows without a known type stay eligible
    // (legacy imports).
    const entryType = String(entry.payload?.type ?? "invoice");
    const typeMatches = (row: EvoluDocumentRow): boolean => {
        const rowType = String(row.documentType ?? "").trim();
        return rowType === "" || rowType === entryType;
    };

    const orderId = entry.woocommerce_order_id;
    if (orderId != null && orderId > 0) {
        const orderTag = `woocommerce_order_id=${orderId}`;
        const byOrder = documents.find(
            (row) =>
                row.companyId === companyId
                && typeMatches(row)
                && String(row.internalNote ?? "").includes(orderTag),
        );
        if (byOrder) {
            return byOrder;
        }
    }

    const wcTag = orderId != null ? `wc_order:${orderId}` : "";
    if (wcTag) {
        const tagsJson = entry.payload?.tags;
        if (Array.isArray(tagsJson) && tagsJson.map(String).includes(wcTag)) {
            const byTags = documents.find((row) => {
                if (row.companyId !== companyId) return false;
                if (!typeMatches(row)) return false;
                try {
                    const tags = JSON.parse(String(row.tagsJson ?? "[]")) as unknown;
                    return Array.isArray(tags) && tags.map(String).includes(wcTag);
                } catch {
                    return false;
                }
            });
            if (byTags) {
                return byTags;
            }
        }
    }

    return null;
}
