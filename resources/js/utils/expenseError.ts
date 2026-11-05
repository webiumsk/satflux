/** Read only the conventional HTTP validation envelope, without interpreting server prose. */
function serverValidationErrors(error: unknown): Record<string, unknown> | null {
    let current = error;
    for (const key of ["response", "data", "errors"]) {
        if (!current || typeof current !== "object") return null;
        current = (current as Record<string, unknown>)[key];
    }
    return current && typeof current === "object" && !Array.isArray(current)
        ? current as Record<string, unknown> : null;
}

/** Known local or server write outcomes use localized recovery guidance. */
export function expenseMutationErrorKey(error: unknown): string | null {
    const errors = serverValidationErrors(error);
    if (errors && Object.keys(errors).length === 1) {
        const status = errors.status;
        const legacy = errors.expense_linked_payment;
        if ((Array.isArray(status) && status.length === 1 && status[0] === "expense_linked_payment")
            || (Array.isArray(legacy) && legacy.length === 1 && typeof legacy[0] === "string")) {
            return "invoicing.expense_linked_payment";
        }
    }
    if (!(error instanceof Error)) return null;
    switch (error.message) {
        case "bank_expense_claim_changed": return "invoicing.bank_expense_match_failed";
        case "expense_linked_payment": return "invoicing.expense_linked_payment";
        case "expense_reconciliation_lock_unavailable": return "invoicing.expense_changes_unavailable";
        case "expense_reconciliation_owner_unavailable": return "invoicing.expense_owner_unavailable";
        case "bank_reconciliation_wait_timed_out": return "invoicing.expense_changes_busy";
        case "bank_reconciliation_outcome_unknown": return "invoicing.expense_changes_unknown";
        default: return null;
    }
}
