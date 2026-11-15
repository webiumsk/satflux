import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { createHash, webcrypto } from "node:crypto";
import { sqliteTrue } from "@evolu/common";
import { deserializeQuery } from "@evolu/common/local-first";
import { invoicingApi } from "@/services/api";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import {
    importEfakturaInboxEntry, reconcileEfakturaInboxWithLocalExpenses, stableExpenseIdFromInboxUuid, stableAttachmentIdFromInboxUuid, utf8ToBase64, type EfakturaInboxEntry,
} from "@/evolu/efakturaInboxImport";
import { createExpenseWorkerProbe } from "./evoluExpenseWorkerTestSupport";
import { installBankLocks } from './bankReconciliationTestSupport';
import { parseExpenseOriginalReference } from '@/evolu/expenseOriginalReference';
import { readVerifiedExpenseAttachment } from '@/evolu/expenseOriginalArchive';

// Import the real client/query module while keeping its unused browser singleton
// inert. The separate probe below executes that production query through Evolu.
vi.mock("@/evolu/reloadGuard", () => ({
    createEvoluWebDepsWithReloadGuard: () => ({
        console: { ...console, log: vi.fn() },
        randomBytes: { create: (size: number) => crypto.getRandomValues(new Uint8Array(size)) },
        time: { now: () => Date.now(), nowIso: () => new Date().toISOString() },
        createDbWorker: () => ({ postMessage: vi.fn(), onMessage: vi.fn() }),
        reloadApp: vi.fn(),
    }),
}));

const custodyToken = btoa("synthetic custody completion token");

/** Build a distinct parked receipt whose stable expense identity can be seeded independently. */
function entry(inboxId: string, uuid: string): EfakturaInboxEntry {
    return {
        inbox_id: inboxId, evolu_expense_id: uuid, external_document_id: inboxId,
        custody_protocol: "post_ack_proof_v1", inbox_status: "pending", created_at: "2026-11-04T10:00:00Z", draft: null,
        summary: { supplier_name: "Synthetic supplier", external_number: inboxId, total: "19.00", currency: "EUR" },
    };
}

beforeEach(() => { installBankLocks(); vi.stubGlobal('crypto', webcrypto); vi.spyOn(invoicingApi.efaktura, "inboxPrepareImported").mockResolvedValue(custodyToken); });
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); clearRowOwnerIndex(); clearCompanyShares(); });

it("keeps a deleted stable-ID receipt pending through the real expense query while acknowledging a live control", async () => {
    const probe = createExpenseWorkerProbe();
    const deleted = entry("synthetic-deleted", "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b");
    const live = entry("synthetic-live", "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6c");
    const xml = '<Invoice><Name>Synthetic supplier</Name></Invoice>';
    const digest = createHash('sha256').update(xml).digest('hex');
    live.original_sha256 = digest;
    probe.state.expense.splice(0);
    probe.state.expense.push(
        { ...probe.candidate, ownerId: probe.appOwnerId, id: stableExpenseIdFromInboxUuid(deleted.evolu_expense_id), isDeleted: sqliteTrue },
        { ...probe.candidate, ownerId: probe.appOwnerId, id: stableExpenseIdFromInboxUuid(live.evolu_expense_id), isDeleted: 0 },
    );
    probe.state.expenseAttachment = [{ id: stableAttachmentIdFromInboxUuid(live.evolu_expense_id),
        expenseId: stableExpenseIdFromInboxUuid(live.evolu_expense_id), ownerId: probe.appOwnerId, contentBase64: utf8ToBase64(xml) }];
    const acknowledge = vi.spyOn(invoicingApi.efaktura, "inboxImported").mockResolvedValue(undefined);
    const remaining = await reconcileEfakturaInboxWithLocalExpenses(
        probe.evolu, probe.candidate.companyId, "synthetic-bridge", [deleted, live],
    );

    expect(remaining).toEqual([deleted]);
    expect(acknowledge).toHaveBeenCalledExactlyOnceWith("synthetic-bridge", live.inbox_id, digest, custodyToken);
    expect(probe.financialMutations()).toHaveLength(0);
});

it.each([false, true])('persists a complete credit-note import through real Evolu validation/worker callbacks, shared owner %s', async (shared) => {
    const probe = createExpenseWorkerProbe(shared);
    const received = entry('synthetic-credit', '0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6d');
    const xml = '\uFEFF<CreditNote><Name>Dodávateľ ľščťž</Name></CreditNote>';
    const digest = createHash('sha256').update(xml).digest('hex');
    probe.state.expense.splice(0);
    vi.spyOn(invoicingApi.efaktura, 'inboxDetail').mockResolvedValue({ ...received, ubl: xml, original_sha256: digest,
        draft: { document_type: 'credit_note', title: 'Synthetic credit note', issue_date: '2026-11-10', total: '-19.00', currency: 'EUR' } });
    const acknowledge = vi.spyOn(invoicingApi.efaktura, 'inboxImported').mockImplementation(async (_bridge, _receipt, hash) => {
        expect(hash).toBe(digest);
        expect(probe.state.expense).toHaveLength(1);
        expect(probe.state.expense[0]).toMatchObject({ companyId: probe.candidate.companyId, total: '-19.00',
            ownerId: shared ? probe.ownerId : probe.appOwnerId });
        expect(probe.state.expenseAttachment).toHaveLength(1);
        expect(probe.state.expenseAttachment[0]).toMatchObject({ contentBase64: utf8ToBase64(xml),
            expenseId: stableExpenseIdFromInboxUuid(received.evolu_expense_id), ownerId: shared ? probe.ownerId : probe.appOwnerId });
    });
    const imported = await importEfakturaInboxEntry(probe.evolu, probe.candidate.companyId, 'synthetic-bridge', received);
    expect(imported).toMatchObject({ ok: true, attachmentSkipped: false });
    expect(acknowledge).toHaveBeenCalledExactlyOnceWith('synthetic-bridge', received.inbox_id, digest, custodyToken);
});

it.each([false, true])('imports and reconciles exact large originals through production queries and mutation limits, shared owner %s', async shared => {
    const probe = createExpenseWorkerProbe(shared);
    const received = entry('synthetic-large-original', '0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6e');
    const xml = '\uFEFF<Invoice><Name>ľščťž</Name><EmbeddedDocumentBinaryObject>' + 'A'.repeat(400_000) + '</EmbeddedDocumentBinaryObject></Invoice>';
    const digest = createHash('sha256').update(xml, 'utf8').digest('hex');
    received.original_sha256 = digest;
    probe.state.expense.splice(0);
    vi.spyOn(invoicingApi.efaktura, 'inboxDetail').mockResolvedValue({ ...received, ubl: xml, original_sha256: digest });
    const acknowledge = vi.spyOn(invoicingApi.efaktura, 'inboxImported').mockResolvedValue(undefined);
    expect(await importEfakturaInboxEntry(probe.evolu, probe.candidate.companyId, 'synthetic-bridge', received)).toMatchObject({ ok: true });
    const attachment = probe.state.expenseAttachment[0];
    const reference = parseExpenseOriginalReference(attachment.contentBase64)!;
    expect(reference).toMatchObject({ companyId: probe.candidate.companyId, receiptId: received.inbox_id, sha256: digest, chunkCount: 2 });
    expect(probe.state.migrationSource[0].savedAt).toEqual(expect.any(String));
    expect(Buffer.from((await readVerifiedExpenseAttachment(probe.evolu, probe.candidate.companyId, attachment as never)).bytes))
        .toEqual(Buffer.from(xml, 'utf8'));
    expect(await reconcileEfakturaInboxWithLocalExpenses(probe.evolu, probe.candidate.companyId, 'synthetic-bridge', [received])).toEqual([]);
    expect(acknowledge).toHaveBeenCalledTimes(2);
    expect(probe.state.expense).toHaveLength(1);
    expect(probe.state.expenseAttachment).toHaveLength(1);
    expect(probe.state.migrationSource).toHaveLength(1);
    expect(probe.state.migrationChunk).toHaveLength(2);
});

it.each(['tax', 'receiving'])('keeps reconciliation pending when same-owner %s changes during post-prepare chunk reconstruction', async change => {
    const probe = createExpenseWorkerProbe();
    const received = entry('synthetic-reconcile-context', '0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6f');
    const xml = '\uFEFF<Invoice>ľščťž' + 'x'.repeat(400_000) + '</Invoice>';
    received.original_sha256 = createHash('sha256').update(xml, 'utf8').digest('hex');
    const serverCopy = { original: xml as string | null };
    probe.state.expense.splice(0);
    vi.spyOn(invoicingApi.efaktura, 'inboxDetail').mockResolvedValue({ ...received, ubl: xml, original_sha256: received.original_sha256 });
    const acknowledge = vi.spyOn(invoicingApi.efaktura, 'inboxImported').mockImplementation(async () => { serverCopy.original = null; });
    expect(await importEfakturaInboxEntry(probe.evolu, probe.candidate.companyId, 'synthetic-bridge', received)).toMatchObject({ ok: true });
    // Simulate a parked receipt retry after the local original has already committed.
    serverCopy.original = xml;
    acknowledge.mockClear();
    let preparing = false;
    let changed = false;
    vi.mocked(invoicingApi.efaktura.inboxPrepareImported).mockImplementation(async () => { preparing = true; return custodyToken; });
    const load = probe.evolu.loadQuery.bind(probe.evolu);
    vi.spyOn(probe.evolu, 'loadQuery').mockImplementation((async (query: Parameters<typeof load>[0]) => {
        const rows = await load(query);
        if (preparing && !changed && deserializeQuery(query).sql.includes('from "migrationChunk"')) {
            changed = true;
            if (change === 'tax') probe.state.company[0].taxId = 'synthetic-changed-tax';
            else probe.state.company[0].appSettingsJson = JSON.stringify({ efaktura_inbound_use_shared_connection: false,
                efaktura_inbound_sapi_client_id: 'synthetic-changed-receiver' });
        }
        return rows;
    }) as typeof probe.evolu.loadQuery);
    expect(await reconcileEfakturaInboxWithLocalExpenses(probe.evolu, probe.candidate.companyId, 'synthetic-bridge', [received])).toEqual([received]);
    expect(changed).toBe(true);
    expect(acknowledge).not.toHaveBeenCalled();
    expect(serverCopy.original).toBe(xml);
    expect(probe.state.expense).toHaveLength(1);
    expect(probe.state.expenseAttachment).toHaveLength(1);
    expect(await reconcileEfakturaInboxWithLocalExpenses(probe.evolu, probe.candidate.companyId, 'synthetic-bridge', [received])).toEqual([]);
    expect(acknowledge).toHaveBeenCalledOnce();
});
