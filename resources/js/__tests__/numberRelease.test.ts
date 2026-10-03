import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const releaseMock = vi.hoisted(() => vi.fn());
const ensureBridgeMock = vi.hoisted(() => vi.fn());

vi.mock("@/services/api", () => ({
    default: {},
    invoicingApi: { numberAllocator: { release: releaseMock } },
}));
vi.mock("@/evolu/bridgeCompanyEnsure", () => ({
    ensureBridgeCompanyIdForLocalCompany: ensureBridgeMock,
}));
// documentBulkLocal transitively imports the real Evolu client.
vi.mock("@/evolu/client", () => {
    const tokens = [
        "allBankImportBatchesQuery",
        "allBankTransactionMatchesQuery",
        "allBankTransactionsQuery",
        "allCompaniesDetailQuery",
        "allCompaniesQuery",
        "allCompanyStockBalancesQuery",
        "allCompanyStockItemsQuery",
        "allCompanyStockMovementsQuery",
        "allCompanyWarehousesQuery",
        "allContactsQuery",
        "allDocumentEventsQuery",
        "allDocumentLinesQuery",
        "allDocumentSnapshotsQuery",
        "allDocumentsQuery",
        "allExpensesQuery",
        "allExpenseAttachmentsQuery",
        "allNumberSeriesQuery",
        "allRecurringProfileLinesQuery",
        "allRecurringProfilesQuery",
    ];
    return Object.fromEntries(tokens.map((token) => [token, token]));
});

import { releaseIssuedNumber } from "../evolu/numberReleaseBridge";
import {
    buildLocalDeletionPolicy,
    canDeleteLocalDocument,
    isLatestForCompanyType,
    sortRowsForSequentialDelete,
} from "../evolu/documentBulkLocal";
import type { EvoluDocumentRow } from "../evolu/documentMap";
import type { EvoluNumberSeriesRow } from "../evolu/numberSeriesMap";

describe("releaseIssuedNumber (gapless numbering)", () => {
    beforeEach(() => {
        releaseMock.mockReset();
        ensureBridgeMock.mockReset();
        ensureBridgeMock.mockResolvedValue({ ok: true, bridgeCompanyId: "bridge-1" });
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it("frees the number on the server before the local delete may proceed", async () => {
        releaseMock.mockResolvedValue({ released: true });

        expect(await releaseIssuedNumber("c1", "invoice", "FV20260075")).toEqual({ ok: true });
        expect(releaseMock).toHaveBeenCalledWith("bridge-1", {
            document_type: "invoice",
            number: "FV20260075",
        });
    });

    it("addresses the reservation by the document id - the local format may differ from the server one", async () => {
        releaseMock.mockResolvedValue({ released: true });

        await releaseIssuedNumber("c1", "invoice", "FV20260075", "doc-75");
        expect(releaseMock).toHaveBeenCalledWith("bridge-1", {
            document_type: "invoice",
            number: "FV20260075",
            issue_request_id: "doc-75",
        });
    });

    it("offline deletion of an issued invoice is blocked", async () => {
        vi.stubGlobal("navigator", { onLine: false });

        expect(await releaseIssuedNumber("c1", "invoice", "FV20260075")).toEqual({
            ok: false,
            error: "delete_requires_online",
        });
        expect(releaseMock).not.toHaveBeenCalled();
    });

    it("a 422 (not the series top) maps to not_last", async () => {
        releaseMock.mockRejectedValue({ response: { status: 422 } });

        expect(await releaseIssuedNumber("c1", "invoice", "FV20260074")).toEqual({
            ok: false,
            error: "not_last",
        });
    });

    it("a company without server identity has no floor to free - no-op ok", async () => {
        ensureBridgeMock.mockResolvedValue({ ok: true, bridgeCompanyId: null });

        expect(await releaseIssuedNumber("c1", "invoice", "FV20260075")).toEqual({ ok: true });
        expect(releaseMock).not.toHaveBeenCalled();
    });
});

describe("sortRowsForSequentialDelete", () => {
    it("orders newest first so a contiguous tail deletes in one pass", () => {
        const rows = [
            { id: "a", issueDate: "2026-07-10", number: "FV20260073" },
            { id: "c", issueDate: "2026-07-14", number: "FV20260075" },
            { id: "b", issueDate: "2026-07-12", number: "FV20260074" },
        ] as unknown as EvoluDocumentRow[];

        expect(sortRowsForSequentialDelete(rows).map((row) => row.number)).toEqual([
            "FV20260075",
            "FV20260074",
            "FV20260073",
        ]);
    });

    it("falls back to NUMERIC number collation on identical issue dates", () => {
        const rows = [
            { id: "a", issueDate: "2026-07-14", number: "FV-10" },
            { id: "b", issueDate: "2026-07-14", number: "FV-2" },
            { id: "c", issueDate: "2026-07-14", number: "FV-11" },
            { id: "d", issueDate: "2026-07-14", number: "FV-9" },
        ] as unknown as EvoluDocumentRow[];

        expect(sortRowsForSequentialDelete(rows).map((row) => row.number)).toEqual([
            "FV-11",
            "FV-10",
            "FV-9",
            "FV-2",
        ]);
    });
});

describe("latest-by-number delete guard", () => {
    const series = [{
        id: "s1",
        companyId: "c1",
        documentType: "invoice",
        format: "FVYYYYNNNN",
        resetPeriod: "yearly",
        isDefault: 1,
        periodKey: "2026",
        lastNumber: "0",
        name: "FV",
    }] as unknown as EvoluNumberSeriesRow[];

    function doc(id: string, number: string | null, status = "issued", issueDate = "2026-07-14"): EvoluDocumentRow {
        return { id, companyId: "c1", documentType: "invoice", number, status, issueDate } as unknown as EvoluDocumentRow;
    }

    it("decides by the number, not by a random id on the same day", () => {
        // "z" sorts after "a" - the old guard made FV20260001 the latest.
        const docs = [doc("z-first", "FV20260001"), doc("a-second", "FV20260002")];
        expect(isLatestForCompanyType(docs[1], docs, series)).toBe(true);
        expect(isLatestForCompanyType(docs[0], docs, series)).toBe(false);
    });

    it("ignores drafts and edited issue dates", () => {
        const docs = [
            doc("d1", "FV20260001", "issued", "2026-12-31"),
            doc("d2", "FV20260002", "issued", "2026-07-01"),
            doc("draft", null, "draft", "2026-12-31"),
        ];
        expect(isLatestForCompanyType(docs[1], docs, series)).toBe(true);
    });

    it("treats a numbered cancelled document like an issued one", () => {
        const policy = buildLocalDeletionPolicy([], series);
        const docs = [doc("d1", "FV20260001", "cancelled"), doc("d2", "FV20260002")];
        expect(canDeleteLocalDocument(docs[0], docs, policy)).toBe(false);
        expect(canDeleteLocalDocument(docs[1], docs, policy)).toBe(true);
        // A cancelled document that never got a number is always deletable.
        expect(canDeleteLocalDocument(doc("d3", null, "cancelled"), docs, policy)).toBe(true);
    });

    it("blocks deleting a bank-matched document", () => {
        const docs = [doc("d1", "FV20260001", "paid")];
        const policy = buildLocalDeletionPolicy([], series, [{ businessDocumentId: "d1" }]);
        expect(canDeleteLocalDocument(docs[0], docs, policy)).toBe(false);
    });
});
