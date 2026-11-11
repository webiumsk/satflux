import { beforeEach, describe, expect, it, vi } from "vitest";
import { webcrypto } from "node:crypto";
vi.mock("@/evolu/client", () => ({
    migrationSourceProofQuery: "source",
    migrationChunkProofQuery: (companyId: string, sourceId: string) => ({ companyId, sourceId }),
}));
vi.mock("@/evolu/expenseReconciliation", () => ({
    assertExpenseCompanyOwner: (scope: { ownerId: string }, company: string, owner: string) => { if (company !== "company-a" || owner !== scope.ownerId) throw new Error("wrong_owner"); },
    checkpointExpenseMutations: async () => undefined,
}));
vi.mock("@/evolu/bankReconciliation", () => ({
    awaitWorkerOutcome: (_evolu: unknown, start: (complete: (value: unknown) => void, unknown: (e?: unknown) => void) => void) => new Promise((resolve, reject) => start(resolve, reject)),
    awaitBankMutation: async (_evolu: unknown, mutate: (done: () => void) => { ok: boolean }) => new Promise<boolean>(resolve => { const result = mutate(() => resolve(true)); if (!result.ok) resolve(false); }),
}));
import { MIGRATION_CHUNK_BYTES, readMigrationOriginal, retainMigrationOriginal, validateMigrationOriginalFiles } from "@/evolu/migrationArchive";
import { validateMigrationSnapshot } from "@/evolu/migrationSnapshot";
function file(bytes: Uint8Array, name = "export.csv"): File { return { name, size: bytes.length, type: "text/csv", arrayBuffer: async () => new Uint8Array(bytes).buffer } as File; }
function storage() {
    const tables: Record<string, Record<string, unknown>[]> = { migrationSource: [], migrationChunk: [] };
    const writes: string[] = [];
    const evolu = {
        loadQuery: vi.fn(async (query: "source" | { companyId: string; sourceId: string }) => query === "source"
            ? tables.migrationSource
            : tables.migrationChunk.filter(row => row.companyId === query.companyId && row.sourceId === query.sourceId)),
        upsert: vi.fn((table: string, patch: Record<string, unknown>, options: { onComplete: () => void; ownerId: string }) => {
            writes.push(table);
            const existing = tables[table].find(row => row.id === patch.id && row.ownerId === options.ownerId);
            if (existing) Object.assign(existing, patch); else tables[table].push({ ...patch, ownerId: options.ownerId });
            queueMicrotask(options.onComplete); return { ok: true, value: { id: patch.id } };
        }),
    };
    return { tables, writes, evolu, scope: { evolu, ownerId: "owner-a" } as never };
}
beforeEach(() => vi.stubGlobal("crypto", webcrypto));
describe("verified encrypted migration original storage", () => {
    it("round-trips exact multi-chunk bytes and reuses the original instead of duplicating it", async () => {
        const store = storage(); const bytes = Uint8Array.from({ length: MIGRATION_CHUNK_BYTES + 17 }, (_, index) => index % 251);
        const input = { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "superfaktura", file: file(bytes), bytes, assertCurrent: () => undefined };
        const source = await retainMigrationOriginal(store.scope, input);
        expect(store.tables.migrationSource).toHaveLength(1); expect(store.tables.migrationChunk).toHaveLength(2);
        const loaded = await readMigrationOriginal(store.evolu as never, "company-a", "owner-a", source.id);
        expect(Array.from(loaded.bytes)).toEqual(Array.from(bytes));
        const count = store.writes.length;
        expect((await retainMigrationOriginal(store.scope, input)).id).toBe(source.id); expect(store.writes).toHaveLength(count);
    });
    it("retains optional original documents with an explicit batch link and validates a snapshot of all bytes", async () => {
        const store = storage(); const bytes = new TextEncoder().encode("number,total\nA1,100");
        const source = await retainMigrationOriginal(store.scope, { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "superfaktura", file: file(bytes), bytes, assertCurrent: () => undefined });
        const original = new TextEncoder().encode("%PDF-1.7\nSynthetic original bytes");
        const saved = await retainMigrationOriginal(store.scope, { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "original", relatedExportId: source.id,
            file: file(original, "invoice.pdf"), bytes: original, assertCurrent: () => undefined });
        expect(saved.relatedExportId).toBe(source.id);
        await expect(validateMigrationSnapshot({ company: [{ id: "company-a" }], migrationSource: store.tables.migrationSource, migrationChunk: store.tables.migrationChunk })).resolves.toBeUndefined();
        expect(Array.from((await readMigrationOriginal(store.evolu as never, "company-a", "owner-a", saved.id)).bytes)).toEqual(Array.from(original));
    });
    it("can download from independently loaded persisted rows after the writer is discarded", async () => {
        const store = storage(); const bytes = new TextEncoder().encode("original export\r\n");
        const source = await retainMigrationOriginal(store.scope, { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "excel", file: file(bytes), bytes, assertCurrent: () => undefined });
        const persisted = JSON.parse(JSON.stringify(store.tables));
        const reloaded = { loadQuery: async (query: string) => query === "source" ? persisted.migrationSource : persisted.migrationChunk };
        expect(Array.from((await readMigrationOriginal(reloaded as never, "company-a", "owner-a", source.id)).bytes)).toEqual(Array.from(bytes));
    });
    it("rejects missing, corrupted or foreign-owner chunks", async () => {
        const store = storage(); const bytes = new TextEncoder().encode("original");
        const source = await retainMigrationOriginal(store.scope, { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "excel", file: file(bytes), bytes, assertCurrent: () => undefined });
        await expect(readMigrationOriginal(store.evolu as never, "other-company", "owner-a", source.id)).rejects.toThrow("archive_unverified");
        const original = store.tables.migrationChunk[0].contentBase64;
        store.tables.migrationChunk[0].contentBase64 = btoa("tampered");
        await expect(readMigrationOriginal(store.evolu as never, "company-a", "owner-a", source.id)).rejects.toThrow("archive_unverified");
        store.tables.migrationChunk[0].contentBase64 = original;
        store.tables.migrationChunk[0].ownerId = "other-owner";
        await expect(readMigrationOriginal(store.evolu as never, "company-a", "owner-a", source.id)).rejects.toThrow("archive_unverified");
    });
    it("stops before writing when the captured company context is no longer current", async () => {
        const store = storage(); const bytes = new Uint8Array([1, 2, 3]);
        await expect(retainMigrationOriginal(store.scope, { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "excel", file: file(bytes), bytes,
            assertCurrent: () => { throw new Error("preview_changed"); } })).rejects.toThrow("preview_changed");
        expect(store.writes).toEqual([]);
    });
    it("keeps an interrupted archive pending and resumes from its deterministic ids", async () => {
        const store = storage(); const bytes = new Uint8Array(MIGRATION_CHUNK_BYTES + 1).fill(7);
        let checks = 0;
        const input = { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "excel", file: file(bytes), bytes, assertCurrent: () => { if (++checks === 3) throw new Error("interrupted"); } };
        await expect(retainMigrationOriginal(store.scope, input)).rejects.toThrow("interrupted");
        expect(store.tables.migrationSource[0].savedAt).toBeNull();
        const source = await retainMigrationOriginal(store.scope, { ...input, assertCurrent: () => undefined });
        expect(store.tables.migrationSource).toHaveLength(1); expect(store.tables.migrationChunk).toHaveLength(2);
        expect(Array.from((await readMigrationOriginal(store.evolu as never, "company-a", "owner-a", source.id)).bytes)).toEqual(Array.from(bytes));
    });
    it("resumes pending originals under their preserved ids after a company owner transition", async () => {
        const store = storage(); const bytes = new Uint8Array(MIGRATION_CHUNK_BYTES + 1).fill(9);
        let checks = 0;
        const input = { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "superfaktura", file: file(bytes), bytes,
            assertCurrent: () => { if (++checks === 3) throw new Error("interrupted"); } };
        await expect(retainMigrationOriginal(store.scope, input)).rejects.toThrow("interrupted");
        const pendingId = store.tables.migrationSource[0].id;
        for (const rows of Object.values(store.tables)) {
            const copies = rows.map(row => ({ ...row, ownerId: "owner-b" }));
            rows.forEach(row => { row.isDeleted = 1; }); rows.push(...copies);
        }
        Reflect.set(store.scope as object, "ownerId", "owner-b");
        const source = await retainMigrationOriginal(store.scope, { ...input, ownerId: "owner-b", assertCurrent: () => undefined });
        expect(source.id).toBe(pendingId);
        expect(store.tables.migrationSource.filter(row => row.ownerId === "owner-b")).toHaveLength(1);
        expect(store.tables.migrationChunk.filter(row => row.ownerId === "owner-b")).toHaveLength(2);
        expect(Array.from((await readMigrationOriginal(store.evolu as never, "company-a", "owner-b", source.id)).bytes)).toEqual(Array.from(bytes));
    });
    it("fails closed on ambiguous current-owner archive evidence instead of selecting or rewriting one", async () => {
        const store = storage(); const bytes = new TextEncoder().encode("original");
        const input = { companyId: "company-a" as never, ownerId: "owner-a", sourceKind: "excel", file: file(bytes), bytes, assertCurrent: () => undefined };
        await retainMigrationOriginal(store.scope, input);
        store.tables.migrationSource.push({ ...store.tables.migrationSource[0], id: "ambiguous-second-source" });
        const before = store.writes.length;
        await expect(retainMigrationOriginal(store.scope, input)).rejects.toThrow("archive_unverified");
        expect(store.writes).toHaveLength(before);
    });
    it("enforces original file count, type, per-file size and combined size", () => {
        const source = file(new Uint8Array([1]));
        expect(() => validateMigrationOriginalFiles(source, [file(new Uint8Array([1]), "bad.html")])).toThrow("invalid_original");
        const pdf = file(new Uint8Array(350 * 1024), "invoice.pdf");
        expect(() => validateMigrationOriginalFiles(source, Array.from({ length: 30 }, () => pdf))).not.toThrow();
        expect(() => validateMigrationOriginalFiles(source, Array.from({ length: 50 }, () => pdf))).not.toThrow();
        expect(() => validateMigrationOriginalFiles(source, Array.from({ length: 51 }, () => pdf))).toThrow("originals_limit");
        expect(() => validateMigrationOriginalFiles(source, [{ name: "large.pdf", size: 11 * 1024 * 1024 } as File])).toThrow("invalid_original");
        expect(() => validateMigrationOriginalFiles({ size: 10 * 1024 * 1024 } as File, [{ name: "a.pdf", size: 10 * 1024 * 1024 } as File, { name: "b.xml", size: 1 } as File])).toThrow("originals_limit");
    });
    it("accepts legacy snapshots and rejects orphan chunks before restore writes", async () => {
        await expect(validateMigrationSnapshot({ company: [] })).resolves.toBeUndefined();
        await expect(validateMigrationSnapshot({ company: [], migrationChunk: [{ id: "orphan", sourceId: "missing" }] })).rejects.toThrow("archive_unverified");
    });
});
