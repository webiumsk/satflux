import { describe, expect, it } from "vitest";
import {
    findLocalDocumentForInboxEntry,
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
});
