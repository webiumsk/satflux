import type { BankPaymentContext } from "./bankCrud";
import type { EvoluBankTransactionRow, EvoluBankTransactionMatchRow } from "./bankMap";
import type { EvoluExpenseRow } from "./expenseMap";
import { bankPaymentBookingDate, bankPaymentCurrency, positiveBankAmount } from "./bankPaymentValues";
import { resolvedBankDirection } from "./bankDirectionGuesser";
import { normalizeVariableSymbol } from "./bankSymbolNormalizer";

export type BankExpenseClaim = {
    v: 1;
    state: "linked" | "released";
    token: string;
    transactionId: string;
    expenseId: string;
    paidIntent: boolean;
    paidAt: string | null;
    bookedAt: string | null;
    amount: string | null;
    currency: string | null;
    direction: "credit" | "debit";
    total: string;
    expenseCurrency: string;
    bankVs: string | null;
    reference: string | null;
    counterpartyName: string | null;
    expenseVs: string | null;
    expenseNumber: string | null;
    source: string;
    directionEvidence: string | null;
    mode: "manual" | "auto" | "create";
};

const required = ["token", "transactionId", "expenseId", "total", "expenseCurrency", "source"] as const;
const optional = ["paidAt", "bookedAt", "amount", "currency", "bankVs", "reference", "counterpartyName", "expenseVs", "expenseNumber", "directionEvidence"] as const;

export type BankExpensePaymentIndex = {
    readonly transactions: readonly EvoluBankTransactionRow[];
    readonly matches: readonly EvoluBankTransactionMatchRow[];
    readonly transactionsByScope: ReadonlyMap<string, readonly EvoluBankTransactionRow[]>;
    readonly invoiceOwnersByTransaction: ReadonlyMap<string, ReadonlySet<unknown>>;
};

/** Encode actual row scope without allowing equal IDs in different owners or companies to collide. */
export function bankExpenseScopeKey(ownerId: unknown, companyId: string, id: string): string {
    return JSON.stringify([typeof ownerId, ownerId, companyId, id]);
}

/** Index one immutable query snapshot, retaining duplicate transaction buckets and unowned invoice reservations. */
export function createBankExpensePaymentIndex(
    transactions: readonly EvoluBankTransactionRow[], matches: readonly EvoluBankTransactionMatchRow[] = [],
): BankExpensePaymentIndex {
    const transactionsByScope = new Map<string, EvoluBankTransactionRow[]>();
    for (const row of transactions) {
        const key = bankExpenseScopeKey(Reflect.get(row, "ownerId"), row.companyId, row.id);
        const bucket = transactionsByScope.get(key);
        if (bucket) bucket.push(row);
        else transactionsByScope.set(key, [row]);
    }
    const invoiceOwnersByTransaction = new Map<string, Set<unknown>>();
    for (const row of matches) {
        if (Reflect.get(row, "isDeleted") === 1) continue;
        const owners = invoiceOwnersByTransaction.get(row.bankTransactionId) ?? new Set<unknown>();
        owners.add(Reflect.get(row, "ownerId"));
        invoiceOwnersByTransaction.set(row.bankTransactionId, owners);
    }
    return { transactions, matches, transactionsByScope, invoiceOwnersByTransaction };
}

/** A live invoice reservation blocks the same owner, while legacy unowned reservations block every owner. */
function hasInvoiceReservation(
    transaction: EvoluBankTransactionRow, matches: readonly EvoluBankTransactionMatchRow[], index?: BankExpensePaymentIndex,
): boolean {
    const ownerId = Reflect.get(transaction, "ownerId");
    if (index?.matches === matches) {
        const owners = index.invoiceOwnersByTransaction.get(transaction.id);
        return Boolean(owners && (owners.has(null) || owners.has(undefined) || owners.has(ownerId)));
    }
    return matches.some((row) => row.bankTransactionId === transaction.id && Reflect.get(row, "isDeleted") !== 1
        && (Reflect.get(row, "ownerId") == null || Reflect.get(row, "ownerId") === ownerId));
}

/** Unknown, malformed and partial cells are conflicts, never legacy associations. */
export function parseBankExpenseClaim(value: string | null | undefined): BankExpenseClaim | null {
    if (!value || value.length > 32768) return null;
    try {
        const claim: unknown = JSON.parse(value);
        if (!claim || typeof claim !== "object" || Array.isArray(claim)) return null;
        const row = claim as Record<string, unknown>;
        if (row.v !== 1 || !["linked", "released"].includes(String(row.state)) || !["manual", "auto", "create"].includes(String(row.mode))
            || !["credit", "debit"].includes(String(row.direction)) || typeof row.paidIntent !== "boolean"
            || (row.paidIntent ? typeof row.paidAt !== "string" || row.paidAt.length !== 10 || bankPaymentBookingDate(row.paidAt) !== row.paidAt : row.paidAt !== null)
            || !["csv", "camt053", "inbound_email", "manual", "wise"].includes(String(row.source))
            || typeof row.token !== "string" || !/^[A-Za-z0-9-]{16,128}$/.test(row.token)
            || required.some((key) => typeof row[key] !== "string" || row[key] === "")
            || optional.some((key) => row[key] !== null && typeof row[key] !== "string")) return null;
        return row as BankExpenseClaim;
    } catch { return null; }
}

/** Strong hints are identical to existing bank matching; a supplied bank VS has precedence. */
export function bankExpenseClaimReason(transaction: EvoluBankTransactionRow, expense: EvoluExpenseRow): "variable_symbol" | "payment_reference" | null {
    const bankVs = transaction.variableSymbol?.trim();
    const expenseVs = expense.variableSymbol?.trim();
    if (expenseVs && !normalizeVariableSymbol(expenseVs)) return null;
    if (bankVs) {
        const symbol = normalizeVariableSymbol(bankVs);
        return symbol && symbol === normalizeVariableSymbol(expenseVs || expense.externalNumber) ? "variable_symbol" : null;
    }
    const reference = transaction.reference?.trim();
    return reference && expense.externalNumber?.trim() === reference ? "payment_reference" : null;
}

/** A new payment association needs verified outgoing movement, exact cents/currency and full hints. */
export function bankExpenseEvidenceMatches(transaction: EvoluBankTransactionRow, expense: EvoluExpenseRow, mode: BankExpenseClaim["mode"] = "manual"): boolean {
    const owner = Reflect.get(transaction, "ownerId");
    const amount = positiveBankAmount(transaction.amount);
    const currency = bankPaymentCurrency(transaction.currency);
    const bound = typeof owner === "string" && owner !== "" && Reflect.get(expense, "ownerId") === owner
        && transaction.companyId === expense.companyId && Boolean(transaction.companyId)
        && Reflect.get(transaction, "isDeleted") !== 1 && Reflect.get(expense, "isDeleted") !== 1
        && ["recorded", "paid"].includes(expense.status) && !expense.cancelledAt;
    // CREATE is the existing explicit bookkeeping decision, including user-confirmed draft overrides.
    // Its provenance must never relax strict existing-expense manual/automatic payment matching.
    if (mode === "create") return bound && resolvedBankDirection(transaction) === "debit";
    return bound && transaction.direction === "debit"
        && (transaction.source !== "inbound_email" || transaction.recordedDirectionEvidence == null || transaction.recordedDirectionEvidence === "debit")
        && ![transaction.reference, transaction.counterpartyName].some((text) => text && /stav na (?:ucte|účte)/i.test(text))
        && Boolean(amount && currency && amount === positiveBankAmount(expense.total)
            && currency === bankPaymentCurrency(expense.currency) && bankPaymentBookingDate(transaction.bookedAt)
            && bankExpenseClaimReason(transaction, expense))
        && ["recorded", "paid"].includes(expense.status) && !expense.cancelledAt;
}

/** One identical cell on each side makes both one-to-many race directions converge without a coordinator. */
export function createBankExpenseClaim(
    transaction: EvoluBankTransactionRow, expense: EvoluExpenseRow, mode: BankExpenseClaim["mode"],
    createIntent?: { paid: boolean; paidAt: string | null },
): string {
    if (!bankExpenseEvidenceMatches(transaction, expense, mode)) throw new Error("bank_expense_match_ineligible");
    const claim: BankExpenseClaim = {
        v: 1, state: "linked", token: crypto.randomUUID(), transactionId: transaction.id, expenseId: expense.id,
        paidIntent: mode === "create" ? createIntent?.paid === true : true,
        paidAt: mode === "create" ? createIntent?.paidAt ?? null : bankPaymentBookingDate(transaction.bookedAt)!,
        bookedAt: transaction.bookedAt ?? null, amount: transaction.amount ?? null, currency: transaction.currency ?? null,
        direction: transaction.direction, total: expense.total!, expenseCurrency: expense.currency!,
        bankVs: transaction.variableSymbol ?? null, reference: transaction.reference ?? null,
        counterpartyName: transaction.counterpartyName ?? null, expenseVs: expense.variableSymbol ?? null,
        expenseNumber: expense.externalNumber ?? null, source: transaction.source,
        directionEvidence: transaction.recordedDirectionEvidence ?? null, mode,
    };
    const json = JSON.stringify(claim);
    if (!parseBankExpenseClaim(json)) throw new Error("bank_expense_match_ineligible");
    return json;
}

/** Canonical association is reciprocal and current; raw status/date remain independent payment history. */
export function activeBankExpenseClaim(
    transaction: EvoluBankTransactionRow, expense: EvoluExpenseRow, matches: readonly EvoluBankTransactionMatchRow[] = [],
    index?: BankExpensePaymentIndex,
): BankExpenseClaim | null {
    if (!transaction.bankPaymentClaimJson || transaction.bankPaymentClaimJson !== expense.bankPaymentClaimJson
        || transaction.businessExpenseId !== expense.id || transaction.matchStatus !== "matched"
        || hasInvoiceReservation(transaction, matches, index)) return null;
    const claim = parseBankExpenseClaim(transaction.bankPaymentClaimJson);
    if (!claim || claim.state !== "linked" || claim.transactionId !== transaction.id || claim.expenseId !== expense.id
        || !bankExpenseEvidenceMatches(transaction, expense, claim.mode)
        || (claim.mode !== "create" && (!claim.paidIntent || claim.paidAt !== bankPaymentBookingDate(transaction.bookedAt)))) return null;
    const evidence: Record<string, unknown> = {
        bookedAt: transaction.bookedAt ?? null, amount: transaction.amount ?? null, currency: transaction.currency ?? null,
        direction: transaction.direction,
        total: expense.total, expenseCurrency: expense.currency, bankVs: transaction.variableSymbol ?? null,
        reference: transaction.reference ?? null, counterpartyName: transaction.counterpartyName ?? null,
        expenseVs: expense.variableSymbol ?? null, expenseNumber: expense.externalNumber ?? null,
        source: transaction.source, directionEvidence: transaction.recordedDirectionEvidence ?? null,
    };
    return Object.entries(evidence).every(([key, value]) => Reflect.get(claim, key) === value) ? claim : null;
}

/** An explicit winning unlink preserves paid history as a receipt, without overwriting independent status/date cells. */
export function releasedBankExpenseReceipt(expense: EvoluExpenseRow): BankExpenseClaim | null {
    const claim = parseBankExpenseClaim(expense.bankPaymentClaimJson);
    const ownerId = Reflect.get(expense, "ownerId");
    if (!claim || !claim.paidIntent || claim.state !== "released" || claim.expenseId !== expense.id || typeof ownerId !== "string" || ownerId === "") return null;
    const transaction = {
        id: claim.transactionId, companyId: expense.companyId, ownerId, direction: claim.direction, source: claim.source,
        bookedAt: claim.bookedAt, amount: claim.amount, currency: claim.currency, variableSymbol: claim.bankVs,
        reference: claim.reference, counterpartyName: claim.counterpartyName, recordedDirectionEvidence: claim.directionEvidence,
    } as unknown as EvoluBankTransactionRow;
    const original = { ...expense, status: "recorded", cancelledAt: null, total: claim.total, currency: claim.expenseCurrency,
        variableSymbol: claim.expenseVs, externalNumber: claim.expenseNumber };
    return bankExpenseEvidenceMatches(transaction, original, claim.mode)
        && (claim.mode === "create" || claim.paidAt === bankPaymentBookingDate(claim.bookedAt)) ? claim : null;
}

/** Presentation projection is never written to backups, mutations or owner-copy maintenance. */
export function effectiveExpensePayment(
    expense: EvoluExpenseRow, transactions: readonly EvoluBankTransactionRow[], matches: readonly EvoluBankTransactionMatchRow[] = [],
    index?: BankExpensePaymentIndex,
): EvoluExpenseRow {
    if (expense.bankPaymentClaimJson == null) return expense;
    const selected = parseBankExpenseClaim(expense.bankPaymentClaimJson);
    const candidates = !selected ? [] : index?.transactions === transactions
        ? index.transactionsByScope.get(bankExpenseScopeKey(Reflect.get(expense, "ownerId"), expense.companyId, selected.transactionId)) ?? []
        : transactions.filter((row) => row.id === selected.transactionId
            && row.companyId === expense.companyId && Reflect.get(row, "ownerId") === Reflect.get(expense, "ownerId"));
    const receipt = releasedBankExpenseReceipt(expense);
    const claim = receipt ?? (candidates.length === 1 ? activeBankExpenseClaim(candidates[0]!, expense, matches, index) : null);
    return {
        ...expense, bankMatchConflict: claim ? 0 : 1,
        ...(claim?.paidIntent && expense.status === "recorded" && !expense.paidAt ? { status: "paid", paidAt: claim.paidAt } : {}),
    };
}

/** Project a presentation/accounting snapshot with one bank index; raw storage and maintenance rows remain unchanged. */
export function projectExpensePayments(
    expenses: readonly EvoluExpenseRow[], transactions: readonly EvoluBankTransactionRow[], matches: readonly EvoluBankTransactionMatchRow[] = [],
): EvoluExpenseRow[] {
    const index = createBankExpensePaymentIndex(transactions, matches);
    return expenses.map((expense) => effectiveExpensePayment(expense, transactions, matches, index));
}

/** Claims, including losing/invalid cells, reserve the record until explicit review/unlink. */
export function hasExpenseBankAssociation(expense: EvoluExpenseRow, context: BankPaymentContext): boolean {
    return (expense.bankPaymentClaimJson != null && !releasedBankExpenseReceipt(expense)) || context.transactions.some((row) => row.businessExpenseId === expense.id
        && row.companyId === expense.companyId && Reflect.get(row, "ownerId") === Reflect.get(expense, "ownerId"));
}
