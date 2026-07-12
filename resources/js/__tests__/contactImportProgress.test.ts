import { describe, expect, it, vi } from "vitest";

// The import module transitively pulls the real Evolu client - replace it
// with inert query tokens before importing (established test pattern).
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

import { importContactsFromCsv } from "@/evolu/contactImportLocal";
import type { CompanyId } from "@/evolu/schema";

function csvWithRows(count: number): string {
    const header = "name";
    const rows = Array.from({ length: count }, (_, index) => `Client ${index + 1}`);
    return [header, ...rows].join("\n");
}

describe("importContactsFromCsv progress (P1 phase 6)", () => {
    it("reports progress during the loop and once at the end", async () => {
        const insert = vi.fn().mockReturnValue({ ok: true, value: { id: "c" } });
        const evolu = { insert } as never;
        const onProgress = vi.fn();

        const result = await importContactsFromCsv(
            evolu,
            "co-1" as CompanyId,
            csvWithRows(60),
            { name: 0 },
            onProgress,
        );

        expect(result.imported).toBe(60);
        // Yields fire every 25 rows (25, 50) plus the final report.
        expect(onProgress).toHaveBeenCalledWith(25, 60);
        expect(onProgress).toHaveBeenCalledWith(50, 60);
        expect(onProgress).toHaveBeenLastCalledWith(60, 60);
    });

    it("keeps the row-level error report intact", async () => {
        const insert = vi.fn().mockReturnValue({ ok: true, value: { id: "c" } });
        const evolu = { insert } as never;

        const csv = "name\nClient A\n\nClient B";
        const result = await importContactsFromCsv(evolu, "co-1" as CompanyId, csv, { name: 0 });
        expect(result.imported).toBe(2);
        expect(result.errors).toEqual([]);
    });
});
