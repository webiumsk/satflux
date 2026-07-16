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
import { sortRowsForSequentialDelete } from "../evolu/documentBulkLocal";
import type { EvoluDocumentRow } from "../evolu/documentMap";

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
