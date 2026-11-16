import { beforeEach, describe, expect, it, vi } from "vitest";
import { webcrypto } from "node:crypto";

vi.mock("@/evolu/client", () => Object.fromEntries([
    "allBankImportBatchesQuery", "allBankTransactionMatchesQuery", "allBankTransactionsQuery", "allCompaniesDetailQuery",
    "allCompanyStockBalancesQuery", "allCompanyStockItemsQuery", "allCompanyStockMovementsQuery", "allCompanyWarehousesQuery",
    "allContactsQuery", "allDocumentEventsQuery", "allDocumentLinesQuery", "allDocumentSnapshotsQuery", "allDocumentsQuery",
    "allExpensesQuery", "allExpenseAttachmentsQuery", "allNumberSeriesQuery", "allInvoiceTemplatesQuery",
    "allRecurringProfilesQuery", "allRecurringProfileLinesQuery", "allMigrationSourcesBackupQuery", "allMigrationChunksQuery",
    "allExpenseOriginalSourcesQuery", "allExpenseOriginalChunksQuery",
].map(name => [name, name])));
vi.mock("@/evolu/expenseReconciliation", () => ({
    withExpenseMaintenance: async (evolu: unknown, run: (scope: unknown) => unknown) => run({ evolu }),
    commitExpenseMutation: async (_client: unknown, mutate: (complete: () => void) => { ok: boolean }) => new Promise(resolve => {
        const result = mutate(() => resolve({ ok: true }));
        if (!result.ok) resolve(result);
    }),
}));

import { EMPTY_INVOICING_SNAPSHOT, restoreInvoicingSnapshotAsync, type InvoicingDataSnapshot } from "@/evolu/invoicingSnapshot";
import { buildBackupEnvelopeFromSnapshot } from "@/evolu/invoicingBackup";
import { parseAndValidateBackup, restoreInvoicingBackup } from "@/evolu/invoicingBackupRestore";
import { snapshotHasExpenseOriginals, validateExpenseOriginalSnapshot } from "@/evolu/expenseOriginalSnapshot";
import { createExpenseOriginalReference, encodeExpenseOriginalReference, expenseOriginalChunkId,
    EXPENSE_ORIGINAL_CHUNK_BYTES, EXPENSE_ORIGINAL_SOURCE_KIND } from "@/evolu/expenseOriginalReference";
import { verifyExpenseOriginalArchiveRows } from "@/evolu/expenseOriginalArchive";
import { deterministicStringify } from "@/evolu/documentSnapshotCrud";
import { migrationSha256 } from "@/evolu/migrationArchiveValues";
import { sha256Hex } from "@/utils/sha256";

beforeEach(() => vi.stubGlobal("crypto", webcrypto));

async function fixture(size = 450 * 1024, suffix = "one") {
    const bytes = new Uint8Array(size).fill(32);
    bytes.set(new TextEncoder().encode('\ufeff<Invoice>synthetic received original</Invoice>'));
    const ref = createExpenseOriginalReference({ companyId: "company", receiptId: `receipt-${suffix}`,
        expenseId: `expense-${suffix}`, attachmentId: `attachment-${suffix}`, sha256: await migrationSha256(bytes), sizeBytes: bytes.length });
    const source = { id: ref.sourceId, companyId: ref.companyId, sourceKind: EXPENSE_ORIGINAL_SOURCE_KIND,
        relatedExportId: null, originalFilename: "received.xml", mimeType: "application/xml", sizeBytes: String(bytes.length),
        checksum: ref.sha256, chunkCount: String(ref.chunkCount), savedAt: "2026-11-15T10:00:00Z" };
    const chunks = Array.from({ length: ref.chunkCount }, (_, index) => ({
        id: expenseOriginalChunkId(ref.sourceId, index), companyId: ref.companyId, sourceId: ref.sourceId,
        chunkIndex: String(index), contentBase64: Buffer.from(bytes.subarray(index * EXPENSE_ORIGINAL_CHUNK_BYTES,
            (index + 1) * EXPENSE_ORIGINAL_CHUNK_BYTES)).toString("base64"),
    }));
    const attachment = { id: ref.attachmentId, expenseId: ref.expenseId, originalFilename: source.originalFilename,
        mimeType: source.mimeType, sizeBytes: source.sizeBytes, contentBase64: encodeExpenseOriginalReference(ref) };
    const snapshot: InvoicingDataSnapshot = { ...EMPTY_INVOICING_SNAPSHOT, company: [{ id: ref.companyId }],
        expense: [{ id: ref.expenseId, companyId: ref.companyId }], expenseAttachment: [attachment],
        migrationSource: [source], migrationChunk: chunks };
    return { snapshot, bytes, ref, source, chunks, attachment };
}

function writer() {
    const upsert = vi.fn((_table: string, _row: unknown, options: { onComplete: () => void }) => {
        queueMicrotask(options.onComplete); return { ok: true, value: { id: "synthetic" } };
    });
    return { evolu: { upsert } as never, upsert };
}

describe("portable expense original snapshots", () => {
    it("exports version 3 and restores exact BOM-bearing bytes, manifest and source links", async () => {
        const x = await fixture();
        const envelope = await buildBackupEnvelopeFromSnapshot(x.snapshot, null);
        expect(envelope.version).toBe(3);
        const parsed = await parseAndValidateBackup(JSON.stringify(envelope), null);
        if (!parsed.ok) throw new Error(parsed.error);
        const target = writer();
        await expect(restoreInvoicingBackup(target.evolu, parsed.value)).resolves.toEqual({ upserted: 4 + x.chunks.length, failed: [] });
        const source = target.upsert.mock.calls.filter(call => call[0] === "migrationSource").map(call => call[1] as Record<string, unknown>);
        const chunks = target.upsert.mock.calls.filter(call => call[0] === "migrationChunk").map(call => call[1] as Record<string, unknown>);
        expect(await verifyExpenseOriginalArchiveRows(x.ref, source, chunks)).toEqual(x.bytes);
        expect(target.upsert.mock.calls.find(call => call[0] === "expenseAttachment")?.[1]).toEqual(x.attachment);
    });

    it("accepts full known-table version-2 backups without omitting any marker or bytes", async () => {
        const x = await fixture();
        const envelope = await buildBackupEnvelopeFromSnapshot(x.snapshot, null);
        const parsed = await parseAndValidateBackup(JSON.stringify({ ...envelope, version: 2 }), null);
        expect(parsed.ok).toBe(true);
        if (parsed.ok) expect(parsed.value.envelope.data).toEqual(x.snapshot);
    });

    it("rejects a hash-valid old version-1 omission before any restore write", async () => {
        const x = await fixture();
        const envelope = await buildBackupEnvelopeFromSnapshot(x.snapshot, null);
        const omitted = { ...x.snapshot, migrationSource: [], migrationChunk: [] };
        const text = JSON.stringify({ ...envelope, version: 1, data: omitted, sha256: await sha256Hex(deterministicStringify(omitted)) });
        expect(await parseAndValidateBackup(text, null)).toEqual({ ok: false, error: "original_unverified" });
        const target = writer();
        await expect(restoreInvoicingSnapshotAsync(target.evolu, omitted)).rejects.toThrow("original_unverified");
        expect(target.upsert).not.toHaveBeenCalled();
    });

    it.each(["missing-chunk", "foreign-company", "wrong-owner", "wrong-filename", "wrong-expense", "tampered-byte", "duplicate-source", "unfinished", "extra-chunk", "unsupported"])("rejects %s before backup output and before any restore write", async kind => {
        const x = await fixture();
        const bad = structuredClone(x.snapshot);
        if (kind === "missing-chunk") bad.migrationChunk = bad.migrationChunk!.slice(1);
        if (kind === "foreign-company") bad.expense[0].companyId = "foreign-company";
        if (kind === "wrong-owner") bad.migrationChunk![0].ownerId = "foreign-owner";
        if (kind === "wrong-filename") bad.expenseAttachment[0].originalFilename = "different.xml";
        if (kind === "wrong-expense") bad.expenseAttachment[0].expenseId = "other-expense";
        if (kind === "tampered-byte") bad.migrationChunk![0].contentBase64 = Buffer.alloc(EXPENSE_ORIGINAL_CHUNK_BYTES, 33).toString("base64");
        if (kind === "duplicate-source") bad.migrationSource = [...bad.migrationSource!, { ...bad.migrationSource![0] }];
        if (kind === "unfinished") bad.migrationSource![0].savedAt = null;
        if (kind === "extra-chunk") bad.migrationChunk = [...bad.migrationChunk!, { ...bad.migrationChunk![0], id: "unexpected" }];
        if (kind === "unsupported") bad.expenseAttachment[0].contentBase64 = "satflux:expense-original:v9:{}";
        expect(snapshotHasExpenseOriginals(bad)).toBe(true);
        await expect(buildBackupEnvelopeFromSnapshot(bad, null)).rejects.toThrow("original_unverified");
        const target = writer();
        await expect(restoreInvoicingSnapshotAsync(target.evolu, bad)).rejects.toThrow("original_unverified");
        expect(target.upsert).not.toHaveBeenCalled();
    });

    it.each((["company", "expense", "expenseAttachment"] as const).flatMap(table =>
        [2, true, "1", 1].map(isDeleted => ({ table, isDeleted }))))(
        "rejects $table live flag $isDeleted before backup output and before any restore write", async ({ table, isDeleted }) => {
            const { snapshot } = await fixture();
            const bad = structuredClone(snapshot);
            bad[table][0].isDeleted = isDeleted;
            await expect(buildBackupEnvelopeFromSnapshot(bad, null)).rejects.toThrow("original_unverified");
            const target = writer();
            await expect(restoreInvoicingSnapshotAsync(target.evolu, bad)).rejects.toThrow("original_unverified");
            expect(target.upsert).not.toHaveBeenCalled();
        },
    );

    it.each([undefined, null, 0])("accepts the canonical live flag %j on every original parent", async isDeleted => {
        const { snapshot, chunks } = await fixture();
        const live = structuredClone(snapshot);
        for (const table of ["company", "expense", "expenseAttachment"] as const) live[table][0].isDeleted = isDeleted;
        expect((await buildBackupEnvelopeFromSnapshot(live, null)).version).toBe(3);
        const target = writer();
        await expect(restoreInvoicingSnapshotAsync(target.evolu, live)).resolves.toBe(4 + chunks.length);
        expect(target.upsert).toHaveBeenCalledTimes(4 + chunks.length);
    });

    it("detects unbound and incomplete new-kind sources even without a marker", async () => {
        const x = await fixture();
        const unbound = { ...x.snapshot, expenseAttachment: [] };
        expect(snapshotHasExpenseOriginals(unbound)).toBe(true);
        await expect(buildBackupEnvelopeFromSnapshot(unbound, null)).rejects.toThrow("original_unverified");
    });

    it("permits more than 50 received originals without a migration batch lifetime cap", async () => {
        const fixtures = await Promise.all(Array.from({ length: 51 }, (_, index) => fixture(80, String(index))));
        const many: InvoicingDataSnapshot = { ...EMPTY_INVOICING_SNAPSHOT, company: fixtures[0].snapshot.company,
            expense: fixtures.flatMap(x => x.snapshot.expense), expenseAttachment: fixtures.flatMap(x => x.snapshot.expenseAttachment),
            migrationSource: fixtures.flatMap(x => x.source), migrationChunk: fixtures.flatMap(x => x.chunks) };
        await expect(validateExpenseOriginalSnapshot(many)).resolves.toBeUndefined();
        expect((await buildBackupEnvelopeFromSnapshot(many, null)).version).toBe(3);
    });
});
