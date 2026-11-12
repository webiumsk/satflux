import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { createHash, webcrypto } from "node:crypto";
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
const originalXml = '<Invoice><Name>Synthetic supplier</Name></Invoice>';
const originalSha256 = createHash('sha256').update(originalXml).digest('hex');
const custodyToken = btoa('synthetic custody completion token');
const entry: EfakturaInboxEntry = {
    inbox_id: "synthetic-receipt", evolu_expense_id: "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b", external_document_id: "synthetic-document",
    custody_protocol: "post_ack_proof_v1", inbox_status: "pending", created_at: "2026-11-04T10:00:00Z",
    draft: { title: "Synthetic supplier", issue_date: "2026-11-04", total: "19.00", currency: "EUR" },
    summary: { supplier_name: "Synthetic supplier", external_number: "INV-19", total: "19.00", currency: "EUR" },
};

function bridgeTransport(hang: "detail" | "ack" | "none", retryStatus = 200,
    errorData: unknown = { errors: { inbox: ["Inbox item is not pending."] } }) {
    const requests: InternalAxiosRequestConfig[] = [];
    let acknowledgements = 0;
    const adapter: AxiosAdapter = (config) => {
        requests.push(config);
        const isAck = config.url?.endsWith("/prepare-import");
        const isComplete = config.url?.endsWith("/imported");
        if (isAck) acknowledgements++;
        const blocked = hang === "detail" && !isAck || hang === "ack" && isAck && acknowledgements === 1;
        if (blocked) {
            // Only this test transport simulates the timeout. Axios' native XHR
            // adapter consumes the same endpoint config as request.timeout.
            return new Promise((_resolve, reject) => {
                if (config.timeout) setTimeout(() => reject(new AxiosError("synthetic_timeout", "ECONNABORTED", config)), config.timeout);
            });
        }
        const response = { config, status: isComplete ? 204 : isAck ? retryStatus : 200, statusText: "Synthetic", headers: {},
            data: isAck && retryStatus >= 400 ? errorData : { data: isAck ? { custody_protocol: "post_ack_proof_v1", custody_token: custodyToken } : isComplete ? undefined : config.url?.endsWith("/inbox") ? [] : { ...entry, ubl: originalXml, original_sha256: originalSha256 } } };
        if (isAck && retryStatus >= 400) return Promise.reject(new AxiosError("synthetic_ack_failure", "ERR_BAD_REQUEST", config, undefined, response));
        return Promise.resolve(response);
    };
    api.defaults.adapter = adapter;
    return requests;
}

beforeEach(() => { flash.errorKey.mockClear(); vi.useFakeTimers(); installBankLocks(); vi.stubGlobal('crypto', webcrypto); });
afterEach(() => { api.defaults.adapter = originalAdapter; clearRowOwnerIndex(); clearCompanyShares(); vi.unstubAllGlobals(); vi.useRealTimers(); });

it("bounds inbox detail HTTP without retaining the financial gate and writes no expense after timeout", async () => {
    const locks = installBankLocks(); const requests = bridgeTransport("detail");
    const { evolu, state } = createExpenseWriterTestAdapter();
    const outcome = importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry).catch((error) => error);
    await vi.waitFor(() => expect(requests).toHaveLength(1));
    expect(requests).toHaveLength(1);
    expect(requests[0]).toMatchObject({ method: "get", timeout: 10_000, withCredentials: true,
        url: "/invoicing/companies/synthetic-bridge/efaktura/inbox/synthetic-receipt" });
    const next = withExpenseReconciliation(evolu as never, () => true);
    expect(await next).toBe(true);
    await vi.advanceTimersByTimeAsync(9_999);
    expect(locks.held).toBe(0); expect(state.expense).toHaveLength(0);
    await vi.advanceTimersByTimeAsync(1);
    expect(await outcome).toMatchObject({ code: "ECONNABORTED" });
    expect(await next).toBe(true);
    expect(locks.held).toBe(0);
    expect(state.expense).toHaveLength(0);
    expect(requests).toHaveLength(1); // No request-level retry or acknowledgement.
});

it("preserves one complete stable expense after an ACK timeout and a successful idempotent retry", async () => {
    const locks = installBankLocks(); const requests = bridgeTransport("ack");
    const { evolu, state } = createExpenseWriterTestAdapter();
    const outcome = importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry).catch((error) => error);
    await vi.waitFor(() => expect(requests).toHaveLength(2));
    expect(requests).toHaveLength(2);
    expect(requests[1]).toMatchObject({ method: "post", timeout: 10_000, withCredentials: true,
        url: "/invoicing/companies/synthetic-bridge/efaktura/inbox/synthetic-receipt/prepare-import" });
    expect(state.expense).toHaveLength(1);
    expect(state.expense[0].id).toBe(stableExpenseIdFromInboxUuid(entry.evolu_expense_id));
    expect(state.expenseAttachment).toHaveLength(1);
    expect(JSON.parse(requests[1].data)).toEqual({ original_sha256: originalSha256 });
    const saved = structuredClone(state.expense[0]);
    const next = withExpenseReconciliation(evolu as never, () => true);
    expect(await next).toBe(true);
    expect(locks.held).toBe(0);
    await vi.advanceTimersByTimeAsync(10_000);
    expect(await outcome).toMatchObject({ code: "ECONNABORTED" });
    expect(await next).toBe(true); expect(locks.held).toBe(0);
    expect(state.expense).toEqual([saved]);
    expect(requests).toHaveLength(2); // Retrying requires a new user action.
    const retry = importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry);
    expect(await retry).toMatchObject({ ok: true, alreadyImported: true });
    expect(state.expense).toEqual([saved]);
    expect(evolu.upsert.mock.calls.filter(([table]) => table === "expense")).toHaveLength(1);
    expect(requests.filter((request) => request.method === "get")).toHaveLength(2);
    expect(await invoicingApi.efaktura.inboxList("synthetic-bridge")).toEqual([]);
    expect(await withExpenseReconciliation(evolu as never, () => true)).toBe(true);
});

it.each([
    [422, { errors: { inbox: ["Different validation error."] } }],
    [422, { errors: { inbox: ["Inbox item is not pending."], company: ["Invalid company."] } }],
    [422, { message: "Inbox item is not pending." }],
    [401, {}], [403, {}], [404, {}], [419, {}], [500, {}],
])("preserves an existing expense and propagates an unrelated ACK failure %s", async (status, errorData) => {
    const requests = bridgeTransport("none", status as number, errorData);
    const stableId = stableExpenseIdFromInboxUuid(entry.evolu_expense_id)!;
    const { evolu, state } = createExpenseWriterTestAdapter([{ id: stableId, companyId: "company-a", total: "19.00", status: "paid" }]);
    const saved = structuredClone(state.expense);
    await expect(importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry))
        .rejects.toMatchObject({ response: { status } });
    expect(state.expense).toEqual(saved);
    expect(requests).toHaveLength(2);
    expect(requests[0].method).toBe("get");
    expect(requests[1].method).toBe("post");
    expect(evolu.upsert.mock.calls.some(([table]) => table === "expense")).toBe(false);
});

it("does not treat a terminal conflict as import proof even after the complete local copy exists", async () => {
    const requests = bridgeTransport("none", 422);
    const { evolu, state } = createExpenseWriterTestAdapter();
    await expect(importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry))
        .rejects.toMatchObject({ response: { status: 422 } });
    expect(state.expense).toHaveLength(1);
    const saved = structuredClone(state.expense);
    await expect(importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry))
        .rejects.toMatchObject({ response: { status: 422 } });
    expect(state.expense).toEqual(saved);
    expect(requests.filter((request) => request.method === "get")).toHaveLength(2);
    expect(evolu.upsert.mock.calls.filter(([table]) => table === "expense")).toHaveLength(1);
});

it.each([
    { kind: 'absent endpoint', status: 404, body: {} },
    { kind: 'missing protocol', status: 200, body: { custody_token: custodyToken } },
    { kind: 'wrong protocol', status: 200, body: { custody_protocol: 'legacy', custody_token: custodyToken } },
    { kind: 'missing token', status: 200, body: { custody_protocol: 'post_ack_proof_v1' } },
    { kind: 'malformed token', status: 200, body: { custody_protocol: 'post_ack_proof_v1', custody_token: 'bad token' } },
    { kind: 'unexpected status', status: 202, body: { custody_protocol: 'post_ack_proof_v1', custody_token: custodyToken } },
])('rejects $kind without falling back to legacy imported', async ({ status, body }) => {
    const requests: InternalAxiosRequestConfig[] = [];
    api.defaults.adapter = async (config) => {
        requests.push(config);
        const response = { config, status, statusText: 'Synthetic', headers: {}, data: { data: body } };
        if (status >= 400) throw new AxiosError('synthetic preparation failure', 'ERR_BAD_REQUEST', config, undefined, response);
        return response;
    };
    await expect(invoicingApi.efaktura.inboxPrepareImported('synthetic-company', 'synthetic-receipt', originalSha256)).rejects.toBeDefined();
    expect(requests).toHaveLength(1);
    expect(requests[0]).toMatchObject({ method: 'post', timeout: 10_000,
        url: '/invoicing/companies/synthetic-company/efaktura/inbox/synthetic-receipt/prepare-import' });
});

it('sends the prepared token and requires a real 204 cleanup response', async () => {
    const requests: InternalAxiosRequestConfig[] = [];
    api.defaults.adapter = async (config) => {
        requests.push(config);
        return { config, status: 200, statusText: 'Synthetic', headers: {},
            data: { data: { custody_protocol: 'post_ack_proof_v1', custody_token: custodyToken } } };
    };
    expect(await invoicingApi.efaktura.inboxPrepareImported('synthetic-company', 'synthetic-receipt', originalSha256)).toBe(custodyToken);
    await expect(invoicingApi.efaktura.inboxImported('synthetic-company', 'synthetic-receipt', originalSha256, custodyToken))
        .rejects.toThrow('efaktura_custody_completion_unconfirmed');
    expect(requests).toHaveLength(2);
    expect(JSON.parse(requests[1].data)).toEqual({ original_sha256: originalSha256, custody_token: custodyToken });
});

it.each([
    { companyId: "company-b", isDeleted: 0 },
    { companyId: "company-a", isDeleted: 1 },
])("rejects a foreign or deleted stable target before acknowledging it: %j", async (target) => {
    const requests = bridgeTransport("none", 422);
    const { evolu, state } = createExpenseWriterTestAdapter([{ id: stableExpenseIdFromInboxUuid(entry.evolu_expense_id), ...target, total: "19.00" }]);
    const saved = structuredClone(state.expense);
    await expect(importEfakturaInboxEntry(evolu as never, "company-a", "synthetic-bridge", entry))
        .resolves.toEqual({ ok: false, error: "expense_insert_failed" });
    expect(requests).toHaveLength(0);
    expect(state.expense).toEqual(saved);
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
