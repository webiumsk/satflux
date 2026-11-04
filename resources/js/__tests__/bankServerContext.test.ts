import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { effectScope, ref, type EffectScope } from "vue";
import { AxiosError, CanceledError } from "axios";

const mocks = vi.hoisted(() => ({ list: vi.fn(), batches: vi.fn(), autoMatch: vi.fn(), match: vi.fn(), ignore: vi.fn(), unmatch: vi.fn() }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => false }));
vi.mock("@evolu/vue", () => ({ useQuery: vi.fn() }));
vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionMatchesQuery: "matches", allBankTransactionsQuery: "transactions",
    allDocumentsQuery: "documents", allExpensesQuery: "expenses", useInvoicingEvolu: vi.fn(),
}));
vi.mock("@/evolu/bankCrud", () => ({}));
vi.mock("@/services/api", () => ({ invoicingApi: { bankTransactions: {
    list: mocks.list, batches: mocks.batches, autoMatchBatch: mocks.autoMatch, match: mocks.match, ignore: mocks.ignore, unmatch: mocks.unmatch,
} } }));

import { useInvoicingBankPayments } from "@/composables/useInvoicingBankPayments";

let scope: EffectScope;
function bankFor() {
    const company = ref("company-a");
    scope = effectScope();
    const bank = scope.run(() => useInvoicingBankPayments(company, ref("EUR")))!;
    return { bank, company };
}
function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: Error) => void;
    const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}
const response = (id: string) => ({ data: [{ id }], meta: { summary: { balance: id } } });

beforeEach(() => { vi.resetAllMocks(); });
afterEach(() => { scope?.stop(); });

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
