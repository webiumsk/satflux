/** Known local write outcomes have recovery guidance; other errors keep their existing UI handling. */
export function expenseMutationErrorKey(error: unknown): string | null {
    if (!(error instanceof Error)) return null;
    switch (error.message) {
        case "expense_reconciliation_lock_unavailable": return "invoicing.expense_changes_unavailable";
        case "expense_reconciliation_owner_unavailable": return "invoicing.expense_owner_unavailable";
        case "bank_reconciliation_wait_timed_out": return "invoicing.expense_changes_busy";
        case "bank_reconciliation_outcome_unknown": return "invoicing.expense_changes_unknown";
        default: return null;
    }
}
