import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { effectScope, ref, type EffectScope } from "vue";
import { AxiosError, CanceledError, type AxiosResponse, type InternalAxiosRequestConfig } from "axios";
import api, { invoicingApi } from "@/services/api";
import { useInvoicingBankPayments } from "@/composables/useInvoicingBankPayments";

const flash = vi.hoisted(() => ({ errorKey: vi.fn() }));
vi.mock("@/store/flash", () => ({ useFlashStore: () => flash }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => false }));
vi.mock("@evolu/vue", () => ({ useQuery: vi.fn() }));
vi.mock("@/evolu/client", () => ({
    allBankImportBatchesQuery: "batches", allBankTransactionMatchesQuery: "matches", allBankTransactionsQuery: "transactions",
    allDocumentsQuery: "documents", allExpensesQuery: "expenses", useInvoicingEvolu: vi.fn(),
}));
vi.mock("@/evolu/bankCrud", () => ({}));

type PendingRequest = { config: InternalAxiosRequestConfig; resolve: (response: AxiosResponse) => void; reject: (reason: unknown) => void };
let requests: PendingRequest[];
let scope: EffectScope;
const originalAdapter = api.defaults.adapter;
function bankFor() {
    const company = ref("company-a"); scope = effectScope();
    const bank = scope.run(() => useInvoicingBankPayments(company, ref("EUR")))!;
    return { bank, company };
}
function respond(request: PendingRequest, id: string) {
    request.resolve({ config: request.config, status: 200, statusText: "Synthetic", headers: {}, data: { data: [{ id }] } });
}

beforeEach(() => {
    requests = []; flash.errorKey.mockClear();
    api.defaults.adapter = (config) => new Promise((resolve, reject) => {
        requests.push({ config, resolve, reject });
        if (config.signal?.aborted) reject(new CanceledError("superseded", config));
        else config.signal?.addEventListener?.("abort", () => reject(new CanceledError("superseded", config)), { once: true });
    });
});
afterEach(async () => {
    scope?.stop(); api.defaults.adapter = originalAdapter; vi.useRealTimers();
    await vi.dynamicImportSettled();
});

it.each(["rows", "batches"] as const)("cancels old-company %s through the real Axios interceptor without a global flash", async (mode) => {
    const { bank, company } = bankFor();
    const pending = mode === "rows" ? bank.load() : bank.loadBatches();
    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests[0].config.skipErrorFlash).toBeUndefined();
    expect(requests[0].config.withCredentials).toBe(true);
    company.value = "company-b";
    await expect(pending).resolves.toBeUndefined();
    await vi.dynamicImportSettled();
    expect(requests[0].config.signal?.aborted).toBe(true);
    expect(flash.errorKey).not.toHaveBeenCalled();
    expect(bank.transactions.value).toEqual([]); expect(bank.batches.value).toEqual([]);
});

it.each(["rows", "batches"] as const)("aborts superseded same-company %s and lets the current request complete", async (mode) => {
    const { bank } = bankFor();
    const load = () => mode === "rows" ? bank.load() : bank.loadBatches();
    const stale = load(); await vi.waitFor(() => expect(requests).toHaveLength(1));
    const current = load(); await vi.waitFor(() => expect(requests).toHaveLength(2));
    await expect(stale).resolves.toBeUndefined();
    respond(requests[1], "latest"); await current;
    expect(requests[0].config.signal?.aborted).toBe(true);
    expect(requests[1].config.signal?.aborted).toBe(false);
    expect(mode === "rows" ? bank.transactions.value : bank.batches.value).toEqual([{ id: "latest" }]);
    expect(flash.errorKey).not.toHaveBeenCalled();
});

it.each([false, true])("propagates current ordinary errors while preserving caller-controlled flashes (skip=%s)", async (skipErrorFlash) => {
    const { bank } = bankFor();
    const pending = bank.load("all", { skipErrorFlash });
    const rejected = expect(pending).rejects.toMatchObject({ response: { status: 503 } });
    await vi.waitFor(() => expect(requests).toHaveLength(1));
    const request = requests[0];
    request.reject(new AxiosError("synthetic failure", "ERR_BAD_RESPONSE", request.config, undefined,
        { config: request.config, status: 503, statusText: "Synthetic", headers: {}, data: {} }));
    await rejected; await vi.dynamicImportSettled();
    expect(flash.errorKey).toHaveBeenCalledTimes(skipErrorFlash ? 0 : 1);
    expect(bank.loading.value).toBe(false);
});

it("forwards a finite best-effort page deadline without changing global Axios defaults", async () => {
    vi.useFakeTimers(); const configs: InternalAxiosRequestConfig[] = [];
    api.defaults.adapter = (config) => new Promise((_resolve, reject) => {
        configs.push(config);
        if (config.timeout) setTimeout(() => reject(new AxiosError("synthetic page timeout", "ECONNABORTED", config)), config.timeout);
    });
    const outcome = invoicingApi.bankTransactions.list("synthetic-bridge", { source: "email", page: 2 },
        { timeout: 10_000, skipErrorFlash: true }).catch((error) => error);
    await vi.advanceTimersByTimeAsync(0);
    expect(configs[0]).toMatchObject({ timeout: 10_000, skipErrorFlash: true, withCredentials: true, params: { source: "email", page: 2 } });
    expect(api.defaults.timeout).toBe(0);
    await vi.advanceTimersByTimeAsync(10_000);
    expect(await outcome).toMatchObject({ code: "ECONNABORTED" });
    expect(flash.errorKey).not.toHaveBeenCalled();
});

it("preserves ordinary inbound-address defaults when no cancellation options are supplied", async () => {
    const pending = invoicingApi.bankTransactions.inboundEmail<{ address: string }>("company-a");
    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests[0].config.signal).toBeUndefined();
    expect(requests[0].config.timeout).toBe(0);
    expect(requests[0].config.withCredentials).toBe(true);
    requests[0].resolve({ config: requests[0].config, status: 200, statusText: "Synthetic", headers: {}, data: { data: { address: "synthetic@example.test" } } });
    await expect(pending).resolves.toEqual({ address: "synthetic@example.test" });
});

it.each([false, true])("cancels an inbound-address read without a global flash (adapter ignores abort=%s)", async (ignoreAbort) => {
    if (ignoreAbort) api.defaults.adapter = (config) => new Promise((resolve, reject) => { requests.push({ config, resolve, reject }); });
    const controller = new AbortController();
    const pending = invoicingApi.bankTransactions.inboundEmail("company-a", { signal: controller.signal });
    const rejected = expect(pending).rejects.toMatchObject({ code: "ERR_CANCELED" });
    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests[0].config.signal).toBe(controller.signal);
    expect(requests[0].config.withCredentials).toBe(true);
    controller.abort();
    if (ignoreAbort) requests[0].reject(new AxiosError("synthetic late network failure", "ERR_NETWORK", requests[0].config));
    await rejected;
    expect(flash.errorKey).not.toHaveBeenCalled();
});
