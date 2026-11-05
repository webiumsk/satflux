import type { Evolu } from "@evolu/common/local-first";
import type { BankPaymentContext } from "./bankCrud";
import { bankPaymentBookingDate, bankPaymentCurrency, positiveBankAmount } from "./bankPaymentValues";
import { activeBankExpenseClaim, bankExpenseClaimReason, bankExpenseEvidenceMatches, createBankExpenseClaim } from "./bankExpenseClaims";
import { isMovementTransaction, type EvoluBankTransactionRow } from "./bankMap";
import { expenseCandidate, type ExpenseCandidate } from "./expenseDuplicateSuggestions";
import { assertExpenseCompanyOwner, commitExpenseMutation, withExpenseReconciliation } from "./expenseReconciliation";
import type { ExpenseReconciliationScope } from "./expenseReconciliation";
import type { InvoicingLocalSchema } from "./schema";

export type BankExpenseMatchSuggestion = {
    expense: ExpenseCandidate;
    reason: "variable_symbol" | "payment_reference";
};

/** A user confirmation cannot promote unverified direction or an account balance into payment evidence. */
function eligibleMovement(transaction: EvoluBankTransactionRow, ctx: BankPaymentContext): boolean {
    return transaction.direction === "debit" && isMovementTransaction(transaction)
        && (transaction.source !== "inbound_email" || transaction.recordedDirectionEvidence == null
            || transaction.recordedDirectionEvidence === "debit")
        && transaction.matchStatus === "unmatched" && !transaction.businessExpenseId && transaction.bankPaymentClaimJson == null
        && !ctx.matches.some((match) => match.bankTransactionId === transaction.id)
        && Boolean(positiveBankAmount(transaction.amount) && bankPaymentCurrency(transaction.currency)
            && bankPaymentBookingDate(transaction.bookedAt));
}

/** Candidate reads are advisory; the same checks run again under the origin writer gate on confirmation. */
export function bankExpenseMatchSuggestions(
    transaction: EvoluBankTransactionRow, ctx: BankPaymentContext, ownerId: string, limit = 20,
): BankExpenseMatchSuggestion[] {
    if (!ownerId || Reflect.get(transaction, "ownerId") !== ownerId || !eligibleMovement(transaction, ctx)) return [];
    return ctx.expenses.filter((expense) => expense.companyId === transaction.companyId
        && Reflect.get(expense, "ownerId") === ownerId
        && expense.status === "recorded" && !expense.paidAt && !expense.cancelledAt && expense.bankPaymentClaimJson == null
        && Reflect.get(expense, "isDeleted") !== 1
        && !ctx.transactions.some((row) => row.businessExpenseId === expense.id)
        && positiveBankAmount(expense.total) === positiveBankAmount(transaction.amount)
        && bankPaymentCurrency(expense.currency) === bankPaymentCurrency(transaction.currency))
        .flatMap((expense) => {
            const reason = bankExpenseClaimReason(transaction, expense);
            return reason ? [{ expense: expenseCandidate(expense), reason }] : [];
        })
        .sort((a, b) => a.expense.internal_number.localeCompare(b.expense.internal_number)
            || a.expense.id.localeCompare(b.expense.id))
        .slice(0, limit);
}

/** Revalidate fresh records and commit reciprocal claims; independent payment fields are never overwritten. */
export async function matchLocalBankTransactionToExpense(
    evolu: Evolu<InvoicingLocalSchema>, companyId: string, transactionId: string, expenseId: string,
): Promise<void> {
    await withExpenseReconciliation(evolu, (scope) => matchBankExpenseInReconciliation(scope, companyId, transactionId, expenseId, "manual"));
}

/** Shared by explicit and automatic matching so offline races cannot bypass canonical claim ownership. */
export async function matchBankExpenseInReconciliation(
    scope: ExpenseReconciliationScope, companyId: string, transactionId: string, expenseId: string, mode: "manual" | "auto",
): Promise<void> {
    assertExpenseCompanyOwner(scope, companyId);
    const transactions = scope.context.transactions.filter((row) => row.id === transactionId && row.companyId === companyId);
    const expenses = scope.context.expenses.filter((row) => row.id === expenseId && row.companyId === companyId);
    if (transactions.length !== 1 || expenses.length !== 1) throw new Error("bank_expense_match_ineligible");
    const transaction = transactions[0]!;
    const expense = expenses[0]!;
    const ownerId = Reflect.get(transaction, "ownerId");
    if (typeof ownerId !== "string" || Reflect.get(expense, "ownerId") !== ownerId) throw new Error("bank_expense_match_ineligible");
    assertExpenseCompanyOwner(scope, companyId, ownerId);
    const existing = activeBankExpenseClaim(transaction, expense, scope.context.matches);
    const legacySame = transaction.bankPaymentClaimJson == null && expense.bankPaymentClaimJson == null
        && transaction.businessExpenseId === expense.id && transaction.matchStatus === "matched" && expense.status === "paid";
    if (existing || legacySame) {
        if (!bankExpenseEvidenceMatches(transaction, expense)
            || scope.context.matches.some((row) => row.bankTransactionId === transaction.id && Reflect.get(row, "ownerId") === ownerId)) {
            throw new Error("bank_expense_match_ineligible");
        }
        return;
    }
    if (!bankExpenseMatchSuggestions(transaction, scope.context, ownerId, Number.MAX_SAFE_INTEGER).some((row) => row.expense.id === expense.id)) {
        throw new Error("bank_expense_match_ineligible");
    }
    const claimJson = createBankExpenseClaim(transaction, expense, mode);
    const linked = { id: transaction.id, companyId: transaction.companyId, businessExpenseId: expense.id,
        matchStatus: "matched" as const, bankPaymentClaimJson: claimJson };
    const claimed = { id: expense.id, companyId: expense.companyId, bankPaymentClaimJson: claimJson };
    if (!scope.evolu.update("bankTransaction", linked, { onlyValidate: true }).ok
        || !scope.evolu.update("expense", claimed, { onlyValidate: true }).ok) throw new Error("expense_mutation_batch_rejected");
    const result = await commitExpenseMutation(scope.evolu, (onComplete) => {
        const link = scope.evolu.update("bankTransaction", linked);
        const claim = scope.evolu.update("expense", claimed, { onComplete });
        return { ok: link.ok && claim.ok };
    });
    if (!result.ok) throw new Error("expense_mutation_batch_rejected");
    assertExpenseCompanyOwner(scope, companyId, ownerId);
    const currentTransactions = scope.context.transactions.filter((row) => row.id === transaction.id && row.companyId === companyId);
    const currentExpenses = scope.context.expenses.filter((row) => row.id === expense.id && row.companyId === companyId);
    const currentTransaction = currentTransactions[0];
    const currentExpense = currentExpenses[0];
    if (currentTransactions.length !== 1 || currentExpenses.length !== 1 || !currentTransaction || !currentExpense
        || !activeBankExpenseClaim(currentTransaction, currentExpense, scope.context.matches)
        || currentTransaction.bankPaymentClaimJson !== claimJson || currentExpense.bankPaymentClaimJson !== claimJson
        || currentExpense.status !== expense.status || currentExpense.paidAt !== expense.paidAt
        || scope.context.matches.some((row) => row.bankTransactionId === transaction.id && Reflect.get(row, "ownerId") === ownerId)) {
        throw new Error("bank_reconciliation_state_changed");
    }
}
