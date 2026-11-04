import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { effectScope, nextTick, ref, type EffectScope } from "vue";
import { AxiosError } from "axios";
import type { OwnerId } from "@evolu/common/local-first";
import { clearCompanyShares, markCompanyShareRegistryReady, setCompanyShare } from "@/evolu/companyShareRegistry";

const mocks = vi.hoisted(() => ({
    localFirst: true,
    companyRows: { value: [] as { id: string; legalName: string; registrationNumber: string | null; ownerId?: string }[] },
    ensure: vi.fn(),
    fallback: vi.fn(),
    list: vi.fn(),
    inbound: vi.fn(),
    wiseStatus: vi.fn(),
    wiseConnect: vi.fn(),
    wiseSync: vi.fn(),
    importInbound: vi.fn(),
    importWise: vi.fn(),
    autoMatch: vi.fn(),
    loadQuery: vi.fn(),
}));

vi.mock("@evolu/vue", async () => {
    const { ref, shallowRef, watch, onScopeDispose } = await import("vue");
    mocks.companyRows = ref([]);
    return { useQuery: (query: string) => {
        if (query !== "companies") return { value: [] };
        // Evolu owns one shallow snapshot per hook and unsubscribes it at scope disposal.
        const copyRows = () => mocks.companyRows.value.map((row) => ({ ...row }));
        const rows = shallowRef(copyRows());
        const unsubscribe = watch(mocks.companyRows, () => { rows.value = copyRows(); }, { deep: true, flush: "sync" });
        onScopeDispose(unsubscribe);
        return rows;
    } };
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
        wise: { status: mocks.wiseStatus, connect: mocks.wiseConnect, sync: mocks.wiseSync },
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
    return { bank, company, scope };
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
    clearCompanyShares(); markCompanyShareRegistryReady();
    mocks.localFirst = true;
    mocks.companyRows.value = ["local-a", "local-b", "company-a", "company-b"].map((id) => ({
        id, legalName: `Company ${id}`, registrationNumber: "123",
    }));
    mocks.loadQuery.mockResolvedValue([]);
    mocks.ensure.mockImplementation(async (id: string) => ({ ok: true,
        bridgeCompanyId: mocks.companyRows.value.find((company) => company.id === id)?.legalName.trim() ? `bridge-${id}` : null,
    }));
    mocks.fallback.mockResolvedValue("wrong-first-company");
    mocks.list.mockResolvedValue({ data: [row], meta: { last_page: 1 } });
    mocks.importInbound.mockResolvedValue({ imported: 1, auto_matched: 0 });
    mocks.importWise.mockResolvedValue({ imported: 1, auto_matched: 0 });
    mocks.autoMatch.mockResolvedValue({ auto_matched: 1 });
    mocks.wiseStatus.mockResolvedValue({ connected: true });
    mocks.wiseConnect.mockResolvedValue(undefined);
    mocks.wiseSync.mockResolvedValue({ rows: [row] });
});

afterEach(() => {
    scopes.splice(0).forEach((scope) => scope.stop());
    clearCompanyShares();
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
        expect(mocks.ensure).toHaveBeenCalledWith("local-b", expect.any(Function), { preferRecordedBridgeId: true });
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

    it("starts fresh work on A-B-A selection and keeps that work shared after the obsolete A settles", async () => {
        const oldA = deferred<{ data: typeof row[] }>();
        const freshA = deferred<{ data: typeof row[] }>();
        const freshRow = { ...row, id: "current-a" };
        let aRequests = 0;
        mocks.list.mockImplementation((bridge) => bridge === "bridge-local-a"
            ? ++aRequests === 1 ? oldA.promise : freshA.promise
            : Promise.resolve({ data: [] }));
        const { bank, company } = bankFor("local-a");
        const obsolete = bank.load();
        await vi.waitFor(() => expect(aRequests).toBe(1));
        company.value = "local-b";
        await bank.load();
        company.value = "local-a";
        const current = bank.load();
        await vi.waitFor(() => expect(aRequests).toBe(2));
        oldA.resolve({ data: [row] });
        await obsolete;
        expect(mocks.importInbound).not.toHaveBeenCalled();
        const overlapping = bank.load();
        await Promise.resolve(); await Promise.resolve();
        expect(aRequests).toBe(2);
        freshA.resolve({ data: [freshRow] });
        await Promise.all([current, overlapping]);
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-a", [freshRow]);
    });

    it("starts fresh work after an identity edit and preserves its promise when obsolete work settles", async () => {
        const oldRead = deferred<{ data: typeof row[] }>();
        const newRead = deferred<{ data: typeof row[] }>();
        mocks.list.mockReturnValueOnce(oldRead.promise).mockReturnValueOnce(newRead.promise);
        const { bank } = bankFor();
        const old = bank.load();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(1));
        mocks.companyRows.value.find((row) => row.id === "local-b")!.legalName = "New legal identity";
        const current = bank.load();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(2));
        oldRead.resolve({ data: [row] });
        await old;
        expect(mocks.importInbound).not.toHaveBeenCalled();
        const overlapping = bank.load();
        await Promise.resolve(); await Promise.resolve();
        expect(mocks.list).toHaveBeenCalledTimes(2);
        const fresh = { ...row, id: "fresh-identity" };
        newRead.resolve({ data: [fresh] });
        await Promise.all([current, overlapping]);
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-b", [fresh]);
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
        expect(mocks.ensure).toHaveBeenCalledTimes(3);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.wiseStatus.value).toBeNull();
        expect(bank.wiseBridgeMissing.value).toBe(true);
        // The rejected startup promise must not poison later subscribed metadata.
        mocks.companyRows.value = [{ id: "local-b", legalName: "Recovered company", registrationNumber: "123" }];
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-b", [row]);
        expect(mocks.ensure).toHaveBeenCalledTimes(4);
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

    it.each(["lookup", "page"] as const)("settles a disposed %s read immediately and ignores stale same-company identity results", async (phase) => {
        vi.useFakeTimers();
        const lookup = deferred<{ ok: true; bridgeCompanyId: string }>();
        const page = deferred<{ data: typeof row[] }>();
        let signal: AbortSignal | undefined;
        if (phase === "lookup") mocks.ensure.mockImplementationOnce((_id, controls) => {
            signal = controls().signal;
            return lookup.promise;
        });
        else mocks.list.mockImplementationOnce((_bridge, _params, options) => {
            signal = options.signal;
            return page.promise;
        });
        const { bank, scope } = bankFor();
        const pending = bank.load();
        const overlapping = bank.load();
        await vi.advanceTimersByTimeAsync(0);
        expect(signal?.aborted).toBe(false);
        expect(vi.getTimerCount()).toBe(1);
        mocks.loadQuery.mockClear();
        scope.stop();
        // The per-hook snapshot is now unsubscribed, exactly as on CompanySettings navigation.
        mocks.companyRows.value = [{ id: "local-b", legalName: "Edited after unmount", registrationNumber: "456" }];
        await Promise.all([pending, overlapping]);
        expect(signal?.aborted).toBe(true);
        expect(vi.getTimerCount()).toBe(0);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
        lookup.resolve({ ok: true, bridgeCompanyId: "old-identity-bridge" });
        page.resolve({ data: [row] });
        await vi.advanceTimersByTimeAsync(20_001);
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
        expect(mocks.list).toHaveBeenCalledTimes(phase === "page" ? 1 : 0);
        expect(mocks.loadQuery).not.toHaveBeenCalled();
        await bank.load(); await bank.loadBatches(); await bank.loadInbound(); await bank.fetchWiseStatus();
        expect(mocks.loadQuery).not.toHaveBeenCalled();
        expect(mocks.ensure).toHaveBeenCalledTimes(1);
        expect(bank.wiseBridgeChecked.value).toBe(false);
        expect(vi.getTimerCount()).toBe(0);
    });

    it("settles disposal while initial metadata never resolves, without waiting for its deadline", async () => {
        vi.useFakeTimers();
        const metadata = deferred<never[]>();
        mocks.companyRows.value = [];
        mocks.loadQuery.mockImplementation((query) => query === "companies" ? metadata.promise : Promise.resolve([]));
        const { bank, scope } = bankFor();
        const pending = bank.load();
        await vi.advanceTimersByTimeAsync(0);
        expect(vi.getTimerCount()).toBe(1);
        scope.stop();
        await expect(pending).resolves.toBeUndefined();
        expect(vi.getTimerCount()).toBe(0);
        mocks.companyRows.value = [{ id: "local-b", legalName: "New identity", registrationNumber: "456" }];
        metadata.resolve([]);
        await vi.advanceTimersByTimeAsync(0);
        expect(mocks.ensure).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
    });

    it.each(["success", "unknown"] as const)("keeps awaiting an already-started financial import after disposal (%s)", async (outcome) => {
        vi.useFakeTimers();
        const importing = deferred<{ imported: number; auto_matched: number }>();
        mocks.importInbound.mockReturnValueOnce(importing.promise);
        const { bank, scope } = bankFor();
        const pending = bank.load();
        const observed = pending.then(() => ({ ok: true }), (error) => ({ error }));
        let settled = false;
        void observed.then(() => { settled = true; });
        await vi.advanceTimersByTimeAsync(0);
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
        expect(vi.getTimerCount()).toBe(0);
        mocks.loadQuery.mockClear();
        scope.stop();
        await vi.advanceTimersByTimeAsync(30_000);
        expect(settled).toBe(false);
        const unknown = new BankReconciliationUnknownError();
        if (outcome === "unknown") importing.reject(unknown);
        else importing.resolve({ imported: 1, auto_matched: 0 });
        expect(await observed).toEqual(outcome === "unknown" ? { error: unknown } : { ok: true });
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
        expect(mocks.loadQuery).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
    });

    it("ignores a late Wise HTTP response after disposal and never starts a background inbound refresh", async () => {
        const wise = deferred<{ rows: typeof row[] }>();
        mocks.wiseSync.mockReturnValueOnce(wise.promise);
        const { bank, scope } = bankFor();
        const pending = bank.syncWise();
        await vi.waitFor(() => expect(mocks.wiseSync).toHaveBeenCalledTimes(1));
        mocks.loadQuery.mockClear();
        scope.stop();
        wise.resolve({ rows: [row] });
        await expect(pending).resolves.toEqual({ imported: 0, auto_matched: 0, skipped_duplicates: 0 });
        expect(mocks.importWise).not.toHaveBeenCalled();
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.loadQuery).not.toHaveBeenCalled();
    });

    it("does not restart reads after an already-started Wise import finishes on a disposed hook", async () => {
        const importing = deferred<{ imported: number; auto_matched: number }>();
        mocks.importWise.mockReturnValueOnce(importing.promise);
        const { bank, scope } = bankFor();
        const pending = bank.syncWise();
        await vi.waitFor(() => expect(mocks.importWise).toHaveBeenCalledTimes(1));
        mocks.loadQuery.mockClear();
        scope.stop();
        importing.resolve({ imported: 1, auto_matched: 0 });
        await expect(pending).resolves.toEqual({ imported: 1, auto_matched: 0, skipped_duplicates: 0 });
        expect(mocks.importWise).toHaveBeenCalledTimes(1);
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.loadQuery).not.toHaveBeenCalled();
    });

    it.each(["selection", "identity"] as const)("preserves completed Wise import counts without starting new reads after a %s change", async (change) => {
        const importing = deferred<{ imported: number; auto_matched: number }>();
        mocks.importWise.mockReturnValueOnce(importing.promise);
        mocks.wiseSync.mockResolvedValueOnce({ rows: [row], skipped_duplicates: 2 });
        const { bank, company } = bankFor("local-a");
        const pending = bank.syncWise();
        await vi.waitFor(() => expect(mocks.importWise).toHaveBeenCalledTimes(1));
        mocks.loadQuery.mockClear();
        if (change === "selection") company.value = "local-b";
        else mocks.companyRows.value.find((row) => row.id === "local-a")!.legalName = "Edited after import entry";
        importing.resolve({ imported: 1, auto_matched: 1 });
        await expect(pending).resolves.toEqual({ imported: 1, auto_matched: 1, skipped_duplicates: 2 });
        expect(mocks.importWise).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-a", [row], "wise", "wise-sync.json");
        expect(mocks.ensure).toHaveBeenCalledTimes(1);
        expect(mocks.list).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.loadQuery).not.toHaveBeenCalled();
    });

    it("skips the batch stage if context changes during the post-Wise inbound read", async () => {
        const email = deferred<{ data: typeof row[] }>();
        mocks.list.mockReturnValueOnce(email.promise);
        const { bank, company } = bankFor("local-a");
        mocks.loadQuery.mockClear();
        const pending = bank.syncWise();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(1));
        expect(mocks.importWise).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-a", [row], "wise", "wise-sync.json");
        company.value = "local-b";
        email.resolve({ data: [row] });
        await expect(pending).resolves.toEqual({ imported: 1, auto_matched: 0, skipped_duplicates: 0 });
        expect(mocks.list).toHaveBeenCalledExactlyOnceWith("bridge-local-a", expect.anything(), expect.anything());
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.loadQuery.mock.calls.filter(([query]) => query === "batches")).toHaveLength(0);
    });

    it("waits for initial trusted share readiness within the existing read budget", async () => {
        vi.useFakeTimers(); clearCompanyShares();
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.advanceTimersByTimeAsync(5_000);
        expect(mocks.ensure).not.toHaveBeenCalled();
        markCompanyShareRegistryReady();
        await pending;
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-b", [row]);
        expect(vi.getTimerCount()).toBe(0);
    });

    it("bounds a never-ready share registry and imports only after an explicit ready retry", async () => {
        vi.useFakeTimers(); clearCompanyShares();
        const { bank } = bankFor();
        const pending = bank.load();
        await vi.advanceTimersByTimeAsync(20_000);
        await pending;
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.ensure).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
        markCompanyShareRegistryReady();
        await Promise.resolve(); await Promise.resolve();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        await bank.load();
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
    });

    it("cleans a readiness wait immediately on disposal without a late import when bootstrap finishes", async () => {
        vi.useFakeTimers(); clearCompanyShares();
        const { bank, scope } = bankFor();
        const pending = bank.load();
        await vi.advanceTimersByTimeAsync(0);
        scope.stop();
        await pending;
        expect(vi.getTimerCount()).toBe(0);
        markCompanyShareRegistryReady();
        await Promise.resolve(); await Promise.resolve();
        expect(mocks.ensure).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
    });

    it("rejects an old connect intent after initial metadata A-B-A navigation, then connects only a new ready intent", async () => {
        const metadata = deferred<never[]>();
        mocks.companyRows.value = [];
        mocks.loadQuery.mockImplementation((query) => query === "companies" ? metadata.promise : Promise.resolve([]));
        const { bank, company } = bankFor("local-a");
        const obsolete = bank.connectWise("synthetic-old-intent").catch((error) => error);
        company.value = "local-b"; company.value = "local-a";
        mocks.companyRows.value = [{ id: "local-a", legalName: "Loaded company", registrationNumber: "123" }];
        metadata.resolve([]);
        expect(await obsolete).toMatchObject({ message: "bank_bridge_lookup_failed" });
        expect(mocks.wiseConnect).not.toHaveBeenCalled();
        await bank.connectWise("synthetic-current-intent");
        expect(mocks.wiseConnect).toHaveBeenCalledExactlyOnceWith("bridge-local-a", "synthetic-current-intent");
    });

    it("reports the importer's actual inserted rows and sums distinct bridge/local duplicates", async () => {
        mocks.wiseSync.mockResolvedValueOnce({ rows: [row, { ...row, id: "second-row" }], skipped_duplicates: 2 });
        mocks.importWise.mockResolvedValueOnce({ imported: 1, auto_matched: 1, skipped_duplicates: 1 });
        const { bank } = bankFor();
        await expect(bank.syncWise()).resolves.toEqual({ imported: 1, auto_matched: 1, skipped_duplicates: 3 });
    });

    it("reports an empty Wise response without invoking the local importer", async () => {
        mocks.wiseSync.mockResolvedValueOnce({ rows: [], skipped_duplicates: 2 });
        const { bank } = bankFor();
        await expect(bank.syncWise()).resolves.toEqual({ imported: 0, auto_matched: 0, skipped_duplicates: 2 });
        expect(mocks.importWise).not.toHaveBeenCalled();
    });

    it.each(["failed", "throws", "missing"] as const)("distinguishes a %s bridge while synchronizing Wise", async (outcome) => {
        if (outcome === "throws") mocks.ensure.mockRejectedValueOnce(new Error("synthetic lookup failure"));
        else mocks.ensure.mockResolvedValueOnce(outcome === "failed" ? { ok: false } : { ok: true, bridgeCompanyId: null });
        const { bank } = bankFor();
        if (outcome === "missing") await expect(bank.syncWise()).resolves.toEqual({ imported: 0, auto_matched: 0, skipped_duplicates: 0 });
        else await expect(bank.syncWise()).rejects.toThrow("bank_bridge_lookup_failed");
        expect(mocks.wiseSync).not.toHaveBeenCalled();
        expect(mocks.importWise).not.toHaveBeenCalled();
    });

    it.each([true, false])("keeps status failure distinct through an explicit pending retry (local=%s)", async (local) => {
        mocks.localFirst = local;
        mocks.wiseStatus.mockRejectedValueOnce(new Error("synthetic status failure"));
        const { bank } = bankFor();
        await bank.fetchWiseStatus();
        expect(bank.wiseStatusFailed.value).toBe(true);
        expect(bank.bridgeLookupFailed.value).toBe(false);
        const retry = deferred<{ connected: boolean }>();
        mocks.wiseStatus.mockReturnValueOnce(retry.promise);
        const pending = bank.fetchWiseStatus();
        await vi.waitFor(() => expect(mocks.wiseStatus).toHaveBeenCalledTimes(2));
        expect(bank.wiseStatusFailed.value).toBe(true);
        retry.resolve({ connected: true });
        await pending;
        expect(bank.wiseStatusFailed.value).toBe(false);
        expect(bank.wiseStatus.value).toEqual({ connected: true });
    });

    it.each(["status", "connect", "sync"] as const)("bounds an initial metadata wait for Wise %s, ignores late resolution and permits explicit retry", async (operation) => {
        vi.useFakeTimers();
        const metadata = deferred<never[]>();
        mocks.companyRows.value = [];
        mocks.loadQuery.mockImplementation((query) => query === "companies" ? metadata.promise : Promise.resolve([]));
        const { bank } = bankFor();
        const invoke = () => operation === "status" ? bank.fetchWiseStatus() : operation === "connect" ? bank.connectWise("synthetic-intent") : bank.syncWise();
        const observed = invoke().catch((error) => error);
        await vi.advanceTimersByTimeAsync(20_000);
        const outcome = await observed;
        if (operation !== "status") expect(outcome).toMatchObject({ message: "bank_bridge_lookup_failed" });
        expect(bank.bridgeLookupFailed.value).toBe(true);
        expect(bank.wiseStatusFailed.value).toBe(false);
        expect(mocks.ensure).not.toHaveBeenCalled();
        expect(mocks.wiseConnect).not.toHaveBeenCalled();
        expect(mocks.wiseSync).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(0);
        mocks.companyRows.value = [{ id: "local-b", legalName: "Ready later", registrationNumber: "123" }];
        metadata.resolve([]);
        await vi.advanceTimersByTimeAsync(0);
        expect(mocks.ensure).not.toHaveBeenCalled();
        await invoke();
        expect(bank.bridgeLookupFailed.value).toBe(false);
        expect(vi.getTimerCount()).toBe(0);
    });

    it("bounds a Wise status GET in the same budget, rejects its late success and retries only explicitly", async () => {
        vi.useFakeTimers();
        const status = deferred<{ connected: boolean }>();
        let signal: AbortSignal | undefined;
        mocks.wiseStatus.mockImplementationOnce((_bridge, options) => { signal = options.signal; return status.promise; });
        const { bank } = bankFor();
        const pending = bank.fetchWiseStatus();
        await vi.advanceTimersByTimeAsync(20_000);
        await pending;
        expect(signal?.aborted).toBe(true);
        expect(bank.wiseStatusFailed.value).toBe(true);
        expect(bank.bridgeLookupFailed.value).toBe(false);
        expect(bank.wiseStatus.value).toBeNull();
        expect(vi.getTimerCount()).toBe(0);
        status.resolve({ connected: true });
        await vi.advanceTimersByTimeAsync(0);
        expect(bank.wiseStatus.value).toBeNull();
        expect(bank.wiseStatusFailed.value).toBe(true);
        await bank.fetchWiseStatus();
        expect(bank.wiseStatusFailed.value).toBe(false);
        expect(bank.wiseStatus.value).toEqual({ connected: true });
        expect(vi.getTimerCount()).toBe(0);
    });

    it("shares one deadline between a slow resolution and its subsequent Wise status GET", async () => {
        vi.useFakeTimers();
        mocks.ensure.mockImplementationOnce(() => new Promise((resolve) => setTimeout(() => resolve({ ok: true, bridgeCompanyId: "bridge-local-b" }), 15_000)));
        let timeout: number | undefined;
        mocks.wiseStatus.mockImplementationOnce((_bridge, options) => { timeout = options.timeout; return new Promise(() => {}); });
        const { bank } = bankFor();
        const pending = bank.fetchWiseStatus();
        await vi.advanceTimersByTimeAsync(15_000);
        expect(timeout).toBe(5_000);
        await vi.advanceTimersByTimeAsync(5_000);
        await pending;
        expect(bank.wiseStatusFailed.value).toBe(true);
        expect(bank.bridgeLookupFailed.value).toBe(false);
        expect(vi.getTimerCount()).toBe(0);
    });

    it("ends the read deadline before an entered credential POST and awaits that POST without cancellation", async () => {
        vi.useFakeTimers();
        const posting = deferred<void>();
        mocks.wiseConnect.mockReturnValueOnce(posting.promise);
        const { bank } = bankFor();
        const pending = bank.connectWise("synthetic-current-intent");
        let settled = false; void pending.then(() => { settled = true; });
        await vi.advanceTimersByTimeAsync(0);
        expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
        expect(vi.getTimerCount()).toBe(0);
        await vi.advanceTimersByTimeAsync(30_000);
        expect(settled).toBe(false);
        expect(mocks.wiseStatus).not.toHaveBeenCalled();
        posting.resolve();
        await pending;
        expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
        expect(mocks.wiseStatus).toHaveBeenCalledTimes(1);
        expect(vi.getTimerCount()).toBe(0);
    });

    it("orders entered local connects across A-B-A so a newer explicit credential cannot be overwritten", async () => {
        const entered = deferred<void>(); let stored = "";
        mocks.wiseConnect.mockImplementationOnce(async () => { await entered.promise; stored = "synthetic-old"; })
            .mockImplementationOnce(async () => { stored = "synthetic-new"; });
        const { bank, company } = bankFor("local-a");
        const old = bank.connectWise("synthetic-old");
        await vi.waitFor(() => expect(mocks.wiseConnect).toHaveBeenCalledTimes(1));
        company.value = "local-b"; company.value = "local-a";
        const fresh = bank.connectWise("synthetic-new");
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(2));
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
        entered.resolve(); await Promise.all([old, fresh]);
        expect(stored).toBe("synthetic-new");
        expect(mocks.wiseConnect.mock.calls).toEqual([["bridge-local-a", "synthetic-old"], ["bridge-local-a", "synthetic-new"]]);
        expect(mocks.wiseStatus).toHaveBeenCalledTimes(1);
    });

    it("does not dispatch a queued local intent made stale by A-B-A or block its fresh successor", async () => {
        const entered = deferred<void>();
        mocks.wiseConnect.mockReturnValueOnce(entered.promise);
        const { bank, company } = bankFor();
        const first = bank.connectWise("synthetic-entered");
        await vi.waitFor(() => expect(mocks.wiseConnect).toHaveBeenCalledTimes(1));
        const stale = bank.connectWise("synthetic-stale").catch((error) => error);
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(2));
        await new Promise((resolve) => setTimeout(resolve, 0));
        company.value = "local-a"; company.value = "local-b";
        const fresh = bank.connectWise("synthetic-fresh");
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(3));
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
        entered.resolve(); await first;
        expect(await stale).toMatchObject({ message: "bank_bridge_lookup_failed" });
        await fresh;
        expect(mocks.wiseConnect.mock.calls).toEqual([["bridge-local-b", "synthetic-entered"], ["bridge-local-b", "synthetic-fresh"]]);
    });

    it("shares a bridge gate with a fresh server hook after the original local view is disposed", async () => {
        const bridgeId = "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b";
        mocks.ensure.mockResolvedValue({ ok: true, bridgeCompanyId: bridgeId });
        const entered = deferred<void>();
        mocks.wiseConnect.mockReturnValueOnce(entered.promise);
        const first = bankFor("local-a");
        const old = first.bank.connectWise("synthetic-local");
        await vi.waitFor(() => expect(mocks.wiseConnect).toHaveBeenCalledTimes(1));
        first.scope.stop();
        mocks.localFirst = false;
        const next = bankFor(bridgeId);
        const fresh = next.bank.connectWise("synthetic-server");
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
        entered.resolve(); await Promise.all([old, fresh]);
        expect(mocks.wiseConnect.mock.calls).toEqual([[bridgeId, "synthetic-local"], [bridgeId, "synthetic-server"]]);
        expect(mocks.wiseStatus).toHaveBeenCalledExactlyOnceWith(bridgeId);
    });

    it("uses that same company bridge for Wise status and imported rows", async () => {
        const { bank } = bankFor();
        await bank.fetchWiseStatus();
        mocks.loadQuery.mockClear();
        await bank.syncWise();
        expect(mocks.list).toHaveBeenCalledTimes(1);
        expect(mocks.importInbound).toHaveBeenCalledTimes(1);
        expect(mocks.loadQuery.mock.calls.filter(([query]) => query === "batches")).toHaveLength(1);
        expect(mocks.wiseStatus).toHaveBeenCalledWith("bridge-local-b", expect.objectContaining({ signal: expect.any(AbortSignal), timeout: expect.any(Number), skipErrorFlash: true }));
        expect(mocks.wiseSync).toHaveBeenCalledWith("bridge-local-b", { local_first: true });
        expect(mocks.importWise).toHaveBeenCalledWith(expect.anything(), "local-b", [row], "wise", "wise-sync.json");
    });

    it.each(["legalName", "registrationNumber", "identity-aba", "selection-aba"] as const)("discards a Wise response after %s changes", async (change) => {
        const wise = deferred<{ rows: typeof row[] }>();
        mocks.wiseSync.mockReturnValueOnce(wise.promise);
        const { bank, company } = bankFor();
        const pending = bank.syncWise();
        await vi.waitFor(() => expect(mocks.wiseSync).toHaveBeenCalledTimes(1));
        const metadata = mocks.companyRows.value.find((row) => row.id === "local-b")!;
        if (change === "selection-aba") {
            company.value = "local-a"; company.value = "local-b";
        } else if (change === "identity-aba") {
            const oldName = metadata.legalName;
            metadata.legalName = "Other"; metadata.legalName = oldName;
        } else metadata[change] = "Other identity";
        wise.resolve({ rows: [row] });
        await expect(pending).resolves.toEqual({ imported: 0, auto_matched: 0, skipped_duplicates: 0 });
        expect(mocks.importWise).not.toHaveBeenCalled();
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.list).not.toHaveBeenCalled();
    });

    it("keeps Wise normalized-identity formatting edits and already-started import outcomes", async () => {
        const wise = deferred<{ rows: typeof row[] }>();
        const importing = deferred<{ imported: number; auto_matched: number }>();
        mocks.wiseSync.mockReturnValueOnce(wise.promise);
        mocks.importWise.mockReturnValueOnce(importing.promise);
        const { bank } = bankFor();
        const pending = bank.syncWise();
        await vi.waitFor(() => expect(mocks.wiseSync).toHaveBeenCalledTimes(1));
        const metadata = mocks.companyRows.value.find((row) => row.id === "local-b")!;
        metadata.legalName = `  ${metadata.legalName.toUpperCase()}  `;
        wise.resolve({ rows: [row] });
        await vi.waitFor(() => expect(mocks.importWise).toHaveBeenCalledTimes(1));
        metadata.legalName = "Edited after financial entry";
        importing.resolve({ imported: 1, auto_matched: 0 });
        await expect(pending).resolves.toMatchObject({ imported: 1 });
        expect(mocks.importWise).toHaveBeenCalledTimes(1);
    });

    it.each([false, true])("exposes a retryable bridge failure without claiming the bridge is missing (helper throws=%s)", async (throws) => {
        if (throws) mocks.ensure.mockRejectedValueOnce(new Error("synthetic lookup failure"));
        else mocks.ensure.mockResolvedValueOnce({ ok: false });
        const { bank } = bankFor();
        await bank.fetchWiseStatus();
        expect(bank.bridgeLookupFailed.value).toBe(true);
        expect(bank.wiseBridgeMissing.value).toBe(false);
        expect(bank.wiseBridgeChecked.value).toBe(false);
        expect(mocks.wiseStatus).not.toHaveBeenCalled();
        await bank.fetchWiseStatus();
        expect(bank.bridgeLookupFailed.value).toBe(false);
        expect(bank.wiseBridgeMissing.value).toBe(false);
        expect(bank.wiseBridgeChecked.value).toBe(true);
        expect(bank.wiseStatus.value).toEqual({ connected: true });
    });

    it("keeps confirmed missing bridge distinct from a transient lookup failure", async () => {
        const { bank } = bankFor();
        mocks.ensure.mockResolvedValueOnce({ ok: false });
        await bank.fetchWiseStatus();
        mocks.ensure.mockResolvedValueOnce({ ok: true, bridgeCompanyId: null });
        await bank.fetchWiseStatus();
        expect(bank.bridgeLookupFailed.value).toBe(false);
        expect(bank.wiseBridgeMissing.value).toBe(true);
        expect(bank.wiseBridgeChecked.value).toBe(true);
    });

    it("does not let an obsolete lookup clear the current failure state", async () => {
        const oldLookup = deferred<{ ok: true; bridgeCompanyId: string }>();
        mocks.ensure.mockReturnValueOnce(oldLookup.promise).mockResolvedValueOnce({ ok: false });
        const { bank } = bankFor();
        const old = bank.fetchWiseStatus();
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(1));
        await bank.fetchWiseStatus();
        expect(bank.bridgeLookupFailed.value).toBe(true);
        oldLookup.resolve({ ok: true, bridgeCompanyId: "obsolete" });
        await old;
        expect(bank.bridgeLookupFailed.value).toBe(true);
        expect(bank.inboundEmailAvailable.value).toBe(false);
    });

    it.each(["lookup", "page", "wise"] as const)("discards an obsolete %s route after recorded pin A-B-A changes", async (phase) => {
        const pin = "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b";
        const share = { companyId: "local-b", ownerId: "synthetic-shared" as OwnerId, role: "member" as const, status: "active" as const, bridgeCompanyId: pin };
        setCompanyShare(share);
        const lookup = deferred<{ ok: true; bridgeCompanyId: string }>();
        const response = deferred<{ data: typeof row[]; rows: typeof row[] }>();
        if (phase === "lookup") mocks.ensure.mockReturnValueOnce(lookup.promise);
        else mocks.ensure.mockResolvedValue({ ok: true, bridgeCompanyId: pin });
        if (phase === "page") mocks.list.mockReturnValueOnce(response.promise);
        if (phase === "wise") mocks.wiseSync.mockReturnValueOnce(response.promise);
        const { bank } = bankFor();
        const routing = bank.bridgeRoutingKey.value;
        const pending = phase === "wise" ? bank.syncWise() : bank.load();
        await vi.waitFor(() => expect(phase === "lookup" ? mocks.ensure : phase === "page" ? mocks.list : mocks.wiseSync).toHaveBeenCalledTimes(1));
        setCompanyShare({ ...share, bridgeCompanyId: "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6c" });
        setCompanyShare(share);
        expect(bank.bridgeRoutingKey.value).toBe(routing);
        lookup.resolve({ ok: true, bridgeCompanyId: pin });
        response.resolve({ data: [row], rows: [row] });
        await pending;
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.importWise).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
        expect(bank.bridgeLookupFailed.value).toBe(false);
    });

    it("keeps selected routing and imports valid data when another company's share changes", async () => {
        const response = deferred<{ data: typeof row[] }>();
        mocks.list.mockReturnValueOnce(response.promise);
        const { bank } = bankFor();
        const routing = bank.bridgeRoutingKey.value;
        const pending = bank.load();
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledTimes(1));
        setCompanyShare({ companyId: "unrelated", ownerId: "other-owner" as OwnerId, role: "member", status: "active", bridgeCompanyId: "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b" });
        expect(bank.bridgeRoutingKey.value).toBe(routing);
        response.resolve({ data: [row] });
        await pending;
        expect(mocks.importInbound).toHaveBeenCalledExactlyOnceWith(expect.anything(), "local-b", [row]);
    });

    it.each(["lookup", "page", "wise"] as const)("discards a pending %s result when selected company ownership or uniqueness changes", async (phase) => {
        const lookup = deferred<{ ok: true; bridgeCompanyId: string }>();
        const response = deferred<{ data: typeof row[]; rows: typeof row[] }>();
        if (phase === "lookup") mocks.ensure.mockReturnValueOnce(lookup.promise);
        if (phase === "page") mocks.list.mockReturnValueOnce(response.promise);
        if (phase === "wise") mocks.wiseSync.mockReturnValueOnce(response.promise);
        const { bank } = bankFor();
        const initial = bank.bridgeRoutingKey.value;
        const pending = phase === "wise" ? bank.syncWise() : bank.load();
        await vi.waitFor(() => expect(phase === "lookup" ? mocks.ensure : phase === "page" ? mocks.list : mocks.wiseSync).toHaveBeenCalledTimes(1));
        if (phase === "page") mocks.companyRows.value.push({ ...mocks.companyRows.value.find((row) => row.id === "local-b")!, ownerId: "duplicate-shared" });
        else mocks.companyRows.value.find((row) => row.id === "local-b")!.ownerId = "unbound-shared";
        expect(bank.bridgeRoutingKey.value).not.toBe(initial);
        lookup.resolve({ ok: true, bridgeCompanyId: "old-owner-bridge" });
        response.resolve({ data: [row], rows: [row] });
        await pending;
        expect(mocks.importInbound).not.toHaveBeenCalled();
        expect(mocks.importWise).not.toHaveBeenCalled();
        expect(bank.inboundEmailAvailable.value).toBe(false);
    });

    it.each(["failed", "missing", "throws"] as const)("rejects a %s bridge before connecting Wise without a request or retry", async (outcome) => {
        if (outcome === "throws") mocks.ensure.mockRejectedValueOnce(new Error("synthetic lookup failure"));
        else mocks.ensure.mockResolvedValueOnce(outcome === "failed" ? { ok: false } : { ok: true, bridgeCompanyId: null });
        const { bank } = bankFor();
        await expect(bank.connectWise("synthetic-token-fixture")).rejects.toThrow("bank_bridge_lookup_failed");
        expect(mocks.wiseConnect).not.toHaveBeenCalled();
        expect(mocks.wiseStatus).not.toHaveBeenCalled();
        expect(mocks.ensure).toHaveBeenCalledTimes(1);
    });

    it("connects Wise once through a verified route and refreshes its status", async () => {
        const { bank } = bankFor();
        await bank.connectWise("synthetic-token-fixture");
        expect(mocks.wiseConnect).toHaveBeenCalledExactlyOnceWith("bridge-local-b", "synthetic-token-fixture");
        expect(mocks.wiseStatus).toHaveBeenCalledExactlyOnceWith("bridge-local-b", expect.objectContaining({ signal: expect.any(AbortSignal), timeout: expect.any(Number), skipErrorFlash: true }));
        expect(mocks.ensure).toHaveBeenCalledTimes(2);
    });

    it.each(["success", "failure"] as const)("preserves an entered Wise connect %s across context changes without starting a new lookup", async (outcome) => {
        const posting = deferred<void>();
        mocks.wiseConnect.mockReturnValueOnce(posting.promise);
        const { bank } = bankFor();
        const pending = bank.connectWise("synthetic-token-fixture");
        const observed = pending.then(() => ({ ok: true }), (error) => ({ error }));
        await vi.waitFor(() => expect(mocks.wiseConnect).toHaveBeenCalledTimes(1));
        mocks.companyRows.value.find((company) => company.id === "local-b")!.legalName = "Changed after POST";
        const failure = new Error("synthetic connect outcome");
        if (outcome === "failure") posting.reject(failure);
        else posting.resolve();
        expect(await observed).toEqual(outcome === "failure" ? { error: failure } : { ok: true });
        expect(mocks.ensure).toHaveBeenCalledTimes(1);
        expect(mocks.wiseStatus).not.toHaveBeenCalled();
        expect(mocks.wiseConnect).toHaveBeenCalledTimes(1);
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
        expect(bank.wiseStatusFailed.value).toBe(false);
    });

    it("ignores an old missing-bridge result before it can overwrite current Wise availability", async () => {
        const first = deferred<{ ok: true; bridgeCompanyId: null }>();
        mocks.ensure.mockReturnValueOnce(first.promise);
        const { bank } = bankFor();
        const stale = bank.fetchWiseStatus();
        await vi.waitFor(() => expect(mocks.ensure).toHaveBeenCalledTimes(1));
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
