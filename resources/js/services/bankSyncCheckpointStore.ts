/** Only server-encrypted cursors and opaque SHA-256 context identifiers are persisted. */
export type BankSyncCheckpoint = { context: string; cursor: string };
export type BankSyncCheckpointResult = "saved" | "conflict" | "stale";
const DB_NAME = "satflux-bank-sync";
const STORE = "checkpoints";

function unavailable(): Error { return new Error("bank_sync_checkpoint_unavailable"); }

/** An aborted open cannot be canceled by IndexedDB; close a late connection instead of reusing it. */
function openDatabase(signal: AbortSignal): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        if (signal.aborted || typeof indexedDB === "undefined") { reject(unavailable()); return; }
        let settled = false;
        const abort = () => { settled = true; signal.removeEventListener("abort", abort); reject(unavailable()); };
        signal.addEventListener("abort", abort, { once: true });
        let request: IDBOpenDBRequest;
        try { request = indexedDB.open(DB_NAME, 1); }
        catch { settled = true; signal.removeEventListener("abort", abort); reject(unavailable()); return; }
        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) request.result.createObjectStore(STORE);
        };
        request.onsuccess = () => {
            signal.removeEventListener("abort", abort);
            if (settled || signal.aborted) { request.result.close(); return; }
            settled = true; resolve(request.result);
        };
        request.onerror = request.onblocked = () => {
            if (settled) return;
            settled = true; signal.removeEventListener("abort", abort); reject(unavailable());
        };
    });
}

/** Normalize the complete persisted record before strict comparison; no plaintext cursor internals are accepted. */
function normalized(value: unknown): BankSyncCheckpoint | null {
    if (value === undefined || value === null) return null;
    if (typeof value !== "object" || !("context" in value) || !("cursor" in value)
        || typeof value.context !== "string" || !/^[a-f0-9]{64}$/.test(value.context)
        || typeof value.cursor !== "string" || value.cursor.length === 0) throw unavailable();
    return { context: value.context, cursor: value.cursor };
}

/** Resolve reads on transaction completion, never from a provisional request callback. */
export async function readBankSyncCheckpoint(slot: string, signal: AbortSignal): Promise<BankSyncCheckpoint | null> {
    const db = await openDatabase(signal);
    return new Promise((resolve, reject) => {
        let tx: IDBTransaction;
        try { tx = db.transaction(STORE, "readonly"); }
        catch { db.close(); reject(unavailable()); return; }
        let result: BankSyncCheckpoint | null = null;
        const abort = () => { try { tx.abort(); } catch { reject(unavailable()); } };
        const cleanup = () => { signal.removeEventListener("abort", abort); db.close(); };
        signal.addEventListener("abort", abort, { once: true });
        const request = tx.objectStore(STORE).get(slot);
        request.onsuccess = () => { try { result = normalized(request.result); } catch { abort(); } };
        tx.oncomplete = () => { cleanup(); if (signal.aborted) reject(unavailable()); else resolve(result); };
        tx.onerror = tx.onabort = () => { cleanup(); reject(unavailable()); };
        if (signal.aborted) abort();
    });
}

/** Compare and replace one opaque slot atomically; stale callbacks never overwrite a newer tab's progress. */
export async function compareAndSetBankSyncCheckpoint(
    slot: string, expected: BankSyncCheckpoint | null, next: BankSyncCheckpoint | null,
    options: { signal: AbortSignal; isCurrent: () => boolean },
): Promise<BankSyncCheckpointResult> {
    const db = await openDatabase(options.signal);
    return new Promise((resolve, reject) => {
        let tx: IDBTransaction;
        try { tx = db.transaction(STORE, "readwrite"); }
        catch { db.close(); reject(unavailable()); return; }
        let result: BankSyncCheckpointResult = "stale";
        const abort = () => { try { tx.abort(); } catch { reject(unavailable()); } };
        const cleanup = () => { options.signal.removeEventListener("abort", abort); db.close(); };
        options.signal.addEventListener("abort", abort, { once: true });
        const store = tx.objectStore(STORE);
        const request = store.get(slot);
        request.onsuccess = () => {
            let current: BankSyncCheckpoint | null;
            try { current = normalized(request.result); } catch { abort(); return; }
            try {
                if (options.signal.aborted || !options.isCurrent()) { result = "stale"; return; }
                if (current?.context !== expected?.context || current?.cursor !== expected?.cursor) { result = "conflict"; return; }
                result = "saved";
                if (next === null) store.delete(slot); else store.put(normalized(next), slot);
            } catch { abort(); }
        };
        tx.oncomplete = () => { cleanup(); resolve(result); };
        tx.onerror = tx.onabort = () => { cleanup(); reject(unavailable()); };
        if (options.signal.aborted) abort();
    });
}
