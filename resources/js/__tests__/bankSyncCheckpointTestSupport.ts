import { vi } from "vitest";

/** Async, serial IndexedDB transactions with provisional writes and controllable commit/open failures. */
export function installBankCheckpointIdb() {
    const records = new Map<string, unknown>();
    const events: string[] = [];
    const opens: Array<() => void> = [];
    const commits: Array<() => void> = [];
    const control = { holdOpen: false, holdCommits: false, failNextCommit: false, closeCount: 0 };
    let previous: Promise<void> = Promise.resolve();
    let created = false;
    const clone = (value: unknown) => value === undefined ? undefined : structuredClone(value);
    const database = {
        objectStoreNames: { contains: () => created },
        createObjectStore: () => { created = true; },
        close: () => { control.closeCount++; },
        transaction: (_store: string, mode: string) => {
            let release!: () => void;
            const finished = new Promise<void>((resolve) => { release = resolve; });
            const before = previous; previous = before.then(() => finished);
            const pending: Array<() => void> = [];
            let staged = new Map<string, unknown>();
            let aborted = false; let ended = false; let started = false;
            const tx = {
                oncomplete: null as null | (() => void), onabort: null as null | (() => void), onerror: null as null | (() => void), error: null,
                abort: () => {
                    if (ended) throw new Error("transaction_finished");
                    aborted = true; ended = true; events.push("abort");
                    queueMicrotask(() => { tx.onabort?.(); release(); });
                },
                objectStore: () => ({
                    get: (key: string) => {
                        const request = { result: undefined as unknown, onsuccess: null as null | (() => void), onerror: null };
                        pending.push(() => { request.result = clone(staged.get(key)); request.onsuccess?.(); });
                        return request;
                    },
                    put: (value: unknown, key: string) => { pending.push(() => { staged.set(key, clone(value)); events.push("put"); }); },
                    delete: (key: string) => { pending.push(() => { staged.delete(key); events.push("delete"); }); },
                }),
            };
            const commit = () => {
                if (aborted || ended) return;
                if (control.failNextCommit) { control.failNextCommit = false; tx.abort(); return; }
                ended = true;
                if (mode === "readwrite") { records.clear(); for (const [key, value] of staged) records.set(key, clone(value)); }
                events.push("complete"); tx.oncomplete?.(); release();
            };
            const pump = () => {
                if (aborted || ended) return;
                const next = pending.shift();
                if (next) { events.push("request"); next(); queueMicrotask(pump); }
                else if (control.holdCommits) commits.push(commit);
                else queueMicrotask(commit);
            };
            void before.then(() => {
                if (aborted || started) return;
                started = true; staged = new Map([...records].map(([key, value]) => [key, clone(value)]));
                queueMicrotask(pump);
            });
            return tx;
        },
    };
    vi.stubGlobal("indexedDB", { open: vi.fn(() => {
        const request = { result: database, onsuccess: null as null | (() => void), onerror: null, onblocked: null,
            onupgradeneeded: null as null | (() => void) };
        const finish = () => { if (!created) request.onupgradeneeded?.(); request.onsuccess?.(); };
        if (control.holdOpen) opens.push(finish); else queueMicrotask(finish);
        return request;
    }) });
    return { records, events, control, flushOpen: () => { opens.splice(0).forEach((finish) => finish()); },
        commitAll: () => { commits.splice(0).forEach((finish) => finish()); } };
}
