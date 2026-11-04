import { afterEach, expect, it, vi } from "vitest";
import { sqliteTrue } from "@evolu/common";
import { invoicingApi } from "@/services/api";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import {
    reconcileEfakturaInboxWithLocalExpenses, stableExpenseIdFromInboxUuid, type EfakturaInboxEntry,
} from "@/evolu/efakturaInboxImport";
import { createExpenseWorkerProbe } from "./evoluExpenseWorkerTestSupport";

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

afterEach(() => { vi.restoreAllMocks(); clearRowOwnerIndex(); clearCompanyShares(); });

it("keeps a deleted stable-ID receipt pending through the real expense query while acknowledging a live control", async () => {
    const probe = createExpenseWorkerProbe();
    const deleted = entry("synthetic-deleted", "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b");
    const live = entry("synthetic-live", "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6c");
    probe.state.expense.splice(0);
    probe.state.expense.push(
        { ...probe.candidate, ownerId: probe.appOwnerId, id: stableExpenseIdFromInboxUuid(deleted.evolu_expense_id), isDeleted: sqliteTrue },
        { ...probe.candidate, ownerId: probe.appOwnerId, id: stableExpenseIdFromInboxUuid(live.evolu_expense_id), isDeleted: 0 },
    );
    const acknowledge = vi.spyOn(invoicingApi.efaktura, "inboxImported").mockResolvedValue(undefined);
    const remaining = await reconcileEfakturaInboxWithLocalExpenses(
        probe.evolu, probe.candidate.companyId, "synthetic-bridge", [deleted, live],
    );

    expect(remaining).toEqual([deleted]);
    expect(acknowledge).toHaveBeenCalledExactlyOnceWith("synthetic-bridge", live.inbox_id);
    expect(probe.financialMutations()).toHaveLength(0);
});
