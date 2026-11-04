import { maxLength, NonEmptyString } from "@evolu/common";
import type { Evolu } from "@evolu/common/local-first";
import type { EvoluExpenseRow } from "./expenseMap";
import type { CompanyId, ExpenseId, InvoicingLocalSchema } from "./schema";
import {
    currentExpenseTarget, expenseReconciliationScope, withExpenseReconciliation, type ExpenseReconciliationScope,
} from "./expenseReconciliation";

const Opt16 = maxLength(16)(NonEmptyString);
const Opt32 = maxLength(32)(NonEmptyString);
const Opt64 = maxLength(64)(NonEmptyString);
const Opt255 = maxLength(255)(NonEmptyString);
const Opt4000 = maxLength(4000)(NonEmptyString);
const InternalNumberType = maxLength(64)(NonEmptyString);
const CurrencyType = maxLength(3)(NonEmptyString);

export type ExpenseSavePayload = {
    title?: string | null;
    external_number?: string | null;
    variable_symbol?: string | null;
    constant_symbol?: string | null;
    specific_symbol?: string | null;
    issue_date: string;
    delivery_date?: string | null;
    due_date?: string | null;
    total: number | string;
    currency?: string;
    internal_note?: string | null;
    mark_paid?: boolean;
    paid_at?: string | null;
};

function parseOpt<R>(value: string | null | undefined, type: { from: (value: string) => R }): R | { ok: true; value: null } {
    if (value == null || value.trim() === "") return { ok: true as const, value: null };
    return type.from(value.trim());
}

function formatTotal(value: number | string): string {
    const n = typeof value === "number" ? value : Number(value);
    return Number.isFinite(n) ? n.toFixed(2) : "0.00";
}

export function nextLocalExpenseInternalNumber(
    companyId: CompanyId,
    rows: EvoluExpenseRow[],
): string {
    const year = new Date().getFullYear();
    const prefix = `EXP${year}`;
    let max = 0n;

    for (const row of rows) {
        if (row.companyId !== companyId || !row.internalNumber.startsWith(prefix)) continue;
        const suffix = row.internalNumber.slice(prefix.length);
        if (!/^\d+$/.test(suffix)) continue;
        const n = BigInt(suffix);
        if (n > max) max = n;
    }

    return `${prefix}${String(max + 1n).padStart(4, "0")}`;
}

function mapPayloadFields(payload: ExpenseSavePayload) {
    const title = parseOpt(payload.title ?? null, Opt255);
    if (!title.ok) return title;
    const external = parseOpt(payload.external_number ?? null, Opt64);
    if (!external.ok) return external;
    const vs = parseOpt(payload.variable_symbol ?? null, Opt32);
    if (!vs.ok) return vs;
    const ks = parseOpt(payload.constant_symbol ?? null, Opt16);
    if (!ks.ok) return ks;
    const ss = parseOpt(payload.specific_symbol ?? null, Opt16);
    if (!ss.ok) return ss;
    const issueDate = parseOpt(payload.issue_date, Opt32);
    if (!issueDate.ok || !issueDate.value) return { ok: false as const, error: "issue_date" };
    const deliveryDate = parseOpt(payload.delivery_date === undefined ? payload.issue_date : payload.delivery_date, Opt32);
    if (!deliveryDate.ok) return deliveryDate;
    const dueDate = parseOpt(payload.due_date ?? null, Opt32);
    if (!dueDate.ok) return dueDate;
    const currency = parseOpt(payload.currency || "EUR", CurrencyType);
    if (!currency.ok || !currency.value) return { ok: false as const, error: "currency" };
    const note = parseOpt(payload.internal_note ?? null, Opt4000);
    if (!note.ok) return note;

    return {
        ok: true as const,
        value: {
            title: title.value,
            externalNumber: external.value,
            variableSymbol: vs.value,
            constantSymbol: ks.value,
            specificSymbol: ss.value,
            issueDate: issueDate.value,
            deliveryDate: deliveryDate.value,
            dueDate: dueDate.value,
            total: formatTotal(payload.total),
            currency: currency.value,
            internalNote: note.value,
        },
    };
}

function insertLocalExpenseCore(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    payload: ExpenseSavePayload,
    existingRows: EvoluExpenseRow[],
    options?: { internalNumber?: string; id?: ExpenseId },
) {
    const mapped = mapPayloadFields(payload);
    if (!mapped.ok) return mapped;

    const numberSource = options?.internalNumber?.trim()
        || nextLocalExpenseInternalNumber(companyId, existingRows);
    const internalNumber = InternalNumberType.from(numberSource);
    if (!internalNumber.ok) return internalNumber;

    const markPaid = Boolean(payload.mark_paid);
    const now = new Date().toISOString();
    const row = {
        companyId,
        status: markPaid ? "paid" : "recorded",
        internalNumber: internalNumber.value,
        ...mapped.value,
        paidAt: markPaid ? (payload.paid_at || now.slice(0, 10)) : null,
        cancelledAt: null,
    };

    // A caller-provided id (stable id derived from a server inbox item) makes
    // the import idempotent across devices: the same item lands on the same
    // row everywhere instead of once per device.
    if (options?.id) {
        return evolu.upsert("expense", { id: options.id, ...row });
    }

    return evolu.insert("expense", row);
}

function updateLocalExpenseCore(
    evolu: Evolu<InvoicingLocalSchema>,
    expenseId: ExpenseId,
    payload: ExpenseSavePayload,
    current?: EvoluExpenseRow | null,
) {
    if (current?.status === "cancelled") {
        return { ok: false as const, error: "cancelled" };
    }

    const mapped = mapPayloadFields(payload);
    if (!mapped.ok) return mapped;

    const { deliveryDate, ...fields } = mapped.value;

    return evolu.update("expense", {
        id: expenseId,
        ...fields,
        ...(payload.delivery_date === undefined ? {} : { deliveryDate }),
    });
}

function markLocalExpensePaidCore(
    evolu: Evolu<InvoicingLocalSchema>,
    expenseId: ExpenseId,
) {
    const now = new Date().toISOString().slice(0, 10);
    return evolu.update("expense", {
        id: expenseId,
        status: "paid",
        paidAt: now,
    });
}

function unmarkLocalExpensePaidCore(
    evolu: Evolu<InvoicingLocalSchema>,
    expenseId: ExpenseId,
) {
    return evolu.update("expense", {
        id: expenseId,
        status: "recorded",
        paidAt: null,
    });
}

function cancelLocalExpenseCore(
    evolu: Evolu<InvoicingLocalSchema>,
    expenseId: ExpenseId,
) {
    const now = new Date().toISOString();
    return evolu.update("expense", {
        id: expenseId,
        status: "cancelled",
        cancelledAt: now.slice(0, 10),
    });
}

function duplicateLocalExpenseCore(
    evolu: Evolu<InvoicingLocalSchema>,
    source: EvoluExpenseRow,
    existingRows: EvoluExpenseRow[],
) {
    const internalNumber = InternalNumberType.from(
        nextLocalExpenseInternalNumber(source.companyId, existingRows),
    );
    if (!internalNumber.ok) return internalNumber;

    const today = new Date().toISOString().slice(0, 10);

    return evolu.insert("expense", {
        companyId: source.companyId,
        status: "recorded",
        internalNumber: internalNumber.value,
        externalNumber: source.externalNumber,
        title: source.title,
        variableSymbol: source.variableSymbol,
        constantSymbol: source.constantSymbol,
        specificSymbol: source.specificSymbol,
        issueDate: today,
        deliveryDate: today,
        dueDate: source.dueDate,
        paidAt: null,
        cancelledAt: null,
        total: source.total,
        currency: source.currency,
        internalNote: source.internalNote,
    });
}

/** Synchronous compound writers must hold this runtime-checked live lease. */
export function insertLocalExpenseInReconciliation(
    scope: ExpenseReconciliationScope, companyId: CompanyId, payload: ExpenseSavePayload,
    options?: { internalNumber?: string; id?: ExpenseId },
) {
    if (expenseReconciliationScope(scope.evolu) !== scope) throw new Error("expense_reconciliation_lease_expired");
    if (options?.id) {
        const existing = currentExpenseTarget(scope, options.id);
        if (existing) {
            if (existing.companyId !== companyId || existing.isDeleted === 1) return { ok: false as const, error: "expense_target_changed" };
            return { ok: true as const, value: { id: options.id } };
        }
    }
    return insertLocalExpenseCore(scope.evolu, companyId, payload, scope.context.expenses, options);
}

export async function insertLocalExpense(
    evolu: Evolu<InvoicingLocalSchema>, companyId: CompanyId, payload: ExpenseSavePayload,
    _existingRows: EvoluExpenseRow[], options?: { internalNumber?: string; id?: ExpenseId },
) {
    return withExpenseReconciliation(evolu, (scope) => insertLocalExpenseInReconciliation(scope, companyId, payload, options));
}

export async function updateLocalExpense(
    evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId, payload: ExpenseSavePayload, current?: EvoluExpenseRow | null,
) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1 || (current && fresh.companyId !== current.companyId)) {
            return { ok: false as const, error: "expense_target_changed" };
        }
        return updateLocalExpenseCore(scope.evolu, expenseId, payload, fresh);
    });
}

export async function updateLocalExpenseNote(
    evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId, note: string | null, expectedCompanyId?: string,
) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1 || fresh.status === "cancelled" || (expectedCompanyId && fresh.companyId !== expectedCompanyId)) {
            return { ok: false as const, error: "expense_target_changed" };
        }
        const value = parseOpt(note, Opt4000);
        if (!value.ok) return value;
        return scope.evolu.update("expense", { id: expenseId, companyId: fresh.companyId, internalNote: value.value });
    });
}

export async function markLocalExpensePaid(evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1 || fresh.status === "cancelled") return { ok: false as const, error: "expense_target_changed" };
        return markLocalExpensePaidCore(scope.evolu, expenseId);
    });
}

export async function unmarkLocalExpensePaid(evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1 || fresh.status === "cancelled") return { ok: false as const, error: "expense_target_changed" };
        return unmarkLocalExpensePaidCore(scope.evolu, expenseId);
    });
}

export async function cancelLocalExpense(evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1) return { ok: false as const, error: "expense_target_changed" };
        return cancelLocalExpenseCore(scope.evolu, expenseId);
    });
}

export async function duplicateLocalExpense(
    evolu: Evolu<InvoicingLocalSchema>, source: EvoluExpenseRow, _existingRows: EvoluExpenseRow[],
) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, source.id);
        if (!fresh || fresh.isDeleted === 1 || fresh.companyId !== source.companyId) return { ok: false as const, error: "expense_target_changed" };
        return duplicateLocalExpenseCore(scope.evolu, fresh, scope.context.expenses);
    });
}
