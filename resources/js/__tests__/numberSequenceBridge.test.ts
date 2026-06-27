import { describe, expect, it } from "vitest";
import {
    formatNumberFromStoreCounter,
    localHighCounterForStoreBridge,
} from "@/evolu/numberSequenceBridge";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import type { CompanyId, DocumentId } from "@/evolu/schema";

const companyId = "cmp-test" as CompanyId;

describe("localHighCounterForStoreBridge", () => {
    it("returns highest issued counter suffix for the default series format", () => {
        const series = [
            {
                id: "series-1",
                companyId,
                documentType: "invoice",
                format: "YYYYNNNN",
                isDefault: 1,
            } as EvoluNumberSeriesRow,
        ];
        const documents = [
            {
                id: "d1" as DocumentId,
                companyId,
                documentType: "invoice",
                number: "20260065",
                status: "issued",
            },
            {
                id: "d2" as DocumentId,
                companyId,
                documentType: "invoice",
                number: "20260068",
                status: "paid",
            },
        ] as EvoluDocumentRow[];

        expect(
            localHighCounterForStoreBridge(companyId, "invoice", documents, series),
        ).toBe(68);
    });
});

describe("formatNumberFromStoreCounter", () => {
    it("formats reserved counter with the local Evolu series pattern", () => {
        const series = [
            {
                id: "series-1",
                companyId,
                documentType: "invoice",
                format: "YYYYNNNN",
                isDefault: 1,
            } as EvoluNumberSeriesRow,
        ];

        expect(formatNumberFromStoreCounter(companyId, "invoice", 66, series)).toBe("20260066");
    });
});
