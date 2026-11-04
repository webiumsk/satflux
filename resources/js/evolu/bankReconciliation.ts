import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import type { BankPaymentContext } from "./bankCrud";
import {
    bankReconciliationBatchesQuery, bankReconciliationTransactionsQuery,
    bankReconciliationMatchesQuery, bankReconciliationDocumentsQuery, bankReconciliationExpensesQuery,
} from "./client";
import { toAppRows } from "./queryLoad";

/** Origin-wide: separate clients and tabs can share the same encrypted database. */
export const BANK_RECONCILIATION_LOCK = "satflux-bank-email-reconciliation-v1";
export const BANK_RECONCILIATION_TIMEOUT_MS = 30_000;
const reconciliations = new WeakMap<Evolu<InvoicingLocalSchema>, Promise<unknown>>();
const quarantined = new WeakSet<Evolu<InvoicingLocalSchema>>();
declare const leaseBrand: unique symbol;
export type BankReconciliationLease = { readonly [leaseBrand]: true };
const leases = new WeakMap<object, Evolu<InvoicingLocalSchema>>();
export function assertBankReconciliationLease(evolu: Evolu<InvoicingLocalSchema>, lease: BankReconciliationLease): void {
    if (leases.get(lease) !== evolu) throw new Error("expense_reconciliation_lease_expired");
}

/** A worker error or missing receipt is not evidence of rollback. */
export class BankReconciliationUnknownError extends Error {
    constructor() {
        super("bank_reconciliation_outcome_unknown");
        this.name = "BankReconciliationUnknownError";
    }
}

export function awaitWorkerOutcome<T>(
    evolu: Evolu<InvoicingLocalSchema>,
    start: (complete: (value: T) => void, unknown: () => void) => void,
): Promise<T> {
    let resolveOutcome!: (value: T) => void;
    let rejectOutcome!: (error: BankReconciliationUnknownError) => void;
    const outcome = new Promise<T>((resolve, reject) => { resolveOutcome = resolve; rejectOutcome = reject; });
    let settled = false;
    let unsubscribe: (() => void) | undefined;
    let timer: ReturnType<typeof setTimeout> | undefined;
    const cleanup = () => {
        if (timer !== undefined) clearTimeout(timer);
        unsubscribe?.();
        unsubscribe = undefined;
    };
    const unknown = () => {
        if (settled) return;
        settled = true;
        cleanup();
        rejectOutcome(new BankReconciliationUnknownError());
    };
    const complete = (value: T) => {
        cleanup();
        if (!settled) {
            settled = true;
            resolveOutcome(value);
        }
    };
    try {
        unsubscribe = evolu.subscribeError(() => { if (evolu.getError()) unknown(); });
        timer = setTimeout(unknown, BANK_RECONCILIATION_TIMEOUT_MS);
        if (evolu.getError()) unknown();
        else start(complete, unknown);
    } catch {
        unknown();
    }
    return outcome;
}

/** Attach the receipt to the last mutation in one synchronous Evolu batch. */
export function awaitBankMutation(
    evolu: Evolu<InvoicingLocalSchema>,
    mutate: (onComplete: () => void) => { ok: boolean },
): Promise<boolean> {
    return awaitWorkerOutcome(evolu, (complete) => {
        const result = mutate(() => complete(true));
        if (!result.ok) complete(false); // Evolu validation rejects the whole batch.
    });
}

/**
 * Call only after a legitimate mutation receipt invalidated the private,
 * unsubscribed keys. These responses are queued after that handler's COMMIT;
 * subscribed UI caches and a mutation's pre-COMMIT callback are not proof.
 */
export function readBankReconciliationContext(evolu: Evolu<InvoicingLocalSchema>): Promise<BankPaymentContext> {
    return awaitWorkerOutcome(evolu, (complete, unknown) => {
        void Promise.all([
            evolu.loadQuery(bankReconciliationBatchesQuery), evolu.loadQuery(bankReconciliationTransactionsQuery),
            evolu.loadQuery(bankReconciliationMatchesQuery), evolu.loadQuery(bankReconciliationDocumentsQuery),
            evolu.loadQuery(bankReconciliationExpensesQuery),
        ]).then(([batches, transactions, matches, documents, expenses]) => complete({
            batches: toAppRows(batches), transactions: toAppRows(transactions), matches: toAppRows(matches),
            documents: toAppRows(documents), expenses: toAppRows(expenses),
        }), unknown);
    });
}

/**
 * A missing receipt or worker error permanently quarantines this session's
 * matching gate. A late pre-COMMIT callback cannot safely release it. Closing or
 * reloading the owning tab is required before a fresh worker-backed attempt.
 */
export function serializeBankEmailReconciliation<T>(
    evolu: Evolu<InvoicingLocalSchema>,
    reconcile: (allowExpenseMatching: boolean, lease?: BankReconciliationLease) => Promise<T>,
    requireLock = false,
): Promise<T> {
    if (quarantined.has(evolu)) return Promise.reject(new BankReconciliationUnknownError());
    let resolvePublic!: (result: T) => void;
    let rejectPublic!: (error: unknown) => void;
    const result = new Promise<T>((resolve, reject) => { resolvePublic = resolve; rejectPublic = reject; });
    let canceled = false;
    const controller = new AbortController();
    const waitError = () => new Error("bank_reconciliation_wait_timed_out");
    const waitTimer = setTimeout(() => {
        canceled = true;
        rejectPublic(waitError());
        controller.abort();
    }, BANK_RECONCILIATION_TIMEOUT_MS);
    const run = async (allowExpenseMatching: boolean): Promise<T> => {
        if (canceled) throw waitError();
        clearTimeout(waitTimer);
        const lease = {} as BankReconciliationLease;
        if (allowExpenseMatching) leases.set(lease, evolu);
        try {
            return await reconcile(allowExpenseMatching, allowExpenseMatching ? lease : undefined);
        } catch (error) {
            if (error instanceof BankReconciliationUnknownError) {
                leases.delete(lease);
                quarantined.add(evolu);
                rejectPublic(error);
                // Keep both the per-client chain and the origin lock held.
                // Queued callers time out before entry and cannot run later.
                await new Promise<never>(() => {});
            }
            throw error;
        } finally {
            leases.delete(lease);
        }
    };
    const previous = reconciliations.get(evolu) ?? Promise.resolve();
    const pending = previous.catch(() => undefined).then(async () => {
        if (canceled) throw waitError();
        const locks = typeof navigator === "undefined" ? undefined : navigator.locks;
        if (!locks?.request) {
            if (requireLock) throw new Error("expense_reconciliation_lock_unavailable");
            return run(false);
        }
        let entered = false;
        try {
            return await locks.request(BANK_RECONCILIATION_LOCK, { mode: "exclusive", signal: controller.signal }, (lock) => {
                if (canceled) throw waitError();
                entered = true;
                if (requireLock && lock === null) throw new Error("expense_reconciliation_lock_unavailable");
                return run(lock !== null);
            });
        } catch (error) {
            if (entered || canceled) throw error;
            if (requireLock) throw new Error("expense_reconciliation_lock_unavailable");
            // Import remains available, but an unlocked debit never pays an expense.
            return run(false);
        }
    });
    reconciliations.set(evolu, pending);
    const release = () => {
        clearTimeout(waitTimer);
        if (reconciliations.get(evolu) === pending) reconciliations.delete(evolu);
    };
    void pending.then((value) => { release(); resolvePublic(value); }, (error: unknown) => { release(); rejectPublic(error); });
    return result;
}
