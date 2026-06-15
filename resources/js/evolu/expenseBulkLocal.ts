import type { Evolu } from "@evolu/common/local-first";
import type { BulkResult } from "./documentBulkLocal";
import { cancelLocalExpense, markLocalExpensePaid } from "./expenseCrud";
import type { EvoluExpenseRow } from "./expenseMap";
import { filterLocalExpenses, type ExpenseListFilterOptions } from "./expenseMap";
import type { CompanyId, ExpenseId, InvoicingLocalSchema } from "./schema";

export type { BulkResult };

export function resolveBulkExpenseTargets(
    companyId: CompanyId,
    selectAll: boolean,
    selectedIds: Iterable<string>,
    allExpenses: EvoluExpenseRow[],
    filter: ExpenseListFilterOptions,
): EvoluExpenseRow[] {
    const companyRows = allExpenses.filter((row) => row.companyId === companyId);
    const filtered = filterLocalExpenses(companyRows, filter);

    if (selectAll) {
        return filtered;
    }

    const idSet = new Set(selectedIds);
    return filtered.filter((row) => idSet.has(row.id));
}

export function bulkMarkPaidLocalExpenses(
    evolu: Evolu<InvoicingLocalSchema>,
    targets: EvoluExpenseRow[],
): BulkResult {
    let processed = 0;
    let skipped = 0;

    for (const row of targets) {
        if (row.status === "paid" || row.status === "cancelled") {
            skipped++;
            continue;
        }
        markLocalExpensePaid(evolu, row.id as ExpenseId);
        processed++;
    }

    return { processed, skipped };
}

export function bulkCancelLocalExpenses(
    evolu: Evolu<InvoicingLocalSchema>,
    targets: EvoluExpenseRow[],
): BulkResult {
    let processed = 0;
    let skipped = 0;

    for (const row of targets) {
        if (row.status === "cancelled") {
            skipped++;
            continue;
        }
        cancelLocalExpense(evolu, row.id as ExpenseId);
        processed++;
    }

    return { processed, skipped };
}
