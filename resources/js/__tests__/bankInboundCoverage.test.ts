import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { importInboundServerTransactionsAsync, importInboundServerTransactionsPageAsync } from "@/evolu/bankCrud";
import { BankReconciliationUnknownError } from "@/evolu/bankReconciliation";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { createExpenseWorkerProbe, type DataRow } from "./evoluExpenseWorkerTestSupport";
vi.mock("@/evolu/client", async () => {
    const { createQuery } = await import("@evolu/common/local-first");
    return {
        allBankImportBatchesQuery: createQuery((db) => db.selectFrom("bankImportBatch").selectAll()),
        allBankTransactionsQuery: createQuery((db) => db.selectFrom("bankTransaction").selectAll()),
        allBankTransactionMatchesQuery: createQuery((db) => db.selectFrom("bankTransactionMatch").selectAll()),
        allDocumentsQuery: createQuery((db) => db.selectFrom("document").selectAll()),
        allExpensesQuery: createQuery((db) => db.selectFrom("expense").selectAll()),
        bankReconciliationBatchesQuery: createQuery((db) => db.selectFrom("bankImportBatch").selectAll().where("id", "is not", null)),
        bankReconciliationTransactionsQuery: createQuery((db) => db.selectFrom("bankTransaction").selectAll().where("id", "is not", null)),
        bankReconciliationMatchesQuery: createQuery((db) => db.selectFrom("bankTransactionMatch").selectAll().where("id", "is not", null)),
        bankReconciliationDocumentsQuery: createQuery((db) => db.selectFrom("document").selectAll().where("id", "is not", null)),
        bankReconciliationExpensesQuery: createQuery((db) => db.selectFrom("expense").selectAll().where("internalNumber", "is not", null).where("id", "is not", null)),
    };
});
vi.mock("@/evolu/documentCrud", () => ({ markLocalDocumentPaidWithAmount: vi.fn(), unmarkLocalDocumentPaid: vi.fn() }));
const row = { booked_at: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", recorded_direction: "debit" };
beforeEach(() => { installBankLocks(); });
afterEach(() => { clearCompanyShares(); clearRowOwnerIndex(); vi.unstubAllGlobals(); });

it.each([false, true])("acknowledges new rows and proven duplicate coverage in the actual owner (shared=%s)", async (shared) => {
    const probe = createExpenseWorkerProbe(shared); probe.state.expense.length = 0;
    const owner = shared ? probe.ownerId : probe.appOwnerId;
    probe.state.bankTransaction[0].dedupeHash = "existing";
    const rows = [{ ...row, dedupe_hash: "new" }, { ...row, dedupe_hash: "existing" }];
    expect(await importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, rows, owner))
        .toEqual({ imported: 1, auto_matched: 0, complete: true, scope_verified: true });
    expect(await importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, rows, owner))
        .toEqual({ imported: 0, auto_matched: 0, complete: true, scope_verified: true });
    expect(probe.state.bankTransaction.filter((item) => item.dedupeHash === "new")).toHaveLength(1);
});

it("keeps a valid subset but refuses coverage for an invalid uncovered row", async () => {
    const probe = createExpenseWorkerProbe(); probe.state.expense.length = 0;
    expect(await importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, [
        { ...row, dedupe_hash: "valid" }, { ...row, dedupe_hash: "invalid", variable_symbol: "x".repeat(33) },
    ], probe.appOwnerId)).toEqual({ imported: 1, auto_matched: 0, complete: false, scope_verified: true });
    expect(probe.state.bankTransaction.some((item) => item.dedupeHash === "valid")).toBe(true);
    expect(probe.state.bankTransaction.some((item) => item.dedupeHash === "invalid")).toBe(false);
});

it("does not acknowledge a real rejected batch creation while preserving the legacy two-field result", async () => {
    const probe = createExpenseWorkerProbe();
    const wrapped = new Proxy(probe.evolu, { get(target, property, receiver) {
        if (property !== "insert") return Reflect.get(target, property, receiver);
        return (table: string, props: DataRow, options?: object) => target.insert(table as never,
            (table === "bankImportBatch" ? { ...props, filename: "x".repeat(256) } : props) as never, options);
    } });
    expect(await importInboundServerTransactionsPageAsync(wrapped, probe.transaction.companyId, [{ ...row, dedupe_hash: "new" }], probe.appOwnerId))
        .toEqual({ imported: 0, auto_matched: 0, complete: false, scope_verified: true });
    expect(await importInboundServerTransactionsAsync(wrapped, probe.transaction.companyId, [{ ...row, dedupe_hash: "new" }]))
        .toEqual({ imported: 0, auto_matched: 0 });
});

it("covers a durable unverified ledger row without marking an expense paid", async () => {
    const probe = createExpenseWorkerProbe();
    expect(await importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, [
        { ...row, recorded_direction: undefined, dedupe_hash: "unverified", variable_symbol: "00019" },
    ], probe.appOwnerId)).toEqual({ imported: 1, auto_matched: 0, complete: true, scope_verified: true });
    expect(probe.state.expense[0].status).toBe("recorded");
    expect(probe.state.bankTransaction.find((item) => item.dedupeHash === "unverified")?.recordedDirectionEvidence).toBe("unverified");
});

it("refuses cursor coverage without a verified origin scope before any mutation", async () => {
    vi.stubGlobal("navigator", {});
    const probe = createExpenseWorkerProbe(); probe.state.expense.length = 0;
    probe.state.bankTransaction[0].dedupeHash = "foreign"; probe.state.bankTransaction[0].ownerId = "different-owner";
    expect(await importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, [
        { ...row, dedupe_hash: "foreign" },
    ], probe.appOwnerId)).toEqual({ imported: 0, auto_matched: 0, complete: false, scope_verified: false });
    expect(probe.financialMutations()).toHaveLength(0);
    expect(probe.state.bankImportBatch).toHaveLength(0);
});

it("propagates an actual worker Unknown without an acknowledgement", async () => {
    const probe = createExpenseWorkerProbe(false, false);
    const pending = importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, [{ ...row, dedupe_hash: "unknown" }], probe.appOwnerId);
    const rejected = expect(pending).rejects.toBeInstanceOf(BankReconciliationUnknownError);
    await probe.pumpUntil(() => probe.financialMutations().length > 0);
    probe.error(); await rejected;
});

it.each([false, true])("rejects a captured page owner that differs from fresh company routing before any bank write (shared=%s)", async (shared) => {
    const probe = createExpenseWorkerProbe(shared);
    const expected = shared ? probe.appOwnerId : probe.ownerId;
    await expect(importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, [
        { ...row, dedupe_hash: "owner-cutover" },
    ], expected)).rejects.toThrow("expense_reconciliation_owner_unavailable");
    expect(probe.financialMutations()).toHaveLength(0);
    expect(probe.state.bankImportBatch).toHaveLength(0);
});

it.each([true, false])("admits an empty page only with verified owner scope and no bank mutations (locks=%s)", async (locks) => {
    if (!locks) vi.stubGlobal("navigator", {});
    const probe = createExpenseWorkerProbe();
    expect(await importInboundServerTransactionsPageAsync(probe.evolu, probe.transaction.companyId, [], probe.appOwnerId))
        .toEqual({ imported: 0, auto_matched: 0, complete: locks, scope_verified: locks });
    expect(probe.financialMutations()).toHaveLength(0);
    expect(probe.state.bankImportBatch).toHaveLength(0);
});
