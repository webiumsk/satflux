import { vi } from "vitest";
import { createEvolu, createId, SimpleName } from "@evolu/common";
import { deserializeQuery, type OwnerId } from "@evolu/common/local-first";
import { CompanyId, InvoicingLocalSchema } from "@/evolu/schema";
import { withCompanyOwnerScoping } from "@/evolu/ownerScope";
import { setCompanyShare } from "@/evolu/companyShareRegistry";

export type DataRow = Record<string, unknown>;
export type WorkerMessage = { type: string; tabId?: string; onCompleteIds?: unknown[]; queries?: string[];
    subscribedQueries?: string[]; changes?: Array<{ table: string; id: string; ownerId?: string; values: DataRow; isDelete?: boolean | null }> };
let sequence = 0;

/** Real Evolu API/queue/cache; only worker storage/transport is synthetic. */
export function createExpenseWorkerProbe(shared = false, automatic = true) {
    const messages: WorkerMessage[] = [];
    const responded = new Set<WorkerMessage>();
    const state: Record<string, DataRow[]> = { company: [], companyShare: [], bankImportBatch: [], bankTransaction: [], bankTransactionMatch: [], document: [], expense: [] };
    let receive!: (message: unknown) => void;
    let appOwnerId = "";
    function reply(message: WorkerMessage, commit = true) {
        responded.add(message);
        if (message.type === "mutate") {
            if (commit) for (const change of message.changes ?? []) {
                const ownerId = change.ownerId ?? appOwnerId;
                const rows = state[change.table] ??= [];
                let row = rows.find((item) => item.id === change.id && item.ownerId === ownerId);
                if (!row) { row = { id: change.id, ownerId }; rows.push(row); }
                Object.assign(row, change.values);
                if (typeof change.isDelete === "boolean") row.isDeleted = change.isDelete ? 1 : 0;
            }
            receive({ type: "onQueryPatches", tabId: message.tabId, queryPatches: [], onCompleteIds: message.onCompleteIds ?? [] });
        }
        if (message.type === "query") {
            const queryPatches = (message.queries ?? []).map((query) => {
                const sql = deserializeQuery(query as never).sql;
                const table = /from "([^"]+)"/.exec(sql)?.[1];
                if (!table) throw new Error("test_query_table_missing");
                const rows = (state[table] ?? []).filter((row) => !sql.includes('"isDeleted" is not') || row.isDeleted !== 1);
                return { query, patches: [{ op: "replaceAll", value: rows.map((row) => ({ ...row })) }] };
            });
            receive({ type: "onQueryPatches", tabId: message.tabId, queryPatches, onCompleteIds: [] });
        }
    }
    const deps = {
        console: { ...console, log: vi.fn() },
        createDbWorker: () => ({
            postMessage: (message: WorkerMessage) => {
                messages.push(message);
                if (automatic && (message.type === "mutate" || message.type === "query")) queueMicrotask(() => reply(message));
            },
            onMessage: (listener: (message: unknown) => void) => { receive = listener; },
        }),
        randomBytes: { create: (size: number) => crypto.getRandomValues(new Uint8Array(size)) },
        time: { now: () => Date.now(), nowIso: () => new Date().toISOString() }, reloadApp: vi.fn(),
    };
    appOwnerId = createId(deps as never);
    const raw = createEvolu(deps as never)(InvoicingLocalSchema, { name: SimpleName.orThrow(`bankBatch${++sequence}`) });
    receive({ type: "onGetAppOwner", appOwner: { id: appOwnerId } });
    const evolu = withCompanyOwnerScoping(raw);
    const companyId = CompanyId.orThrow(createId(deps as never));
    const ownerId = createId(deps as never) as OwnerId;
    const financialOwnerId = shared ? ownerId : appOwnerId;
    if (shared) {
        setCompanyShare({ companyId, ownerId, role: "owner", status: "active", bridgeCompanyId: null });
        state.companyShare.push({ id: createId(deps as never), ownerId: appOwnerId, companyId, sharedOwnerId: ownerId, status: "active", role: "owner" });
    }
    state.company.push({ id: companyId, ownerId: financialOwnerId, legalName: "Synthetic company" });
    const expenseId = createId(deps as never);
    const bank = { id: createId(deps as never), companyId, businessExpenseId: expenseId, matchStatus: "matched" };
    const expense = { id: expenseId, companyId, status: "paid", paidAt: "2026-11-03" };
    const transaction = { ...bank, businessExpenseId: null, matchStatus: "unmatched", recordedDirectionEvidence: "debit",
        direction: "debit", source: "inbound_email", amount: "19.00", currency: "EUR", bookedAt: "2026-11-03T10:15:30Z",
        variableSymbol: "00019", counterpartyName: null, reference: null };
    const candidate = { ...expense, status: "recorded", paidAt: null, cancelledAt: null, internalNumber: "EXP-1",
        total: "19.00", currency: "EUR", variableSymbol: "00019", externalNumber: "INV-00019" };
    state.bankTransaction.push({ ...transaction, ownerId: financialOwnerId }); state.expense.push({ ...candidate, ownerId: financialOwnerId });
    const ctx = { batches: [], transactions: [transaction], matches: [], documents: [], expenses: [candidate] };
    const financialMutations = () => messages.filter((message) => message.type === "mutate"
        && message.changes?.some((change) => change.table !== "_expenseReconciliation"));
    const pumpUntil = async (predicate: () => boolean) => {
        await vi.waitFor(() => {
            for (const message of messages) {
                if (predicate()) break;
                if (!responded.has(message) && (message.type === "mutate" || message.type === "query")) reply(message);
            }
            if (!predicate()) throw new Error("test_worker_condition_pending");
        });
    };
    return { evolu, messages, state, ownerId, appOwnerId, bank, expense, transaction, candidate, ctx, reply, financialMutations, pumpUntil,
        resume: () => { automatic = true; for (const message of messages) if (!responded.has(message) && ["mutate", "query"].includes(message.type)) reply(message); },
        error: () => receive({ type: "onError", error: { type: "SqliteError", error: { type: "CommitError" } } }) };
}
