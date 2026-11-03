import { describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import { insertLocalExpense, updateLocalExpense, type ExpenseSavePayload } from "@/evolu/expenseCrud";
import { evoluExpenseToApi, type EvoluExpenseRow } from "@/evolu/expenseMap";
import type { CompanyId, ExpenseId, InvoicingLocalSchema } from "@/evolu/schema";

function adapter() {
    let saved: Record<string, unknown> = {};
    const raw = {
        insert: vi.fn((_table: string, data: Record<string, unknown>) => {
            saved = { ...data, id: "expense-a" };
            return { ok: true, value: { id: "expense-a" } };
        }),
        update: vi.fn((_table: string, data: Record<string, unknown>) => {
            Object.assign(saved, data);
            return { ok: true, value: { id: "expense-a" } };
        }),
    };
    return { evolu: raw as unknown as Evolu<InvoicingLocalSchema>, saved: () => saved };
}
const companyId = "company-a" as CompanyId;
const expenseId = "expense-a" as ExpenseId;
const base: ExpenseSavePayload = { issue_date: "2026-11-03", total: "10.00" };

describe("local expense delivery date persistence", () => {
    it.each([null, "2026-11-01"])("persists and reopens an explicit delivery value %s", (delivery) => {
        const { evolu, saved } = adapter();
        expect(insertLocalExpense(evolu, companyId, { ...base, delivery_date: delivery }, []).ok).toBe(true);
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBe(delivery);
    });
    it("defaults an omitted delivery date only during creation", () => {
        const { evolu, saved } = adapter();
        expect(insertLocalExpense(evolu, companyId, base, []).ok).toBe(true);
        expect(saved().deliveryDate).toBe("2026-11-03");
        expect(updateLocalExpense(evolu, expenseId, { ...base, issue_date: "2026-11-04" }).ok).toBe(true);
        expect(saved().deliveryDate).toBe("2026-11-03");
    });
    it("retains a cleared delivery date when a subsequent update omits it", () => {
        const { evolu, saved } = adapter();
        insertLocalExpense(evolu, companyId, base, []);
        expect(updateLocalExpense(evolu, expenseId, { ...base, delivery_date: null }).ok).toBe(true);
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBeNull();
        updateLocalExpense(evolu, expenseId, { ...base, issue_date: "2026-11-04" });
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBeNull();
        updateLocalExpense(evolu, expenseId, { ...base, delivery_date: "2026-11-02" });
        expect(evoluExpenseToApi(saved() as EvoluExpenseRow).delivery_date).toBe("2026-11-02");
    });
});
