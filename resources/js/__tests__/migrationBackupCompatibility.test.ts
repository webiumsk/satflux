import { beforeEach, describe, expect, it, vi } from 'vitest';
import { webcrypto } from 'node:crypto';

vi.mock('@/evolu/client', () => Object.fromEntries([
    'allBankImportBatchesQuery', 'allBankTransactionMatchesQuery', 'allBankTransactionsQuery', 'allCompaniesDetailQuery',
    'allCompanyStockBalancesQuery', 'allCompanyStockItemsQuery', 'allCompanyStockMovementsQuery', 'allCompanyWarehousesQuery',
    'allContactsQuery', 'allDocumentEventsQuery', 'allDocumentLinesQuery', 'allDocumentSnapshotsQuery', 'allDocumentsQuery',
    'allExpensesQuery', 'allExpenseAttachmentsQuery', 'allNumberSeriesQuery', 'allInvoiceTemplatesQuery',
    'allRecurringProfilesQuery', 'allRecurringProfileLinesQuery', 'allMigrationSourcesBackupQuery', 'allMigrationChunksQuery',
].map(name => [name, name])));
vi.mock('@/evolu/expenseReconciliation', () => ({
    withExpenseMaintenance: async (evolu: unknown, run: (scope: unknown) => unknown) => run({ evolu }),
    commitExpenseMutation: async (_client: unknown, mutate: (complete: () => void) => { ok: boolean }) => new Promise(resolve => {
        const result = mutate(() => resolve({ ok: true }));
        if (!result.ok) resolve(result);
    }),
}));

import { buildBackupEnvelopeFromSnapshot, INVOICING_BACKUP_VERSION, INVOICING_ARCHIVE_BACKUP_VERSION } from '@/evolu/invoicingBackup';
import { decryptAndValidateBackup, parseAndValidateBackup, restoreInvoicingBackup } from '@/evolu/invoicingBackupRestore';
import { encryptBackupEnvelopeText } from '@/evolu/invoicingBackupCrypto';
import { decryptBackupEnvelopeText } from '@/evolu/invoicingBackupCrypto';
import { parseAndValidateBackup as parseAndValidateLegacyBackup } from './fixtures/legacyBackupV1';
import { EMPTY_INVOICING_SNAPSHOT } from '@/evolu/invoicingSnapshot';
import { migrationSha256 } from '@/evolu/migrationArchiveValues';

beforeEach(() => vi.stubGlobal('crypto', webcrypto));

async function archived() {
    const bytes = new TextEncoder().encode('\ufeff<Invoice>Žltý</Invoice>');
    const checksum = await migrationSha256(bytes);
    const source = { id: 'source', companyId: 'company', sourceKind: 'superfaktura', relatedExportId: null,
        originalFilename: 'source.xml', mimeType: 'application/xml', sizeBytes: String(bytes.length), checksum, chunkCount: '1', savedAt: '2026-11-10T12:00:00Z' };
    const chunk = { id: 'chunk', companyId: 'company', sourceId: 'source', chunkIndex: '0', contentBase64: Buffer.from(bytes).toString('base64') };
    return { bytes, source, chunk, snapshot: { ...EMPTY_INVOICING_SNAPSHOT, company: [{ id: 'company' }], migrationSource: [source], migrationChunk: [chunk] } };
}

describe('archive-bearing backup version compatibility', () => {
    it('keeps legacy and archive-empty exports at version 1', async () => {
        const backup = await buildBackupEnvelopeFromSnapshot({ ...EMPTY_INVOICING_SNAPSHOT }, null);
        expect(backup.version).toBe(INVOICING_BACKUP_VERSION);
        expect(backup.version).toBe(1);
        expect((await parseAndValidateBackup(JSON.stringify(backup), null)).ok).toBe(true);
        expect((await parseAndValidateLegacyBackup(JSON.stringify(backup), null)).ok).toBe(true);
    });

    it('requires version 2 for source-only, chunk-only and complete archives', async () => {
        const data = await archived();
        for (const snapshot of [data.snapshot, { ...data.snapshot, migrationChunk: [] }, { ...data.snapshot, migrationSource: [] }]) {
            const backup = await buildBackupEnvelopeFromSnapshot(snapshot, null);
            expect(backup.version).toBe(INVOICING_ARCHIVE_BACKUP_VERSION);
            // Execute the verbatim historical parser, not a reimplementation of its rule.
            expect(await parseAndValidateLegacyBackup(JSON.stringify(backup), null)).toEqual({ ok: false, error: 'unsupported_version' });
        }
    });

    it('restores exact original bytes and source links using the current reader', async () => {
        const data = await archived();
        const backup = await buildBackupEnvelopeFromSnapshot(data.snapshot, null);
        const parsed = await parseAndValidateBackup(JSON.stringify(backup), null);
        if (!parsed.ok) throw new Error('Synthetic version-2 backup was refused');
        const upsert = vi.fn((_table: string, _row: unknown, options: { onComplete: () => void }) => {
            queueMicrotask(options.onComplete);
            return { ok: true, value: { id: 'synthetic' } };
        });
        const result = await restoreInvoicingBackup({ upsert } as never, parsed.value);
        expect(result).toMatchObject({ upserted: 3, failed: [] });
        expect(upsert.mock.calls.find(call => call[0] === 'migrationSource')?.[1]).toEqual(data.source);
        const retained = upsert.mock.calls.find(call => call[0] === 'migrationChunk')?.[1] as typeof data.chunk;
        expect(retained).toEqual(data.chunk);
        expect(Buffer.from(retained.contentBase64, 'base64')).toEqual(Buffer.from(data.bytes));
    });

    it('keeps the encrypted outer format while protecting the inner version 2', async () => {
        const data = await archived();
        const backup = await buildBackupEnvelopeFromSnapshot(data.snapshot, null);
        const passphrase = 'synthetic-backup-test-passphrase';
        const encrypted = await encryptBackupEnvelopeText(JSON.stringify(backup), passphrase);
        expect(encrypted.version).toBe(1);
        const oldInner = await decryptBackupEnvelopeText(encrypted, passphrase);
        expect(await parseAndValidateLegacyBackup(oldInner, null)).toEqual({ ok: false, error: 'unsupported_version' });
        const decoded = await decryptAndValidateBackup(encrypted, passphrase, null);
        expect(decoded.ok).toBe(true);
        if (decoded.ok) expect(decoded.value.envelope.version).toBe(2);
    });

    it.each([0, 3, '2', null])('refuses an unsupported or nonnumeric version %j', async version => {
        const backup = await buildBackupEnvelopeFromSnapshot({ ...EMPTY_INVOICING_SNAPSHOT }, null);
        expect(await parseAndValidateBackup(JSON.stringify({ ...backup, version }), null)).toEqual({ ok: false, error: 'unsupported_version' });
    });
});
