import { reactive } from "vue";
import { defaultIssuePeriodState, resolveIssuePeriodRange, toIsoDate, type IssuePeriodState } from "./useInvoicingIssuePeriod";
import type { ExpenseListFilterOptions } from "../evolu/expenseListFilters";

export type ExpenseAdvancedFilters = {
    due: "all" | "overdue" | "custom";
    dueFrom: string;
    dueTo: string;
    amountMin: string;
    amountMax: string;
    search: string;
};

export function defaultExpenseAdvancedFilters(): ExpenseAdvancedFilters {
    return { due: "all", dueFrom: "", dueTo: "", amountMin: "", amountMax: "", search: "" };
}

export function expenseListFilterParams(filter: string, period: IssuePeriodState, advanced: ExpenseAdvancedFilters): ExpenseListFilterOptions {
    const range = resolveIssuePeriodRange(period);
    return {
        filter: filter === "all" ? undefined : filter,
        issue_from: range.from,
        issue_to: range.to,
        due: advanced.due === "all" ? undefined : advanced.due,
        overdue_before: filter === "overdue" || advanced.due === "overdue" ? toIsoDate(new Date()) : undefined,
        due_from: advanced.due === "custom" ? advanced.dueFrom || undefined : undefined,
        due_to: advanced.due === "custom" ? advanced.dueTo || undefined : undefined,
        amount_min: String(advanced.amountMin).trim() || undefined,
        amount_max: String(advanced.amountMax).trim() || undefined,
        search: advanced.search.trim() || undefined,
    };
}

export function useExpenseListFilters(allYears = false) {
    const issuePeriod = reactive<IssuePeriodState>(allYears
        ? { preset: "all", customFrom: "", customTo: "" } : defaultIssuePeriodState());
    const advancedApplied = reactive(defaultExpenseAdvancedFilters());
    const advancedDraft = reactive(defaultExpenseAdvancedFilters());
    const hasActiveAdvanced = () => advancedApplied.due !== "all" || advancedApplied.amountMin !== ""
        || advancedApplied.amountMax !== "" || advancedApplied.search.trim() !== "";
    const resetAdvancedDraft = () => Object.assign(advancedDraft, advancedApplied);
    function clear() {
        Object.assign(issuePeriod, { preset: "all", customFrom: "", customTo: "" });
        Object.assign(advancedApplied, defaultExpenseAdvancedFilters());
        Object.assign(advancedDraft, defaultExpenseAdvancedFilters());
    }
    return { issuePeriod, advancedApplied, advancedDraft, hasActiveAdvanced, resetAdvancedDraft, clear };
}
