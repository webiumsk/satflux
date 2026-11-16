import { File as NodeFile } from "node:buffer";
import { webcrypto } from "node:crypto";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createIdFromString } from "@evolu/common";
import { saveManualExpenseAttachmentArchive } from "@/evolu/manualExpenseAttachment";
import { deleteVerifiedExpenseAttachment, readVerifiedExpenseAttachment } from "@/evolu/expenseOriginalArchive";
import { EXPENSE_ORIGINAL_MAX_BYTES, parseExpenseOriginalReference } from "@/evolu/expenseOriginalReference";
import { validateExpenseOriginalSnapshot, validateRegularMigrationSnapshot } from "@/evolu/expenseOriginalSnapshot";
import { EMPTY_INVOICING_SNAPSHOT } from "@/evolu/invoicingSnapshot";
import { buildBackupEnvelopeFromSnapshot } from "@/evolu/invoicingBackup";
import { fingerprintInvoicingSnapshot } from "@/evolu/invoicingRelayFingerprintCore";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import { createExpenseWorkerProbe } from "./evoluExpenseWorkerTestSupport";
import { createExpenseSqliteProbe } from "./expenseSqliteTestSupport";
import { installBankLocks } from "./bankReconciliationTestSupport";

vi.mock("@/evolu/reloadGuard", () => ({ createEvoluWebDepsWithReloadGuard: () => ({
    console: { ...console, log: vi.fn() }, randomBytes: { create: (size: number) => crypto.getRandomValues(new Uint8Array(size)) },
    time: { now: () => Date.now(), nowIso: () => new Date().toISOString() },
    createDbWorker: () => ({ postMessage: vi.fn(), onMessage: vi.fn() }), reloadApp: vi.fn(),
}) }));

beforeEach(() => { installBankLocks(); vi.stubGlobal("crypto", webcrypto); });
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); clearRowOwnerIndex(); clearCompanyShares(); });

function fixture(shared = false, size = 450 * 1024, mime = "application/pdf") {
    const probe = createExpenseWorkerProbe(shared);
    const bytes = Uint8Array.from({ length: size }, (_, index) => index % 251);
    const input = { companyId: probe.candidate.companyId, expenseId: probe.candidate.id as never,
        attachmentId: createIdFromString("synthetic-manual-attachment") as never,
        file: new NodeFile([bytes], "synthetic-document.pdf", { type: mime }) as unknown as File, assertCurrent: vi.fn() };
    const save = () => saveManualExpenseAttachmentArchive(probe.evolu, input);
    const snapshot = () => ({ ...structuredClone(EMPTY_INVOICING_SNAPSHOT), ...structuredClone(probe.state) });
    return { ...probe, bytes, input, save, snapshot };
}

describe("manual large expense attachments", () => {
    it.each([false, true])("retains a 450 KiB file byte-exactly in the correct owner, shared %s", async shared => {
        const store = fixture(shared);
        await store.save();
        const row = store.state.expenseAttachment[0];
        const ref = parseExpenseOriginalReference(row.contentBase64)!;
        expect(ref).toMatchObject({ version: 2, mimeType: "application/pdf", sizeBytes: store.bytes.length });
        expect(ref).not.toHaveProperty("receiptId");
        const owner = shared ? store.ownerId : store.appOwnerId;
        expect(row.ownerId).toBe(owner);
        expect(store.state.migrationSource[0]).toMatchObject({ sourceKind: "expense-attachment", ownerId: owner });
        const initial = store.financialMutations()[0];
        expect(initial.changes?.map(change => change.table)).toEqual(["migrationSource", "expenseAttachment"]);
        const read = await readVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never);
        expect(read.bytes.length).toBe(store.bytes.length);
        expect(Buffer.from(read.bytes).equals(Buffer.from(store.bytes))).toBe(true);
        expect(read.blob.type).toBe("application/pdf");
        await store.save();
        expect(store.state.expenseAttachment).toHaveLength(1);
        expect(store.state.migrationSource).toHaveLength(1);
        expect(store.state.migrationChunk).toHaveLength(2);
    });

    it.each(["image/jpeg", "application/xml", "application/octet-stream"])("preserves manual MIME %s through portable backup and integrity fingerprints", async mime => {
        const store = fixture(false, 450 * 1024, mime);
        await store.save();
        const snapshot = store.snapshot();
        await expect(validateRegularMigrationSnapshot(snapshot as never)).resolves.toBeUndefined();
        const backup = await buildBackupEnvelopeFromSnapshot(snapshot as never, store.appOwnerId);
        expect(backup.version).toBe(3);
        expect(backup.data.expenseAttachment[0].mimeType).toBe(mime);
        const source = backup.data.migrationSource![0];
        const before = fingerprintInvoicingSnapshot(backup.data);
        source.checksum = "b".repeat(64);
        expect(fingerprintInvoicingSnapshot(backup.data)).not.toBe(before);
        source.checksum = parseExpenseOriginalReference(backup.data.expenseAttachment[0].contentBase64)!.sha256;
    });

    it("preserves a former-owner same-id attachment while creating the active shared-owner copy", async () => {
        const store = fixture(true);
        const foreign = { id: store.input.attachmentId, expenseId: store.input.expenseId, ownerId: store.appOwnerId,
            originalFilename: "former.pdf", mimeType: "application/pdf", sizeBytes: "3", contentBase64: btoa("old") };
        store.state.expenseAttachment = [{ ...foreign }];
        await store.save();
        expect(store.state.expenseAttachment.find(row => row.ownerId === store.appOwnerId)).toEqual(foreign);
        expect(store.state.expenseAttachment.filter(row => row.ownerId === store.ownerId)).toHaveLength(1);
        expect(store.state.migrationSource.every(row => row.ownerId === store.ownerId)).toBe(true);
        expect(store.state.migrationChunk.every(row => row.ownerId === store.ownerId)).toBe(true);
    });

    it("accepts exactly 10 MiB without changing a cell limit and rejects +1 before reading the File", async () => {
        const store = fixture(false, EXPENSE_ORIGINAL_MAX_BYTES);
        await store.save();
        expect(store.state.migrationChunk).toHaveLength(40);
        expect(store.state.migrationChunk.every(row => String(row.contentBase64).length < 524288)).toBe(true);
        const read = await readVerifiedExpenseAttachment(store.evolu, store.input.companyId, store.state.expenseAttachment[0] as never);
        expect(read.bytes.length).toBe(store.bytes.length);
        expect(Buffer.from(read.bytes).equals(Buffer.from(store.bytes))).toBe(true);
        const arrayBuffer = vi.fn();
        await expect(saveManualExpenseAttachmentArchive(store.evolu, { ...store.input,
            file: { size: EXPENSE_ORIGINAL_MAX_BYTES + 1, arrayBuffer } as unknown as File })).rejects.toThrow("file_too_large");
        expect(arrayBuffer).not.toHaveBeenCalled();
    }, 30_000);

    it("retains a visible incomplete attachment after an interrupted chunk and resumes the same identity", async () => {
        const store = fixture();
        const upsert = store.evolu.upsert.bind(store.evolu);
        let chunks = 0;
        const spy = vi.spyOn(store.evolu, "upsert").mockImplementation(((table: string, patch: unknown, options: unknown) => {
            if (table === "migrationChunk" && ++chunks === 2) return { ok: false, error: "synthetic quota" };
            return upsert(table as never, patch as never, options as never);
        }) as never);
        await expect(store.save()).rejects.toThrow("original_unverified");
        const row = store.state.expenseAttachment[0];
        expect(row.id).toBe(store.input.attachmentId);
        expect(store.state.migrationSource[0].savedAt).toBeUndefined();
        await expect(readVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never)).rejects.toThrow("original_unverified");
        await expect(validateExpenseOriginalSnapshot(store.snapshot() as never)).rejects.toThrow("original_unverified");
        spy.mockRestore();
        await store.save();
        expect(store.state.expenseAttachment).toHaveLength(1);
        expect(store.state.migrationSource).toHaveLength(1);
        await expect(validateExpenseOriginalSnapshot(store.snapshot() as never)).resolves.toBeUndefined();
    });

    it("deletes incomplete reference and chunks without decoding them and never revives them on retry", async () => {
        const store = fixture();
        store.input.assertCurrent.mockImplementation(() => {
            if ((store.state.migrationChunk?.length ?? 0) > 0) throw new Error("synthetic context changed");
        });
        await expect(store.save()).rejects.toThrow("synthetic context changed");
        const row = store.state.expenseAttachment[0];
        await deleteVerifiedExpenseAttachment(store.evolu, store.input.companyId, row as never);
        expect(store.state.expenseAttachment[0].isDeleted).toBe(1);
        expect(store.state.migrationSource[0].isDeleted).toBe(1);
        expect(store.state.migrationChunk.every(chunk => chunk.isDeleted === 1)).toBe(true);
        store.input.assertCurrent.mockReset();
        await expect(store.save()).rejects.toThrow("original_unverified");
    });

    it("refuses changed MIME or bytes under an already used attachment identity", async () => {
        const store = fixture();
        await store.save();
        const before = structuredClone(store.state);
        for (const file of [new NodeFile([store.bytes], "synthetic-document.pdf", { type: "image/jpeg" }),
            new NodeFile([new Uint8Array(store.bytes.length)], "synthetic-document.pdf", { type: "application/pdf" })]) {
            await expect(saveManualExpenseAttachmentArchive(store.evolu, { ...store.input, file: file as unknown as File })).rejects.toThrow("original_unverified");
        }
        expect(store.state).toEqual(before);
    });

    it("rejects a changed company/owner after a checkpoint and retains the incomplete row for cleanup", async () => {
        const store = fixture();
        store.input.assertCurrent.mockImplementation(() => {
            if ((store.state.migrationChunk?.length ?? 0) > 0) store.state.company[0].ownerId = "foreign-owner";
        });
        await expect(store.save()).rejects.toThrow();
        expect(store.state.expenseAttachment).toHaveLength(1);
        expect(store.state.migrationSource[0].savedAt).toBeUndefined();
        expect(store.state.expenseAttachment[0].ownerId).toBe(store.appOwnerId);
    });

    it("uses real SDK/SQLite atomic validation rollback for the initial source+attachment batch", async () => {
        const probe = await createExpenseSqliteProbe();
        try {
            const input = { companyId: probe.ids.company, expenseId: probe.ids.expense as never,
                attachmentId: createIdFromString("synthetic-native-manual") as never,
                file: new NodeFile([new Uint8Array(450 * 1024).fill(7)], "native.pdf", { type: "application/pdf" }) as unknown as File,
                assertCurrent: () => undefined };
            const upsert = probe.evolu.upsert.bind(probe.evolu);
            const spy = vi.spyOn(probe.evolu, "upsert").mockImplementation(((table: string, patch: Record<string, unknown>, options: unknown) => {
                return upsert(table as never, (table === "expenseAttachment" ? { ...patch, expenseId: "invalid" } : patch) as never, options as never);
            }) as never);
            await expect(saveManualExpenseAttachmentArchive(probe.evolu, input)).rejects.toThrow();
            const failed = await probe.archiveSnapshot();
            expect(failed.expenseAttachment).toHaveLength(0);
            expect(failed.migrationSource).toHaveLength(0);
            spy.mockRestore();
            await saveManualExpenseAttachmentArchive(probe.evolu, input);
            const saved = await probe.archiveSnapshot();
            expect(saved.expenseAttachment).toHaveLength(1);
            expect(saved.migrationSource).toHaveLength(1);
            const read = await readVerifiedExpenseAttachment(probe.evolu, input.companyId, saved.expenseAttachment[0] as never);
            expect(read.bytes.length).toBe(450 * 1024);
            expect(read.bytes.every(byte => byte === 7)).toBe(true);
        } finally { await probe.close(); }
    }, 30_000);
});
