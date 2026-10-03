import { describe, expect, it, vi } from "vitest";
import {
    highestIssuedDocumentCounter,
    localHighCounterForSeries,
    previewNextLocalDocumentNumber,
    syncNumberSeriesCounterFromDocuments,
} from "@/evolu/numberSeriesCrud";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import type { CompanyId, DocumentId } from "@/evolu/schema";

const companyId = "cmp-test" as CompanyId;
const may2026 = new Date(2026, 4, 10);

function makeSeries(lastNumber: string, overrides: Partial<EvoluNumberSeriesRow> = {}): EvoluNumberSeriesRow {
    return {
        id: "series-1",
        companyId,
        documentType: "invoice",
        name: "Invoices",
        format: "YYYYNNNN",
        resetPeriod: "yearly",
        periodKey: String(new Date().getFullYear()),
        lastNumber,
        isDefault: 1,
        isDeleted: 0,
        ...overrides,
    } as EvoluNumberSeriesRow;
}

function makeDocument(
    number: string,
    status: EvoluDocumentRow["status"] = "issued",
    issueDate: string | null = null,
): EvoluDocumentRow {
    return {
        id: `doc-${number}` as DocumentId,
        companyId,
        documentType: "invoice",
        number,
        status,
        issueDate,
        isDeleted: 0,
    } as EvoluDocumentRow;
}

function fakeEvolu() {
    return { update: vi.fn().mockReturnValue({ ok: true }), insert: vi.fn() };
}

describe("number series counter after delete", () => {
    it("lowers the counter below the released top numbers (gapless delete)", () => {
        const year = new Date().getFullYear();
        const series = [makeSeries("68")];
        const evolu = fakeEvolu();

        syncNumberSeriesCounterFromDocuments(
            evolu as never,
            companyId,
            "invoice",
            [makeDocument(`${year}0065`)],
            series,
            { releasedNumbers: [`${year}0068`, `${year}0067`, `${year}0066`] },
        );

        expect(evolu.update).toHaveBeenCalledWith("numberSeries", {
            id: "series-1",
            periodKey: String(year),
            lastNumber: "65",
        });
    });

    it("never lowers the counter without released numbers - a manual start value is a floor", () => {
        const year = new Date().getFullYear();
        const series = [makeSeries("100")];

        const preview = previewNextLocalDocumentNumber(
            {} as never,
            companyId,
            "invoice",
            [makeDocument(`${year}0003`)],
            series,
        );
        expect(preview).toBe(`${year}0101`);
    });

    it("previews from the documents when they are above the stored counter", () => {
        const year = new Date().getFullYear();
        const preview = previewNextLocalDocumentNumber(
            {} as never,
            companyId,
            "invoice",
            [makeDocument(`${year}0065`), makeDocument(`${year}0068`)],
            [makeSeries("0")],
        );
        expect(preview).toBe(`${year}0069`);
    });
});

describe("highestIssuedDocumentCounter", () => {
    it("ignores numbers of an earlier year in a yearly series (no carry-over into the new year)", () => {
        const documents = [makeDocument("INV20250342"), makeDocument("INV20260007")];
        expect(highestIssuedDocumentCounter(companyId, "invoice", "INVYYYYNNNN", documents, "yearly", may2026)).toBe(7);
        expect(highestIssuedDocumentCounter(companyId, "invoice", "INVYYYYNNNN", [makeDocument("INV20250342")], "yearly", may2026)).toBe(0);
    });

    it("keeps counting across years when the series never resets", () => {
        const documents = [makeDocument("INV20250342"), makeDocument("INV20260007")];
        expect(highestIssuedDocumentCounter(companyId, "invoice", "INVYYYYNNNN", documents, "never", may2026)).toBe(342);
    });

    it("parses the counter from the full format, not the trailing digits", () => {
        // Widened counter run: the trailing 5 digits of INV20260012 are 60012.
        expect(highestIssuedDocumentCounter(companyId, "invoice", "FVYYYYNNNNN", [makeDocument("INV20260012")], "yearly", may2026)).toBe(0);
        // Year after the counter.
        expect(highestIssuedDocumentCounter(companyId, "invoice", "FNNNNYYYY", [makeDocument("F00122026")], "yearly", may2026)).toBe(12);
        // Overflowing counter.
        expect(highestIssuedDocumentCounter(companyId, "invoice", "INVYYYYNNNN", [makeDocument("INV202610000")], "yearly", may2026)).toBe(10000);
    });

    it("uses the issue date for formats without a year", () => {
        const documents = [
            makeDocument("FV0099", "issued", "2025-12-30"),
            makeDocument("FV0004", "issued", "2026-02-01"),
        ];
        expect(highestIssuedDocumentCounter(companyId, "invoice", "FVNNNN", documents, "yearly", may2026)).toBe(4);
    });

    it("counts cancelled numbers (they stay used) but not drafts", () => {
        const documents = [
            makeDocument("INV20260009", "cancelled"),
            makeDocument("INV20260012", "draft"),
        ];
        expect(highestIssuedDocumentCounter(companyId, "invoice", "INVYYYYNNNN", documents, "yearly", may2026)).toBe(9);
    });
});

describe("localHighCounterForSeries", () => {
    it("ignores a stored counter of an earlier period", () => {
        const series = makeSeries("342", { periodKey: "2025" });
        expect(localHighCounterForSeries(series, companyId, "invoice", [], may2026)).toBe(0);
    });
});
