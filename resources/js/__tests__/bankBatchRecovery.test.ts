import { describe, expect, it, vi } from "vitest";
import type { BankImportBatchId, BankTransactionId, CompanyId } from "@/evolu/schema";
import type { EvoluBankImportBatchRow, EvoluBankTransactionRow } from "@/evolu/bankMap";

vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionsQuery: "transactions",
    allBankTransactionMatchesQuery: "matches", allDocumentsQuery: "documents", allExpensesQuery: "expenses",
    bankReconciliationBatchesQuery: "fresh:batches", bankReconciliationTransactionsQuery: "fresh:transactions",
    bankReconciliationMatchesQuery: "fresh:matches", bankReconciliationDocumentsQuery: "fresh:documents",
    bankReconciliationExpensesQuery: "fresh:expenses",
}));

import { mapLocalBankBatchesToApi } from "@/evolu/bankCrud";

const companyId = "company-a" as CompanyId;
const batch: EvoluBankImportBatchRow = {
    id: "batch-a" as BankImportBatchId, companyId, source: "inbound_email", filename: "b-mail-sync",
    rowCount: "1", importedCount: "0", skippedDuplicates: "0", autoMatchedCount: "0",
};
const transaction: EvoluBankTransactionRow = {
    id: "transaction-a" as BankTransactionId, companyId, bankImportBatchId: batch.id,
    bookedAt: "2026-11-04T12:00:00", amount: "19.00", currency: "EUR", direction: "debit",
    recordedDirectionEvidence: "debit", matchStatus: "unmatched", businessExpenseId: null,
    variableSymbol: "0019", constantSymbol: null, specificSymbol: null, counterpartyName: null,
    counterpartyIban: null, reference: null, bankTransactionId: null, dedupeHash: "synthetic-row", source: "inbound_email",
};

describe("persisted bank batch recovery availability", () => {
    it("offers explicit matching for a committed unmatched row with an unverified zero counter", () => {
        expect(mapLocalBankBatchesToApi([batch], companyId, [transaction])[0])
            .toMatchObject({ imported_count: 0, can_auto_match: true });
    });

    it.each(["matched", "ignored"] as const)("does not use the counter when all retained rows are %s", (matchStatus) => {
        expect(mapLocalBankBatchesToApi([{ ...batch, importedCount: "1" }], companyId, [{ ...transaction, matchStatus }])[0])
            .toMatchObject({ imported_count: 1, can_auto_match: false });
    });

    it("does not borrow unmatched rows from another company or batch", () => {
        const rows = [
            { ...transaction, companyId: "company-b" as CompanyId },
            { ...transaction, bankImportBatchId: "batch-b" as BankImportBatchId },
        ];
        expect(mapLocalBankBatchesToApi([batch], companyId, rows)[0]?.can_auto_match).toBe(false);
        expect(mapLocalBankBatchesToApi([batch], "company-b" as CompanyId, rows)).toEqual([]);
    });

    it("hides recovery for an empty batch even if its counter is positive", () => {
        expect(mapLocalBankBatchesToApi([{ ...batch, importedCount: "1" }], companyId, [])[0]?.can_auto_match).toBe(false);
    });
});
