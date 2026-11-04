import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { effectScope, nextTick, ref, type EffectScope } from "vue";
import { AxiosError } from "axios";

const mocks = vi.hoisted(() => ({
    localFirst: true,
    companyRows: { value: [] as { id: string; legalName: string; registrationNumber: string | null }[] },
    ensure: vi.fn(),
    fallback: vi.fn(),
    list: vi.fn(),
    inbound: vi.fn(),
    wiseStatus: vi.fn(),
    wiseSync: vi.fn(),
    importInbound: vi.fn(),
    importWise: vi.fn(),
    autoMatch: vi.fn(),
    loadQuery: vi.fn(),
}));

vi.mock("@evolu/vue", async () => {
    const { ref } = await import("vue");
    mocks.companyRows = ref([]);
    return { useQuery: (query: string) => query === "companies" ? mocks.companyRows : { value: [] } };
});
vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches",
    allBankTransactionMatchesQuery: "matches",
    allBankTransactionsQuery: "transactions",
    allDocumentsQuery: "documents",
    allExpensesQuery: "expenses",
    allCompaniesDetailQuery: "companies",
    bankReconciliationBatchesQuery: "fresh:batches", bankReconciliationTransactionsQuery: "fresh:transactions",
    bankReconciliationMatchesQuery: "fresh:matches", bankReconciliationDocumentsQuery: "fresh:documents", bankReconciliationExpensesQuery: "fresh:expenses",
    useInvoicingEvolu: () => ({ loadQuery: mocks.loadQuery }),
}));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => mocks.localFirst }));
vi.mock("@/evolu/bridgeCompanyEnsure", () => ({ ensureBridgeCompanyIdForLocalCompany: mocks.ensure }));
vi.mock("@/evolu/ephemeralBridge", () => ({ resolveEphemeralBridgeCompanyId: mocks.fallback }));
vi.mock("@/services/api", () => ({
    invoicingApi: {
        bankTransactions: { list: mocks.list, inboundEmail: mocks.inbound },
        wise: { status: mocks.wiseStatus, sync: mocks.wiseSync },
    },
}));
vi.mock("@/evolu/bankCrud", () => ({
    importInboundServerTransactionsAsync: mocks.importInbound,
    importLocalBankRows: mocks.importWise,
    autoMatchLocalBatchAsync: mocks.autoMatch,
}));

import { useInvoicingBankPayments } from "@/composables/useInvoicingBankPayments";
import { BankReconciliationUnknownError } from "@/evolu/bankReconciliation";

const scopes: EffectScope[] = [];
function bankFor(id = "local-b") {
    const company = ref(id);
    const scope = effectScope();
    scopes.push(scope);
    const bank = scope.run(() => useInvoicingBankPayments(company, ref("EUR")))!;
    return { bank, company };
}

function deferred<T>() {
    let resolve!: (value: T) => void;
    let reject!: (reason: Error) => void;
    const promise = new Promise<T>((done, fail) => { resolve = done; reject = fail; });
    return { promise, resolve, reject };
}

const row = { id: "server-tx-b", booked_at: "2026-08-01", amount: "19.00", currency: "EUR" };

beforeEach(() => {
    vi.resetAllMocks();
    mocks.localFirst = true;
    mocks.companyRows.value = ["local-a", "local-b", "company-a", "company-b"].map((id) => ({
        id, legalName: `Company ${id}`, registrationNumber: "123",
    }));
    mocks.loadQuery.mockResolvedValue([]);
    mocks.ensure.mockImplementation(async (id: string) => ({ ok: true, bridgeCompanyId: `bridge-${id}` }));
    mocks.fallback.mockResolvedValue("wrong-first-company");
    mocks.list.mockResolvedValue({ data: [row], meta: { last_page: 1 } });
    mocks.importInbound.mockResolvedValue({ imported: 1, auto_matched: 0 });
    mocks.importWise.mockResolvedValue({ imported: 1, auto_matched: 0 });
    mocks.autoMatch.mockResolvedValue({ auto_matched: 1 });
    mocks.wiseStatus.mockResolvedValue({ connected: true });
    mocks.wiseSync.mockResolvedValue({ rows: [row] });
});

afterEach(() => {
    scopes.splice(0).forEach((scope) => scope.stop());
    vi.useRealTimers();
});

describe("bank movement bridge synchronization", () => {
    it("leaves refresh to the page after local batch matching succeeds", async () => {
        const { bank } = bankFor();
        mocks.loadQuery.mockClear();
        mocks.loadQuery.mockRejectedValue(new Error("synthetic refresh failure"));
        await expect(bank.autoMatchBatch("batch-a")).resolves.toBeUndefined();
        expect(mocks.autoMatch).toHaveBeenCalledExactlyOnceWith(expect.anything(), "batch-a");
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.loadQuery).not.toHaveBeenCalled();
    });
    it("reads the selected company's bridge and imports every email page into that local company", async () => {
        const { bank } = bankFor();
        mocks.list.mockResolvedValueOnce({ data: [row], meta: { last_page: 2 } })
            .mockResolvedValueOnce({ data: [{ ...row, id: "server-tx-b2" }], meta: { last_page: 2 } });
        await bank.load();
        expect(mocks.ensure).toHaveBeenCalledWith("local-b", expect.any(Function));
        expect(mocks.fallback).not.toHaveBeenCalled();
        expect(mocks.list).toHaveBeenNthCalledWith(1, "bridge-local-b", { source: "email", per_page: 100, page: 1 }, { timeout: 10_000, skipErrorFlash: true, signal: expect.any(AbortSignal) });
        expect(mocks.list).toHaveBeenNthCalledWith(2, "bridge-local-b", { source: "email", per_page: 100, page: 2 }, { timeout: 10_000, skipErrorFlash: true, signal: expect.any(AbortSignal) });
        expect(mocks.importInbound).toHaveBeenCalledWith(expect.anything(), "local-b", [row, { ...row, id: "server-tx-b2" }]);
    });

    it.each([{ ok: true, bridgeCompanyId: null }, { ok: false }])("never falls back to another company when resolution returns %j", async (result) => {
        mocks.ensure.mockResolvedValue(result);
        const { bank } = bankFor();
        await bank.load();
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.fallback).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
    });

    it("retries a failed bridge lookup on the next refresh", async () => {
        mocks.ensure.mockResolvedValueOnce({ ok: false });
        const { bank } = bankFor();
        await bank.load();
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
        expect(bank.inboundEmailAvailable.value).toBe(true);
    });

    it("does not import a previous company's response after switching companies", async () => {
        const response = deferred<{ data: typeof row[]; meta: { last_page: number } }>();
        mocks.list.mockImplementation((id: string) => id === "bridge-local-a"
            ? response.promise : Promise.resolve({ data: [], meta: { last_page: 1 } }));
        const { bank, company } = bankFor("local-a");
        const pending = bank.load();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledWith("bridge-local-a", expect.anything(), { timeout: 10_000, skipErrorFlash: true, signal: expect.any(AbortSignal) }));
        company.value = "local-b";
        await nextTick();
        response.resolve({ data: [row], meta: { last_page: 1 } });
        await pending;
        expect(mocks.importInbound).not.toHaveBeenCalled();
    });

    it("ignores stale bridge lookup results and keeps the current company's availability", async () => {
        const result = deferred<{ ok: true; bridgeCompanyId: string }>();
        mocks.ensure.mockImplementation((id: string) => id === "local-a"
            ? result.promise : Promise.resolve({ ok: true, bridgeCompanyId: null }));
        const { bank, company } = bankFor("local-a");
        const pending = bank.load();
        company.value = "local-b";
        await nextTick();
        result.resolve({ ok: true, bridgeCompanyId: "bridge-local-a" });
        await pending;
        expect(mocks.list).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
    });

    it("shares an overlapping refresh so the same email rows are imported once", async () => {
        const response = deferred<{ data: typeof row[] }>();
        mocks.list.mockReturnValue(response.promise);
        const { bank } = bankFor();
        const first = bank.load();
        const second = bank.load();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(1));
        response.resolve({ data: [row] });
        await Promise.all([first, second]);
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
    });

    it("retains local access after a failed email fetch and permits a later retry", async () => {
        mocks.list.mockRejectedValueOnce(new Error("offline"));
        const { bank } = bankFor();
        await expect(bank.load()).resolves.toBeUndefined();
        expect(bank.loading.value).toBe(false);
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
    });

    it("times out a later email page without partial import, releases overlapping work and retries only on a new refresh", async () => {
        vi.useFakeTimers();
        mocks.list.mockResolvedValueOnce({ data: [row], meta: { last_page: 2 } })
            .mockImplementationOnce((_id, _params, options) => new Promise((_resolve, reject) => {
                if (options.timeout) setTimeout(() => reject(new AxiosError("synthetic page timeout", "ECONNABORTED")), options.timeout);
            }));
        const { bank } = bankFor(); mocks.loadQuery.mockClear();
        const first = bank.load(); const overlapping = bank.load();
        let settled = false;
        void Promise.all([first, overlapping]).then(() => { settled = true; });
        await vi.advanceTimersByTimeAsync(1);
        expect(mocks.list).toHaveBeenCalledTimes(2);
        expect(mocks.list).toHaveBeenLastCalledWith("bridge-local-b", { source: "email", per_page: 100, page: 2 },
            { timeout: 10_000, skipErrorFlash: true, signal: expect.any(AbortSignal) });
        await vi.advanceTimersByTimeAsync(9_998);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(settled).toBe(false);
        await vi.advanceTimersByTimeAsync(1); await Promise.all([first, overlapping]);
        expect(bank.loading.value).toBe(false);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(new Set(mocks.loadQuery.mock.calls.map(([query]) => query))).toEqual(new Set(["transactions", "matches", "documents", "expenses"]));
        expect(mocks.list).toHaveBeenCalledTimes(2);
        await bank.load();
        expect(mocks.list).toHaveBeenCalledTimes(3);
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-b", [row]);
    });

    it("leaves company-change refresh ownership with the page so import failures remain observable", async () => {
        const { bank, company } = bankFor("local-a");
        await Promise.resolve(); mocks.loadQuery.mockClear();
        company.value = "local-b"; await nextTick();
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.loadQuery).not.toHaveBeenCalled();
        const failure = new BankReconciliationUnknownError();
        mocks.importInbound.mockRejectedValueOnce(failure);
        await expect(bank.load()).rejects.toBe(failure);
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
    });

    it.each([new Error("expense_mutation_batch_rejected"), new BankReconciliationUnknownError()])("propagates local import failure unchanged and does not retry it automatically: %s", async (failure) => {
            mocks.importInbound.mockRejectedValue(failure);
            const { bank } = bankFor();
            const first = bank.load(); const overlapping = bank.load();
            const outcomes = await Promise.allSettled([first, overlapping]);
            expect(outcomes).toEqual([{ status: "rejected", reason: failure }, { status: "rejected", reason: failure }]);
            expect(bank.loading.value).toBe(false);
            expect(mocks.list).toHaveBeenCalledTimes(1);
            expect(mocks.importInbound).toHaveBeenCalledTimes(1);
            await Promise.resolve();
            expect(mocks.importInbound).toHaveBeenCalledTimes(1);
        });

    it("waits for initial company metadata before capturing the first legal identity", async () => {
        const metadata = deferred<never[]>();
        mocks.companyRows.value = [];
        mocks.loadQuery.mockImplementation((query) => query === "companies" ? metadata.promise : Promise.resolve([]));
        const { bank } = bankFor();
        const pending = bank.load();
        await Promise.resolve();
        expect(mocks.ensure).not.toHaveBeenCalled();
        mocks.companyRows.value = [{ id: "local-b", legalName: "First company", registrationNumber: "123" }];
        metadata.resolve([]);
        await pending;
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-b", [row]);
    });

    it("keeps metadata read failure best-effort for inbound and existing Wise status callers", async () => {
        mocks.companyRows.value = [];
        mocks.loadQuery.mockImplementation((query) => query === "companies"
            ? Promise.reject(new Error("synthetic metadata read failure")) : Promise.resolve([]));
        const { bank } = bankFor();
        await expect(bank.load()).resolves.toBeUndefined();
        await expect(bank.fetchWiseStatus()).resolves.toBeUndefined();
        await expect(bank.loadInbound()).resolves.toBeUndefined();
        expect(mocks.ensure).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.wiseStatus.value).toBeNull();
        expect(bank.wiseBridgeMissing.value).toBe(true);
    });

    it.each(["legalName", "registrationNumber"] as const)("discards an old bridge lookup after %s changes, then allows explicit retry", async (field) => {
        const lookup = deferred<{ ok: true; bridgeCompanyId: string }>();
        mocks.ensure.mockReturnValueOnce(lookup.promise);
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(1));
        mocks.companyRows.value.find((company) => company.id === "local-b")![field] = "Changed identity";
        lookup.resolve({ ok: true, bridgeCompanyId: "old-bridge" });
        await pending;
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
    });

    it.each(["legalName", "registrationNumber"] as const)("discards all fetched pages after %s changes during pagination", async (field) => {
        const page = deferred<{ data: typeof row[] }>();
        mocks.list.mockResolvedValueOnce({ data: [row], meta: { last_page: 2 } }).mockReturnValueOnce(page.promise);
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(2));
        mocks.companyRows.value.find((company) => company.id === "local-b")![field] = "Changed identity";
        page.resolve({ data: [{ ...row, id: "page-two" }] });
        await pending;
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
    });

    it("invalidates a lookup across an identity A-B-A edit", async () => {
        const lookup = deferred<{ ok: true; bridgeCompanyId: string }>();
        mocks.ensure.mockReturnValueOnce(lookup.promise);
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(1));
        const company = mocks.companyRows.value.find((company) => company.id === "local-b")!;
        const previous = company.legalName;
        company.legalName = "Different";
        company.legalName = previous;
        lookup.resolve({ ok: true, bridgeCompanyId: "old-bridge" });
        await pending;
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
    });

    it("accepts outer whitespace and case edits that preserve the existing normalized identity", async () => {
        const page = deferred<{ data: typeof row[] }>();
        mocks.list.mockReturnValueOnce(page.promise);
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(1));
        const company = mocks.companyRows.value.find((company) => company.id === "local-b")!;
        company.legalName = `  ${company.legalName.toUpperCase()}  `;
        company.registrationNumber = `  ${company.registrationNumber}  `;
        page.resolve({ data: [row] });
        await pending;
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-b", [row]);
        expect(bank.inboundEmailAvailable.value).toBe(true);
    });

    it("bounds a hung bridge lookup, ignores its late cache result and permits a new refresh", async () => {
        vi.useFakeTimers();
        const lookup = deferred<{ ok: true; bridgeCompanyId: string }>();
        mocks.ensure.mockReturnValueOnce(lookup.promise);
        const { bank } = bankFor();
        mocks.loadQuery.mockClear();
        const pending = bank.load();
        const overlapping = bank.load();
        await vi.advanceTimersByTimeAsync(20_000);
        await Promise.all([pending, overlapping]);
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(new Set(mocks.loadQuery.mock.calls.map(([query]) => query))).toEqual(new Set(["transactions", "matches", "documents", "expenses"]));
        expect(vi.getTimerCount()).toBe(0);
        lookup.resolve({ ok: true, bridgeCompanyId: "late-bridge" });
        await Promise.resolve(); await Promise.resolve();
        expect(bank.inboundEmailAvailable.value).toBe(false);
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
        expect(vi.getTimerCount()).toBe(0);
    });

    it("bounds initial metadata loading without classifying read timeout as financial Unknown", async () => {
        vi.useFakeTimers();
        const metadata = deferred<never[]>();
        mocks.companyRows.value = [];
        mocks.loadQuery.mockImplementation((query) => query === "companies" ? metadata.promise : Promise.resolve([]));
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.advanceTimersByTimeAsync(20_000);
        await expect(pending).resolves.toBeUndefined();
        expect(mocks.ensure).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
        mocks.companyRows.value = [{ id: "local-b", legalName: "Ready later", registrationNumber: "123" }];
        metadata.resolve([]);
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
    });

    it("bounds many individually fast pages together, aborts all partial data and retries explicitly", async () => {
        vi.useFakeTimers();
        const signals: AbortSignal[] = [];
        const timeouts: number[] = [];
        mocks.list.mockImplementation((_bridge, _params, options) => {
            signals.push(options.signal); timeouts.push(options.timeout);
            // Deliberately ignore abort: the overall deadline must still settle the caller.
            return new Promise((resolve) => setTimeout(() => resolve({ data: [row], meta: { last_page: 100 } }), 7_000));
        });
        const { bank } = bankFor();
        mocks.loadQuery.mockClear();
        const pending = bank.load();
        const overlapping = bank.load();
        await vi.advanceTimersByTimeAsync(20_000);
        await Promise.all([pending, overlapping]);
        expect(timeouts).toHaveLength(3);
        expect(timeouts.slice(0, 2)).toEqual([10_000, 10_000]);
        expect(timeouts[2]).toBeGreaterThan(5_900);
        expect(timeouts[2]).toBeLessThanOrEqual(6_000);
        expect(new Set(signals).size).toBe(1);
        expect(signals.every((signal) => signal.aborted)).toBe(true);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(new Set(mocks.loadQuery.mock.calls.map(([query]) => query))).toEqual(new Set(["transactions", "matches", "documents", "expenses"]));
        await vi.advanceTimersByTimeAsync(2_000);
        expect(mocks.list).toHaveBeenCalledTimes(3);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
        mocks.list.mockResolvedValueOnce({ data: [row] });
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
        expect(vi.getTimerCount()).toBe(0);
    });

    it("yields between immediately resolved endless pages and checks the elapsed budget", async () => {
        vi.useFakeTimers();
        let clock = 0;
        const now = vi.spyOn(performance, "now").mockImplementation(() => clock);
        mocks.list.mockImplementation(async () => {
            clock += 3_000;
            return { data: [row], meta: { last_page: Number.MAX_SAFE_INTEGER } };
        });
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.advanceTimersByTimeAsync(20);
        await pending;
        expect(mocks.list).toHaveBeenCalledTimes(7);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
        now.mockRestore();
    });

    it("checks elapsed time even when the total timer has not fired and the adapter returns a late success", async () => {
        vi.useFakeTimers();
        let clock = 0;
        const now = vi.spyOn(performance, "now").mockImplementation(() => clock);
        mocks.list.mockImplementationOnce(async () => {
            clock = 20_001;
            return { data: [row] };
        });
        const { bank } = bankFor();
        await bank.load();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
        now.mockRestore();
    });

    it.each([false, true])("cleans its total timer and abort signal before financial import (fetch failure=%s)", async (fails) => {
        vi.useFakeTimers();
        let signal: AbortSignal | undefined;
        mocks.list.mockImplementationOnce(async (_bridge, _params, options) => {
            signal = options.signal;
            if (fails) throw new Error("offline");
            return { data: [row] };
        });
        mocks.importInbound.mockImplementationOnce(async () => {
            expect(signal?.aborted).toBe(true);
            expect(vi.getTimerCount()).toBe(0);
            return { imported: 1, auto_matched: 0 };
        });
        const { bank } = bankFor();
        await bank.load();
        expect(signal?.aborted).toBe(true);
        expect(vi.getTimerCount()).toBe(0);
        expect(mocks.importInbound).toHaveBeenCalledTimes(fails ? 0 : 1);
    });

    it("uses that same company bridge for Wise status and imported rows", async () => {
        const { bank } = bankFor();
        await bank.fetchWiseStatus();
        await bank.syncWise();
        expect(mocks.wiseStatus).toHaveBeenCalledWith("bridge-local-b");
        expect(mocks.wiseSync).toHaveBeenCalledWith("bridge-local-b", { local_first: true });
        expect(mocks.importWise).toHaveBeenCalledWith(expect.anything(), "local-b", [row], "wise", "wise-sync.json");
    });

    it("does not import a stale Wise response into the newly selected company", async () => {
        const response = deferred<{ rows: typeof row[] }>();
        mocks.wiseSync.mockReturnValue(response.promise);
        const { bank, company } = bankFor("local-a");
        const pending = bank.syncWise();
        await vi.waitFor(() => expect(mocks.wiseSync).toHaveBeenCalled());
        company.value = "local-b";
        await nextTick();
        response.resolve({ rows: [row] });
        expect(await pending).toEqual({ imported: 0, auto_matched: 0, skipped_duplicates: 0 });
        expect(mocks.importWise).not.toHaveBeenCalled();
    });

    it("keeps server-mode listing on its original company UUID", async () => {
        mocks.localFirst = false;
        const { bank } = bankFor("server-company-uuid");
        await bank.load();
        expect(mocks.list).toHaveBeenCalledWith("server-company-uuid", { per_page: 50 }, { signal: expect.any(AbortSignal) });
        expect(mocks.ensure).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
    });

    it.each([true, false])("keeps the latest same-company Wise status (local=%s)", async (local) => {
        mocks.localFirst = local;
        const first = deferred<{ connected: boolean; profile_id: number }>();
        mocks.wiseStatus.mockReturnValueOnce(first.promise).mockResolvedValueOnce({ connected: true, profile_id: 2 });
        const { bank } = bankFor();
        const stale = bank.fetchWiseStatus();
        await vi.waitFor(() => expect(mocks.wiseStatus).toHaveBeenCalledTimes(1));
        await bank.fetchWiseStatus();
        first.resolve({ connected: false, profile_id: 1 });
        await stale;
        expect(bank.wiseStatus.value).toEqual({ connected: true, profile_id: 2 });
    });

    it.each([true, false])("clears and invalidates Wise status across A-B-A (local=%s)", async (local) => {
        mocks.localFirst = local;
        const first = deferred<{ connected: boolean; profile_id: number }>();
        mocks.wiseStatus.mockReturnValueOnce(first.promise)
            .mockResolvedValueOnce({ connected: true, profile_id: 2 })
            .mockResolvedValueOnce({ connected: true, profile_id: 3 });
        const { bank, company } = bankFor("company-a");
        const stale = bank.fetchWiseStatus();
        await vi.waitFor(() => expect(mocks.wiseStatus).toHaveBeenCalledTimes(1));
        company.value = "company-b";
        expect(bank.wiseStatus.value).toBeNull();
        expect(bank.wiseBridgeChecked.value).toBe(false);
        await nextTick();
        await bank.fetchWiseStatus();
        expect(bank.wiseStatus.value?.profile_id).toBe(2);
        company.value = "company-a";
        expect(bank.wiseStatus.value).toBeNull();
        expect(bank.wiseBridgeChecked.value).toBe(false);
        await nextTick();
        await bank.fetchWiseStatus();
        first.resolve({ connected: false, profile_id: 1 });
        await stale;
        expect(bank.wiseStatus.value?.profile_id).toBe(3);
    });

    it.each([true, false])("does not clear a newer Wise status when an older request fails (local=%s)", async (local) => {
        mocks.localFirst = local;
        const first = deferred<{ connected: boolean }>();
        mocks.wiseStatus.mockReturnValueOnce(first.promise).mockResolvedValueOnce({ connected: true });
        const { bank } = bankFor();
        const stale = bank.fetchWiseStatus();
        await vi.waitFor(() => expect(mocks.wiseStatus).toHaveBeenCalledTimes(1));
        await bank.fetchWiseStatus();
        first.reject(new Error("synthetic stale request failure"));
        await stale;
        expect(bank.wiseStatus.value).toEqual({ connected: true });
    });

    it("ignores an old missing-bridge result before it can overwrite current Wise availability", async () => {
        const first = deferred<{ ok: true; bridgeCompanyId: null }>();
        mocks.ensure.mockReturnValueOnce(first.promise);
        const { bank } = bankFor();
        const stale = bank.fetchWiseStatus();
        await bank.fetchWiseStatus();
        first.resolve({ ok: true, bridgeCompanyId: null });
        await stale;
        expect(mocks.wiseStatus).toHaveBeenCalledTimes(1);
        expect(bank.wiseStatus.value).toEqual({ connected: true });
        expect(bank.wiseBridgeMissing.value).toBe(false);
        expect(bank.inboundEmailAvailable.value).toBe(true);
    });

    it("keeps current Wise availability when an older bank refresh resolves without a bridge", async () => {
        const first = deferred<{ ok: false }>();
        mocks.ensure.mockReturnValueOnce(first.promise);
        const { bank } = bankFor();
        const stale = bank.load();
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(1));
        await bank.fetchWiseStatus();
        first.resolve({ ok: false });
        await stale;
        expect(bank.wiseStatus.value).toEqual({ connected: true });
        expect(bank.wiseBridgeMissing.value).toBe(false);
        expect(bank.inboundEmailAvailable.value).toBe(true);
        expect(mocks.importInbound).not.toHaveBeenCalled();
    });
});
