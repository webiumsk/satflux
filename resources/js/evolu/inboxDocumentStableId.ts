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

    const orderId = entry.woocommerce_order_id;
    if (orderId != null && orderId > 0) {
        const orderTag = `woocommerce_order_id=${orderId}`;
        const byOrder = documents.find(
            (row) =>
                row.companyId === companyId
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
