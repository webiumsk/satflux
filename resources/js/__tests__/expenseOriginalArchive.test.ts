import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createHash, webcrypto } from "node:crypto";
import { createIdFromString } from "@evolu/common";
import { retainExpenseOriginalArchive, readVerifiedExpenseAttachment, deleteVerifiedExpenseAttachment, verifyExpenseOriginalArchiveRows } from "@/evolu/expenseOriginalArchive";
import { createExpenseOriginalReference, encodeExpenseOriginalReference, expenseOriginalChunkId,
    EXPENSE_ORIGINAL_MAX_BYTES, EXPENSE_ORIGINAL_SOURCE_KIND } from "@/evolu/expenseOriginalReference";
import { insertLocalExpenseOriginalReference } from "@/evolu/expenseAttachmentCrud";
import { withExpenseReconciliation, checkpointExpenseMutations } from "@/evolu/expenseReconciliation";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares, setCompanyShare } from "@/evolu/companyShareRegistry";
import { createExpenseWorkerProbe } from "./evoluExpenseWorkerTestSupport";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { createExpenseSqliteProbe } from "./expenseSqliteTestSupport";
import { validateExpenseOriginalSnapshot } from "@/evolu/expenseOriginalSnapshot";

vi.mock("@/evolu/reloadGuard", () => ({ createEvoluWebDepsWithReloadGuard: () => ({
    console: { ...console, log: vi.fn() }, randomBytes: { create: (size: number) => crypto.getRandomValues(new Uint8Array(size)) },
    time: { now: () => Date.now(), nowIso: () => new Date().toISOString() },
    createDbWorker: () => ({ postMessage: vi.fn(), onMessage: vi.fn() }), reloadApp: vi.fn(),
}) }));

beforeEach(() => { installBankLocks(); vi.stubGlobal("crypto", webcrypto); });
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); clearRowOwnerIndex(); clearCompanyShares(); });

function fixture(shared = false, size = 400_000) {
    const probe = createExpenseWorkerProbe(shared);
    const bytes = Uint8Array.from({ length: size }, (_, index) => index % 251);
    const input = { companyId: probe.candidate.companyId, ownerId: shared ? probe.ownerId : probe.appOwnerId,
        receiptId: "synthetic-receipt", expenseId: probe.candidate.id, attachmentId: createIdFromString("synthetic-original-attachment"),
        filename: "synthetic-original.xml", bytes, assertCurrent: () => undefined };
    const retain = () => withExpenseReconciliation(probe.evolu, scope => retainExpenseOriginalArchive(scope, input));
    const attach = async () => withExpenseReconciliation(probe.evolu, async scope => {
        const ref = await retainExpenseOriginalArchive(scope, input);
        expect(insertLocalExpenseOriginalReference(scope.evolu, input.expenseId as never, input.attachmentId as never, ref, input.filename).ok).toBe(true);
        await checkpointExpenseMutations(scope.evolu);
        return { ref, row: { ...probe.state.expenseAttachment[0] } };
    }, undefined, ["allExpenseAttachmentsQuery"]);
    return { ...probe, input, retain, attach, bytes };
}

describe("owner-scoped received-original archives", () => {
    it.each(["missing-all", "missing-chunk"])("keeps older SDK archive history deleted when it arrives after deletion: %s", async missing => {
        const probe = await createExpenseSqliteProbe();
        try {
            const ownerId = (await probe.evolu.appOwner).id;
            const bytes = new Uint8Array(400_000).fill(7);
            const attachmentId = createIdFromString("synthetic-sqlite-attachment");
            const ref = await withExpenseReconciliation(probe.evolu, async scope => {
                const reference = await retainExpenseOriginalArchive(scope, { companyId: probe.ids.company, ownerId,
                    receiptId: "synthetic-sqlite-receipt", expenseId: probe.ids.expense, attachmentId,
                    filename: "synthetic-original.xml", bytes, assertCurrent: () => undefined });
                expect(insertLocalExpenseOriginalReference(scope.evolu, probe.ids.expense as never, attachmentId as never,
                    reference, "synthetic-original.xml").ok).toBe(true);
                return reference;
            }, undefined, ["allExpenseAttachmentsQuery"]);
            const chunkIds = Array.from({ length: ref.chunkCount }, (_, index) => expenseOriginalChunkId(ref.sourceId, index));
            // Record actual older live-cell updates through the SDK, not newer
            // writes after deletion or a synthetic last-write-wins model.
            await probe.mutate("migrationSource", { id: ref.sourceId, companyId: probe.ids.company, isDeleted: 0 });
            for (const id of chunkIds) await probe.mutate("migrationChunk", { id, companyId: probe.ids.company, sourceId: ref.sourceId, isDeleted: 0 });
            const before = await probe.archiveSnapshot();
            const row = before.expenseAttachment.find(attachment => attachment.id === attachmentId)!;
            const parked = await probe.parkArchiveHistory({ ownerId, sourceId: ref.sourceId,
                includeSource: missing === "missing-all", chunkIds: missing === "missing-all" ? chunkIds : chunkIds.slice(1) });
            expect(parked.parkedCells).toBeGreaterThan(0);
            await deleteVerifiedExpenseAttachment(probe.evolu, probe.ids.company, row as never);
            const intents = await probe.archiveSnapshot();
            const replay = await probe.replayOlderArchive();
            const after = await probe.archiveSnapshot();
            const liveSources = after.migrationSource.filter(source => source.isDeleted !== 1);
            const liveChunks = after.migrationChunk.filter(chunk => chunk.isDeleted !== 1);
            expect(liveSources).toHaveLength(0);
            expect(liveChunks).toHaveLength(0);
            expect(replay).toEqual({ replayedCells: parked.parkedCells, olderThanDelete: true });
            expect(intents.migrationSource.filter(source => source.id === ref.sourceId && source.ownerId === ownerId && source.isDeleted === 1)).toHaveLength(1);
            for (const id of chunkIds) expect(intents.migrationChunk.filter(chunk => chunk.id === id && chunk.ownerId === ownerId && chunk.isDeleted === 1)).toHaveLength(1);
            if (missing === "missing-all") {
                expect(intents.migrationSource[0]).toMatchObject({ sourceKind: null, companyId: null, checksum: null });
                expect(after.migrationSource[0]).toMatchObject({ sourceKind: EXPENSE_ORIGINAL_SOURCE_KIND, companyId: probe.ids.company, checksum: ref.sha256, isDeleted: 1 });
            }
            for (const id of missing === "missing-all" ? chunkIds : chunkIds.slice(1)) {
                expect(intents.migrationChunk.find(chunk => chunk.id === id)).toMatchObject({ contentBase64: null, sourceId: null, companyId: null });
            }
            await expect(validateExpenseOriginalSnapshot({ ...after, expenseAttachment: after.expenseAttachment.filter(attachment => attachment.isDeleted !== 1),
                migrationSource: liveSources, migrationChunk: liveChunks } as never)).resolves.toBeUndefined();
        } finally { await probe.close(); }
    });
    it.each([false, true])("commits bounded real Evolu mutations and reads exact bytes in current owner partition, shared %s", async shared => {
        const store = fixture(shared);
        const { ref, row } = await store.attach();
        expect(store.state.migrationSource).toHaveLength(1);
        expect(store.state.migrationSource[0]).toMatchObject({ sourceKind: EXPENSE_ORIGINAL_SOURCE_KIND, checksum: ref.sha256,
            ownerId: store.input.ownerId, savedAt: expect.any(String) });
        expect(store.state.migrationChunk).toHaveLength(2);
        expect(store.state.migrationChunk.every(chunk => String(chunk.contentBase64).length < 524288)).toBe(true);
        const read = await readVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never);
        expect(Buffer.from(read.bytes)).toEqual(Buffer.from(store.bytes));
        expect(read.blob.size).toBe(store.bytes.length);
        const writes = store.financialMutations().length;
        expect(await store.retain()).toEqual(ref);
        expect(store.financialMutations()).toHaveLength(writes);
    });

    it("supports the existing exact 10 MiB bound as forty safe mutations", async () => {
        const store = fixture(false, EXPENSE_ORIGINAL_MAX_BYTES);
        const ref = await store.retain();
        expect(ref.chunkCount).toBe(40);
        expect(store.state.migrationChunk).toHaveLength(40);
        const bytes = await verifyExpenseOriginalArchiveRows(ref, store.state.migrationSource, store.state.migrationChunk, store.input.ownerId);
        expect(createHash("sha256").update(bytes).digest("hex")).toBe(ref.sha256);
    }, 20_000);

    it("resumes a persisted interrupted chunk write without duplicating or falsely completing evidence", async () => {
        const store = fixture();
        const upsert = store.evolu.upsert.bind(store.evolu);
        let chunks = 0;
        const spy = vi.spyOn(store.evolu, "upsert").mockImplementation(((table: string, patch: unknown, options: unknown) => {
            if (table === "migrationChunk" && ++chunks === 2) return { ok: false, error: "synthetic quota" };
            return upsert(table as never, patch as never, options as never);
        }) as never);
        await expect(store.retain()).rejects.toThrow("original_unverified");
        expect(store.state.migrationSource[0].savedAt).toBeUndefined();
        expect(store.state.migrationChunk).toHaveLength(1);
        expect(store.state.expenseAttachment).toBeUndefined();
        spy.mockRestore();
        await store.retain();
        expect(store.state.migrationSource).toHaveLength(1);
        expect(store.state.migrationChunk).toHaveLength(2);
        expect(store.state.migrationSource[0].savedAt).toEqual(expect.any(String));
    });

    it.each(["source-deleted", "chunk-deleted", "chunk-corrupt", "chunk-foreign", "extra-chunk"])("never repairs or revives invalid completed evidence: %s", async invalid => {
        const store = fixture();
        await store.retain();
        if (invalid === "source-deleted") store.state.migrationSource[0].isDeleted = 1;
        if (invalid === "chunk-deleted") store.state.migrationChunk[0].isDeleted = 1;
        if (invalid === "chunk-corrupt") store.state.migrationChunk[0].contentBase64 = btoa("wrong");
        if (invalid === "chunk-foreign") store.state.migrationChunk[0].ownerId = "foreign";
        if (invalid === "extra-chunk") store.state.migrationChunk.push({ ...store.state.migrationChunk[0], id: createIdFromString("extra") });
        const before = structuredClone(store.state);
        await expect(store.retain()).rejects.toThrow("original_unverified");
        expect(store.state.migrationSource).toEqual(before.migrationSource);
        expect(store.state.migrationChunk).toEqual(before.migrationChunk);
    });

    it("preserves a concurrently completed ready cell instead of resetting it during a pending retry", async () => {
        const store = fixture();
        const upsert = store.evolu.upsert.bind(store.evolu);
        const spy = vi.spyOn(store.evolu, "upsert").mockImplementation(((table: string, patch: Record<string, unknown>, options: unknown) => {
            if (table === "migrationSource" && !("savedAt" in patch)) {
                expect(patch).not.toHaveProperty("savedAt");
                store.state.migrationSource ??= [];
                store.state.migrationSource.push({ ...patch, ownerId: store.input.ownerId, savedAt: "2026-11-15T10:00:00Z" });
            }
            return upsert(table as never, patch as never, options as never);
        }) as never);
        await store.retain();
        spy.mockRestore();
        expect(store.state.migrationSource[0].savedAt).toEqual(expect.any(String));
    });

    it("keeps receipt/source/chunk identity through an authoritative company-owner transition", async () => {
        const store = fixture();
        const { ref } = await store.attach();
        for (const table of ["expense", "expenseAttachment", "migrationSource", "migrationChunk"]) {
            const copies = store.state[table].map(row => ({ ...row, ownerId: store.ownerId }));
            store.state[table].forEach(row => { row.isDeleted = 1; });
            store.state[table].push(...copies);
        }
        store.state.company[0].ownerId = store.ownerId;
        store.state.companyShare.push({ id: createIdFromString("new-share"), ownerId: store.appOwnerId,
            companyId: store.input.companyId, sharedOwnerId: store.ownerId, status: "active", role: "owner" });
        setCompanyShare({ companyId: store.input.companyId, ownerId: store.ownerId, role: "owner", status: "active", bridgeCompanyId: null });
        store.input.ownerId = store.ownerId;
        expect(await store.retain()).toEqual(ref);
        const row = store.state.expenseAttachment.find(attachment => attachment.ownerId === store.ownerId)!;
        expect(Buffer.from((await readVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never)).bytes)).toEqual(Buffer.from(store.bytes));
        expect(store.state.migrationSource.filter(source => source.ownerId === store.ownerId)).toHaveLength(1);
        expect(store.state.migrationChunk.filter(chunk => chunk.ownerId === store.ownerId)).toHaveLength(2);
    });

    it("rejects wrong companies, stale attachment content and consumer caps before archive byte loading", async () => {
        const store = fixture();
        const { row } = await store.attach();
        await expect(readVerifiedExpenseAttachment(store.evolu, "foreign", row as never)).rejects.toThrow();
        await expect(readVerifiedExpenseAttachment(store.evolu, store.input.companyId, { ...row, contentBase64: btoa("stale") } as never)).rejects.toThrow("original_unverified");
        await expect(readVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never, { maxBytes: 384 * 1024 })).rejects.toThrow("original_too_large");
        expect(store.state.migrationChunk).toHaveLength(2);
    });

    it("deletes only the authorized reference and its archive, leaving unrelated company evidence", async () => {
        const store = fixture();
        const { row } = await store.attach();
        store.state.migrationSource.push({ id: "unrelated", companyId: "other", ownerId: store.appOwnerId });
        await deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never);
        expect(store.state.expenseAttachment[0].isDeleted).toBe(1);
        expect(store.state.migrationSource[0].isDeleted).toBe(1);
        expect(store.state.migrationChunk.every(chunk => chunk.isDeleted === 1)).toBe(true);
        expect(store.state.migrationSource[1].isDeleted).toBeUndefined();
        await expect(store.retain()).rejects.toThrow("original_unverified");
    });

    it("leaves the reference and every archive row intact when a deletion batch fails real Evolu validation", async () => {
        const store = fixture();
        const { row } = await store.attach();
        const update = store.evolu.update.bind(store.evolu);
        vi.spyOn(store.evolu, "update").mockImplementation(((table: string, patch: Record<string, unknown>, options: unknown) =>
            update(table as never, (table === "migrationChunk" ? { ...patch, isDeleted: "invalid" } : patch) as never, options as never)) as never);
        await expect(deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never)).rejects.toThrow("original_unverified");
        expect(store.state.expenseAttachment.every(attachment => attachment.isDeleted !== 1)).toBe(true);
        expect(store.state.migrationSource.every(source => source.isDeleted !== 1)).toBe(true);
        expect(store.state.migrationChunk.every(chunk => chunk.isDeleted !== 1)).toBe(true);
    });

    it("does not commit any missing-row tombstone intents when the atomic batch fails validation", async () => {
        const store = fixture(false, 3);
        const { row } = await store.attach();
        store.state.migrationSource.splice(0); store.state.migrationChunk.splice(0);
        const update = store.evolu.update.bind(store.evolu);
        vi.spyOn(store.evolu, "update").mockImplementation(((table: string, patch: Record<string, unknown>, options: unknown) =>
            update(table as never, (table === "migrationChunk" ? { ...patch, isDeleted: "invalid" } : patch) as never, options as never)) as never);
        await expect(deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never)).rejects.toThrow("original_unverified");
        expect(store.state.expenseAttachment[0].isDeleted).not.toBe(1);
        expect(store.state.migrationSource).toHaveLength(0);
        expect(store.state.migrationChunk).toHaveLength(0);
    });

    it("records exactly forty-two canonical intent mutations at the maximum bound without inventing archive content", async () => {
        const store = fixture(false, 3);
        const { ref, row } = await store.attach();
        const largest = createExpenseOriginalReference({ ...ref, sizeBytes: EXPENSE_ORIGINAL_MAX_BYTES });
        row.contentBase64 = encodeExpenseOriginalReference(largest);
        row.sizeBytes = String(largest.sizeBytes);
        Object.assign(store.state.expenseAttachment[0], row);
        store.state.migrationSource.splice(0); store.state.migrationChunk.splice(0);
        const before = store.financialMutations().length;
        await deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never);
        const batches = store.financialMutations().slice(before);
        expect(batches).toHaveLength(1);
        expect(batches[0].changes).toHaveLength(42);
        expect(store.state.migrationSource).toHaveLength(1);
        expect(store.state.migrationChunk).toHaveLength(40);
        expect([...store.state.migrationSource, ...store.state.migrationChunk].every(archive => archive.ownerId === store.input.ownerId
            && archive.isDeleted === 1 && archive.contentBase64 == null && archive.companyId == null)).toBe(true);
    });

    it("rejects a claimed completion when a canonical absent-row intent is missing from readback", async () => {
        const store = fixture(false, 3);
        const { row } = await store.attach();
        store.state.migrationSource.splice(0); store.state.migrationChunk.splice(0);
        const update = store.evolu.update.bind(store.evolu);
        vi.spyOn(store.evolu, "update").mockImplementation(((table: string, patch: Record<string, unknown>, options: { onComplete?: () => void }) => {
            if (table === "migrationChunk") {
                queueMicrotask(() => options?.onComplete?.());
                return { ok: true, value: { id: patch.id } };
            }
            return update(table as never, patch as never, options as never);
        }) as never);
        await expect(deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never)).rejects.toThrow("original_unverified");
        expect(store.state.migrationChunk).toHaveLength(0);
    });

    it.each(["corrupt-chunk", "missing-chunk", "missing-source", "missing-all", "tombstoned-chunk"])("records canonical deletion intents without requiring complete original bytes: %s", async damage => {
        const store = fixture();
        const { ref, row } = await store.attach();
        if (damage === "corrupt-chunk") store.state.migrationChunk[0].contentBase64 = "corrupt bytes";
        if (damage === "missing-chunk") store.state.migrationChunk.pop();
        if (damage === "missing-source" || damage === "missing-all") store.state.migrationSource.splice(0);
        if (damage === "missing-all") store.state.migrationChunk.splice(0);
        if (damage === "tombstoned-chunk") store.state.migrationChunk[0].isDeleted = 1;
        const sourcesBefore = new Set(store.state.migrationSource.map(source => source.id));
        const chunksBefore = new Set(store.state.migrationChunk.map(chunk => chunk.id));
        const chunkIds = Array.from({ length: ref.chunkCount }, (_, index) => expenseOriginalChunkId(ref.sourceId, index));
        await deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never);
        expect(store.state.expenseAttachment[0].isDeleted).toBe(1);
        expect(store.state.migrationSource.map(source => source.id)).toEqual([ref.sourceId]);
        expect(store.state.migrationChunk.map(chunk => chunk.id).sort()).toEqual(chunkIds.sort());
        expect(store.state.migrationSource.every(source => source.isDeleted === 1)).toBe(true);
        expect(store.state.migrationChunk.every(chunk => chunk.isDeleted === 1)).toBe(true);
        for (const source of store.state.migrationSource.filter(source => !sourcesBefore.has(source.id))) {
            expect(source.ownerId).toBe(store.input.ownerId);
            expect(source.sourceKind ?? null).toBeNull();
            expect(source.companyId ?? null).toBeNull();
        }
        for (const chunk of store.state.migrationChunk.filter(chunk => !chunksBefore.has(chunk.id))) {
            expect(chunk.ownerId).toBe(store.input.ownerId);
            expect(chunk.contentBase64 ?? null).toBeNull();
            expect(chunk.companyId ?? null).toBeNull();
            expect(chunk.sourceId ?? null).toBeNull();
        }
        await expect(store.retain()).rejects.toThrow("original_unverified");
    });

    it.each(["source-company", "source-kind", "chunk-company", "chunk-source", "chunk-id", "chunk-index", "duplicate-chunk", "foreign-reference", "malformed-reference"])("rejects unauthorized or ambiguous deletion bindings before mutation: %s", async damage => {
        const store = fixture(false, 3);
        const { ref, row } = await store.attach();
        if (damage === "source-company") store.state.migrationSource[0].companyId = createIdFromString("foreign-company");
        if (damage === "source-kind") store.state.migrationSource[0].sourceKind = "original";
        if (damage === "chunk-company") store.state.migrationChunk[0].companyId = createIdFromString("foreign-company");
        if (damage === "chunk-source") store.state.migrationChunk[0].sourceId = createIdFromString("foreign-source");
        if (damage === "chunk-id") store.state.migrationChunk[0].id = createIdFromString("wrong-chunk-id");
        if (damage === "chunk-index") store.state.migrationChunk[0].chunkIndex = "1";
        if (damage === "duplicate-chunk") store.state.migrationChunk.push({ ...store.state.migrationChunk[0] });
        if (damage === "foreign-reference") {
            const marker = encodeExpenseOriginalReference(createExpenseOriginalReference({ ...ref, companyId: createIdFromString("foreign-company") }));
            row.contentBase64 = marker; store.state.expenseAttachment[0].contentBase64 = marker;
        }
        if (damage === "malformed-reference") {
            row.contentBase64 = "satflux:expense-original:v2:{}";
            store.state.expenseAttachment[0].contentBase64 = row.contentBase64;
        }
        const before = structuredClone(store.state);
        const mutations = store.financialMutations().length;
        await expect(deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never)).rejects.toThrow("original_unverified");
        expect(store.financialMutations()).toHaveLength(mutations);
        for (const table of ["expenseAttachment", "migrationSource", "migrationChunk"]) expect(store.state[table]).toEqual(before[table]);
    });

    it("preserves former-owner copies with identical archive ids while deleting only the current partition", async () => {
        const store = fixture(false, 3);
        const { row } = await store.attach();
        const formerOwner = createIdFromString("former-owner");
        const formerSource = { ...store.state.migrationSource[0], ownerId: formerOwner };
        const formerChunk = { ...store.state.migrationChunk[0], ownerId: formerOwner };
        store.state.migrationSource.push(formerSource); store.state.migrationChunk.push(formerChunk);
        await deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never);
        expect(store.state.migrationSource[0].isDeleted).toBe(1);
        expect(store.state.migrationChunk[0].isDeleted).toBe(1);
        expect(Reflect.get(formerSource, "isDeleted")).toBeUndefined();
        expect(Reflect.get(formerChunk, "isDeleted")).toBeUndefined();
    });

    it("validates ownerless snapshot bytes but rejects mixed owners, dangling chunks and missing ready proof", async () => {
        const bytes = new Uint8Array([0, 255, 1]);
        const ref = createExpenseOriginalReference({ companyId: "company", expenseId: "expense", attachmentId: "attachment",
            receiptId: "receipt", sizeBytes: bytes.length, sha256: createHash("sha256").update(bytes).digest("hex") });
        const source = { id: ref.sourceId, companyId: ref.companyId, sourceKind: EXPENSE_ORIGINAL_SOURCE_KIND, sizeBytes: "3",
            checksum: ref.sha256, chunkCount: "1", originalFilename: "original.xml", mimeType: "application/xml", savedAt: "2026-11-15" };
        const chunk = { id: expenseOriginalChunkId(ref.sourceId, 0), sourceId: ref.sourceId, companyId: ref.companyId, chunkIndex: "0", contentBase64: btoa("\x00\xff\x01") };
        expect(Buffer.from(await verifyExpenseOriginalArchiveRows(ref, [source], [chunk]))).toEqual(Buffer.from(bytes));
        await expect(verifyExpenseOriginalArchiveRows(ref, [{ ...source, savedAt: null }], [chunk])).rejects.toThrow("original_unverified");
        await expect(verifyExpenseOriginalArchiveRows(ref, [source], [{ ...chunk, ownerId: "foreign" }])).rejects.toThrow("original_unverified");
        await expect(verifyExpenseOriginalArchiveRows(ref, [source], [])).rejects.toThrow("original_unverified");
        expect(encodeExpenseOriginalReference(ref)).not.toBe(chunk.contentBase64);
    });
});
