import { sqliteTrue } from "@evolu/common";
import { createQuery, type Evolu } from "@evolu/common/local-first";
import * as clientQueries from "./client";
import {
    assertBankReconciliationLease, awaitBankMutation, awaitWorkerOutcome, BankReconciliationUnknownError,
    readBankReconciliationContext, serializeBankEmailReconciliation, type BankReconciliationLease,
} from "./bankReconciliation";
import { ExpenseReconciliationId, type InvoicingLocalSchema } from "./schema";
import type { BankPaymentContext } from "./bankCrud";
import type { EvoluExpenseRow } from "./expenseMap";
import { resolveMutationOwner } from "./ownerScope";

type Client = Evolu<InvoicingLocalSchema>;
type Row = Record<string, unknown>;
const markerId = ExpenseReconciliationId.orThrow("AAAAAAAAAAAAAAAAAAAAAA");
const scopeKey = Symbol("expense-reconciliation-scope");
const childParents: Record<string, readonly [string, string]> = {
    documentLine: ["documentId", "document"], documentEvent: ["documentId", "document"],
    documentSnapshot: ["documentId", "document"], expenseAttachment: ["expenseId", "expense"],
    recurringProfileLine: ["recurringProfileId", "recurringProfile"], bankTransactionMatch: ["bankTransactionId", "bankTransaction"],
};
declare const scopeBrand: unique symbol;
export type ExpenseReconciliationScope = {
    readonly [scopeBrand]: true;
    readonly evolu: Client;
    readonly context: BankPaymentContext;
};
type Scope = {
    facade: ExpenseReconciliationScope;
    raw: Client; evolu: Client; active: boolean; context: BankPaymentContext;
    rows: Map<unknown, readonly Row[]>; targets: readonly Row[];
    abortedGroups: Set<object>; lastAbortedGroup?: object;
    expected: Map<string, { table: string; id: string; ownerId: string; patch: Row }>; appOwnerId: string;
    activeAux: Set<number>;
    companyRows: readonly Row[]; shareRows: readonly Row[];
    tableRows: Map<string, readonly Row[]>;
};
const scopes = new WeakMap<object, Scope>();

// Fixed, private keys for maintenance decisions outside the five financial
// projections. These are never subscribed, backed up or shared as app data.
const auxiliary = [
    ["company", ["allCompaniesQuery", "allCompaniesDetailQuery"], "legalName"],
    ["contact", ["allContactsQuery"], "name"],
    ["numberSeries", ["allNumberSeriesQuery"]],
    ["documentLine", ["allDocumentLinesQuery"], "name"],
    ["documentEvent", ["allDocumentEventsQuery"], "action"],
    ["documentSnapshot", ["allDocumentSnapshotsQuery"], "payloadJson"],
    ["invoiceTemplate", ["allInvoiceTemplatesQuery"], "name"],
    ["expenseAttachment", ["allExpenseAttachmentsQuery"]],
    ["recurringProfile", ["allRecurringProfilesQuery"]],
    ["recurringProfileLine", ["allRecurringProfileLinesQuery"], "name"],
    ["companyWarehouse", ["allCompanyWarehousesQuery"], "name"],
    ["companyStockItem", ["allCompanyStockItemsQuery"], "name"],
    ["companyStockBalance", ["allCompanyStockBalancesQuery"]],
    ["companyStockMovement", ["allCompanyStockMovementsQuery"]],
    ["companyShare", ["allCompanySharesQuery"]],
] as const;
const auxiliaryQueries = auxiliary.map(([table]) => createQuery((db) => db.selectFrom(table)
    .selectAll().where("isDeleted", "is not", sqliteTrue).where("id", "is not", null).orderBy("createdAt")));
const expenseTargetsQuery = createQuery((db) => db.selectFrom("expense").selectAll().where("id", "is not", null));
const financialBindings = [
    ["allBankImportBatchesQuery", "batches", "bankImportBatch"], ["allBankTransactionsQuery", "transactions", "bankTransaction"],
    ["allBankTransactionMatchesQuery", "matches", "bankTransactionMatch"], ["allDocumentsQuery", "documents", "document"],
    ["allExpensesQuery", "expenses", "expense"],
] as const;

/**
 * Read only an exported query binding; missing bindings receive no scope-cache override.
 */
function queryExport(name: string): unknown {
    return Object.prototype.hasOwnProperty.call(clientQueries, name) ? Reflect.get(clientQueries, name) : undefined;
}

/**
 * Return this client's active registered facade, rejecting raw, foreign or revoked clients.
 */
export function expenseReconciliationScope(evolu: Client): ExpenseReconciliationScope {
    const facade = Reflect.get(evolu, scopeKey) as ExpenseReconciliationScope | undefined;
    const scope = facade && scopes.get(facade);
    if (!scope || !scope.active || scope.evolu !== evolu) throw new Error("expense_reconciliation_lease_expired");
    return facade;
}

/**
 * Prefer a live expense copy, but retain a tombstone when no live copy exists.
 * This lets guarded callers reject deletion instead of recreating a stale target.
 */
export function currentExpenseTarget(scope: ExpenseReconciliationScope, id: string): (EvoluExpenseRow & { isDeleted?: unknown }) | undefined {
    const internal = scopes.get(scope);
    if (!internal?.active) throw new Error("expense_reconciliation_lease_expired");
    const candidates = internal.targets.filter((row) => row.id === id && typeof row.companyId === "string" && row.companyId !== "");
    return (candidates.find((row) => row.isDeleted !== sqliteTrue) ?? candidates[0]) as
        (EvoluExpenseRow & { isDeleted?: unknown }) | undefined;
}

/**
 * Reload financial queries and the enabled finite auxiliary pool for fresh owner and target decisions.
 * Must follow a write that invalidates these private, unsubscribed query keys.
 */
async function refresh(scope: Scope): Promise<void> {
    const indices = [...scope.activeAux];
    const [context, loaded] = await Promise.all([
        readBankReconciliationContext(scope.raw),
        awaitWorkerOutcome<readonly (readonly Row[])[]>(scope.raw, (complete, unknown) => {
            void Promise.all([...indices.map((index) => auxiliaryQueries[index]), expenseTargetsQuery].map((query) => scope.raw.loadQuery(query)))
                .then((loaded) => complete(loaded), unknown);
        }),
    ]);
    scope.context = context;
    scope.targets = loaded[indices.length];
    const companiesIndex = indices.indexOf(0);
    const sharesIndex = indices.indexOf(auxiliary.length - 1);
    scope.companyRows = loaded[companiesIndex];
    scope.shareRows = loaded[sharesIndex];
    for (const [name, key, table] of financialBindings) {
        scope.tableRows.set(table, context[key]);
        const query = queryExport(name);
        if (query !== undefined) scope.rows.set(query, context[key]);
    }
    indices.forEach((index, loadedIndex) => {
        const [, names, required] = auxiliary[index];
        const live = required ? loaded[loadedIndex].filter((row) => row[required] != null) : loaded[loadedIndex];
        scope.tableRows.set(auxiliary[index][0], live);
        for (const name of names) {
            const query = queryExport(name);
            if (query !== undefined) scope.rows.set(query, live);
        }
    });
}

/**
 * Use an explicit maintenance owner or the exact fresh company and target partition.
 * Reject unavailable partitions, ambiguous or pending shares and cross-owner moves instead of falling back to stale registry state.
 */
function financialOwner(scope: Scope, table: string, patch: Row, explicit?: string): string {
    if (explicit) return explicit;
    const source = table === "expense" ? scope.targets : table === "bankTransaction"
        ? scope.context.transactions : scope.context.batches;
    const existing = source.filter((row) => row.id === patch.id && Reflect.get(row, "isDeleted") !== sqliteTrue);
    if (existing.length > 1) throw new Error("expense_reconciliation_owner_unavailable");
    const companyId = existing[0]?.companyId ?? patch.companyId;
    const companies = scope.companyRows.filter((row) => row.id === companyId);
    if (companies.length !== 1 || typeof companies[0].ownerId !== "string") throw new Error("expense_reconciliation_owner_unavailable");
    const ownerId = companies[0].ownerId;
    if (existing.length === 1 && patch.companyId != null && patch.companyId !== companyId) {
        const destination = scope.companyRows.filter((row) => row.id === patch.companyId);
        if (destination.length !== 1 || destination[0].ownerId !== ownerId) throw new Error("expense_reconciliation_owner_unavailable");
    }
    const shares = scope.shareRows.filter((row) => row.companyId === companyId && row.ownerId === scope.appOwnerId && row.status !== "revoked");
    if (shares.length > 1 || (shares.length === 1 && (shares[0].status !== "active" || shares[0].sharedOwnerId !== ownerId))
        || (shares.length === 0 && ownerId !== scope.appOwnerId)
        || (existing.length === 1 && Reflect.get(existing[0], "ownerId") !== ownerId)) {
        throw new Error("expense_reconciliation_owner_unavailable");
    }
    return ownerId;
}

/** Pure preflight before starting a bank mutation receipt or queueing rows. */
export function assertExpenseCompanyOwner(scope: ExpenseReconciliationScope, companyId: string): void {
    const internal = scopes.get(scope);
    if (!internal?.active) throw new Error("expense_reconciliation_lease_expired");
    financialOwner(internal, "expense", { companyId });
}

/** A real local-only write invalidates private caches before the worker read. */
async function marker(scope: Scope): Promise<void> {
    const completed = await awaitBankMutation(scope.raw, (onComplete) => scope.raw.upsert("_expenseReconciliation", {
        id: markerId, pulse: sqliteTrue,
    }, { onComplete }));
    if (!completed) throw new Error("expense_reconciliation_marker_rejected");
    await refresh(scope);
}

/** For awaited maintenance writes, prove the handler finished before cutover. */
export async function commitExpenseMutation(
    evolu: Client,
    mutate: (onComplete: () => void) => { ok: boolean; error?: unknown },
): Promise<{ ok: boolean; error?: unknown }> {
    const scope = scopes.get(expenseReconciliationScope(evolu))!;
    let result: { ok: boolean; error?: unknown } = { ok: false };
    const completed = await awaitBankMutation(scope.raw, (onComplete) => { result = mutate(onComplete); return result; });
    if (completed) await refresh(scope);
    return completed ? { ok: true } : result;
}

export async function checkpointExpenseMutations(evolu: Client): Promise<void> {
    const scope = scopes.get(expenseReconciliationScope(evolu))!;
    // A previous known validation abort must flush before queuing the marker.
    await Promise.resolve();
    await marker(scope);
}

/**
 * Compare expected financial patches with post-handler rows in their exact owner partition.
 * Unacknowledged batch aborts fail; a missing row satisfies only an expected tombstone.
 */
function verify(scope: Scope): void {
    if (scope.abortedGroups.size > 0) throw new Error("expense_mutation_batch_rejected");
    for (const expected of scope.expected.values()) {
        const rows = expected.table === "expense" ? scope.targets : scope.tableRows.get(expected.table) ?? [];
        const row = rows.find((candidate) => candidate.id === expected.id && candidate.ownerId === expected.ownerId);
        if (!row && expected.patch.isDeleted === sqliteTrue) continue;
        if (!row || Object.entries(expected.patch).some(([key, value]) => row[key] !== value)) {
            throw new Error("expense_reconciliation_state_changed");
        }
    }
}

/** A caller has handled Evolu's known whole-microtask validation abort. */
export function acknowledgeExpenseValidationAbort(evolu: Client): void {
    const scope = scopes.get(expenseReconciliationScope(evolu))!;
    if (scope.lastAbortedGroup) scope.abortedGroups.delete(scope.lastAbortedGroup);
    scope.lastAbortedGroup = undefined;
}

/** Public writers need a real origin lock; nested cores use this live lease. */
export async function withExpenseReconciliation<T>(
    evolu: Client,
    run: (scope: ExpenseReconciliationScope) => T | Promise<T>,
    bankLease?: BankReconciliationLease,
    auxiliaryNames: readonly string[] = [],
): Promise<T> {
    const existing = Reflect.get(evolu, scopeKey) as ExpenseReconciliationScope | undefined;
    if (existing) {
        expenseReconciliationScope(evolu);
        const internal = scopes.get(existing)!;
        auxiliary.forEach(([, names], index) => { if (names.some((name) => auxiliaryNames.includes(name))) internal.activeAux.add(index); });
        await checkpointExpenseMutations(evolu);
        const result = await run(existing);
        await checkpointExpenseMutations(evolu);
        verify(internal);
        return result;
    }
    /**
     * Establish the marker barrier, run one admitted writer and verify its final financial state.
     * Expose only a revocable facade; worker uncertainty skips compensation and propagates to origin quarantine.
     */
    const execute = async (): Promise<T> => {
        const activeAux = new Set([0, auxiliary.length - 1]);
        auxiliary.forEach(([, names], index) => { if (names.some((name) => auxiliaryNames.includes(name))) activeAux.add(index); });
        const facade = Object.freeze({ get evolu(): Client { return scope.evolu; }, get context(): BankPaymentContext { return scope.context; } }) as ExpenseReconciliationScope;
        const scope: Scope = { facade, raw: evolu, evolu, context: { batches: [], transactions: [], matches: [], documents: [], expenses: [] },
            active: true, rows: new Map(), targets: [], abortedGroups: new Set(), expected: new Map(), activeAux, appOwnerId: "",
            companyRows: [], shareRows: [], tableRows: new Map() };
        scopes.set(scope.facade, scope);
        let group: { valid: number; invalid: boolean; before: Scope["expected"] } | undefined;
        scope.evolu = new Proxy(evolu, {
            /**
             * Serve refreshed query snapshots and route mutations through fresh owner checks.
             * Track synchronous batch expectations so known aborts restore only in-memory proof state.
             */
            get(target, property, receiver) {
                if (property === scopeKey) return scope.facade;
                const value = Reflect.get(target, property, receiver) as unknown;
                if (property === "loadQuery" || property === "getQueryRows") {
                    return (query: unknown) => {
                        if (!scope.active || scopes.get(scope.facade) !== scope) throw new Error("expense_reconciliation_lease_expired");
                        const rows = scope.rows.get(query);
                        if (rows) return property === "loadQuery" ? Promise.resolve(rows) : rows;
                        return Reflect.apply(value as (...args: unknown[]) => unknown, target, [query]);
                    };
                }
                if (["insert", "upsert", "update"].includes(String(property))) {
                    return (...args: unknown[]) => {
                        if (!scope.active || scopes.get(scope.facade) !== scope) throw new Error("expense_reconciliation_lease_expired");
                        const options = args[2] as { onlyValidate?: boolean; ownerId?: string } | undefined;
                        if (["expense", "bankTransaction", "bankImportBatch"].includes(String(args[0]))) {
                            args[2] = { ...options, ownerId: financialOwner(scope, String(args[0]), args[1] as Row, options?.ownerId) };
                        } else if (!options?.ownerId && args[0] !== "companyShare" && args[0] !== "_expenseReconciliation") {
                            const patch = args[1] as Row;
                            const owners = new Set((scope.tableRows.get(String(args[0])) ?? []).filter((row) => row.id === patch.id)
                                .map((row) => row.ownerId).filter((owner): owner is string => typeof owner === "string"));
                            if (owners.size > 1) throw new Error("expense_reconciliation_owner_unavailable");
                            if (owners.size === 1) args[2] = { ...options, ownerId: [...owners][0] };
                            else if (typeof patch.companyId === "string") {
                                args[2] = { ...options, ownerId: financialOwner(scope, "expense", { companyId: patch.companyId }) };
                            } else if (childParents[String(args[0])]) {
                                const [parentKey, parentTable] = childParents[String(args[0])];
                                const parents = (scope.tableRows.get(parentTable) ?? []).filter((row) => row.id === patch[parentKey]);
                                if (parents.length !== 1 || typeof parents[0].ownerId !== "string") throw new Error("expense_reconciliation_owner_unavailable");
                                const parentOwner = ["expense", "bankTransaction"].includes(parentTable)
                                    ? financialOwner(scope, parentTable, { id: parents[0].id })
                                    : financialOwner(scope, "expense", { companyId: parents[0].companyId });
                                if (parents[0].ownerId !== parentOwner) throw new Error("expense_reconciliation_owner_unavailable");
                                args[2] = { ...options, ownerId: parentOwner };
                            }
                        }
                        let result: { ok: boolean; value?: { id?: string } };
                        try { result = Reflect.apply(value as (...args: unknown[]) => typeof result, target, args); }
                        catch { throw new BankReconciliationUnknownError(); }
                        if (!options?.onlyValidate) {
                            if (!group) {
                                const created = { valid: 0, invalid: false, before: new Map(scope.expected) }; group = created;
                                queueMicrotask(() => {
                                    if (created.invalid) {
                                        scope.expected = created.before;
                                        scope.lastAbortedGroup = created;
                                        if (created.valid > 0) scope.abortedGroups.add(created);
                                    }
                                    if (group === created) group = undefined;
                                });
                            }
                            if (result.ok) group.valid++;
                            else group.invalid = true;
                            if (result.ok && ["expense", "bankTransaction", "bankImportBatch"].includes(String(args[0])) && result.value?.id) {
                                const table = String(args[0]);
                                const patch = { ...args[1] as Row };
                                const explicit = (args[2] as { ownerId?: string } | undefined)?.ownerId;
                                const ownerId = resolveMutationOwner(table, patch, explicit as never) ?? scope.appOwnerId;
                                for (const field of ["id", "ownerId", "createdAt", "updatedAt"]) delete patch[field];
                                const key = `${table}:${ownerId}:${result.value.id}`;
                                const previous = scope.expected.get(key);
                                scope.expected.set(key, { table, id: result.value.id, ownerId, patch: { ...previous?.patch, ...patch } });
                            }
                        }
                        return result;
                    };
                }
                return value;
            },
        });
        try {
            scope.appOwnerId = await awaitWorkerOutcome<string>(evolu, (complete, unknown) => {
                void evolu.appOwner.then((owner) => complete(owner.id), unknown);
            });
            await marker(scope);
            let result!: T; let failure: unknown;
            try { result = await run(scope.facade); } catch (error) { failure = error; }
            if (failure instanceof BankReconciliationUnknownError) throw failure;
            await checkpointExpenseMutations(scope.evolu);
            if (failure !== undefined) throw failure;
            verify(scope);
            return result;
        } finally { scope.active = false; scopes.delete(scope.facade); }
    };
    if (bankLease) {
        assertBankReconciliationLease(evolu, bankLease);
        return execute();
    }
    return serializeBankEmailReconciliation(evolu, execute, true);
}

/** Maintenance explicitly requests its finite table set before synchronous guards. */
export function withExpenseMaintenance<T>(evolu: Client, run: (scope: ExpenseReconciliationScope) => T | Promise<T>): Promise<T> {
    return withExpenseReconciliation(evolu, run, undefined, auxiliary.flatMap(([, names]) => [...names]));
}
