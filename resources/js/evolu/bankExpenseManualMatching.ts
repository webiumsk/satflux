import type { Evolu } from "@evolu/common/local-first";
import type { BankPaymentContext } from "./bankCrud";
import { bankPaymentBookingDate, bankPaymentCurrency, positiveBankAmount } from "./bankPaymentValues";
import { isMovementTransaction, type EvoluBankTransactionRow } from "./bankMap";
import { normalizeVariableSymbol } from "./bankSymbolNormalizer";
import { expenseCandidate, type ExpenseCandidate } from "./expenseDuplicateSuggestions";
import type { EvoluExpenseRow } from "./expenseMap";
import { assertExpenseCompanyOwner, commitExpenseMutation, withExpenseReconciliation } from "./expenseReconciliation";
import type { InvoicingLocalSchema } from "./schema";

export type BankExpenseMatchSuggestion = {
    expense: ExpenseCandidate;
    reason: "variable_symbol" | "payment_reference";
};

/** Use a strong full reference; a supplied bank VS always takes precedence over free-text references. */
function matchReason(transaction: EvoluBankTransactionRow, expense: EvoluExpenseRow): BankExpenseMatchSuggestion["reason"] | null {
    const bankVs = transaction.variableSymbol?.trim();
    const expenseVs = expense.variableSymbol?.trim();
    if (expenseVs && !normalizeVariableSymbol(expenseVs)) return null;
    if (bankVs) {
        const symbol = normalizeVariableSymbol(bankVs);
        return symbol && symbol === normalizeVariableSymbol(expenseVs || expense.externalNumber)
            ? "variable_symbol" : null;
    }
    const reference = transaction.reference?.trim();
    return reference && expense.externalNumber?.trim() === reference ? "payment_reference" : null;
}

/** A user confirmation cannot promote unverified direction or an account balance into payment evidence. */
function eligibleMovement(transaction: EvoluBankTransactionRow, ctx: BankPaymentContext): boolean {
    return transaction.direction === "debit" && isMovementTransaction(transaction)
        && (transaction.source !== "inbound_email" || transaction.recordedDirectionEvidence == null
            || transaction.recordedDirectionEvidence === "debit")
        && transaction.matchStatus === "unmatched" && !transaction.businessExpenseId
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
        && expense.status === "recorded" && !expense.paidAt && !expense.cancelledAt
        && Reflect.get(expense, "isDeleted") !== 1
        && !ctx.transactions.some((row) => row.businessExpenseId === expense.id)
        && positiveBankAmount(expense.total) === positiveBankAmount(transaction.amount)
        && bankPaymentCurrency(expense.currency) === bankPaymentCurrency(transaction.currency))
        .flatMap((expense) => {
            const reason = matchReason(transaction, expense);
            return reason ? [{ expense: expenseCandidate(expense), reason }] : [];
        })
        .sort((a, b) => a.expense.internal_number.localeCompare(b.expense.internal_number)
            || a.expense.id.localeCompare(b.expense.id))
        .slice(0, limit);
}

/** Revalidate fresh records, queue both financial writes together and prove their persistence before returning. */
export async function matchLocalBankTransactionToExpense(
    evolu: Evolu<InvoicingLocalSchema>, companyId: string, transactionId: string, expenseId: string,
): Promise<void> {
    await withExpenseReconciliation(evolu, async (scope) => {
        assertExpenseCompanyOwner(scope, companyId);
        const transactions = scope.context.transactions.filter((row) => row.id === transactionId && row.companyId === companyId);
        const expenses = scope.context.expenses.filter((row) => row.id === expenseId && row.companyId === companyId);
        if (transactions.length !== 1 || expenses.length !== 1) throw new Error("bank_expense_match_ineligible");
        const transaction = transactions[0]!;
        const expense = expenses[0]!;
        const ownerId = Reflect.get(transaction, "ownerId");
        if (typeof ownerId !== "string" || Reflect.get(expense, "ownerId") !== ownerId) throw new Error("bank_expense_match_ineligible");
        assertExpenseCompanyOwner(scope, companyId, ownerId);
        const sameLink = transaction.businessExpenseId === expense.id && transaction.matchStatus === "matched"
            && expense.status === "paid";
        // A retry may reuse its own established link, but still revalidates all current financial evidence.
        const candidateTransaction = sameLink ? { ...transaction, ownerId, businessExpenseId: null, matchStatus: "unmatched" as const } : transaction;
        const candidateContext = sameLink ? {
            ...scope.context,
            transactions: scope.context.transactions.filter((row) => row !== transaction),
            expenses: [{ ...expense, ownerId, status: "recorded", paidAt: null }],
        } : scope.context;
        const candidate = bankExpenseMatchSuggestions(candidateTransaction, candidateContext, ownerId, Number.MAX_SAFE_INTEGER)
            .find((row) => row.expense.id === expense.id);
        if (!candidate) throw new Error("bank_expense_match_ineligible");
        if (sameLink) return;
        const paidAt = bankPaymentBookingDate(transaction.bookedAt)!;
        const linked = { id: transaction.id, companyId: transaction.companyId, businessExpenseId: expense.id, matchStatus: "matched" as const };
        const paid = { id: expense.id, companyId: expense.companyId, status: "paid" as const, paidAt };
        if (!scope.evolu.update("bankTransaction", linked, { onlyValidate: true }).ok
            || !scope.evolu.update("expense", paid, { onlyValidate: true }).ok) throw new Error("expense_mutation_batch_rejected");
        const result = await commitExpenseMutation(scope.evolu, (onComplete) => {
            const link = scope.evolu.update("bankTransaction", linked);
            const payment = scope.evolu.update("expense", paid, { onComplete });
            return { ok: link.ok && payment.ok };
        });
        if (!result.ok) throw new Error("expense_mutation_batch_rejected");
        assertExpenseCompanyOwner(scope, companyId, ownerId);
        // A remote sync can change evidence while this origin holds its local writer gate.
        // Verify the post-handler rows, without compensating a possibly committed payment.
        const currentTransactions = scope.context.transactions.filter((row) => row.id === transaction.id && row.companyId === companyId);
        const currentExpenses = scope.context.expenses.filter((row) => row.id === expense.id && row.companyId === companyId);
        const currentTransaction = currentTransactions[0];
        const currentExpense = currentExpenses[0];
        if (currentTransactions.length !== 1 || currentExpenses.length !== 1 || !currentTransaction || !currentExpense) {
            throw new Error('bank_reconciliation_state_changed');
        }
        if (Reflect.get(currentTransaction, 'ownerId') !== ownerId || Reflect.get(currentExpense, 'ownerId') !== ownerId
            || currentTransaction?.businessExpenseId !== expense.id || currentTransaction.matchStatus !== 'matched'
            || currentExpense?.status !== 'paid' || currentExpense.paidAt !== paidAt
            || (["companyId", "amount", "currency", "direction", "recordedDirectionEvidence", "bookedAt", "variableSymbol", "reference", "source"] as const)
                .some((key) => currentTransaction[key] !== transaction[key])
            || (["companyId", "total", "currency", "variableSymbol", "externalNumber", "cancelledAt"] as const)
                .some((key) => currentExpense[key] !== expense[key])
            || scope.context.matches.some((match) => match.bankTransactionId === transaction.id)
            || scope.context.transactions.some((row) => row.id !== transaction.id && row.businessExpenseId === expense.id)) {
            throw new Error('bank_reconciliation_state_changed');
        }
    });
}
