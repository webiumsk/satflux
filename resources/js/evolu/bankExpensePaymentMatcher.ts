import type { Evolu } from "@evolu/common/local-first";
import type { BankPaymentContext } from "./bankCrud";
import { isMovementTransaction, type EvoluBankTransactionRow } from "./bankMap";
import { normalizeVariableSymbol } from "./bankSymbolNormalizer";
import type { EvoluExpenseRow } from "./expenseMap";
import type { InvoicingLocalSchema } from "./schema";
import { resolveMutationOwner } from "./ownerScope";
import { awaitBankMutation, readBankReconciliationContext } from "./bankReconciliation";
import { withExpenseReconciliation, acknowledgeExpenseValidationAbort } from "./expenseReconciliation";

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
        || transaction.matchStatus !== "unmatched" || transaction.businessExpenseId
        || !isMovementTransaction(transaction)
        || ctx.matches.some((match) => match.bankTransactionId === transaction.id)) return null;
    const amount = positiveBankAmount(transaction.amount);
    const currency = bankPaymentCurrency(transaction.currency);
    const paidAt = bankPaymentBookingDate(transaction.bookedAt);
    if (!amount || !currency || !paidAt) return null;
    const candidates = ctx.expenses.filter((expense) => expense.companyId === transaction.companyId
        && expense.status === "recorded" && !expense.paidAt && !expense.cancelledAt
        && !ctx.transactions.some((row) => row.businessExpenseId === expense.id)
        && positiveBankAmount(expense.total) === amount
        && bankPaymentCurrency(expense.currency) === currency
        && matchesExpenseHints(transaction, expense));
    return candidates.length === 1 ? { expense: candidates[0]!, paidAt } : null;
}

/** These bookkeeping updates share Evolu's single synchronous mutation queue. */
export async function tryAutoMatchBankEmailExpense(
    evolu: Evolu<InvoicingLocalSchema>,
    transaction: EvoluBankTransactionRow,
    ctx: BankPaymentContext,
): Promise<boolean> {
    if (transaction.source !== "inbound_email") return false;
    // A stale caller can only decline here. Every payment decision is repeated
    // against worker-fresh rows while the shared writer gate is held.
    if (!findBankEmailExpenseMatch(transaction, ctx)) return false;
    const result = await withExpenseReconciliation(evolu, async (scope) => {
        const fresh = scope.context.transactions.find((row) => row.id === transaction.id && row.companyId === transaction.companyId);
        if (!fresh) return { matched: false };
        const matched = await matchBankEmailExpenseInReconciliation(scope.evolu, fresh, scope.context);
        return { matched, fresh, paid: scope.context.expenses.find((row) => row.id === fresh.businessExpenseId) };
    });
    if (result.matched && result.fresh) {
        Object.assign(transaction, { businessExpenseId: result.fresh.businessExpenseId, matchStatus: result.fresh.matchStatus });
        const callerExpense = ctx.expenses.find((row) => row.id === result.paid?.id);
        if (result.paid && callerExpense) Object.assign(callerExpense, { status: result.paid.status, paidAt: result.paid.paidAt });
    }
    return result.matched;
}

/**
 * Within a live lease, validate one owner partition and queue both bookkeeping writes synchronously.
 * Return false for a known batch rejection; require fresh financial-state proof before publishing context.
 * Uncertain worker outcomes propagate to the enclosing quarantine without compensation.
 */
async function matchBankEmailExpenseInReconciliation(
    evolu: Evolu<InvoicingLocalSchema>, transaction: EvoluBankTransactionRow, ctx: BankPaymentContext,
): Promise<boolean> {
    const match = findBankEmailExpenseMatch(transaction, ctx);
    if (!match) return false;
    const transactionPatch = {
        id: transaction.id, companyId: transaction.companyId,
        businessExpenseId: match.expense.id, matchStatus: "matched" as const,
    };
    const expensePatch = {
        id: match.expense.id, companyId: transaction.companyId,
        status: "paid" as const, paidAt: match.paidAt,
    };
    if (resolveMutationOwner("bankTransaction", transactionPatch, undefined)
        !== resolveMutationOwner("expense", expensePatch, undefined)) return false;
    const transactionOwner = Reflect.get(transaction, "ownerId");
    const expenseOwner = Reflect.get(match.expense, "ownerId");
    if (typeof transactionOwner === "string" && typeof expenseOwner === "string" && transactionOwner !== expenseOwner) return false;
    if (!evolu.update("bankTransaction", transactionPatch, { onlyValidate: true }).ok
        || !evolu.update("expense", expensePatch, { onlyValidate: true }).ok) return false;
    // No await between writes: Evolu aborts the entire microtask batch if either
    // mutation fails validation. Do not publish optimistic context on failure.
    const completed = await awaitBankMutation(evolu, (onComplete) => {
        const linked = evolu.update("bankTransaction", transactionPatch);
        const paid = evolu.update("expense", expensePatch, { onComplete });
        return { ok: linked.ok && paid.ok };
    });
    if (!completed) { acknowledgeExpenseValidationAbort(evolu); return false; }
    const committed = await readBankReconciliationContext(evolu);
    const linked = committed.transactions.find((row) => row.id === transaction.id && row.companyId === transaction.companyId);
    const paid = committed.expenses.find((row) => row.id === match.expense.id && row.companyId === transaction.companyId);
    if (linked?.businessExpenseId !== match.expense.id || linked.matchStatus !== "matched"
        || paid?.status !== "paid" || paid.paidAt !== match.paidAt
        || (["companyId", "amount", "currency", "direction", "recordedDirectionEvidence", "bookedAt", "variableSymbol", "reference", "source"] as const)
            .some((key) => linked[key] !== transaction[key])
        || (["companyId", "total", "currency", "variableSymbol", "externalNumber", "cancelledAt"] as const)
            .some((key) => paid[key] !== match.expense[key])) {
        throw new Error("bank_reconciliation_state_changed");
    }
    transaction.businessExpenseId = match.expense.id;
    transaction.matchStatus = "matched";
    match.expense.status = "paid";
    match.expense.paidAt = match.paidAt;
    return true;
}
