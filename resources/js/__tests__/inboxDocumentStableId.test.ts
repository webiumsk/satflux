import { describe, expect, it } from "vitest";
import {
    findLocalDocumentForInboxEntry,
    resolveLinkedSourceDocumentId,
    stableDocumentIdFromInboxUuid,
} from "@/evolu/inboxDocumentStableId";
import type { CompanyId } from "@/evolu/schema";
import type { EvoluDocumentRow } from "@/evolu/documentMap";

const companyId = "company-1" as CompanyId;
const inboxUuid = "a1b2c3d4-e5f6-4789-a012-3456789abcde";

describe("inboxDocumentStableId", () => {
    it("derives the same branded id for the same inbox uuid", () => {
        const a = stableDocumentIdFromInboxUuid(inboxUuid);
        const b = stableDocumentIdFromInboxUuid(inboxUuid.toUpperCase());
        expect(a).toBeTruthy();
        expect(a).toBe(b);
    });

    it("finds a legacy import by woocommerce order note", () => {
        const stableId = stableDocumentIdFromInboxUuid(inboxUuid)!;
        const legacyId = "legacy-random-id" as EvoluDocumentRow["id"];
        const documents = [
            {
                id: legacyId,
                companyId,
                internalNote: "woocommerce_order_id=1403",
                tagsJson: '["woocommerce","wc_order:1403"]',
            },
        ] as EvoluDocumentRow[];

        expect(
            findLocalDocumentForInboxEntry(documents, companyId, {
                evolu_document_id: inboxUuid,
                woocommerce_order_id: 1403,
                payload: { tags: ["woocommerce", "wc_order:1403"] },
            })?.id,
        ).toBe(legacyId);

        expect(
            findLocalDocumentForInboxEntry(
                [{ ...documents[0]!, id: stableId }],
                companyId,
                {
                    evolu_document_id: inboxUuid,
                    woocommerce_order_id: 1403,
                    payload: {},
                },
            )?.id,
        ).toBe(stableId);
    });

    it("never matches an order-scoped fallback across document types", () => {
        // Deferred-payment order: the proforma is already imported, the final
        // invoice entry arrives later for the SAME order.
        const proforma = {
            id: "local-proforma-id" as EvoluDocumentRow["id"],
            companyId,
            documentType: "proforma",
            internalNote: "woocommerce_order_id=1500",
            tagsJson: '["woocommerce","wc_order:1500"]',
        } as EvoluDocumentRow;

        const invoiceEntry = {
            evolu_document_id: inboxUuid,
            woocommerce_order_id: 1500,
            payload: { type: "invoice", tags: ["woocommerce", "wc_order:1500"] },
        };

        expect(findLocalDocumentForInboxEntry([proforma], companyId, invoiceEntry)).toBeNull();

        // The proforma's own entry still matches it.
        expect(
            findLocalDocumentForInboxEntry([proforma], companyId, {
                ...invoiceEntry,
                evolu_document_id: "0f0e0d0c-0b0a-4987-a654-3210fedcba98",
                payload: { ...invoiceEntry.payload, type: "proforma" },
            })?.id,
        ).toBe(proforma.id);
    });

    it("maps source_evolu_document_id to the proforma's stable local id", () => {
        const sourceUuid = "b2c3d4e5-f6a7-4890-b123-456789abcdef";
        expect(resolveLinkedSourceDocumentId({ source_evolu_document_id: sourceUuid })).toBe(
            stableDocumentIdFromInboxUuid(sourceUuid),
        );
        expect(resolveLinkedSourceDocumentId({})).toBeNull();
        expect(resolveLinkedSourceDocumentId({ source_evolu_document_id: "  " })).toBeNull();
    });
});
