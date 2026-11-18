import { effectiveExpensePayment, hasExpenseBankAssociation } from "./bankExpenseClaims";
import { bankPaymentCurrency } from "./bankPaymentValues";
import { booleanToSqliteBoolean, maxLength, NonEmptyString } from "@evolu/common";
import { normalizePaymentIban } from "@/utils/paymentIban";
import { expensePaymentConfirmationTuple, expensePaymentReviewRequired, hasExpensePaymentEvidence } from "@/utils/expensePaymentReview";
import type { Evolu } from "@evolu/common/local-first";
import type { EvoluExpenseRow } from "./expenseMap";
import { ContactId, type CompanyId, type ExpenseId, type InvoicingLocalSchema } from "./schema";
import {
    assertExpenseCompanyOwner, assertExpenseContact, hasLiveExpenseContact, currentExpenseTarget, expenseReconciliationScope, withExpenseReconciliation, type ExpenseReconciliationScope,
} from "./expenseReconciliation";

const Opt16 = maxLength(16)(NonEmptyString);
const Opt32 = maxLength(32)(NonEmptyString);
const Opt64 = maxLength(64)(NonEmptyString);
const Opt255 = maxLength(255)(NonEmptyString);
const Opt4000 = maxLength(4000)(NonEmptyString);
const InternalNumberType = maxLength(64)(NonEmptyString);
const CurrencyType = maxLength(3)(NonEmptyString);

export type ExpenseSavePayload = {
    payment_details_confirmation?: string | null;
    company_contact_id?: string | null;
    supplier_name?: string | null;
    supplier_iban?: string | null;
    payment_review_required?: boolean | null;
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

/** Edit equivalence also covers explicit CREATE bookkeeping overrides; bank matching stays positive-only. */
function canonicalExpenseCents(value: string | null | undefined): string | null {
    const parts = /^([+-]?)(\d+)(?:\.(\d{1,2}))?$/.exec(value?.trim() ?? "");
    if (!parts) return null;
    const cents = `${parts[2]}${(parts[3] ?? "").padEnd(2, "0")}`.replace(/^0+/, "") || "0";
    return parts[1] === "-" && cents !== "0" ? `-${cents}` : cents;
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

/**
 * Validate API-shaped fields and map them to Evolu column names.
 * An omitted delivery value maps to the issue date; update callers decide whether omission should be written.
 */
export function mapLocalExpensePayloadFields(payload: ExpenseSavePayload) {
    const contact = parseOpt(payload.company_contact_id, ContactId);
    if (!contact.ok) return contact;
    const supplier = parseOpt(payload.supplier_name, Opt255);
    if (!supplier.ok) return supplier;
    const rawIban = payload.supplier_iban?.trim();
    const iban = rawIban ? normalizePaymentIban(rawIban) : null;
    if (rawIban && !iban) return { ok: false as const, error: "supplier_iban" };
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
            companyContactId: contact.value,
            supplierName: supplier.value,
            supplierIban: iban,
            paymentReviewRequired: booleanToSqliteBoolean(expensePaymentReviewRequired(payload.payment_review_required, payload.internal_note)),
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

/**
 * Build the expense using the caller's already-fresh numbering context.
 * Stable IDs use upsert; the guarded caller must first exclude existing or deleted targets.
 */
function insertLocalExpenseCore(
    evolu: Evolu<InvoicingLocalSchema>,
    companyId: CompanyId,
    payload: ExpenseSavePayload,
    existingRows: EvoluExpenseRow[],
    options?: { internalNumber?: string; id?: ExpenseId; bankPaymentClaimJson?: string },
) {
    if (payload.company_contact_id) assertExpenseContact(expenseReconciliationScope(evolu), companyId, payload.company_contact_id);
    const mapped = mapLocalExpensePayloadFields(payload);
    if (!mapped.ok) return mapped;

    const numberSource = options?.internalNumber?.trim()
        || nextLocalExpenseInternalNumber(companyId, existingRows);
    const internalNumber = InternalNumberType.from(numberSource);
    if (!internalNumber.ok) return internalNumber;

    const markPaid = Boolean(payload.mark_paid);
    if (payload.payment_details_confirmation != null) {
        const tuple = expensePaymentConfirmationTuple(payload);
        if (!tuple || payload.payment_details_confirmation !== tuple || markPaid || payload.paid_at
            || options?.bankPaymentClaimJson || hasExpensePaymentEvidence(mapped.value.internalNote)) {
            throw new Error("expense_payment_details_confirmation");
        }
        mapped.value.paymentReviewRequired = booleanToSqliteBoolean(false);
    }
    const now = new Date().toISOString();
    const row = {
        companyId,
        bankPaymentClaimJson: options?.bankPaymentClaimJson ?? null,
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

/**
 * Reject cancelled targets or invalid fields before writing the supplied expense values.
 * Omitted delivery dates are not written, preserving saved overrides and explicit nulls.
 */
function updateLocalExpenseCore(
    evolu: Evolu<InvoicingLocalSchema>,
    expenseId: ExpenseId,
    payload: ExpenseSavePayload,
    current?: EvoluExpenseRow | null,
) {
    if (current?.status === "cancelled") {
        return { ok: false as const, error: "cancelled" };
    }

    const mapped = mapLocalExpensePayloadFields(payload);
    if (!mapped.ok) return mapped;

    const { deliveryDate, ...fields } = mapped.value;
    if (payload.company_contact_id && current) assertExpenseContact(expenseReconciliationScope(evolu), current.companyId, payload.company_contact_id);
    for (const [input, column] of [["company_contact_id", "companyContactId"], ["supplier_name", "supplierName"], ["supplier_iban", "supplierIban"]] as const) {
        if (payload[input] === undefined) Reflect.deleteProperty(fields, column);
    }
    fields.paymentReviewRequired = booleanToSqliteBoolean(
        expensePaymentReviewRequired(current?.paymentReviewRequired, current?.internalNote)
        || (payload.payment_review_required != null && payload.payment_review_required),
    );
    if (payload.payment_details_confirmation != null) {
        const scope = expenseReconciliationScope(evolu);
        const effective = current && effectiveExpensePayment(current, scope.context.transactions, scope.context.matches);
        const tuple = expensePaymentConfirmationTuple({
            supplier_name: "supplierName" in fields ? fields.supplierName : current?.supplierName,
            supplier_iban: "supplierIban" in fields ? fields.supplierIban : current?.supplierIban,
            total: payload.total, currency: fields.currency, variable_symbol: fields.variableSymbol,
        });
        if (!current || !effective || !tuple || payload.payment_details_confirmation !== tuple
            || current.status !== "recorded" || effective.status !== "recorded" || current.paidAt || effective.paidAt
            || current.cancelledAt || effective.bankMatchConflict || payload.mark_paid || payload.paid_at
            || hasExpenseBankAssociation(current, scope.context)
            || hasExpensePaymentEvidence(current.internalNote) || hasExpensePaymentEvidence(fields.internalNote)) {
            throw new Error("expense_payment_details_confirmation");
        }
        assertExpenseCompanyOwner(scope, current.companyId, Reflect.get(current, "ownerId"));
        fields.paymentReviewRequired = booleanToSqliteBoolean(false);
    }
    if (current && hasExpenseBankAssociation(current, expenseReconciliationScope(evolu).context)) {
        const beforeAmount = canonicalExpenseCents(current.total);
        const afterAmount = canonicalExpenseCents(fields.total);
        if (!beforeAmount || !afterAmount || beforeAmount !== afterAmount
            || bankPaymentCurrency(current.currency) !== bankPaymentCurrency(fields.currency)) {
            throw new Error("expense_linked_payment");
        }
        // Canonically equal edits preserve the exact claimed evidence rather than rewriting its formatting.
        fields.total = current.total!;
        fields.currency = current.currency as typeof fields.currency;
    }

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
        bankPaymentClaimJson: null,
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

/**
 * Create a recorded, unpaid copy with a new local number and today's issue/delivery dates.
 * Retain the source reference, amounts, currency and other copied fields.
 */
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
        companyContactId: source.companyContactId ?? null,
        supplierName: source.supplierName ?? null,
        supplierIban: source.supplierIban ?? null,
        paymentReviewRequired: booleanToSqliteBoolean(expensePaymentReviewRequired(source.paymentReviewRequired, source.internalNote)),
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
    options?: { internalNumber?: string; id?: ExpenseId; bankPaymentClaimJson?: string },
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
    _existingRows: EvoluExpenseRow[], options?: { internalNumber?: string; id?: ExpenseId; bankPaymentClaimJson?: string },
) {
    return withExpenseReconciliation(evolu, (scope) => insertLocalExpenseInReconciliation(scope, companyId, payload, options), undefined, payload.company_contact_id ? ["allContactsQuery"] : []);
}

// Fields used to build the expense text-PDF review, including the saved payment state.
const reviewedExpenseFields = [
    "companyContactId", "supplierName", "supplierIban", "paymentReviewRequired", "title", "externalNumber",
    "variableSymbol", "constantSymbol", "specificSymbol", "issueDate", "deliveryDate", "dueDate",
    "total", "currency", "internalNote",
] as const satisfies readonly (keyof EvoluExpenseRow)[];

/**
 * Update a fresh live expense under the required origin gate, retaining the caller's expected company binding.
 * Reject missing/deleted/cancelled targets or a provided company mismatch, then prove persistence before returning the Result.
 */
export async function updateLocalExpense(
    evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId, payload: ExpenseSavePayload,
    expected?: EvoluExpenseRow | { reviewBaseline: Readonly<EvoluExpenseRow> } | null,
) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        const isReview = expected && "reviewBaseline" in expected;
        const current = isReview ? expected.reviewBaseline : expected;
        if (!fresh || fresh.isDeleted === 1 || (current && fresh.companyId !== current.companyId)) {
            return { ok: false as const, error: "expense_target_changed" };
        }
        if (isReview && (expected.reviewBaseline.id !== fresh.id
            || reviewedExpenseFields.some(field => (expected.reviewBaseline[field] ?? null) !== (fresh[field] ?? null)))) {
            return { ok: false as const, error: "expense_review_changed" };
        }
        return updateLocalExpenseCore(scope.evolu, expenseId, payload, fresh);
    }, undefined, payload.company_contact_id ? ["allContactsQuery"] : []);
}

/**
 * Save note-only intent after fresh live, company and cancellation checks.
 * Never replay cached financial fields while a concurrent bank reconciliation may have changed them.
 */
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
        return scope.evolu.update("expense", { id: expenseId, companyId: fresh.companyId, internalNote: value.value,
            paymentReviewRequired: booleanToSqliteBoolean(expensePaymentReviewRequired(fresh.paymentReviewRequired, fresh.internalNote)),
        });
    });
}

/**
 * Mark a fresh live, noncancelled expense paid using today's date.
 * Await the origin gate and persistence proof before returning the mutation Result.
 */
export async function markLocalExpensePaid(evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1 || fresh.status === "cancelled") return { ok: false as const, error: "expense_target_changed" };
        if (fresh.bankPaymentClaimJson != null && effectiveExpensePayment(fresh, scope.context.transactions, scope.context.matches).status === "paid") {
            return { ok: true as const, value: { id: expenseId } };
        }
        return markLocalExpensePaidCore(scope.evolu, expenseId);
    });
}

/**
 * Explicitly return a fresh live, noncancelled expense to recorded status and clear its paid date.
 * The returned Result follows the shared writer gate's completion proof.
 */
export async function unmarkLocalExpensePaid(evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1 || fresh.status === "cancelled") return { ok: false as const, error: "expense_target_changed" };
        if (hasExpenseBankAssociation(fresh, scope.context)) throw new Error("expense_linked_payment");
        return unmarkLocalExpensePaidCore(scope.evolu, expenseId);
    });
}

/**
 * Cancel a fresh live expense under the shared writer gate, rejecting missing or deleted targets.
 * Return only after the worker-backed financial-state check completes.
 */
export async function cancelLocalExpense(evolu: Evolu<InvoicingLocalSchema>, expenseId: ExpenseId) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, expenseId);
        if (!fresh || fresh.isDeleted === 1) return { ok: false as const, error: "expense_target_changed" };
        if (hasExpenseBankAssociation(fresh, scope.context)) throw new Error("expense_linked_payment");
        return cancelLocalExpenseCore(scope.evolu, expenseId);
    });
}

/**
 * Re-read the source and allocate the copy's number from fresh company rows inside the writer gate.
 * Caller snapshots supply identity, not authoritative field values or numbering state.
 */
export async function duplicateLocalExpense(
    evolu: Evolu<InvoicingLocalSchema>, source: EvoluExpenseRow, _existingRows: EvoluExpenseRow[],
) {
    return withExpenseReconciliation(evolu, (scope) => {
        const fresh = currentExpenseTarget(scope, source.id);
        if (!fresh || fresh.isDeleted === 1 || fresh.companyId !== source.companyId) return { ok: false as const, error: "expense_target_changed" };
        assertExpenseCompanyOwner(scope, fresh.companyId, Reflect.get(fresh, "ownerId"));
        const copySource = fresh.companyContactId && !hasLiveExpenseContact(scope, fresh.companyContactId)
            ? { ...fresh, companyContactId: null } : fresh;
        if (copySource.companyContactId) assertExpenseContact(scope, copySource.companyId, copySource.companyContactId);
        return duplicateLocalExpenseCore(scope.evolu, copySource, scope.context.expenses);
    }, undefined, ["allContactsQuery"]);
}
