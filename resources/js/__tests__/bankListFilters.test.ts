import { describe, expect, it, vi } from "vitest";
import { bankFilterMinorUnits, createBankListSummary, matchesBankListFilters, normalizeBankListFilters } from "@/evolu/bankListFilters";
import { buildBankSummary, filterLocalBankTransactions, type BankTxApiRow, type EvoluBankTransactionRow } from "@/evolu/bankMap";
import type { BankPaymentContext } from "@/evolu/bankCrud";
import { mapLocalBankTransactionsToApi } from "@/evolu/bankCrud";

vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionMatchesQuery: "matches", allBankTransactionsQuery: "transactions",
    allCompaniesDetailQuery: "companies", allDocumentsQuery: "documents", allExpensesQuery: "expenses", allExpenseAttachmentsQuery: "attachments",
    allExpenseOriginalSourcesQuery: "original-sources", allExpenseOriginalChunksQuery: "original-chunks", useInvoicingEvolu: vi.fn(),
}));

function movement(id: string, patch: Partial<EvoluBankTransactionRow> = {}): EvoluBankTransactionRow {
    return { id, ownerId: "owner-a", companyId: "company-a", bankImportBatchId: null, bookedAt: "2026-11-16T12:00:00.000Z",
        amount: "19.05", currency: "EUR", direction: "debit", matchStatus: "unmatched", businessExpenseId: null,
        variableSymbol: "001905", constantSymbol: null, specificSymbol: null, counterpartyName: "ŽLTÝ TEST",
        counterpartyIban: "SYNTHETIC-ACCOUNT-001", reference: "Literal %_\\ reference", bankTransactionId: null, dedupeHash: null,
        source: "csv", ...patch } as unknown as EvoluBankTransactionRow;
}
function context(transactions: EvoluBankTransactionRow[]): BankPaymentContext {
    return { transactions, batches: [], matches: [], documents: [], expenses: [] };
}
const apiRow = (patch: Partial<BankTxApiRow> = {}): BankTxApiRow => ({ id: "tx", booked_at: "2026-11-16T23:59:59.999Z",
    amount: "19.05", currency: "EUR", direction: "debit", match_status: "unmatched", ...patch });

describe("bank list criteria", () => {
    it("finds older matching movements before the existing 50-row display cap and totals all matches", () => {
        const rows = Array.from({ length: 125 }, (_, index) => movement(`tx-${index}`, { reference: index < 65 ? "other" : "needle" }));
        const accumulator = createBankListSummary();
        const visible = mapLocalBankTransactionsToApi(rows, context(rows), { limit: 50, filters: { search: "needle" }, onMatch: accumulator.add });
        expect(visible).toHaveLength(50);
        expect(visible[0].id).toBe("tx-65");
        expect(accumulator.finish()).toMatchObject({ debit_count: 60, debit_total: "1143.00", balance: "-1143.00" });
    });

    it.each(["žltý", "%_\\", "SYNTHETIC-ACCOUNT-001", "001905"])("performs literal case-insensitive metadata search for %s", search => {
        const row = movement("tx");
        expect(mapLocalBankTransactionsToApi([row], context([row]), { filters: { search } })).toHaveLength(1);
    });

    it("searches scoped linked document metadata but never foreign-owner or foreign-company copies", () => {
        const row = movement("tx");
        const ctx = context([row]);
        ctx.matches = [{ id: "match", ownerId: "owner-a", bankTransactionId: row.id, businessDocumentId: "doc", matchedAmount: "19.05", matchType: "manual", matchedAt: null }] as unknown as BankPaymentContext["matches"];
        ctx.documents = [
            { id: "doc", ownerId: "owner-b", companyId: "company-a", number: "foreign-owner" },
            { id: "doc", ownerId: "owner-a", companyId: "company-b", number: "foreign-company" },
            { id: "doc", ownerId: "owner-a", companyId: "company-a", number: "INV-SEARCH-019", title: "ŽLTÝ DOKLAD" },
        ] as unknown as BankPaymentContext["documents"];
        expect(mapLocalBankTransactionsToApi([row], ctx, { filters: { search: "inv-search" } })).toHaveLength(1);
        expect(mapLocalBankTransactionsToApi([row], ctx, { filters: { search: "žltý doklad" } })).toHaveLength(1);
        for (const search of ["foreign-owner", "foreign-company"]) expect(mapLocalBankTransactionsToApi([row], ctx, { filters: { search } })).toHaveLength(0);
    });

    it("filters using resolved display direction while retaining original payment direction evidence", () => {
        const row = movement("legacy", { direction: "credit", reference: "Debet na ucte - synthetic" });
        expect(mapLocalBankTransactionsToApi([row], context([row]), { filters: { direction: "credit" } })).toHaveLength(0);
        expect(mapLocalBankTransactionsToApi([row], context([row]), { filters: { direction: "debit" } })[0])
            .toMatchObject({ direction: "debit", recorded_direction: "credit" });
    });

    it("includes the full last booking day and exact nonnegative amount magnitude bounds", () => {
        const filters = normalizeBankListFilters({ from: "2026-11-16", to: "2026-11-16", amount_min: "19.05", amount_max: "19.05" });
        expect(matchesBankListFilters(apiRow({ amount: "-19.05" }), filters)).toBe(true);
        expect(matchesBankListFilters(apiRow({ amount: "19.04" }), filters)).toBe(false);
        expect(matchesBankListFilters(apiRow({ booked_at: "2026-11-17T00:00:00.000Z" }), filters)).toBe(false);
    });

    it.each(["-1", "NaN", "Infinity", "1e309", "1e2", "1.001", "90071992547409.92"])("rejects unsupported amount %s", amount_min => {
        expect(() => normalizeBankListFilters({ amount_min })).toThrow("bank_filters_invalid");
    });

    it("rejects reversed ranges, impossible dates and year zero without broadening the result", () => {
        for (const filters of [{ from: "0000-01-01" }, { from: "2026-02-30" }, { from: "2026-11-17", to: "2026-11-16" },
            { amount_min: "1.01", amount_max: "1.00" }]) expect(() => normalizeBankListFilters(filters)).toThrow("bank_filters_invalid");
        expect(bankFilterMinorUnits("00019.05")).toBe(1905);
        expect(bankFilterMinorUnits("90071992547409.91")).toBe(Number.MAX_SAFE_INTEGER);
    });

    it.each(["bad\0query", "bad\ud800query"])("rejects malformed search text", search => {
        expect(() => normalizeBankListFilters({ search })).toThrow("bank_filters_invalid");
    });

    it("preserves stored amounts above the filter-input ceiling and compares them without rounding", () => {
        const row = apiRow({ amount: "90071992547410.00" });
        expect(matchesBankListFilters(row, {})).toBe(true);
        expect(matchesBankListFilters(row, normalizeBankListFilters({ amount_min: "1.00" }))).toBe(true);
        expect(matchesBankListFilters(row, normalizeBankListFilters({ amount_max: "90071992547409.91" }))).toBe(false);
        const accumulator = createBankListSummary(); accumulator.add(row);
        expect(accumulator.finish()).toMatchObject({ debit_count: 1, debit_total: "90071992547410.00" });
    });

    it("keeps currencies separate and bank-reported balance independent of filter totals", () => {
        const accumulator = createBankListSummary();
        accumulator.add(apiRow()); accumulator.add(apiRow({ currency: "USD", direction: "credit", amount: "2.00" }));
        const balance = { amount: "-25.00", currency: "EUR", as_of: "2026-11-17" };
        expect(accumulator.finish(balance)).toMatchObject({ debit_count: 1, credit_count: 1, currency: null, balance: null, account_balance: balance });
        expect(accumulator.finish().by_currency).toHaveLength(2);
        expect(buildBankSummary([movement("snapshot", { amount: "-25.00", reference: "stav na ucte" })]).account_balance?.amount).toBe("-25.00");
    });

    it("uses a stable booking-date and id order and excludes another company before projection", () => {
        const rows = [movement("a"), movement("z"), movement("foreign", { companyId: "company-b" as never })];
        expect(filterLocalBankTransactions(rows, "company-a" as never).map(row => row.id)).toEqual(["z", "a"]);
    });
});
