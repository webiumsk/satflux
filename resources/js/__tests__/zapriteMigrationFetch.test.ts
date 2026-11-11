import { beforeEach, describe, expect, it, vi } from "vitest";
vi.mock("@/services/api", () => ({ default: { post: vi.fn() } }));
vi.mock("@/evolu/migrationZaprite", () => ({ parseZapriteMigrationExport: vi.fn() }));
import api from "@/services/api";
import { parseZapriteMigrationExport } from "@/evolu/migrationZaprite";
import { fetchZapriteMigrationExport } from "@/services/zapriteMigration";

const page = (index: number, pages = 2) => ({ items: [{ id: `order-${index}` }], meta: { page: index, pagesCount: pages, perPage: 1, itemsCount: pages } });
const response = (body: unknown) => ({ data: { data: body }, config: { data: "synthetic-secret" } });
beforeEach(() => vi.clearAllMocks());
describe("transient Zaprite export fetch", () => {
    it("fetches every page sequentially, verifies the complete credential-free source and drops request bodies", async () => {
        const first = response(page(1)); const second = response(page(2));
        vi.mocked(api.post).mockResolvedValueOnce(first).mockResolvedValueOnce(second);
        const progress = vi.fn();
        const bytes = await fetchZapriteMigrationExport("synthetic-secret", { signal: new AbortController().signal, onProgress: progress });
        const parsed = JSON.parse(new TextDecoder().decode(bytes));
        expect(parsed).toEqual({ version: 1, provider: "zaprite", pages: [page(1), page(2)] });
        expect(new TextDecoder().decode(bytes)).not.toContain("synthetic-secret");
        expect(api.post).toHaveBeenCalledTimes(2);
        expect(api.post).toHaveBeenNthCalledWith(2, "/invoicing/ephemeral/migration/zaprite", { api_key: "synthetic-secret", page: 2 }, expect.objectContaining({ timeout: 25_000, skipErrorFlash: true }));
        expect(parseZapriteMigrationExport).toHaveBeenCalledWith(bytes);
        expect(first.config.data).toBeUndefined(); expect(second.config.data).toBeUndefined();
        expect(progress.mock.calls).toEqual([[1, 2], [2, 2]]);
    });
    it("cancels between pages without returning a partial export", async () => {
        const controller = new AbortController();
        vi.mocked(api.post).mockResolvedValueOnce(response(page(1)));
        await expect(fetchZapriteMigrationExport("synthetic-secret", { signal: controller.signal, onProgress: () => controller.abort() })).rejects.toThrow("zaprite_cancelled");
        expect(api.post).toHaveBeenCalledTimes(1); expect(parseZapriteMigrationExport).not.toHaveBeenCalled();
    });
    it("rejects changed pagination and oversized page counts", async () => {
        vi.mocked(api.post).mockResolvedValueOnce(response(page(1))).mockResolvedValueOnce(response(page(2, 3)));
        await expect(fetchZapriteMigrationExport("synthetic-secret", { signal: new AbortController().signal })).rejects.toThrow("zaprite_incomplete_export");
        vi.mocked(api.post).mockResolvedValueOnce(response(page(1, 21)));
        await expect(fetchZapriteMigrationExport("synthetic-secret", { signal: new AbortController().signal })).rejects.toThrow("zaprite_limit_exceeded");
    });
    it("never exposes raw provider, network or Axios errors", async () => {
        const failure = { message: "Authorization: synthetic-secret", config: { data: "synthetic-secret" }, response: { data: { code: "arbitrary-private-error" } } };
        vi.mocked(api.post).mockRejectedValueOnce(failure);
        await expect(fetchZapriteMigrationExport("synthetic-secret", { signal: new AbortController().signal })).rejects.toThrow(/^zaprite_unavailable$/);
        expect(failure.config.data).toBeUndefined();
    });
    it("propagates only finite proxy codes", async () => {
        vi.mocked(api.post).mockRejectedValueOnce({ response: { data: { code: "zaprite_auth_failed" } } });
        await expect(fetchZapriteMigrationExport("synthetic-secret", { signal: new AbortController().signal })).rejects.toThrow(/^zaprite_auth_failed$/);
    });
});
