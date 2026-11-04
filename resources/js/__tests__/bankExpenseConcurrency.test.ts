import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { BANK_RECONCILIATION_TIMEOUT_MS, BankReconciliationUnknownError } from "@/evolu/bankReconciliation";
import { installBankLocks, bankTestQueryTable } from "./bankReconciliationTestSupport";
vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionsQuery: "transactions",
    allBankTransactionMatchesQuery: "matches", allDocumentsQuery: "documents", allExpensesQuery: "expenses",
    bankReconciliationBatchesQuery: "fresh:batches", bankReconciliationTransactionsQuery: "fresh:transactions",
    bankReconciliationMatchesQuery: "fresh:matches", bankReconciliationDocumentsQuery: "fresh:documents", bankReconciliationExpensesQuery: "fresh:expenses",
}));
vi.mock("@/evolu/documentCrud", () => ({ markLocalDocumentPaidWithAmount: vi.fn(), unmarkLocalDocumentPaid: vi.fn() }));
import { autoMatchLocalBatchAsync, importInboundServerTransactionsAsync } from "@/evolu/bankCrud";

type DataRow = Record<string, unknown>;
function fixture(existing = false) {
    const expense = { id: "expense-a", companyId: "company-a", status: "recorded", paidAt: null, cancelledAt: null,
        total: "19.00", currency: "EUR", variableSymbol: "0019", externalNumber: "INV-0019" };
    const state: Record<string, DataRow[]> = { batches: [], transactions: [], matches: [], documents: [], expenses: [{ ...expense }] };
    if (existing) {
        state.batches.push({ id: "existing-batch", companyId: "company-a", autoMatchedCount: "0" });
        state.transactions.push({ id: "existing-a", companyId: "company-a", bankImportBatchId: "existing-batch",
            bookedAt: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", matchStatus: "unmatched",
            businessExpenseId: null, variableSymbol: "0019", reference: null, counterpartyName: null, dedupeHash: "oldhash", source: "inbound_email" });
    }
    const tables: Record<string, DataRow[]> = { bankImportBatch: state.batches, bankTransaction: state.transactions, expense: state.expenses,
        company: [{ id: "company-a", legalName: "Synthetic company", ownerId: "app-owner" }] };
    for (const rows of Object.values(tables)) for (const row of rows) row.ownerId = "app-owner";
    let sequence = 0;
    function client() {
        // Every client starts with its own stale, subscribed UI query snapshots.
        const ui = new Map(Object.entries(state).map(([key, rows]) => [key, rows.map((row) => ({ ...row }))]));
        let heldReceipt: (() => void) | undefined;
        let pauseExpense = false;
        let workerError: unknown = null;
        const errorListeners = new Set<() => void>();
        const complete = (table: string, onComplete?: () => void) => {
            if (table === "expense" && pauseExpense && onComplete) heldReceipt = onComplete;
            else queueMicrotask(() => onComplete?.());
        };
        const evolu = {
            appOwner: Promise.resolve({ id: "app-owner" }),
            upsert: vi.fn((_table: string, props: DataRow, options?: { onComplete?: () => void }) => {
                queueMicrotask(() => options?.onComplete?.()); return { ok: true, value: { id: props.id } };
            }),
            subscribeError: (listener: () => void) => { errorListeners.add(listener); return () => { errorListeners.delete(listener); }; },
            getError: () => workerError,
            loadQuery: vi.fn(async (key: string) => key.startsWith("fresh:")
                ? state[key.slice(6)].map((row) => ({ ...row }))
                : (tables[bankTestQueryTable(key) ?? ""]?.map((row) => ({ ...row })) ?? ui.get(key) ?? [])),
            insert: vi.fn((table: string, props: DataRow, options?: { onlyValidate?: boolean; onComplete?: () => void }) => {
                const id = `insert-${++sequence}`;
                if (!options?.onlyValidate) {
                    tables[table].push({ ...props, id, ownerId: "app-owner" });
                    complete(table, options?.onComplete);
                }
                return { ok: true, value: { id } };
            }),
            update: vi.fn((table: string, patch: DataRow, options?: { onlyValidate?: boolean; onComplete?: () => void }) => {
                if (!options?.onlyValidate) {
                    Object.assign(tables[table].find((row) => row.id === patch.id) ?? {}, patch);
                    complete(table, options?.onComplete);
                }
                return { ok: true, value: { id: patch.id } };
            }),
        };
        return { evolu, failWorker: () => { workerError = { type: "SqliteError" }; errorListeners.forEach((listener) => listener()); },
            pauseExpense: () => { pauseExpense = true; }, hasHeldReceipt: () => Boolean(heldReceipt),
            releaseExpense: () => { const receipt = heldReceipt; heldReceipt = undefined; receipt?.(); } };
    }
    const row = { booked_at: "2026-11-03", amount: "19.00", currency: "EUR", direction: "debit", recorded_direction: "debit", variable_symbol: "0019" };
    return { client, row, state, expense };
}

beforeEach(() => { installBankLocks(); });
afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals(); });

it("serializes two client objects across one origin despite stale subscribed UI snapshots", async () => {
    const locks = installBankLocks(); const { client, row, state, expense } = fixture();
    const first = client(); const second = client();
    await Promise.all([
        importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "hash-a" }]),
        importInboundServerTransactionsAsync(second.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "hash-b" }]),
    ]);
    expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
    expect(second.evolu.loadQuery.mock.calls.some(([key]) => key === "fresh:expenses")).toBe(true);
    expect(await second.evolu.loadQuery("expenses")).toMatchObject([{ status: "recorded" }]);
    expect(state.expenses[0].status).toBe("paid");
    const fresh = new Set([...first.evolu.loadQuery.mock.calls, ...second.evolu.loadQuery.mock.calls]
        .map(([key]) => key).filter((key) => key.startsWith("fresh:")));
    expect(fresh.size).toBe(5);
    expect(locks.request).toHaveBeenCalledTimes(2);
    for (const [name, options] of locks.request.mock.calls) {
        expect(name).toBe("satflux-bank-email-reconciliation-v1");
        expect(options).toMatchObject({ mode: "exclusive", signal: expect.any(AbortSignal) });
    }
});

it("does not admit another tab until the expense receipt and post-handler reads finish", async () => {
    const locks = installBankLocks();
    const { client, row, state, expense } = fixture();
    const first = client(); const second = client(); first.pauseExpense();
    const pending = importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "hash-a" }]);
    await vi.waitFor(() => expect(first.hasHeldReceipt()).toBe(true));
    const next = importInboundServerTransactionsAsync(second.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "hash-b" }]);
    await Promise.resolve(); await Promise.resolve();
    expect(locks.held).toBe(1);
    expect(second.evolu.insert).not.toHaveBeenCalled();
    first.releaseExpense();
    await Promise.all([pending, next]);
    expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
    expect(locks.held).toBe(0);
});

it("serializes a new import and an explicit legacy batch even across clients", async () => {
    const { client, row, state, expense } = fixture(true);
    const first = client(); const second = client();
    await Promise.all([
        importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "newhash" }]),
        autoMatchLocalBatchAsync(second.evolu as never, "existing-batch" as never),
    ]);
    expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
});

it("allows the next operation after a known validation abort", async () => {
    const { client, row, state, expense } = fixture(); const first = client();
    first.evolu.insert.mockReturnValueOnce({ ok: false, value: { id: "rejected" } });
    const results = await Promise.all([
        importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "failedhash" }]),
        importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "nexthash" }]),
    ]);
    expect(results).toEqual([{ imported: 0, auto_matched: 0 }, { imported: 1, auto_matched: 1 }]);
    expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
});

it("keeps an unknown outcome quarantined after a late receipt and rejects waiting work without running it", async () => {
    vi.useFakeTimers();
    const locks = installBankLocks(); const { client, row, state, expense } = fixture();
    const first = client(); const second = client(); first.pauseExpense();
    const pending = importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "hash-a" }]);
    const reported = expect(pending).rejects.toBeInstanceOf(BankReconciliationUnknownError);
    for (let i = 0; i < 80 && !first.hasHeldReceipt(); i++) await Promise.resolve();
    expect(first.hasHeldReceipt()).toBe(true);
    const next = importInboundServerTransactionsAsync(second.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "hash-b" }]);
    const nextReported = expect(next).rejects.toThrow("bank_reconciliation_wait_timed_out");
    await vi.advanceTimersByTimeAsync(BANK_RECONCILIATION_TIMEOUT_MS + 1);
    await reported; await nextReported;
    expect(locks.held).toBe(1);
    expect(second.evolu.insert).not.toHaveBeenCalled();
    first.releaseExpense();
    for (let i = 0; i < 10; i++) await Promise.resolve();
    expect(locks.held).toBe(1);
    expect(second.evolu.insert).not.toHaveBeenCalled();
    expect(state.transactions.filter((transaction) => transaction.businessExpenseId === expense.id)).toHaveLength(1);
    await expect(importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [row])).rejects.toBeInstanceOf(BankReconciliationUnknownError);
});

it.each(["absent", "rejected", "null"])("keeps imports available without expense matching when Web Locks are %s", async (mode) => {
    if (mode === "absent") vi.stubGlobal("navigator", {});
    if (mode === "rejected") vi.stubGlobal("navigator", { locks: { request: vi.fn().mockRejectedValue(new Error("unavailable")) } });
    if (mode === "null") vi.stubGlobal("navigator", { locks: { request: vi.fn(async (_name, _options, callback) => callback(null)) } });
    const { client, row, state } = fixture(); const first = client();
    expect(await importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [{ ...row, dedupe_hash: "hash-a" }]))
        .toEqual({ imported: 1, auto_matched: 0 });
    expect(state.expenses[0].status).toBe("recorded");
    expect(state.transactions[0].recordedDirectionEvidence).toBe("debit");
});

it("does not create a phantom batch for an unknown batch handle", async () => {
    const { client } = fixture(); const first = client();
    expect(await autoMatchLocalBatchAsync(first.evolu as never, "missing" as never)).toEqual({ auto_matched: 0 });
    expect(first.evolu.update).not.toHaveBeenCalled();
});

it("never starts canceled work even if a lock manager later grants its request", async () => {
    vi.useFakeTimers();
    const locks = installBankLocks(); const { client, row } = fixture(); const first = client();
    let release!: () => void;
    const held = locks.request("external-holder", {}, () => new Promise<void>((resolve) => { release = resolve; }));
    await Promise.resolve();
    const pending = importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [row]);
    const reported = expect(pending).rejects.toThrow("bank_reconciliation_wait_timed_out");
    await vi.advanceTimersByTimeAsync(BANK_RECONCILIATION_TIMEOUT_MS + 1);
    await reported;
    release(); await held;
    for (let i = 0; i < 10; i++) await Promise.resolve();
    expect(first.evolu.insert).not.toHaveBeenCalled();
    expect(first.evolu.loadQuery).not.toHaveBeenCalled();
    expect(locks.held).toBe(0);
});

it("does not start mutations when the worker already has an unhandled error", async () => {
    const locks = installBankLocks(); const { client, row } = fixture(); const first = client();
    first.failWorker();
    await expect(importInboundServerTransactionsAsync(first.evolu as never, "company-a" as never, [row]))
        .rejects.toBeInstanceOf(BankReconciliationUnknownError);
    expect(first.evolu.insert).not.toHaveBeenCalled();
    expect(locks.held).toBe(1);
});
