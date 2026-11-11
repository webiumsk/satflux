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
/**
 * Reject forged, expired or foreign-client leases before reusing an acquired origin lock.
 */
export function assertBankReconciliationLease(evolu: Evolu<InvoicingLocalSchema>, lease: BankReconciliationLease): void {
    if (leases.get(lease) !== evolu) throw new Error("expense_reconciliation_lease_expired");
}

/** A worker error or missing receipt is not evidence of rollback. */
export type BankReconciliationUnknownReason = "worker_error" | "receipt_timeout" | "start_exception" | "unavailable";
export class BankReconciliationUnknownError extends Error {
    constructor(readonly reason: BankReconciliationUnknownReason = "unavailable") {
        super("bank_reconciliation_outcome_unknown");
        this.name = "BankReconciliationUnknownError";
    }
}

/**
 * Observe one known completion with an error subscription and bounded deadline.
 * Throws, worker errors or missing completion become Unknown, which does not establish rollback.
 */
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
    /**
     * Dispose the deadline and error observer when a terminal outcome wins the race.
     */
    const cleanup = () => {
        if (timer !== undefined) clearTimeout(timer);
        unsubscribe?.();
        unsubscribe = undefined;
    };
    /**
     * Reject once as uncertain; a later completion cannot turn this outcome into success.
     */
    const rejectUnknown = (reason: BankReconciliationUnknownReason) => {
        if (settled) return;
        settled = true;
        cleanup();
        rejectOutcome(new BankReconciliationUnknownError(reason));
    };
    const unknown = () => rejectUnknown("unavailable");
    /**
     * Resolve the first known outcome and ignore completion after an Unknown rejection.
     */
    const complete = (value: T) => {
        cleanup();
        if (!settled) {
            settled = true;
            resolveOutcome(value);
        }
    };
    // A relay quota rejects remote uploads. Local SQLite writes still require
    // their own receipt and post-handler readback before the caller can succeed.
    const hasBlockingError = () => {
        const error = evolu.getError();
        return Boolean(error && error.type !== "ProtocolQuotaError");
    };
    try {
        unsubscribe = evolu.subscribeError(() => { if (hasBlockingError()) rejectUnknown("worker_error"); });
        timer = setTimeout(() => rejectUnknown("receipt_timeout"), BANK_RECONCILIATION_TIMEOUT_MS);
        if (hasBlockingError()) rejectUnknown("worker_error");
        else start(complete, unknown);
    } catch {
        rejectUnknown("start_exception");
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
    /**
     * Execute only admitted work and issue a lease only when expense matching is locked.
     * Unknown rejects the public call while keeping the client queue and any acquired origin lock held.
     */
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
    /**
     * Clear the finished waiter and remove its queue entry only if it is still the tail.
     */
    const release = () => {
        clearTimeout(waitTimer);
        if (reconciliations.get(evolu) === pending) reconciliations.delete(evolu);
    };
    void pending.then((value) => { release(); resolvePublic(value); }, (error: unknown) => { release(); rejectPublic(error); });
    return result;
}
