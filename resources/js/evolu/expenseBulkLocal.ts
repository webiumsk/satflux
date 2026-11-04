import type { Evolu } from "@evolu/common/local-first";
import type { BulkResult } from "./documentBulkLocal";
import { withExpenseReconciliation } from "./expenseReconciliation";
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

/**
 * Recheck selected company targets under the shared writer gate before marking them paid.
 * Skip missing, paid or cancelled rows; return counters only after the batch is verified.
 */
export async function bulkMarkPaidLocalExpenses(
    evolu: Evolu<InvoicingLocalSchema>,
    targets: EvoluExpenseRow[],
): Promise<BulkResult> {
    return withExpenseReconciliation(evolu, (scope) => {
    let processed = 0;
    let skipped = 0;

    for (const target of targets) {
        const row = scope.context.expenses.find((candidate) => candidate.id === target.id && candidate.companyId === target.companyId);
        if (!row || row.status === "paid" || row.status === "cancelled") {
            skipped++;
            continue;
        }
        const result = scope.evolu.update("expense", { id: row.id as ExpenseId, companyId: row.companyId,
            status: "paid", paidAt: new Date().toISOString().slice(0, 10) });
        if (!result.ok) throw new Error("expense_mutation_batch_rejected");
        processed++;
    }

    return { processed, skipped };
    });
}

/**
 * Cancel fresh selected-company targets in one guarded batch, skipping missing or already cancelled rows.
 * A rejected write fails the operation instead of reporting optimistic processed counts.
 */
export async function bulkCancelLocalExpenses(
    evolu: Evolu<InvoicingLocalSchema>,
    targets: EvoluExpenseRow[],
): Promise<BulkResult> {
    return withExpenseReconciliation(evolu, (scope) => {
    let processed = 0;
    let skipped = 0;

    for (const target of targets) {
        const row = scope.context.expenses.find((candidate) => candidate.id === target.id && candidate.companyId === target.companyId);
        if (!row || row.status === "cancelled") {
            skipped++;
            continue;
        }
        const result = scope.evolu.update("expense", { id: row.id as ExpenseId, companyId: row.companyId,
            status: "cancelled", cancelledAt: new Date().toISOString().slice(0, 10) });
        if (!result.ok) throw new Error("expense_mutation_batch_rejected");
        processed++;
    }

    return { processed, skipped };
    });
}
