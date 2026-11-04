import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { effectScope, ref, type EffectScope } from "vue";
import { AxiosError, CanceledError } from "axios";

const mocks = vi.hoisted(() => ({ list: vi.fn(), batches: vi.fn(), autoMatch: vi.fn(), match: vi.fn(), ignore: vi.fn(), unmatch: vi.fn(), wiseConnect: vi.fn(), wiseStatus: vi.fn() }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => false }));
vi.mock("@evolu/vue", () => ({ useQuery: vi.fn() }));
vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionMatchesQuery: "matches", allBankTransactionsQuery: "transactions",
    allDocumentsQuery: "documents", allExpensesQuery: "expenses", useInvoicingEvolu: vi.fn(),
}));
vi.mock("@/evolu/bankCrud", () => ({}));
vi.mock("@/services/api", () => ({ invoicingApi: { bankTransactions: {
    list: mocks.list, batches: mocks.batches, autoMatchBatch: mocks.autoMatch, match: mocks.match, ignore: mocks.ignore, unmatch: mocks.unmatch,
}, wise: { connect: mocks.wiseConnect, status: mocks.wiseStatus } } }));

import { useInvoicingBankPayments } from "@/composables/useInvoicingBankPayments";

let scope: EffectScope;
const scopes: EffectScope[] = [];
function bankFor(id = "company-a") {
    const company = ref(id);
    scope = effectScope();
    scopes.push(scope);
    const bank = scope.run(() => useInvoicingBankPayments(company, ref("EUR")))!;
    return { bank, company, scope };
}
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: Error) => void;
    const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}
const response = (id: string) => ({ data: [{ id }], meta: { summary: { balance: id } } });

beforeEach(() => { vi.resetAllMocks(); mocks.wiseConnect.mockResolvedValue(undefined); mocks.wiseStatus.mockResolvedValue({ connected: true }); });
afterEach(() => { scopes.splice(0).forEach((active) => active.stop()); vi.useRealTimers(); });

it("keeps the selected company's rows and summary after an older post-match refresh resolves", async () => {
    const pending = deferred<ReturnType<typeof response>>();
    mocks.list.mockReturnValueOnce(pending.promise).mockResolvedValueOnce(response("bank-b"));
    const { bank, company } = bankFor();
    const stale = bank.load("all", { skipErrorFlash: true });
    company.value = "company-b";
    await bank.load();
    pending.resolve(response("bank-a"));
    await stale;
    expect(bank.transactions.value).toEqual([{ id: "bank-b" }]);
    expect(bank.summary.value.balance).toBe("bank-b");
    expect(bank.loading.value).toBe(false);
});

it.each(["resolve", "reject"] as const)("does not clear B's loading state when a stale A request %ss", async (outcome) => {
    const first = deferred<ReturnType<typeof response>>();
    const second = deferred<ReturnType<typeof response>>();
    mocks.list.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
    const { bank, company } = bankFor();
    const stale = bank.load().catch((error: unknown) => error);
    company.value = "company-b";
    const current = bank.load();
    if (outcome === "resolve") first.resolve(response("bank-a"));
    else first.reject(new Error("synthetic stale failure"));
    await stale;
    expect(bank.loading.value).toBe(true);
    expect(bank.transactions.value).toEqual([]);
    expect(bank.summary.value.balance).toBe("0.00");
    second.resolve(response("bank-b"));
    await current;
    expect(bank.transactions.value).toEqual([{ id: "bank-b" }]);
    expect(bank.loading.value).toBe(false);
});

it("invalidates a stale refresh across A-B-A even before a replacement load", async () => {
    const pending = deferred<ReturnType<typeof response>>();
    mocks.list.mockReturnValueOnce(pending.promise);
    const { bank, company } = bankFor();
    const stale = bank.load();
    company.value = "company-b";
    company.value = "company-a";
    pending.resolve(response("old-bank-a"));
    await stale;
    expect(bank.transactions.value).toEqual([]);
    expect(bank.summary.value.balance).toBe("0.00");
    expect(bank.loading.value).toBe(false);
});

it("retains the latest same-company filter result when loads finish out of order", async () => {
    const pending = deferred<ReturnType<typeof response>>();
    mocks.list.mockReturnValueOnce(pending.promise).mockResolvedValueOnce(response("matched-bank-a"));
    const { bank } = bankFor();
    const stale = bank.load("unmatched");
    await bank.load("matched");
    pending.resolve(response("unmatched-bank-a"));
    await stale;
    expect(bank.transactions.value).toEqual([{ id: "matched-bank-a" }]);
});

it("opts controlled mutations and their refresh out of the unscoped global error flash", async () => {
    const { bank } = bankFor();
    mocks.list.mockResolvedValue(response("bank-a"));
    await bank.autoMatchBatch("batch-a");
    await bank.matchTransaction("tx-a", "invoice-a");
    await bank.ignoreTransaction("tx-a");
    await bank.unmatchTransaction("tx-a");
    await bank.load("unmatched", { skipErrorFlash: true });
    expect(mocks.autoMatch).toHaveBeenCalledExactlyOnceWith("company-a", "batch-a", { skipErrorFlash: true });
    expect(mocks.match).toHaveBeenCalledExactlyOnceWith("company-a", "tx-a", "invoice-a", { skipErrorFlash: true });
    expect(mocks.ignore).toHaveBeenCalledExactlyOnceWith("company-a", "tx-a", { skipErrorFlash: true });
    expect(mocks.unmatch).toHaveBeenCalledExactlyOnceWith("company-a", "tx-a", { skipErrorFlash: true });
    expect(mocks.list).toHaveBeenCalledExactlyOnceWith("company-a", { per_page: 50, match_status: "unmatched" }, { skipErrorFlash: true, signal: expect.any(AbortSignal) });
});

it("keeps company B batches after an ignored abort delivers late company A rows", async () => {
    const first = deferred<Array<{ id: string }>>();
    mocks.batches.mockReturnValueOnce(first.promise).mockResolvedValueOnce([{ id: "batch-b" }]);
    const { bank, company } = bankFor();
    const stale = bank.loadBatches();
    const oldSignal = mocks.batches.mock.calls[0][1].signal as AbortSignal;
    company.value = "company-b";
    expect(oldSignal.aborted).toBe(true);
    expect(bank.batches.value).toEqual([]);
    await bank.loadBatches({ skipErrorFlash: true });
    first.resolve([{ id: "old-batch-a" }]); await stale;
    expect(bank.batches.value).toEqual([{ id: "batch-b" }]);
    expect(mocks.batches).toHaveBeenLastCalledWith("company-b", { skipErrorFlash: true, signal: expect.any(AbortSignal) });
});

it("invalidates batch responses across A-B-A without a replacement request", async () => {
    const first = deferred<Array<{ id: string }>>(); mocks.batches.mockReturnValueOnce(first.promise);
    const { bank, company } = bankFor(); const stale = bank.loadBatches();
    company.value = "company-b"; company.value = "company-a";
    first.resolve([{ id: "old-batch-a" }]); await stale;
    expect(bank.batches.value).toEqual([]);
});

it("retains the active batch controller when an older same-company request settles", async () => {
    const first = deferred<Array<{ id: string }>>(); const second = deferred<Array<{ id: string }>>();
    mocks.batches.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise).mockResolvedValueOnce([{ id: "latest" }]);
    const { bank } = bankFor(); const old = bank.loadBatches(); const newer = bank.loadBatches();
    const firstSignal = mocks.batches.mock.calls[0][1].signal as AbortSignal;
    const secondSignal = mocks.batches.mock.calls[1][1].signal as AbortSignal;
    expect(firstSignal.aborted).toBe(true);
    first.resolve([{ id: "old" }]); await old;
    await bank.loadBatches();
    expect(secondSignal.aborted).toBe(true);
    second.resolve([{ id: "newer-but-stale" }]); await newer;
    expect(bank.batches.value).toEqual([{ id: "latest" }]);
});

it.each(["list", "batches"] as const)("consumes actual cancellation from a replaced %s request", async (endpoint) => {
    const mock = endpoint === "list" ? mocks.list : mocks.batches;
    mock.mockImplementationOnce((_id, paramsOrOptions, options) => new Promise((_resolve, reject) => {
        const signal = (endpoint === "list" ? options : paramsOrOptions).signal as AbortSignal;
        signal.addEventListener("abort", () => reject(new CanceledError("superseded")), { once: true });
    }));
    const { bank, company } = bankFor();
    const stale = endpoint === "list" ? bank.load() : bank.loadBatches();
    company.value = "company-b";
    await expect(stale).resolves.toBeUndefined();
    expect(bank.loading.value).toBe(false);
});

it.each(["list", "batches"] as const)("propagates ordinary %s failures instead of treating them as cancellation", async (endpoint) => {
    const failure = new Error("synthetic network failure");
    (endpoint === "list" ? mocks.list : mocks.batches).mockRejectedValueOnce(failure);
    const { bank } = bankFor();
    await expect(endpoint === "list" ? bank.load() : bank.loadBatches()).rejects.toBe(failure);
});

it("does not swallow an arbitrary error merely carrying a cancellation code", async () => {
    const failure = new AxiosError("not an actual cancellation", "ERR_CANCELED");
    mocks.list.mockRejectedValueOnce(failure);
    const { bank } = bankFor(); await expect(bank.load()).rejects.toBe(failure);
});

it("aborts active list and batch requests when the owning scope is disposed", async () => {
    const first = deferred<ReturnType<typeof response>>(); const batch = deferred<Array<{ id: string }>>();
    mocks.list.mockReturnValueOnce(first.promise); mocks.batches.mockReturnValueOnce(batch.promise);
    const { bank } = bankFor(); const rows = bank.load(); const batches = bank.loadBatches();
    const listSignal = mocks.list.mock.calls[0][2].signal as AbortSignal;
    const batchSignal = mocks.batches.mock.calls[0][1].signal as AbortSignal;
    scope.stop(); expect(listSignal.aborted).toBe(true); expect(batchSignal.aborted).toBe(true);
    first.resolve(response("disposed")); batch.resolve([{ id: "disposed" }]); await Promise.all([rows, batches]);
    expect(bank.transactions.value).toEqual([]); expect(bank.batches.value).toEqual([]);
});


it("orders entered server connects across A-B-A so the newer explicit credential wins", async () => {
    const entered = deferred<void>(); let stored = "";
    mocks.wiseConnect.mockImplementationOnce(async () => { await entered.promise; stored = "synthetic-old"; })
        .mockImplementationOnce(async () => { stored = "synthetic-new"; });
    const { bank, company } = bankFor();
    const old = bank.connectWise("synthetic-old");
    expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
    company.value = "company-b"; company.value = "company-a";
    const fresh = bank.connectWise("synthetic-new");
    expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
    entered.resolve(); await Promise.all([old, fresh]);
    expect(stored).toBe("synthetic-new");
    expect(mocks.wiseConnect.mock.calls).toEqual([["company-a", "synthetic-old"], ["company-a", "synthetic-new"]]);
    expect(mocks.wiseStatus).toHaveBeenCalledExactlyOnceWith("company-a");
});

it.each(["selection", "disposal"] as const)("skips a queued server intent made stale by %s without blocking its fresh successor", async (change) => {
    const entered = deferred<void>();
    mocks.wiseConnect.mockReturnValueOnce(entered.promise);
    const current = bankFor();
    const first = current.bank.connectWise("synthetic-entered");
    const stale = current.bank.connectWise("synthetic-obsolete").catch((error) => error);
    if (change === "disposal") current.scope.stop();
    else { current.company.value = "company-b"; current.company.value = "company-a"; }
    const fresh = (change === "disposal" ? bankFor().bank : current.bank).connectWise("synthetic-fresh");
    expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
    entered.resolve(); await first;
    expect(await stale).toMatchObject({ message: "bank_bridge_lookup_failed" });
    await fresh;
    expect(mocks.wiseConnect.mock.calls).toEqual([["company-a", "synthetic-entered"], ["company-a", "synthetic-fresh"]]);
});

it.each(["success", "failure"] as const)("shares an entered server gate across disposed and fresh hooks while preserving its %s", async (outcome) => {
    const entered = deferred<void>(); const failure = new Error("synthetic exact entered outcome");
    mocks.wiseConnect.mockReturnValueOnce(entered.promise);
    const first = bankFor();
    const old = first.bank.connectWise("synthetic-old").catch((error) => error);
    first.scope.stop();
    const next = bankFor();
    const fresh = next.bank.connectWise("synthetic-new");
    expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
    if (outcome === "failure") entered.reject(failure); else entered.resolve();
    expect(await old).toBe(outcome === "failure" ? failure : undefined);
    await fresh;
    expect(mocks.wiseConnect).toHaveBeenCalledTimes(2);
    expect(mocks.wiseStatus).toHaveBeenCalledExactlyOnceWith("company-a");
});

it("releases the server credential gate before an independent status GET completes", async () => {
    const status = deferred<{ connected: boolean }>();
    mocks.wiseStatus.mockReturnValueOnce(status.promise);
    const { bank } = bankFor();
    const first = bank.connectWise("synthetic-first");
    await vi.waitFor(() => expect(mocks.wiseStatus).toHaveBeenCalledTimes(1));
    const second = bank.connectWise("synthetic-second");
    await second;
    expect(mocks.wiseConnect).toHaveBeenCalledTimes(2);
    status.resolve({ connected: false }); await first;
    expect(bank.wiseStatus.value).toEqual({ connected: true });
});
