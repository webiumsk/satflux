import { beforeEach, describe, expect, it, vi } from "vitest";
import { SimpleName } from "@evolu/common";

const mocked = vi.hoisted(() => ({
    shared: vi.fn(),
    reload: vi.fn(),
    worker: vi.fn(),
    db: { send: vi.fn(), [Symbol.dispose]: vi.fn() },
}));
vi.mock("@evolu/web", () => ({
    createSharedWebWorker: mocked.shared,
    evoluWebDeps: { reloadApp: mocked.reload, createDbWorker: vi.fn(), preserved: "public-dependency" },
}));

import { allowEvoluPageReload, createEvoluWebDepsWithReloadGuard } from "../evolu/reloadGuard";

beforeEach(() => {
    vi.clearAllMocks();
    sessionStorage.clear();
    mocked.shared.mockImplementation((_name, factory) => { factory(); return mocked.db; });
    vi.stubGlobal("Worker", class { constructor(url: URL, options: WorkerOptions) { mocked.worker(url, options); } });
});

// Tests exercise the dependency boundary only; no worker, database or network starts.
describe("observed database worker dependency", () => {
    it("preserves the SDK shared database name/lock and only substitutes the module worker entry", () => {
        const deps = createEvoluWebDepsWithReloadGuard();
        const name = SimpleName.orThrow("synthetic-observed-db");
        expect(deps.createDbWorker(name)).toBe(mocked.db);
        expect(mocked.shared.mock.calls[0]?.[0]).toBe(name);
        expect(mocked.worker.mock.calls[0]?.[0]).toBeInstanceOf(URL);
        expect(String(mocked.worker.mock.calls[0]?.[0])).toContain("/evolu/Db.worker.ts");
        expect(mocked.worker.mock.calls[0]?.[1]).toEqual({ type: "module" });
        expect(deps).toMatchObject({ preserved: "public-dependency" });
        vi.unstubAllGlobals();
    });

    it("preserves the one-shot explicit reload guard", () => {
        const deps = createEvoluWebDepsWithReloadGuard();
        const warn = vi.spyOn(console, "warn").mockImplementation(() => undefined);
        deps.reloadApp("https://app.example.test");
        expect(mocked.reload).not.toHaveBeenCalled();
        allowEvoluPageReload();
        deps.reloadApp("https://app.example.test");
        expect(mocked.reload).toHaveBeenCalledTimes(1);
        deps.reloadApp("https://app.example.test");
        expect(mocked.reload).toHaveBeenCalledTimes(1);
        warn.mockRestore();
        vi.unstubAllGlobals();
    });
});
