import { beforeEach, expect, it, vi } from "vitest";

const get = vi.hoisted(() => vi.fn());
vi.mock("axios", () => ({ default: { get } }));

beforeEach(() => {
    vi.resetModules();
    get.mockReset();
});

it("retries the CSRF cookie fetch after a failure", async () => {
    get.mockRejectedValueOnce(new Error("offline")).mockResolvedValueOnce({});
    const { ensureCsrfCookie } = await import("../services/csrf");

    await expect(ensureCsrfCookie()).resolves.toBe(false);
    await expect(ensureCsrfCookie()).resolves.toBe(true);
    expect(get).toHaveBeenCalledTimes(2);
});

it("fetches the cookie only once after a success", async () => {
    get.mockResolvedValue({});
    const { ensureCsrfCookie } = await import("../services/csrf");

    await ensureCsrfCookie();
    await ensureCsrfCookie();
    expect(get).toHaveBeenCalledTimes(1);
});
