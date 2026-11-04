import { afterEach, expect, it, vi } from "vitest";
import { compareAndSetBankSyncCheckpoint, readBankSyncCheckpoint } from "@/services/bankSyncCheckpointStore";
import { installBankCheckpointIdb } from "./bankSyncCheckpointTestSupport";
const slot = "a".repeat(64);
const first = { context: "b".repeat(64), cursor: "synthetic-server-ciphertext-1" };
const next = { context: first.context, cursor: "synthetic-server-ciphertext-2" };
const options = () => ({ signal: new AbortController().signal, isCurrent: () => true });
afterEach(() => { vi.unstubAllGlobals(); });

it("persists only ciphertext/hash metadata and returns data across independent reads", async () => {
    const db = installBankCheckpointIdb();
    expect(await compareAndSetBankSyncCheckpoint(slot, null, first, options())).toBe("saved");
    expect(await readBankSyncCheckpoint(slot, new AbortController().signal)).toEqual(first);
    expect([...db.records]).toEqual([[slot, first]]);
    expect(Object.keys(db.records.get(slot) as object)).toEqual(["context", "cursor"]);
});

it("does not resolve a provisional put before commit and preserves old progress after a failed commit", async () => {
    const db = installBankCheckpointIdb(); db.records.set(slot, first); db.control.holdCommits = true;
    let settled = false;
    const saving = compareAndSetBankSyncCheckpoint(slot, first, next, options()).then((result) => { settled = true; return result; });
    await vi.waitFor(() => expect(db.events).toContain("put"));
    expect(settled).toBe(false); expect(db.records.get(slot)).toEqual(first);
    db.control.failNextCommit = true; db.commitAll();
    await expect(saving).rejects.toThrow("bank_sync_checkpoint_unavailable");
    expect(db.records.get(slot)).toEqual(first);
});

it("allows only one concurrent CAS winner and refuses an old context/token overwrite", async () => {
    installBankCheckpointIdb();
    const a = compareAndSetBankSyncCheckpoint(slot, null, first, options());
    const b = compareAndSetBankSyncCheckpoint(slot, null, next, options());
    expect(await Promise.all([a, b])).toEqual(["saved", "conflict"]);
    expect(await compareAndSetBankSyncCheckpoint(slot, { ...first, context: "c".repeat(64) }, next, options())).toBe("conflict");
    expect(await readBankSyncCheckpoint(slot, new AbortController().signal)).toEqual(first);
});

it("checks current context inside the transaction and aborts an in-flight provisional write", async () => {
    const db = installBankCheckpointIdb(); db.records.set(slot, first);
    expect(await compareAndSetBankSyncCheckpoint(slot, first, next, { ...options(), isCurrent: () => false })).toBe("stale");
    expect(db.records.get(slot)).toEqual(first);
    db.control.holdCommits = true;
    const controller = new AbortController();
    const saving = compareAndSetBankSyncCheckpoint(slot, first, next, { signal: controller.signal, isCurrent: () => true });
    const rejected = expect(saving).rejects.toThrow("bank_sync_checkpoint_unavailable");
    await vi.waitFor(() => expect(db.events).toContain("put"));
    controller.abort(); await rejected; db.commitAll();
    expect(db.records.get(slot)).toEqual(first);
});

it("closes an ignored late open after abort and never starts its transaction", async () => {
    const db = installBankCheckpointIdb(); db.control.holdOpen = true;
    const controller = new AbortController();
    const reading = readBankSyncCheckpoint(slot, controller.signal);
    const rejected = expect(reading).rejects.toThrow("bank_sync_checkpoint_unavailable");
    controller.abort(); await rejected; db.flushOpen();
    expect(db.control.closeCount).toBe(1); expect(db.events).toEqual([]);
});
