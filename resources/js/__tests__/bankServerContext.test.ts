import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { effectScope, ref, type EffectScope } from "vue";

const mocks = vi.hoisted(() => ({ list: vi.fn(), autoMatch: vi.fn(), match: vi.fn(), ignore: vi.fn(), unmatch: vi.fn() }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => false }));
vi.mock("@evolu/vue", () => ({ useQuery: vi.fn() }));
vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionMatchesQuery: "matches", allBankTransactionsQuery: "transactions",
    allDocumentsQuery: "documents", allExpensesQuery: "expenses", useInvoicingEvolu: vi.fn(),
}));
vi.mock("@/evolu/bankCrud", () => ({}));
vi.mock("@/services/api", () => ({ invoicingApi: { bankTransactions: {
    list: mocks.list, autoMatchBatch: mocks.autoMatch, match: mocks.match, ignore: mocks.ignore, unmatch: mocks.unmatch,
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
    expect(mocks.list).toHaveBeenCalledExactlyOnceWith("company-a", { per_page: 50, match_status: "unmatched" }, { skipErrorFlash: true });
});
