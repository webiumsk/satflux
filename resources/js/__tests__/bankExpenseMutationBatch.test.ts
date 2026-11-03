import { afterEach, describe, expect, it, vi } from "vitest";
import { createEvolu, createId, SimpleName } from "@evolu/common";
import { deserializeQuery } from "@evolu/common/local-first";
import { CompanyId, InvoicingLocalSchema } from "@/evolu/schema";
import { clearRowOwnerIndex, withCompanyOwnerScoping } from "@/evolu/ownerScope";
import { clearCompanyShares, setCompanyShare } from "@/evolu/companyShareRegistry";
import type { OwnerId } from "@evolu/common/local-first";
import { tryAutoMatchBankEmailExpense } from "@/evolu/bankExpensePaymentMatcher";
import { BankReconciliationUnknownError } from "@/evolu/bankReconciliation";
import { importInboundServerTransactionsAsync } from "@/evolu/bankCrud";
import { installBankLocks } from "./bankReconciliationTestSupport";

vi.mock("@/evolu/client", async () => {
    const { createQuery } = await import("@evolu/common/local-first");
    return {
        allBankImportBatchesQuery: createQuery((db) => db.selectFrom("bankImportBatch").selectAll()),
        allBankTransactionsQuery: createQuery((db) => db.selectFrom("bankTransaction").selectAll()),
        allBankTransactionMatchesQuery: createQuery((db) => db.selectFrom("bankTransactionMatch").selectAll()),
        allDocumentsQuery: createQuery((db) => db.selectFrom("document").selectAll()),
        allExpensesQuery: createQuery((db) => db.selectFrom("expense").selectAll()),
        bankReconciliationBatchesQuery: createQuery((db) => db.selectFrom("bankImportBatch").selectAll().where("id", "is not", null)),
        bankReconciliationTransactionsQuery: createQuery((db) => db.selectFrom("bankTransaction").selectAll().where("id", "is not", null)),
        bankReconciliationMatchesQuery: createQuery((db) => db.selectFrom("bankTransactionMatch").selectAll().where("id", "is not", null)),
        bankReconciliationDocumentsQuery: createQuery((db) => db.selectFrom("document").selectAll().where("id", "is not", null)),
        bankReconciliationExpensesQuery: createQuery((db) => db.selectFrom("expense").selectAll().where("id", "is not", null)),
    };
});
vi.mock("@/evolu/documentCrud", () => ({ markLocalDocumentPaidWithAmount: vi.fn(), unmarkLocalDocumentPaid: vi.fn() }));

type DataRow = Record<string, unknown>;
type Message = { type: string; tabId?: string; onCompleteIds?: unknown[]; queries?: string[];
    subscribedQueries?: string[]; changes?: Array<{ table: string; id: string; ownerId?: string; values: DataRow }> };
let sequence = 0;
function createProbe(shared = false, automatic = true) {
    const messages: Message[] = [];
    const state: Record<string, DataRow[]> = { bankImportBatch: [], bankTransaction: [], bankTransactionMatch: [], document: [], expense: [] };
    let receive!: (message: unknown) => void;
    function reply(message: Message, commit = true) {
        if (message.type === "mutate") {
            if (commit) for (const change of message.changes ?? []) {
                let row = state[change.table].find((item) => item.id === change.id);
                if (!row) { row = { id: change.id }; state[change.table].push(row); }
                Object.assign(row, change.values, change.ownerId ? { ownerId: change.ownerId } : {});
            }
            receive({ type: "onQueryPatches", tabId: message.tabId, queryPatches: [], onCompleteIds: message.onCompleteIds ?? [] });
        }
        if (message.type === "query") {
            const queryPatches = (message.queries ?? []).map((query) => {
                const table = /from "([^"]+)"/.exec(deserializeQuery(query as never).sql)?.[1];
                if (!table) throw new Error("test_query_table_missing");
                return { query, patches: [{ op: "replaceAll", value: state[table].map((row) => ({ ...row })) }] };
            });
            receive({ type: "onQueryPatches", tabId: message.tabId, queryPatches, onCompleteIds: [] });
        }
    }
    const deps = {
        console: { ...console, log: vi.fn() },
        createDbWorker: () => ({
            postMessage: (message: Message) => {
                messages.push(message);
                if (automatic && (message.type === "mutate" || message.type === "query")) queueMicrotask(() => reply(message));
            },
            onMessage: (listener: (message: unknown) => void) => { receive = listener; },
        }),
        randomBytes: { create: (size: number) => crypto.getRandomValues(new Uint8Array(size)) },
        time: { now: () => Date.now(), nowIso: () => new Date().toISOString() }, reloadApp: vi.fn(),
    };
    const raw = createEvolu(deps as never)(InvoicingLocalSchema, { name: SimpleName.orThrow(`bankBatch${++sequence}`) });
    const evolu = withCompanyOwnerScoping(raw);
    const companyId = CompanyId.orThrow(createId(deps as never));
    const ownerId = createId(deps as never) as OwnerId;
    if (shared) setCompanyShare({ companyId, ownerId, role: "owner", status: "active", bridgeCompanyId: null });
    const expenseId = createId(deps as never);
    const bank = { id: createId(deps as never), companyId, businessExpenseId: expenseId, matchStatus: "matched" };
    const expense = { id: expenseId, companyId, status: "paid", paidAt: "2026-11-03" };
    const transaction = { ...bank, businessExpenseId: null, matchStatus: "unmatched", recordedDirectionEvidence: "debit",
        direction: "debit", source: "inbound_email", amount: "19.00", currency: "EUR", bookedAt: "2026-11-03T10:15:30Z",
        variableSymbol: "00019", counterpartyName: null, reference: null };
    const candidate = { ...expense, status: "recorded", paidAt: null, cancelledAt: null, internalNumber: "EXP-1",
        total: "19.00", currency: "EUR", variableSymbol: "00019", externalNumber: "INV-00019" };
    state.bankTransaction.push({ ...transaction }); state.expense.push({ ...candidate });
    const ctx = { batches: [], transactions: [transaction], matches: [], documents: [], expenses: [candidate] };
    return { evolu, messages, state, ownerId, bank, expense, transaction, candidate, ctx, reply,
        error: () => receive({ type: "onError", error: { type: "SqliteError", error: { type: "CommitError" } } }) };
}
afterEach(() => { clearRowOwnerIndex(); clearCompanyShares(); vi.unstubAllGlobals(); });

describe("real Evolu mutation queue and worker-response boundary", () => {
    it.each([false, true])("actual matcher waits for one atomic mutation and post-handler fresh query (shared=%s)", async (shared) => {
        const { evolu, messages, ownerId, transaction, candidate, ctx } = createProbe(shared);
        const before = structuredClone(ctx);
        const pending = tryAutoMatchBankEmailExpense(evolu, transaction as never, ctx as never);
        expect(ctx).toEqual(before);
        expect(await pending).toBe(true);
        expect(transaction).toMatchObject({ matchStatus: "matched", businessExpenseId: candidate.id });
        expect(candidate).toMatchObject({ status: "paid", paidAt: "2026-11-03" });
        const mutations = messages.filter((message) => message.type === "mutate");
        expect(mutations).toHaveLength(1);
        expect(mutations[0].changes?.map((change) => change.table)).toEqual(["bankTransaction", "expense"]);
        expect(mutations[0].changes?.map((change) => change.ownerId)).toEqual(shared ? [ownerId, ownerId] : [undefined, undefined]);
        expect(mutations[0].subscribedQueries).toEqual([]);
        const queries = messages.filter((message) => message.type === "query");
        expect(queries).toHaveLength(1); expect(queries[0].queries).toHaveLength(5);
    });
    it.each([false, true])("queues both valid writes in one worker transaction (shared=%s)", async (shared) => {
        const { evolu, messages, ownerId, bank, expense } = createProbe(shared);
        expect(evolu.update("bankTransaction", bank as never, { onlyValidate: true }).ok).toBe(true);
        expect(evolu.update("expense", expense as never, { onlyValidate: true }).ok).toBe(true);
        await Promise.resolve();
        expect(messages.filter((message) => message.type === "mutate")).toHaveLength(0);
        expect(evolu.update("bankTransaction", bank as never).ok).toBe(true);
        expect(evolu.update("expense", expense as never).ok).toBe(true);
        await Promise.resolve();
        const mutations = messages.filter((message) => message.type === "mutate");
        expect(mutations).toHaveLength(1);
        expect(mutations[0].changes?.map((change) => change.table)).toEqual(["bankTransaction", "expense"]);
        expect(mutations[0].changes?.map((change) => change.ownerId)).toEqual(shared ? [ownerId, ownerId] : [undefined, undefined]);
    });
    it("rejects invalid prevalidation without sending any mutation", async () => {
        const { evolu, messages, bank, expense } = createProbe(true);
        expect(evolu.update("bankTransaction", bank as never, { onlyValidate: true }).ok).toBe(true);
        expect(evolu.update("expense", { ...expense, paidAt: "x".repeat(33) } as never, { onlyValidate: true }).ok).toBe(false);
        await Promise.resolve(); expect(messages.filter((message) => message.type === "mutate")).toHaveLength(0);
    });
    it.each([false, true])("sends no mutation when the actual matcher's real expense write fails (shared=%s)", async (shared) => {
        const { evolu: scoped, messages, ctx, transaction } = createProbe(shared);
        const evolu = {
            subscribeError: scoped.subscribeError, getError: scoped.getError, loadQuery: scoped.loadQuery,
            update: (table: string, patch: DataRow, options?: { onlyValidate?: boolean; onComplete?: () => void }) => scoped.update(
                table as never, (table === "expense" && !options?.onlyValidate ? { ...patch, paidAt: "x".repeat(33) } : patch) as never, options),
        } as unknown as typeof scoped;
        const before = structuredClone(ctx);
        expect(await tryAutoMatchBankEmailExpense(evolu, transaction as never, ctx as never)).toBe(false);
        expect(ctx).toEqual(before);
        await Promise.resolve(); expect(messages.filter((message) => message.type === "mutate")).toHaveLength(0);
    });
    it("does not publish context after the pre-COMMIT callback but before fresh query responses", async () => {
        const probe = createProbe(false, false); const before = structuredClone(probe.ctx);
        const pending = tryAutoMatchBankEmailExpense(probe.evolu, probe.transaction as never, probe.ctx as never);
        await vi.waitFor(() => expect(probe.messages.some((message) => message.type === "mutate")).toBe(true));
        probe.reply(probe.messages.find((message) => message.type === "mutate")!);
        await vi.waitFor(() => expect(probe.messages.some((message) => message.type === "query")).toBe(true));
        expect(probe.ctx).toEqual(before);
        probe.reply(probe.messages.find((message) => message.type === "query")!);
        expect(await pending).toBe(true);
    });
    it("reports a worker COMMIT error after the callback without optimistic paid context", async () => {
        const probe = createProbe(false, false); const before = structuredClone(probe.ctx);
        const pending = tryAutoMatchBankEmailExpense(probe.evolu, probe.transaction as never, probe.ctx as never);
        const rejected = expect(pending).rejects.toBeInstanceOf(BankReconciliationUnknownError);
        await vi.waitFor(() => expect(probe.messages.some((message) => message.type === "mutate")).toBe(true));
        probe.reply(probe.messages.find((message) => message.type === "mutate")!, false);
        await vi.waitFor(() => expect(probe.messages.some((message) => message.type === "query")).toBe(true));
        probe.error(); await rejected;
        expect(probe.ctx).toEqual(before);
        expect(probe.state.expense[0].status).toBe("recorded");
    });
    it.each([false, true])("batches validated rows with duplicates and invalid rows in three context reads (shared=%s)", async (shared) => {
        installBankLocks();
        const { evolu, state, messages, transaction } = createProbe(shared);
        state.expense.length = 0;
        state.bankTransaction[0].dedupeHash = "existing";
        const row = { booked_at: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", recorded_direction: "debit" };
        expect(await importInboundServerTransactionsAsync(evolu, transaction.companyId, [
            { ...row, dedupe_hash: "new-a" }, { ...row, dedupe_hash: "new-a" },
            { ...row, dedupe_hash: "new-b", variable_symbol: "x".repeat(33) },
            { ...row, dedupe_hash: "new-b" }, { ...row, dedupe_hash: "existing" },
        ])).toEqual({ imported: 2, auto_matched: 0 });
        expect(state.bankTransaction.map((item) => item.dedupeHash)).toEqual(["existing", "new-a", "new-b"]);
        expect(state.bankImportBatch[0]).toMatchObject({ rowCount: "5", importedCount: "2", skippedDuplicates: "3", autoMatchedCount: "0" });
        const mutations = messages.filter((message) => message.type === "mutate");
        expect(mutations.map((message) => message.changes?.map((change) => change.table)))
            .toEqual([["bankImportBatch"], ["bankTransaction", "bankTransaction"], ["bankImportBatch"]]);
        expect(mutations[1].onCompleteIds).toHaveLength(1);
        const queries = messages.filter((message) => message.type === "query");
        expect(queries).toHaveLength(3);
        expect(queries.flatMap((message) => message.queries ?? [])).toHaveLength(15);
        expect(new Set(queries.flatMap((message) => message.queries ?? [])).size).toBe(5);
        expect(mutations.every((message) => message.subscribedQueries?.length === 0)).toBe(true);
    });
    it("skips the row-batch receipt when every row is rejected in prevalidation", async () => {
        installBankLocks();
        const { evolu, state, messages, transaction } = createProbe();
        expect(await importInboundServerTransactionsAsync(evolu, transaction.companyId, [{
            booked_at: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", recorded_direction: "debit",
            variable_symbol: "x".repeat(33), dedupe_hash: "invalid",
        }])).toEqual({ imported: 0, auto_matched: 0 });
        expect(state.bankTransaction).toHaveLength(1);
        expect(state.bankImportBatch[0]).toMatchObject({ importedCount: "0", skippedDuplicates: "1" });
        expect(messages.filter((message) => message.type === "mutate")).toHaveLength(2);
        expect(messages.filter((message) => message.type === "query")).toHaveLength(2);
    });
    it.each([false, true])("aborts all rows when the second actual insert fails real Evolu validation (shared=%s)", async (shared) => {
        installBankLocks();
        const { evolu: scoped, state, messages, transaction } = createProbe(shared);
        let actualInserts = 0;
        const evolu = {
            subscribeError: scoped.subscribeError, getError: scoped.getError, loadQuery: scoped.loadQuery, update: scoped.update,
            insert: (table: string, props: DataRow, options?: { onlyValidate?: boolean; onComplete?: () => void }) => {
                const fail = table === "bankTransaction" && !options?.onlyValidate && ++actualInserts === 2;
                return scoped.insert(table as never, (fail ? { ...props, variableSymbol: "x".repeat(33) } : props) as never, options);
            },
        } as unknown as typeof scoped;
        const before = structuredClone(state.bankTransaction);
        const row = { booked_at: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", recorded_direction: "debit" };
        await expect(importInboundServerTransactionsAsync(evolu, transaction.companyId, [
            { ...row, dedupe_hash: "new-a" }, { ...row, dedupe_hash: "new-b" },
        ])).rejects.toThrow("bank_import_row_batch_rejected");
        await Promise.resolve();
        expect(state.bankTransaction).toEqual(before);
        expect(state.expense[0].status).toBe("recorded");
        expect(state.bankImportBatch[0]).toMatchObject({ importedCount: "0", autoMatchedCount: "0" });
        expect(messages.filter((message) => message.type === "mutate")).toHaveLength(1);
        expect(messages.filter((message) => message.type === "query")).toHaveLength(1);
    });
    it.each(["id", "companyId", "dedupeHash"])("verifies every row's %s in fresh state before matching or reporting counts", async (field) => {
        installBankLocks();
        const probe = createProbe(false, false);
        const row = { booked_at: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", recorded_direction: "debit", variable_symbol: "00019" };
        const pending = importInboundServerTransactionsAsync(probe.evolu, probe.transaction.companyId, [
            { ...row, dedupe_hash: "new-a" }, { ...row, dedupe_hash: "new-b" },
        ]);
        const rejected = expect(pending).rejects.toThrow("bank_import_row_missing");
        const messagesOfType = (type: string) => probe.messages.filter((message) => message.type === type);
        await vi.waitFor(() => expect(messagesOfType("mutate")).toHaveLength(1));
        probe.reply(messagesOfType("mutate")[0]);
        await vi.waitFor(() => expect(messagesOfType("query")).toHaveLength(1));
        probe.reply(messagesOfType("query")[0]);
        await vi.waitFor(() => expect(messagesOfType("mutate")).toHaveLength(2));
        probe.reply(messagesOfType("mutate")[1]);
        probe.state.bankTransaction.find((transaction) => transaction.dedupeHash === "new-b")![field] = "different";
        await vi.waitFor(() => expect(messagesOfType("query")).toHaveLength(2));
        probe.reply(messagesOfType("query")[1]);
        await rejected;
        expect(probe.state.expense[0].status).toBe("recorded");
        expect(probe.state.bankImportBatch[0]).toMatchObject({ importedCount: "0", autoMatchedCount: "0" });
        expect(messagesOfType("mutate")).toHaveLength(2);
    });
});
