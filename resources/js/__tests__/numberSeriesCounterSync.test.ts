import { describe, expect, it } from "vitest";
import {
    allocateNextNumberForIssue,
    previewNextLocalDocumentNumber,
} from "@/evolu/numberSeriesCrud";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import type { CompanyId, DocumentId } from "@/evolu/schema";

const companyId = "cmp-test" as CompanyId;

function makeSeries(lastNumber: string): EvoluNumberSeriesRow {
    return {
        id: "series-1",
        companyId,
        documentType: "invoice",
        name: "Invoices",
        format: "YYYYNNNN",
        resetPeriod: "yearly",
        periodKey: "2026",
        lastNumber,
        isDefault: 1,
        isDeleted: 0,
    } as EvoluNumberSeriesRow;
}

function makeDocument(number: string, status: EvoluDocumentRow["status"] = "issued"): EvoluDocumentRow {
    return {
        id: `doc-${number}` as DocumentId,
        companyId,
        documentType: "invoice",
        number,
        status,
        isDeleted: 0,
    } as EvoluDocumentRow;
}

describe("number series counter after delete", () => {
    it("lowers preview when highest issued documents were removed", () => {
        const series = [makeSeries("68")];
        const documents = [makeDocument("20260065"), makeDocument("20260068")];

        const preview = previewNextLocalDocumentNumber(
            {} as never,
            companyId,
            "invoice",
            documents,
            series,
        );
        expect(preview).toBe("20260069");

        const afterDelete = previewNextLocalDocumentNumber(
            {} as never,
            companyId,
            "invoice",
            [makeDocument("20260065")],
            series,
        );
        expect(afterDelete).toBe("20260066");
    });

    it("allocates the next number from remaining issued documents", () => {
        const series = [makeSeries("68")];
        const documents = [makeDocument("20260065")];

        const allocation = allocateNextNumberForIssue(
            {} as never,
            companyId,
            "invoice",
            documents,
            series,
        );

        expect(allocation.ok).toBe(true);
        if (allocation.ok) {
            expect(allocation.value).toBe("20260066");
            expect(allocation.nextCounter).toBe(66);
        }
    });
});
