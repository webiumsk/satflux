import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { effectScope, nextTick, ref, type EffectScope } from "vue";

const mocks = vi.hoisted(() => ({
    localFirst: true,
    ensure: vi.fn(),
    fallback: vi.fn(),
    list: vi.fn(),
    inbound: vi.fn(),
    wiseStatus: vi.fn(),
    wiseSync: vi.fn(),
    importInbound: vi.fn(),
    importWise: vi.fn(),
    loadQuery: vi.fn(),
}));

vi.mock("@evolu/vue", () => ({ useQuery: () => ({ value: [] }) }));
vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches",
    allBankTransactionMatchesQuery: "matches",
    allBankTransactionsQuery: "transactions",
    allDocumentsQuery: "documents",
    allExpensesQuery: "expenses",
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
}));

import { useInvoicingBankPayments } from "@/composables/useInvoicingBankPayments";

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
    mocks.loadQuery.mockResolvedValue([]);
    mocks.ensure.mockImplementation(async (id: string) => ({ ok: true, bridgeCompanyId: `bridge-${id}` }));
    mocks.fallback.mockResolvedValue("wrong-first-company");
    mocks.list.mockResolvedValue({ data: [row], meta: { last_page: 1 } });
    mocks.importInbound.mockResolvedValue({ imported: 1, auto_matched: 0 });
    mocks.importWise.mockResolvedValue({ imported: 1, auto_matched: 0 });
    mocks.wiseStatus.mockResolvedValue({ connected: true });
    mocks.wiseSync.mockResolvedValue({ rows: [row] });
});

afterEach(() => {
    scopes.splice(0).forEach((scope) => scope.stop());
});

describe("bank movement bridge synchronization", () => {
    it("reads the selected company's bridge and imports every email page into that local company", async () => {
        const { bank } = bankFor();
        mocks.list.mockResolvedValueOnce({ data: [row], meta: { last_page: 2 } })
            .mockResolvedValueOnce({ data: [{ ...row, id: "server-tx-b2" }], meta: { last_page: 2 } });
        await bank.load();
        expect(mocks.ensure).toHaveBeenCalledWith("local-b");
        expect(mocks.fallback).not.toHaveBeenCalled();
        expect(mocks.list).toHaveBeenNthCalledWith(1, "bridge-local-b", { source: "email", per_page: 100, page: 1 });
        expect(mocks.list).toHaveBeenNthCalledWith(2, "bridge-local-b", { source: "email", per_page: 100, page: 2 });
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
        await vi.waitFor(() => expect(mocks.list).toHaveBeenCalledWith("bridge-local-a", expect.anything()));
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
        expect(mocks.list).toHaveBeenCalledWith("server-company-uuid", { per_page: 50 });
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
        await bank.fetchWiseStatus();
        first.resolve({ ok: false });
        await stale;
        expect(bank.wiseStatus.value).toEqual({ connected: true });
        expect(bank.wiseBridgeMissing.value).toBe(false);
        expect(bank.inboundEmailAvailable.value).toBe(true);
        expect(mocks.importInbound).not.toHaveBeenCalled();
    });
});
