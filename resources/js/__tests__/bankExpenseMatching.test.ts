import { effectiveExpensePayment } from '@/evolu/bankExpenseClaims';
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import type { BankPaymentContext } from "@/evolu/bankCrud";
import type { EvoluBankTransactionRow } from "@/evolu/bankMap";
import type { EvoluExpenseRow } from "@/evolu/expenseMap";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import { bankPaymentBookingDate, findBankEmailExpenseMatch, tryAutoMatchBankEmailExpense } from "@/evolu/bankExpensePaymentMatcher";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import { installBankLocks, bankTestQueryTable } from "./bankReconciliationTestSupport";

vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionsQuery: "transactions",
    allBankTransactionMatchesQuery: "matches", allDocumentsQuery: "documents", allExpensesQuery: "expenses",
    bankReconciliationBatchesQuery: "fresh:batches", bankReconciliationTransactionsQuery: "fresh:transactions",
    bankReconciliationMatchesQuery: "fresh:matches", bankReconciliationDocumentsQuery: "fresh:documents", bankReconciliationExpensesQuery: "fresh:expenses",
}));
vi.mock("@/evolu/documentCrud", () => ({ markLocalDocumentPaidWithAmount: vi.fn(), unmarkLocalDocumentPaid: vi.fn() }));
import { autoMatchLocalBatchAsync, importInboundServerTransactionsAsync, tryAutoMatchTransaction, unmatchLocalBankTransaction } from "@/evolu/bankCrud";

function context(tx: Partial<EvoluBankTransactionRow> = {}, expense: Partial<EvoluExpenseRow> = {}): BankPaymentContext {
    return {
        batches: [{ id: "batch-a", companyId: "company-a", source: "inbound_email", filename: "legacy", rowCount: "1", importedCount: "1", skippedDuplicates: "0", autoMatchedCount: "0" } as BankPaymentContext["batches"][number]], matches: [], documents: [],
        transactions: [{ id: "tx-a", companyId: "company-a", bankImportBatchId: "batch-a",
            bookedAt: "2026-11-03T11:12:13Z", amount: "19.00", currency: "EUR", direction: "debit",
            matchStatus: "unmatched", businessExpenseId: null, variableSymbol: "00123", constantSymbol: null,
            specificSymbol: null, counterpartyName: null, counterpartyIban: null, reference: "INV-00123",
            bankTransactionId: null, dedupeHash: "dedupe-a", source: "inbound_email", ...tx } as EvoluBankTransactionRow],
        expenses: [{ id: "expense-a", companyId: "company-a", status: "recorded", internalNumber: "EXP2026001",
            externalNumber: "INV-00123", title: "Synthetic expense", variableSymbol: "00123", constantSymbol: null,
            specificSymbol: null, issueDate: "2026-11-01", deliveryDate: null, dueDate: null,
            paidAt: null, cancelledAt: null, total: "19.00", currency: "EUR", internalNote: null,
            ...expense } as EvoluExpenseRow],
    };
}

function fakeEvolu(ctx: BankPaymentContext) {
    let counter = 0;
    const state = {
        batches: ctx.batches.map((row) => ({ ...row })), transactions: ctx.transactions.map((row) => ({ ...row })), matches: ctx.matches.map((row) => ({ ...row })),
        documents: ctx.documents.map((row) => ({ ...row })), expenses: ctx.expenses.map((row) => ({ ...row })),
    };
    const tables: Record<string, Array<Record<string, unknown>>> = {
        bankImportBatch: state.batches, bankTransaction: state.transactions, expense: state.expenses,
        company: [{ id: "company-a", legalName: "Synthetic company", ownerId: "app-owner" }],
    } as unknown as Record<string, Array<Record<string, unknown>>>;
    for (const rows of Object.values(tables)) for (const row of rows) row.ownerId = "app-owner";
    const update = vi.fn((table: string, patch: Record<string, unknown>, options?: { onlyValidate?: boolean; onComplete?: () => void }) => {
        if (!options?.onlyValidate) {
            Object.assign(tables[table]?.find((row) => row.id === patch.id) ?? {}, patch);
            queueMicrotask(() => options?.onComplete?.());
        }
        return { ok: true, value: { id: patch.id } };
    });
    const evolu = {
        appOwner: Promise.resolve({ id: "app-owner" }),
        upsert: vi.fn((_table: string, props: Record<string, unknown>, options?: { onComplete?: () => void }) => {
            queueMicrotask(() => options?.onComplete?.()); return { ok: true, value: { id: props.id } };
        }),
        loadQuery: vi.fn(async (query: string) => {
            const rows = state[query.replace("fresh:", "") as keyof typeof state] ?? tables[bankTestQueryTable(query) ?? ""] ?? [];
            return rows.map((row) => ({ ...row }));
        }), update,
        subscribeError: () => () => {}, getError: () => null,
        insert: vi.fn((table: string, props: Record<string, unknown>, options?: { onlyValidate?: boolean; onComplete?: () => void }) => {
            const id = `insert-${++counter}`;
            if (!options?.onlyValidate) {
                tables[table]!.push({ ...props, id, ownerId: "app-owner" });
                queueMicrotask(() => options?.onComplete?.());
            }
            return { ok: true, value: { id } };
        }),
    } as unknown as Evolu<InvoicingLocalSchema>;
    return { evolu, update, state };
}
beforeEach(() => { installBankLocks(); });
afterEach(() => { clearRowOwnerIndex(); clearCompanyShares(); vi.unstubAllGlobals(); });

describe("exact existing-expense bank email reconciliation", () => {
    it("links one exact debit to the expense with the bank booking date", async () => {
        const ctx = context(); const { evolu, update, state } = fakeEvolu(ctx);
        expect(await tryAutoMatchTransaction(evolu, ctx.transactions[0]!, ctx)).toBe(true);
        expect(ctx.expenses[0]).toMatchObject({ status: "recorded", paidAt: null });
        expect(effectiveExpensePayment(state.expenses[0]!, state.transactions)).toMatchObject({ status: "paid", paidAt: "2026-11-03" });
        expect(ctx.transactions[0]).toMatchObject({ matchStatus: "matched", businessExpenseId: "expense-a" });
        expect(update.mock.calls.filter((call) => !call[2]?.onlyValidate)).toHaveLength(2);
        expect(await tryAutoMatchTransaction(evolu, ctx.transactions[0]!, ctx)).toBe(false);
    });
    it.each([
        [{}, { variableSymbol: null }],
        [{ variableSymbol: null }, {}],
        [{ variableSymbol: "" }, { externalNumber: "INV-00123" }],
    ])("supports the bounded external-number fallback %j %j", (tx, expense) => {
        const ctx = context(tx, expense);
        expect(findBankEmailExpenseMatch(ctx.transactions[0]!, ctx)?.expense.id).toBe("expense-a");
    });
    it.each([
        { direction: "credit" }, { direction: "unknown" }, { source: "wise" }, { source: "csv" },
        { matchStatus: "ignored" }, { matchStatus: "matched" }, { businessExpenseId: "other" },
        { recordedDirectionEvidence: "unverified" }, { recordedDirectionEvidence: "credit" },
        { variableSymbol: "123" }, { variableSymbol: "999", reference: "INV-00123" },
        { variableSymbol: "letters", reference: "INV-00123" },
        { variableSymbol: null, reference: "Payment INV-00123" },
        { amount: "19oops" }, { amount: "1.9e1" }, { amount: "NaN" }, { amount: "Infinity" },
        { amount: "-19.00" }, { amount: "0.00" }, { amount: "18.99" }, { amount: "19.01" }, { amount: "19.001" },
        { currency: null }, { currency: "US" }, { currency: "USD" },
        { bookedAt: null }, { bookedAt: "2026-02-30" }, { bookedAt: "2026-11-03T24:00:00Z" },
        { reference: "Stav na účte" },
    ])("does not pay an expense for ineligible transaction %j", async (patch) => {
        const ctx = context(patch as Partial<EvoluBankTransactionRow>);
        const { evolu, update } = fakeEvolu(ctx);
        expect(await tryAutoMatchBankEmailExpense(evolu, ctx.transactions[0]!, ctx)).toBe(false);
        expect(update).not.toHaveBeenCalled();
    });
    it.each([
        { companyId: "company-b" }, { status: "paid" }, { status: "cancelled" }, { status: "draft" },
        { paidAt: "2026-11-01" }, { cancelledAt: "2026-11-01" }, { variableSymbol: "letters" },
        { variableSymbol: "999" }, { total: "19oops" }, { total: "0" }, { total: "18.99" },
        { currency: null }, { currency: "USD" },
    ])("does not pay an ineligible expense %j", (patch) => {
        const ctx = context({}, patch as Partial<EvoluExpenseRow>);
        expect(findBankEmailExpenseMatch(ctx.transactions[0]!, ctx)).toBeNull();
    });
    it("rejects ambiguous expenses, existing expense links and invoice evidence", () => {
        const ctx = context();
        ctx.expenses.push({ ...ctx.expenses[0]!, id: "expense-b" } as EvoluExpenseRow);
        expect(findBankEmailExpenseMatch(ctx.transactions[0]!, ctx)).toBeNull();
        ctx.expenses.pop();
        ctx.transactions.push({ ...ctx.transactions[0]!, id: "other-tx", businessExpenseId: ctx.expenses[0]!.id } as EvoluBankTransactionRow);
        expect(findBankEmailExpenseMatch(ctx.transactions[0]!, ctx)).toBeNull();
        ctx.transactions.pop();
        ctx.matches.push({ bankTransactionId: ctx.transactions[0]!.id } as BankPaymentContext["matches"][number]);
        expect(findBankEmailExpenseMatch(ctx.transactions[0]!, ctx)).toBeNull();
    });
    it.each([0, 1, 2, 3])("does not change context or report success if update %s fails", async (failedCall) => {
        const ctx = context(); const before = structuredClone(ctx);
        let call = 0;
        const { evolu, update } = fakeEvolu(ctx);
        const original = update.getMockImplementation()!;
        update.mockImplementation((...args) => call++ === failedCall ? { ok: false, value: { id: args[1].id } } : original(...args));
        expect(await tryAutoMatchBankEmailExpense(evolu, ctx.transactions[0]!, ctx)).toBe(false);
        expect(ctx).toEqual(before);
    });
    it("links a second debit only once and preserves paid state when unlinked", async () => {
        const ctx = context(); const { evolu } = fakeEvolu(ctx);
        expect(await tryAutoMatchBankEmailExpense(evolu, ctx.transactions[0]!, ctx)).toBe(true);
        const second = { ...ctx.transactions[0]!, id: "tx-b", matchStatus: "unmatched", businessExpenseId: null } as EvoluBankTransactionRow;
        ctx.transactions.push(second);
        expect(await tryAutoMatchBankEmailExpense(evolu, second, ctx)).toBe(false);
        await unmatchLocalBankTransaction(evolu, ctx.transactions[0]!.id, ctx,
            { companyId: ctx.transactions[0]!.companyId, claim: ctx.transactions[0]!.bankPaymentClaimJson! });
        expect(ctx.expenses[0]!.status).toBe("recorded");
        expect(effectiveExpensePayment(ctx.expenses[0]!, ctx.transactions).status).toBe("paid");
        expect(ctx.transactions[0]!.businessExpenseId).toBeNull();
    });
    it("accepts real leap days and rejects impossible dates", () => {
        expect(bankPaymentBookingDate("2024-02-29")).toBe("2024-02-29");
        expect(bankPaymentBookingDate("2026-02-29")).toBeNull();
    });
});

describe("email ingestion retains evidence for automatic expense matching", () => {
    const input = { booked_at: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", recorded_direction: "debit", variable_symbol: "00123", dedupe_hash: "server-hash" };
    it("imports and reconciles the expense once without replaying duplicates", async () => {
        const ctx = context(); ctx.transactions = [];
        const { evolu, state } = fakeEvolu(ctx);
        expect(await importInboundServerTransactionsAsync(evolu, ctx.expenses[0]!.companyId, [input])).toEqual({ imported: 1, auto_matched: 1 });
        expect(state.expenses[0]).toMatchObject({ status: "recorded", paidAt: null });
        expect(effectiveExpensePayment(state.expenses[0]!, state.transactions)).toMatchObject({ status: "paid", paidAt: "2026-11-03" });
        expect(await importInboundServerTransactionsAsync(evolu, ctx.expenses[0]!.companyId, [input])).toEqual({ imported: 0, auto_matched: 0 });
    });
    it.each([{ amount: "19oops" }, { amount: "-19.00" }, { amount: "19.001" }, { currency: "" }, { currency: "EU" }, { booked_at: "2026-02-30" }])("never manufactures matching evidence from %j", async (patch) => {
        const ctx = context(); ctx.transactions = [];
        const { evolu, state } = fakeEvolu(ctx);
        const result = await importInboundServerTransactionsAsync(evolu, ctx.expenses[0]!.companyId, [{ ...input, ...patch }]);
        expect(result).toEqual({ imported: 1, auto_matched: 0 });
        expect(state.expenses[0]!.status).toBe("recorded");
        expect(await autoMatchLocalBatchAsync(evolu, state.transactions[0]!.bankImportBatchId!)).toEqual({ auto_matched: 0 });
        expect(state.expenses[0]!.status).toBe("recorded");
    });
    it("leaves a historical duplicate untouched until an explicit batch auto-match", async () => {
        const ctx = context({ dedupeHash: input.dedupe_hash });
        const { evolu, state } = fakeEvolu(ctx);
        expect(await importInboundServerTransactionsAsync(evolu, ctx.expenses[0]!.companyId, [input])).toEqual({ imported: 0, auto_matched: 0 });
        expect(state.expenses[0]!.status).toBe("recorded");
        expect(await autoMatchLocalBatchAsync(evolu, ctx.transactions[0]!.bankImportBatchId!)).toEqual({ auto_matched: 1 });
        expect(state.expenses[0]!.status).toBe("recorded");
        expect(effectiveExpensePayment(state.expenses[0]!, state.transactions).status).toBe("paid");
    });
    it.each([undefined, "credit", "invalid"])("does not auto-pay using an inferred display debit when recorded direction is %s", async (recordedDirection) => {
        const ctx = context(); ctx.transactions = [];
        const { evolu, state } = fakeEvolu(ctx);
        const { recorded_direction: _, ...legacyInput } = input;
        const row = recordedDirection === undefined ? legacyInput : { ...legacyInput, recorded_direction: recordedDirection };
        expect(await importInboundServerTransactionsAsync(evolu, ctx.expenses[0]!.companyId, [row])).toEqual({ imported: 1, auto_matched: 0 });
        expect(state.expenses[0]!.status).toBe("recorded");
        expect(state.transactions[0]!.recordedDirectionEvidence).toBe(recordedDirection === "credit" ? "credit" : "unverified");
        expect(await autoMatchLocalBatchAsync(evolu, state.transactions[0]!.bankImportBatchId!)).toEqual({ auto_matched: 0 });
        expect(state.expenses[0]!.status).toBe("recorded");
    });
});
