import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { createHash, webcrypto } from "node:crypto";
import { sqliteTrue } from "@evolu/common";
import { invoicingApi } from "@/services/api";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import {
    importEfakturaInboxEntry, reconcileEfakturaInboxWithLocalExpenses, stableExpenseIdFromInboxUuid, stableAttachmentIdFromInboxUuid, utf8ToBase64, type EfakturaInboxEntry,
} from "@/evolu/efakturaInboxImport";
import { createExpenseWorkerProbe } from "./evoluExpenseWorkerTestSupport";
import { installBankLocks } from './bankReconciliationTestSupport';

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

/** Build a distinct parked receipt whose stable expense identity can be seeded independently. */
function entry(inboxId: string, uuid: string): EfakturaInboxEntry {
    return {
        inbox_id: inboxId, evolu_expense_id: uuid, external_document_id: inboxId,
        inbox_status: "pending", created_at: "2026-11-04T10:00:00Z", draft: null,
        summary: { supplier_name: "Synthetic supplier", external_number: inboxId, total: "19.00", currency: "EUR" },
    };
}

beforeEach(() => { installBankLocks(); vi.stubGlobal('crypto', webcrypto); });
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
    expect(acknowledge).toHaveBeenCalledExactlyOnceWith("synthetic-bridge", live.inbox_id, digest);
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
    expect(acknowledge).toHaveBeenCalledExactlyOnceWith('synthetic-bridge', received.inbox_id, digest);
});
