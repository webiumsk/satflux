import { afterEach, beforeEach, expect, it, vi } from "vitest";
import api, { invoicingApi } from "@/services/api";
import { withBankReadBudget } from "@/evolu/bankReadBudget";
import type { InternalAxiosRequestConfig } from "axios";

const flash = vi.hoisted(() => ({ errorKey: vi.fn() }));
vi.mock("@/store/flash", () => ({ useFlashStore: () => flash }));
const originalAdapter = api.defaults.adapter;
beforeEach(() => { flash.errorKey.mockClear(); });
afterEach(() => { api.defaults.adapter = originalAdapter; vi.restoreAllMocks(); vi.useRealTimers(); });

it("forwards scoped Wise GET controls and cleans the read budget without changing default requests", async () => {
    vi.useFakeTimers();
    const requests: InternalAxiosRequestConfig[] = [];
    api.defaults.adapter = async (config) => {
        requests.push(config);
        return { config, status: 200, statusText: "Synthetic", headers: {}, data: { data: { connected: true } } };
    };
    const parent = new AbortController();
    const add = vi.spyOn(parent.signal, "addEventListener");
    const remove = vi.spyOn(parent.signal, "removeEventListener");
    const result = await withBankReadBudget((controls) => invoicingApi.wise.status("synthetic-company", controls()), {
        parentOptions: () => ({ signal: parent.signal, timeout: 6_000, skipErrorFlash: true }),
    });
    expect(result).toEqual({ connected: true });
    expect(requests[0]).toMatchObject({ timeout: 6_000, skipErrorFlash: true, withCredentials: true, url: "/invoicing/companies/synthetic-company/wise/status" });
    expect(requests[0].signal?.aborted).toBe(true);
    expect(parent.signal.aborted).toBe(false);
    expect(add).toHaveBeenCalledTimes(1); expect(remove).toHaveBeenCalledTimes(1);
    expect(vi.getTimerCount()).toBe(0);
    await invoicingApi.wise.status("synthetic-company");
    expect(requests[1].timeout).toBe(0);
    expect(requests[1].signal).toBeUndefined();
    expect(requests[1].skipErrorFlash).toBeUndefined();
    expect(flash.errorKey).not.toHaveBeenCalled();
});

it("rejects a late successful GET using elapsed time even before timer dispatch", async () => {
    vi.useFakeTimers();
    let clock = 0;
    vi.spyOn(performance, "now").mockImplementation(() => clock);
    api.defaults.adapter = async (config) => {
        clock = 20_001;
        return { config, status: 200, statusText: "Synthetic", headers: {}, data: { data: { connected: true } } };
    };
    await expect(withBankReadBudget((controls) => invoicingApi.wise.status("synthetic-company", controls())))
        .rejects.toThrow("bank_read_expired");
    expect(vi.getTimerCount()).toBe(0);
    expect(flash.errorKey).not.toHaveBeenCalled();
});
