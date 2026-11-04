import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createExpenseWorkerProbe } from "./evoluExpenseWorkerTestSupport";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { insertLocalExpense, insertLocalExpenseInReconciliation, updateLocalExpense, updateLocalExpenseNote, cancelLocalExpense } from "@/evolu/expenseCrud";
import { withExpenseReconciliation, expenseReconciliationScope, type ExpenseReconciliationScope } from "@/evolu/expenseReconciliation";
import { BankReconciliationUnknownError, BANK_RECONCILIATION_TIMEOUT_MS, serializeBankEmailReconciliation } from "@/evolu/bankReconciliation";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { tryAutoMatchTransaction } from "@/evolu/bankCrud";

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

beforeEach(() => { installBankLocks(); });
afterEach(() => { clearCompanyShares(); clearRowOwnerIndex(); vi.unstubAllGlobals(); vi.useRealTimers(); });
const payload = { issue_date: "2026-11-04", total: "20.00", currency: "EUR" };

describe("expense writer reconciliation authority", () => {
    it("reuses one inert marker and keeps normal edits away from attachment/snapshot/stock queries", async () => {
        const probe = createExpenseWorkerProbe();
        expect((await updateLocalExpense(probe.evolu, probe.expense.id as never, payload)).ok).toBe(true);
        expect((await updateLocalExpenseNote(probe.evolu, probe.expense.id as never, "Synthetic note", probe.transaction.companyId)).ok).toBe(true);
        expect(probe.state._expenseReconciliation).toHaveLength(1);
        const markerChanges = probe.messages.flatMap((message) => message.changes ?? []).filter((change) => change.table === "_expenseReconciliation");
        expect(markerChanges).toHaveLength(4);
        expect(new Set(markerChanges.map((change) => change.id)).size).toBe(1);
        expect(markerChanges.every((change) => Object.keys(change.values).join() === "pulse")).toBe(true);
        const queries = probe.messages.filter((message) => message.type === "query").flatMap((message) => message.queries ?? []);
        expect(new Set(queries).size).toBe(8);
        expect(probe.state.expense[0]).toMatchObject({ total: "20.00", currency: "EUR", internalNote: "Synthetic note", status: "recorded" });
    });

    it("does not save a queued note once the fresh expense is cancelled", async () => {
        const probe = createExpenseWorkerProbe();
        expect((await cancelLocalExpense(probe.evolu, probe.expense.id as never)).ok).toBe(true);
        const before = probe.financialMutations().length;
        expect((await updateLocalExpenseNote(probe.evolu, probe.expense.id as never, "late note")).ok).toBe(false);
        expect(probe.financialMutations()).toHaveLength(before);
        expect(probe.state.expense[0].status).toBe("cancelled");
        expect(probe.state.expense[0].internalNote).toBeUndefined();
    });

    it.each(["absent", "rejected", "null"])("requires an origin lock before the first marker when it is %s", async (mode) => {
        if (mode === "absent") vi.stubGlobal("navigator", {});
        if (mode === "rejected") vi.stubGlobal("navigator", { locks: { request: vi.fn().mockRejectedValue(new Error("unavailable")) } });
        if (mode === "null") vi.stubGlobal("navigator", { locks: { request: vi.fn(async (_name, _options, callback) => callback(null)) } });
        const probe = createExpenseWorkerProbe();
        await expect(updateLocalExpense(probe.evolu, probe.expense.id as never, payload)).rejects.toThrow("expense_reconciliation_lock_unavailable");
        expect(probe.messages.filter((message) => message.type === "mutate")).toHaveLength(0);
    });

    it("revokes retained scopes and rejects forged or foreign capabilities", async () => {
        const probe = createExpenseWorkerProbe(); const foreign = createExpenseWorkerProbe();
        const retained = await withExpenseReconciliation(probe.evolu, (scope) => {
            expect(Object.isFrozen(scope)).toBe(true);
            expect(Reflect.get(scope, "raw")).toBeUndefined();
            const forged = { evolu: scope.evolu, context: scope.context } as ExpenseReconciliationScope;
            expect(() => insertLocalExpenseInReconciliation(forged, probe.transaction.companyId, payload)).toThrow("expense_reconciliation_lease_expired");
            expect(() => expenseReconciliationScope(foreign.evolu)).toThrow("expense_reconciliation_lease_expired");
            return scope;
        });
        expect(Reflect.set(retained, "active", true)).toBe(false);
        expect(() => retained.evolu.update("expense", { id: probe.expense.id as never, total: "999.00" })).toThrow("expense_reconciliation_lease_expired");
        await expect(withExpenseReconciliation(retained.evolu, () => true)).rejects.toThrow("expense_reconciliation_lease_expired");
        await expect(serializeBankEmailReconciliation(probe.evolu, (_allow, lease) =>
            withExpenseReconciliation(foreign.evolu, () => true, lease))).rejects.toThrow("expense_reconciliation_lease_expired");
        expect(foreign.messages.filter((message) => message.type === "mutate")).toHaveLength(0);
    });

    it("routes a new shared expense by fresh metadata despite a missing registry entry", async () => {
        const probe = createExpenseWorkerProbe(true); clearCompanyShares(); clearRowOwnerIndex();
        expect((await insertLocalExpense(probe.evolu, probe.transaction.companyId, payload, [])).ok).toBe(true);
        expect(probe.state.expense).toHaveLength(2);
        expect(probe.state.expense.every((row) => row.ownerId === probe.ownerId)).toBe(true);
        expect(probe.state._expenseReconciliation.every((row) => row.ownerId === probe.appOwnerId)).toBe(true);
    });

    it.each(["unavailable", "migrating", "rekeying", "ambiguous", "target-owner"])("declines inconsistent fresh owner metadata (%s) without a financial write", async (mode) => {
        const probe = createExpenseWorkerProbe(true);
        if (mode === "unavailable") probe.state.company.length = 0;
        if (mode === "migrating" || mode === "rekeying") probe.state.companyShare[0].status = mode;
        if (mode === "ambiguous") probe.state.companyShare.push({ ...probe.state.companyShare[0], id: "different-share" });
        if (mode === "target-owner") probe.state.expense[0].ownerId = probe.appOwnerId;
        await expect(updateLocalExpense(probe.evolu, probe.expense.id as never, payload)).rejects.toThrow("expense_reconciliation_owner_unavailable");
        expect(probe.financialMutations()).toHaveLength(0);
    });

    it("bounds owner initialization and quarantines a worker that never supplies it", async () => {
        vi.useFakeTimers();
        const probe = createExpenseWorkerProbe();
        const client = new Proxy(probe.evolu, { get: (target, key) => key === "appOwner" ? new Promise<never>(() => {}) : Reflect.get(target, key) });
        const pending = updateLocalExpense(client, probe.expense.id as never, payload);
        const rejected = expect(pending).rejects.toBeInstanceOf(BankReconciliationUnknownError);
        await vi.advanceTimersByTimeAsync(BANK_RECONCILIATION_TIMEOUT_MS + 1); await rejected;
        expect(probe.messages.filter((message) => message.type === "mutate")).toHaveLength(0);
        await expect(updateLocalExpense(client, probe.expense.id as never, payload)).rejects.toBeInstanceOf(BankReconciliationUnknownError);
    });

    it.each(["csv", "wise"])("declines %s expense matching without requiring Web Locks or marker writes", async (source) => {
        vi.stubGlobal("navigator", {}); const probe = createExpenseWorkerProbe();
        const transaction = { ...probe.transaction, source };
        expect(await tryAutoMatchTransaction(probe.evolu, transaction as never, { ...probe.ctx, transactions: [transaction] } as never)).toBe(false);
        expect(probe.messages.filter((message) => message.type === "mutate")).toHaveLength(0);
    });
});
