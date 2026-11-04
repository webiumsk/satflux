import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { AxiosError, type AxiosAdapter, type InternalAxiosRequestConfig } from "axios";
import api, { invoicingApi } from "@/services/api";
import { importEfakturaInboxEntry, stableExpenseIdFromInboxUuid, type EfakturaInboxEntry } from "@/evolu/efakturaInboxImport";
import { withExpenseReconciliation } from "@/evolu/expenseReconciliation";
import { createExpenseWriterTestAdapter } from "./expenseReconciliationTestSupport";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";

vi.mock("@/evolu/client", async () => (await import("./expenseReconciliationTestSupport")).expenseTestQueryExports());
const flash = vi.hoisted(() => ({ errorKey: vi.fn() }));
vi.mock("@/store/flash", () => ({ useFlashStore: () => flash }));

const originalAdapter = api.defaults.adapter;
const entry: EfakturaInboxEntry = {
    inbox_id: "synthetic-receipt", evolu_expense_id: "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b", external_document_id: "synthetic-document",
    inbox_status: "pending", created_at: "2026-11-04T10:00:00Z",
    draft: { title: "Synthetic supplier", issue_date: "2026-11-04", total: "19.00", currency: "EUR" },
    summary: { supplier_name: "Synthetic supplier", external_number: "INV-19", total: "19.00", currency: "EUR" },
};

function bridgeTransport(hang: "detail" | "ack", retryStatus = 200) {
    const requests: InternalAxiosRequestConfig[] = [];
    let acknowledgements = 0;
    const adapter: AxiosAdapter = (config) => {
        requests.push(config);
        const isAck = config.url?.endsWith("/imported");
        if (isAck) acknowledgements++;
        const blocked = hang === "detail" && !isAck || hang === "ack" && isAck && acknowledgements === 1;
        if (blocked) {
            // Only this test transport simulates the timeout. Axios' native XHR
            // adapter consumes the same endpoint config as request.timeout.
            return new Promise((_resolve, reject) => {
                if (config.timeout) setTimeout(() => reject(new AxiosError("synthetic_timeout", "ECONNABORTED", config)), config.timeout);
            });
        }
        const response = { config, status: isAck ? retryStatus : 200, statusText: "Synthetic", headers: {},
            data: { data: config.url?.endsWith("/inbox") ? [] : { ...entry, ubl: "" } } };
        if (isAck && retryStatus === 422) return Promise.reject(new AxiosError("synthetic_already_processed", "ERR_BAD_REQUEST", config, undefined, response));
        return Promise.resolve(response);
    };
    api.defaults.adapter = adapter;
    return requests;
}

beforeEach(() => { flash.errorKey.mockClear(); vi.useFakeTimers(); installBankLocks(); });
afterEach(() => { api.defaults.adapter = originalAdapter; clearRowOwnerIndex(); clearCompanyShares(); vi.unstubAllGlobals(); vi.useRealTimers(); });

it("bounds inbox detail below the gate wait, writes no expense and releases the gate after timeout", async () => {
    const locks = installBankLocks(); const requests = bridgeTransport("detail");
    const { evolu, state } = createExpenseWriterTestAdapter();
    const outcome = importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry).catch((error) => error);
    await vi.advanceTimersByTimeAsync(0);
    expect(requests).toHaveLength(1);
    expect(requests[0]).toMatchObject({ method: "get", timeout: 10_000, withCredentials: true,
        url: "/invoicing/companies/synthetic-bridge/efaktura/inbox/synthetic-receipt" });
    const next = withExpenseReconciliation(evolu as never, () => true);
    await vi.advanceTimersByTimeAsync(9_999);
    expect(locks.held).toBe(1); expect(state.expense).toHaveLength(0);
    await vi.advanceTimersByTimeAsync(1);
    expect(await outcome).toMatchObject({ code: "ECONNABORTED" });
    expect(await next).toBe(true);
    expect(locks.held).toBe(0);
    expect(state.expense).toHaveLength(0);
    expect(requests).toHaveLength(1); // No request-level retry or acknowledgement.
});

it.each([200, 422])("preserves one committed stable expense after an ACK timeout and a retry status %s", async (retryStatus) => {
    const locks = installBankLocks(); const requests = bridgeTransport("ack", retryStatus);
    const { evolu, state } = createExpenseWriterTestAdapter();
    const outcome = importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry).catch((error) => error);
    await vi.advanceTimersByTimeAsync(0);
    expect(requests).toHaveLength(2);
    expect(requests[1]).toMatchObject({ method: "post", timeout: 10_000, withCredentials: true,
        url: "/invoicing/companies/synthetic-bridge/efaktura/inbox/synthetic-receipt/imported" });
    expect(state.expense).toHaveLength(1);
    expect(state.expense[0].id).toBe(stableExpenseIdFromInboxUuid(entry.evolu_expense_id));
    const saved = structuredClone(state.expense[0]);
    const next = withExpenseReconciliation(evolu as never, () => true);
    await vi.advanceTimersByTimeAsync(10_000);
    expect(await outcome).toMatchObject({ code: "ECONNABORTED" });
    expect(await next).toBe(true); expect(locks.held).toBe(0);
    expect(state.expense).toEqual([saved]);
    expect(requests).toHaveLength(2); // Retrying requires a new user action.
    const retry = importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry);
    if (retryStatus === 200) expect(await retry).toMatchObject({ ok: true, alreadyImported: true });
    else await expect(retry).rejects.toMatchObject({ response: { status: 422 } });
    expect(state.expense).toEqual([saved]);
    expect(evolu.upsert.mock.calls.filter(([table]) => table === "expense")).toHaveLength(1);
    expect(requests.filter((request) => request.method === "get")).toHaveLength(1);
    expect(await invoicingApi.efaktura.inboxList("synthetic-bridge")).toEqual([]);
    expect(await withExpenseReconciliation(evolu as never, () => true)).toBe(true);
});

const scopedBankRequests = [
    { name: "list", path: "", method: "get", call: (options: { skipErrorFlash?: boolean }) => invoicingApi.bankTransactions.list("synthetic-company", { page: 2 }, options) },
    { name: "auto match", path: "/batches/synthetic-batch/auto-match", method: "post", call: (options: { skipErrorFlash?: boolean }) => invoicingApi.bankTransactions.autoMatchBatch("synthetic-company", "synthetic-batch", options) },
    { name: "manual match", path: "/synthetic-transaction/match", method: "post", call: (options: { skipErrorFlash?: boolean }) => invoicingApi.bankTransactions.match("synthetic-company", "synthetic-transaction", "synthetic-document", options) },
    { name: "ignore", path: "/synthetic-transaction/ignore", method: "post", call: (options: { skipErrorFlash?: boolean }) => invoicingApi.bankTransactions.ignore("synthetic-company", "synthetic-transaction", options) },
    { name: "unmatch", path: "/synthetic-transaction/unmatch", method: "post", call: (options: { skipErrorFlash?: boolean }) => invoicingApi.bankTransactions.unmatch("synthetic-company", "synthetic-transaction", options) },
];

it.each(scopedBankRequests)("allows caller-scoped $name errors without changing default global error behavior", async ({ path, method, call }) => {
    vi.useRealTimers();
    const requests: InternalAxiosRequestConfig[] = [];
    api.defaults.adapter = (config) => {
        requests.push(config);
        return Promise.reject(new AxiosError("synthetic_failure", "ERR_BAD_RESPONSE", config, undefined,
            { status: 503, statusText: "Synthetic", data: {}, headers: {}, config }));
    };
    await expect(call({ skipErrorFlash: true })).rejects.toMatchObject({ response: { status: 503 } });
    await vi.dynamicImportSettled();
    expect(flash.errorKey).not.toHaveBeenCalled();
    expect(requests[0]).toMatchObject({ skipErrorFlash: true, withCredentials: true, method,
        url: `/invoicing/companies/synthetic-company/bank-transactions${path}` });
    if (method === "get") expect(requests[0].params).toEqual({ page: 2 });
    else if (path.endsWith("/match")) expect(JSON.parse(requests[0].data)).toEqual({ business_document_id: "synthetic-document" });
    else expect(requests[0].data).toBeUndefined();
    await expect(call({})).rejects.toMatchObject({ response: { status: 503 } });
    await vi.dynamicImportSettled();
    expect(flash.errorKey).toHaveBeenCalledTimes(1);
    expect(requests).toHaveLength(2);
});
