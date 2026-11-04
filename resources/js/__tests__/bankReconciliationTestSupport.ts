import { vi } from "vitest";
import { deserializeQuery } from "@evolu/common/local-first";

/** Decode a real public query in synthetic adapters; never alter its identity. */
export function bankTestQueryTable(query: unknown): string | undefined {
    try { return /from "([^"]+)"/.exec(deserializeQuery(query as never).sql)?.[1]; }
    catch { return undefined; }
}

/** Separate client objects share this origin's lock manager, as browser tabs do. */
export function bankLockManager() {
    let previous: Promise<unknown> = Promise.resolve();
    let held = 0;
    const request = vi.fn((name: string, _options: unknown, callback: (lock: Lock | null) => Promise<unknown>) => {
        const current = previous.catch(() => undefined).then(async () => {
            held++;
            try {
                return await callback({ name, mode: "exclusive" } as Lock);
            } finally {
                held--;
            }
        });
        previous = current;
        return current;
    });
    return { request, get held() { return held; } };
}

export function installBankLocks(manager = bankLockManager()) {
    vi.stubGlobal("navigator", { locks: manager });
    return manager;
}
