import { beforeEach, describe, expect, it, vi } from "vitest";
import { createIdFromString } from "@evolu/common";
import { CompanyId, ExpenseId } from "@/evolu/schema";
import { filterLocalExpenses, type EvoluExpenseRow } from "@/evolu/expenseMap";
import { expenseFilterCents, validExpenseListFilters, type ExpenseListFilterOptions } from "@/evolu/expenseListFilters";
import { expenseListFilterParams, defaultExpenseAdvancedFilters } from "@/composables/useExpenseListFilters";
import { resolveBulkExpenseTargets, bulkCancelLocalExpenses, bulkMarkPaidLocalExpenses } from "@/evolu/expenseBulkLocal";

const mocks = vi.hoisted(() => ({ gate: vi.fn(), update: vi.fn() }));
vi.mock("@/evolu/expenseReconciliation", () => ({ withExpenseReconciliation: mocks.gate }));
const company = CompanyId.orThrow(createIdFromString("synthetic-filter-company"));
function row(name: string, patch: Partial<EvoluExpenseRow> = {}): EvoluExpenseRow {
    return { id: ExpenseId.orThrow(createIdFromString(name)), companyId: company, status: "recorded", internalNumber: `EXP-${name}`,
        externalNumber: null, title: name, variableSymbol: null, constantSymbol: null, specificSymbol: null,
        issueDate: "2026-01-01", deliveryDate: null, dueDate: "2026-02-01", paidAt: null, cancelledAt: null,
        total: "10.00", currency: "EUR", internalNote: null, ...patch };
}

beforeEach(() => { vi.clearAllMocks(); });

describe("expense list applied predicates", () => {
    it("combines inclusive issue, due, status, exact amount and literal search before bulk selection", () => {
        const selected = row("matched", { externalNumber: "SOURCE-001", variableSymbol: "000123", internalNote: "ŽIAROVKA 10%_!" });
        const candidates = [selected, row("too small", { total: "9.99" }), row("outside", { issueDate: "2025-12-31" }),
            row("paid", { status: "paid" }), row("foreign", { companyId: CompanyId.orThrow(createIdFromString("other")) }),
            row("cancelled", { status: "cancelled" }), row("wildcard impostor", { internalNote: "žiarovka 10xxx" })];
        const filters = { filter: "unpaid", issue_from: "2026-01-01", issue_to: "2026-01-01", due: "custom",
            due_from: "2026-02-01", due_to: "2026-02-01", amount_min: "10.00", amount_max: "10", search: "žiarovka 10%_!" };
        expect(resolveBulkExpenseTargets(company, true, [], candidates, filters)).toEqual([selected]);
        expect(resolveBulkExpenseTargets(company, false, [selected.id, candidates[1].id], candidates, filters)).toEqual([selected]);
    });

    it.each(["internalNumber", "externalNumber", "title", "variableSymbol", "internalNote"] as const)(
        "searches %s case-insensitively without searching hidden attachment content", field => {
            const selected = row("target", { [field]: "Mixed CASE ŤAVA" });
            expect(filterLocalExpenses([selected, row("unrelated")], { search: "case ťava" })).toEqual([selected]);
        });

    it("supports open inclusive issue bounds and year fallback without narrowing explicit ranges", () => {
        const older = row("old", { issueDate: "2025-12-31" });
        const newer = row("new", { issueDate: "2026-01-01" });
        expect(filterLocalExpenses([older, newer], { year: 2026 })).toEqual([newer]);
        expect(filterLocalExpenses([older, newer], { year: 2026, issue_to: "2025-12-31" })).toEqual([older]);
        expect(filterLocalExpenses([older, newer], { year: 2025, issue_from: "2026-01-01" })).toEqual([newer]);
    });

    it("compares decimal cents beyond floating-point precision and includes zero", () => {
        const smaller = row("smaller", { total: "90071992547409.90" });
        const exact = row("exact", { total: "90071992547409.91" });
        expect(filterLocalExpenses([smaller, exact], { amount_min: "90071992547409.91" })).toEqual([exact]);
        expect(filterLocalExpenses([row("zero", { total: "0" }), row("nonzero")], { amount_max: "0.00" })).toHaveLength(1);
        expect(expenseFilterCents("0.01")).toBe(1n);
    });

    it.each<ExpenseListFilterOptions>([
        { issue_from: "2026-02-30" }, { issue_from: "0000-01-01" }, { issue_from: "2026-03-02", issue_to: "2026-03-01" },
        { due: "custom" }, { due: "custom", due_from: "2026-03-02", due_to: "2026-03-01" },
        { amount_min: "Infinity" }, { amount_min: "90071992547409.92" }, { amount_min: "1e309" }, { amount_min: "NaN" }, { amount_min: "-1" },
        { amount_min: "12.345" }, { amount_min: "2", amount_max: "1.99" }, { search: "x".repeat(201) }, { search: "bad\u0000term" },
    ])("fails closed for invalid options %j", options => {
        expect(validExpenseListFilters(options)).toBe(false);
        expect(filterLocalExpenses([row("candidate")], options)).toEqual([]);
    });

    it("serializes only applied due bounds and never embeds search in navigation", () => {
        const applied = { ...defaultExpenseAdvancedFilters(), search: "  original text  ", amountMin: "0", dueFrom: "2020-01-01" };
        expect(expenseListFilterParams("all", { preset: "all", customFrom: "", customTo: "" }, applied))
            .toEqual(expect.objectContaining({ search: "original text", amount_min: "0", due_from: undefined, due_to: undefined }));
    });
});

describe("queued expense bulk scope", () => {
    it.each([bulkMarkPaidLocalExpenses, bulkCancelLocalExpenses])("checks current selection inside the writer gate", async operation => {
        let enter!: () => void;
        const pending = new Promise<void>(resolve => { enter = resolve; });
        const selected = row("target");
        mocks.gate.mockImplementation(async (_evolu, callback) => {
            await pending;
            return callback({ evolu: { update: mocks.update }, context: { expenses: [selected], transactions: [], matches: [] } });
        });
        let current = true;
        const assertCurrent = vi.fn(() => { if (!current) throw new Error("selection_changed"); });
        const result = operation({} as never, [selected], assertCurrent);
        current = false;
        enter();
        await expect(result).rejects.toThrow("selection_changed");
        expect(assertCurrent).toHaveBeenCalledOnce();
        expect(mocks.update).not.toHaveBeenCalled();
    });
});
