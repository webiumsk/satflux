import { describe, expect, it, vi } from "vitest";

// integrationInboxImport transitively imports the real Evolu client.
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
vi.mock("@/services/api", () => ({ default: { get: vi.fn(), post: vi.fn() } }));

import { canAutoReconcileEntry } from "../evolu/integrationInboxImport";

/**
 * Guard against the production incident (2026-07-14): a re-sent WooCommerce
 * order matched an OLDER local invoice by order id and the reconcile silently
 * deleted the auto-issued entry - its allocated (and emailed) number never
 * entered the books.
 */
describe("canAutoReconcileEntry", () => {
    it("unstamped entries reconcile freely (relay-import case)", () => {
        expect(canAutoReconcileEntry({ payload: {} }, null)).toBe(true);
        expect(canAutoReconcileEntry({ payload: {} }, "FV20260071")).toBe(true);
    });

    it("stamped entry reconciles only against a document with the SAME number", () => {
        const entry = { payload: { number: "FV20260072" } };
        expect(canAutoReconcileEntry(entry, "FV20260072")).toBe(true);
        expect(canAutoReconcileEntry(entry, "FV20260071")).toBe(false);
        expect(canAutoReconcileEntry(entry, null)).toBe(false);
        expect(canAutoReconcileEntry(entry, "")).toBe(false);
    });
});
