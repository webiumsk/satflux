import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import { insertLocalExpense, updateLocalExpense, type ExpenseSavePayload } from "@/evolu/expenseCrud";
import { evoluExpenseToApi, type EvoluExpenseRow } from "@/evolu/expenseMap";
import type { CompanyId, ExpenseId, InvoicingLocalSchema } from "@/evolu/schema";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { createExpenseWriterTestAdapter } from "./expenseReconciliationTestSupport";
vi.mock("@/evolu/client", async () => (await import("./expenseReconciliationTestSupport")).expenseTestQueryExports());

function adapter() {
    const { evolu, state } = createExpenseWriterTestAdapter();
    return { evolu: evolu as unknown as Evolu<InvoicingLocalSchema>, saved: () => state.expense[0] };
}
const companyId = "company-a" as CompanyId;
const expenseId = "expense-1" as ExpenseId;
const base: ExpenseSavePayload = { issue_date: "2026-11-03", total: "10.00" };
beforeEach(() => { installBankLocks(); });
afterEach(() => { vi.unstubAllGlobals(); });

describe("local expense delivery date persistence", () => {
    it.each([null, "2026-11-01"])("persists and reopens an explicit delivery value %s", async (delivery) => {
        const { evolu, saved } = adapter();
        expect((await insertLocalExpense(evolu, companyId, { ...base, delivery_date: delivery }, [])).ok).toBe(true);
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBe(delivery);
    });
    it("defaults an omitted delivery date only during creation", async () => {
        const { evolu, saved } = adapter();
        expect((await insertLocalExpense(evolu, companyId, base, [])).ok).toBe(true);
        expect(saved().deliveryDate).toBe("2026-11-03");
        expect((await updateLocalExpense(evolu, expenseId, { ...base, issue_date: "2026-11-04" })).ok).toBe(true);
        expect(saved().deliveryDate).toBe("2026-11-03");
    });
    it("retains a cleared delivery date when a subsequent update omits it", async () => {
        const { evolu, saved } = adapter();
        await insertLocalExpense(evolu, companyId, base, []);
        expect((await updateLocalExpense(evolu, expenseId, { ...base, delivery_date: null })).ok).toBe(true);
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBeNull();
        await updateLocalExpense(evolu, expenseId, { ...base, issue_date: "2026-11-04" });
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBeNull();
        await updateLocalExpense(evolu, expenseId, { ...base, delivery_date: "2026-11-02" });
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBe("2026-11-02");
    });
});
