import type { Evolu } from "@evolu/common/local-first";
import type { BankPaymentContext } from "./bankCrud";
import { isMovementTransaction, type EvoluBankTransactionRow } from "./bankMap";
import { normalizeVariableSymbol } from "./bankSymbolNormalizer";
import type { EvoluExpenseRow } from "./expenseMap";
import type { InvoicingLocalSchema } from "./schema";
import { matchBankExpenseInReconciliation } from "./bankExpenseManualMatching";
import { withExpenseReconciliation } from "./expenseReconciliation";

import { bankPaymentBookingDate, bankPaymentCurrency, positiveBankAmount } from "./bankPaymentValues";
export { bankPaymentBookingDate, bankPaymentCurrency, positiveBankAmount } from "./bankPaymentValues";

/**
 * Require valid normalized symbols when supplied and give the bank symbol precedence.
 * Only a missing bank symbol permits an exact full external-number reference match.
 */
function matchesExpenseHints(transaction: EvoluBankTransactionRow, expense: EvoluExpenseRow): boolean {
    const bankVs = transaction.variableSymbol?.trim() ?? "";
    const expenseVs = expense.variableSymbol?.trim() ?? "";
    if (expenseVs && !normalizeVariableSymbol(expenseVs)) return false;
    if (bankVs) {
        const symbol = normalizeVariableSymbol(bankVs);
        if (!symbol) return false;
        return symbol === normalizeVariableSymbol(expenseVs || expense.externalNumber);
    }
    const reference = transaction.reference?.trim();
    return Boolean(reference && expense.externalNumber?.trim() === reference);
}

/**
 * Select exactly one same-company recorded, unpaid expense by exact cents, currency and hints.
 * Decline balances, existing links and unverified new email debits; legacy null evidence remains eligible.
 */
export function findBankEmailExpenseMatch(
    transaction: EvoluBankTransactionRow,
    ctx: BankPaymentContext,
): { expense: EvoluExpenseRow; paidAt: string } | null {
    if (transaction.source !== "inbound_email" || transaction.direction !== "debit"
        || (transaction.recordedDirectionEvidence != null && transaction.recordedDirectionEvidence !== "debit")
        || transaction.matchStatus !== "unmatched" || transaction.businessExpenseId || transaction.bankPaymentClaimJson != null
        || !isMovementTransaction(transaction)
        || ctx.matches.some((match) => match.bankTransactionId === transaction.id)) return null;
    const amount = positiveBankAmount(transaction.amount);
    const currency = bankPaymentCurrency(transaction.currency);
    const paidAt = bankPaymentBookingDate(transaction.bookedAt);
    if (!amount || !currency || !paidAt) return null;
    const candidates = ctx.expenses.filter((expense) => expense.companyId === transaction.companyId
        && expense.status === "recorded" && !expense.paidAt && !expense.cancelledAt && expense.bankPaymentClaimJson == null
        && !ctx.transactions.some((row) => row.businessExpenseId === expense.id)
        && positiveBankAmount(expense.total) === amount
        && bankPaymentCurrency(expense.currency) === currency
        && matchesExpenseHints(transaction, expense));
    return candidates.length === 1 ? { expense: candidates[0]!, paidAt } : null;
}

/** Automatic email matching shares the reciprocal claim writer and origin/persistence proof. */
export async function tryAutoMatchBankEmailExpense(
    evolu: Evolu<InvoicingLocalSchema>, transaction: EvoluBankTransactionRow, ctx: BankPaymentContext,
): Promise<boolean> {
    if (transaction.source !== "inbound_email" || !findBankEmailExpenseMatch(transaction, ctx)) return false;
    const result = await withExpenseReconciliation(evolu, async (scope) => {
        const fresh = scope.context.transactions.find((row) => row.id === transaction.id && row.companyId === transaction.companyId);
        if (!fresh) return null;
        const match = findBankEmailExpenseMatch(fresh, scope.context);
        if (!match) return null;
        await matchBankExpenseInReconciliation(scope, fresh.companyId, fresh.id, match.expense.id, "auto");
        return { transaction: scope.context.transactions.find((row) => row.id === fresh.id),
            expense: scope.context.expenses.find((row) => row.id === match.expense.id) };
    }).catch((error: unknown) => {
        if (error instanceof Error && error.message === "expense_mutation_batch_rejected") return null;
        throw error;
    });
    if (!result?.transaction || !result.expense) return false;
    Object.assign(transaction, { businessExpenseId: result.transaction.businessExpenseId, matchStatus: result.transaction.matchStatus,
        bankPaymentClaimJson: result.transaction.bankPaymentClaimJson });
    const expense = ctx.expenses.find((row) => row.id === result.expense!.id);
    if (expense) expense.bankPaymentClaimJson = result.expense.bankPaymentClaimJson;
    return true;
}
