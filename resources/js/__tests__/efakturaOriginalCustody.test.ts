import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { createHash, webcrypto } from "node:crypto";
import { invoicingApi } from "@/services/api";
import {
    importEfakturaInboxEntry, reconcileEfakturaInboxWithLocalExpenses, stableExpenseIdFromInboxUuid,
    stableAttachmentIdFromInboxUuid, utf8ToBase64, type EfakturaInboxEntry,
} from "@/evolu/efakturaInboxImport";
import { createExpenseWriterTestAdapter } from "./expenseReconciliationTestSupport";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { withExpenseReconciliation } from "@/evolu/expenseReconciliation";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";

vi.mock("@/evolu/client", async () => (await import("./expenseReconciliationTestSupport")).expenseTestQueryExports());

const xml = "\uFEFF<Invoice><Name>Dodávateľ ľščťž</Name></Invoice>";
const digest = (body: string) => createHash("sha256").update(body, "utf8").digest("hex");
const entry: EfakturaInboxEntry = {
    inbox_id: "synthetic-receipt", evolu_expense_id: "0192a3b4-c5d6-7e8f-9a0b-1c2d3e4f5a6b", external_document_id: "synthetic-document",
    custody_protocol: "post_ack_proof_v1", inbox_status: "pending", created_at: "2026-11-10T10:00:00Z", original_sha256: digest(xml),
    draft: { title: "Synthetic supplier", issue_date: "2026-11-10", total: "19.00", currency: "EUR" },
    summary: { supplier_name: "Synthetic supplier", external_number: "INV-19", total: "19.00", currency: "EUR" },
};
const custodyToken = btoa("synthetic custody completion token");
const expenseId = stableExpenseIdFromInboxUuid(entry.evolu_expense_id)!;
const attachmentId = stableAttachmentIdFromInboxUuid(entry.evolu_expense_id)!;

function setup(existing = false, body = xml) {
    const adapter = createExpenseWriterTestAdapter(existing
        ? [{ id: expenseId, companyId: "company-a", total: "19.00", status: "paid", internalNumber: "EXP-1" }] : []);
    const serverCopy = { original: body as string | null };
    const detail = vi.spyOn(invoicingApi.efaktura, "inboxDetail").mockImplementation(async () => ({ ...entry, ubl: serverCopy.original, original_sha256: digest(body) }));
    const prepared = vi.spyOn(invoicingApi.efaktura, "inboxPrepareImported").mockResolvedValue(custodyToken);
    const imported = vi.spyOn(invoicingApi.efaktura, "inboxImported").mockImplementation(async () => { serverCopy.original = null; });
    return { ...adapter, detail, prepared, imported, serverCopy };
}

beforeEach(() => { installBankLocks(); vi.stubGlobal("crypto", webcrypto); });
afterEach(() => { vi.restoreAllMocks(); vi.unstubAllGlobals(); clearRowOwnerIndex(); clearCompanyShares(); });

describe("received original custody", () => {
    it.each([false, true])("retains the server original when local attachment removal occurs during prepare, reconcile %s", async (reconcile) => {
        const locks = installBankLocks();
        const { evolu, state, prepared, imported, serverCopy } = setup(reconcile);
        if (reconcile) state.expenseAttachment = [{ id: attachmentId, expenseId, ownerId: "app-owner", contentBase64: utf8ToBase64(xml) }];
        prepared.mockImplementation(async () => {
            expect(locks.held).toBe(0);
            expect(serverCopy.original).toBe(xml);
            state.expenseAttachment = [];
            return custodyToken;
        });
        if (reconcile) expect(await reconcileEfakturaInboxWithLocalExpenses(evolu as never, "company-a", "bridge-a", [entry])).toEqual([entry]);
        else expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).toEqual({ ok: false, error: "original_storage_failed" });
        expect(imported).not.toHaveBeenCalled();
        expect(serverCopy.original).toBe(xml);
        prepared.mockResolvedValue(custodyToken);
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).toMatchObject({ ok: true, alreadyImported: true });
        expect(state.expense).toHaveLength(1);
        expect(imported).toHaveBeenCalledExactlyOnceWith("bridge-a", entry.inbox_id, digest(xml), custodyToken);
        expect(serverCopy.original).toBeNull();
    });

    it("fails closed against an old server before making any imported request", async () => {
        const { evolu, state, detail, prepared, imported } = setup();
        detail.mockResolvedValue({ ...entry, custody_protocol: undefined, ubl: xml, original_sha256: digest(xml) });
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).toEqual({ ok: false, error: "original_unavailable" });
        expect(prepared).not.toHaveBeenCalled(); expect(imported).not.toHaveBeenCalled();
        expect(state.expense).toHaveLength(0);
    });

    it("does not fall back to legacy cleanup if a cached advertisement reaches an old server", async () => {
        const { evolu, prepared, imported, serverCopy } = setup();
        prepared.mockRejectedValue({ response: { status: 404 } });
        await expect(importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).rejects.toMatchObject({ response: { status: 404 } });
        expect(imported).not.toHaveBeenCalled(); expect(serverCopy.original).toBe(xml);
    });

    it.each([undefined, "", "malformed token", "x".repeat(8196)])("never completes custody with malformed preparation evidence: %s", async (token) => {
        const { evolu, prepared, imported, serverCopy } = setup();
        prepared.mockResolvedValue(token as never);
        await expect(importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).rejects.toThrow("efaktura_custody_protocol_unavailable");
        expect(imported).not.toHaveBeenCalled(); expect(serverCopy.original).toBe(xml);
    });

    it("retries a lost prepare response without duplicating expenses or losing the server original", async () => {
        const { evolu, state, prepared, imported, serverCopy } = setup();
        prepared.mockRejectedValueOnce(new Error("synthetic lost prepare response"));
        await expect(importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).rejects.toThrow("synthetic lost prepare response");
        expect(serverCopy.original).toBe(xml); expect(imported).not.toHaveBeenCalled();
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).toMatchObject({ ok: true, alreadyImported: true });
        expect(state.expense).toHaveLength(1); expect(state.expenseAttachment).toHaveLength(1);
    });

    it("retries a lost completion response using already persisted local bytes", async () => {
        const { evolu, state, imported, serverCopy } = setup();
        imported.mockImplementationOnce(async () => { serverCopy.original = null; throw new Error("synthetic lost completion response"); });
        await expect(importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).rejects.toThrow("synthetic lost completion response");
        expect(serverCopy.original).toBeNull();
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).toMatchObject({ ok: true, alreadyImported: true });
        expect(state.expense).toHaveLength(1); expect(state.expenseAttachment).toHaveLength(1);
    });
    it("reads back BOM and non-ASCII XML before handing off custody, preserving exact original bytes", async () => {
        const { evolu, state, imported } = setup();
        imported.mockImplementation(async (_bridge, _receipt, hash) => {
            expect(state.expense).toHaveLength(1);
            expect(state.expenseAttachment).toHaveLength(1);
            expect(state.expenseAttachment[0].contentBase64).toBe(utf8ToBase64(xml));
            expect(hash).toBe(digest(xml));
        });
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toMatchObject({ ok: true, attachmentSkipped: false, alreadyImported: false });
        expect(imported).toHaveBeenCalledExactlyOnceWith("bridge-a", entry.inbox_id, digest(xml), custodyToken);
    });

    it("repairs an existing expense without duplicating it or replacing its financial status", async () => {
        const { evolu, state, imported } = setup(true);
        const before = structuredClone(state.expense);
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toMatchObject({ ok: true, alreadyImported: true });
        expect(state.expense).toEqual(before);
        expect(state.expenseAttachment).toHaveLength(1);
        expect(imported).toHaveBeenCalledTimes(1);
    });

    it("keeps oversize originals pending and does not create a misleading partial expense", async () => {
        const { evolu, state, imported } = setup(false, "<Invoice>" + "x".repeat(384 * 1024) + "</Invoice>");
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toEqual({ ok: false, error: "original_too_large" });
        expect(state.expense).toHaveLength(0);
        expect(imported).not.toHaveBeenCalled();
    });

    it("does not hand off custody if a worker accepts an attachment but it is absent from read-back", async () => {
        const { evolu, state, imported } = setup();
        const upsert = evolu.upsert.getMockImplementation()!;
        evolu.upsert.mockImplementation((table, props, options) => table === "expenseAttachment"
            ? { ok: true, value: { id: String(props.id) } } : upsert(table, props, options));
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toEqual({ ok: false, error: "original_storage_failed" });
        expect(state.expense).toHaveLength(1);
        expect(imported).not.toHaveBeenCalled();
    });

    it("does not hand off custody after a known attachment validation failure", async () => {
        const { evolu, imported } = setup(true);
        const upsert = evolu.upsert.getMockImplementation()!;
        evolu.upsert.mockImplementation((table, props, options) => table === "expenseAttachment"
            ? { ok: false, error: "synthetic_attachment_failure" } : upsert(table, props, options));
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toEqual({ ok: false, error: "original_storage_failed" });
        expect(imported).not.toHaveBeenCalled();
    });

    it("rejects missing or mismatching integrity evidence without saving an expense", async () => {
        const { evolu, state, detail, imported } = setup();
        detail.mockResolvedValue({ ...entry, ubl: xml, original_sha256: digest("different") });
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toEqual({ ok: false, error: "original_unavailable" });
        detail.mockResolvedValue({ ...entry, ubl: xml, original_sha256: null });
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toEqual({ ok: false, error: "original_unavailable" });
        expect(state.expense).toHaveLength(0);
        expect(imported).not.toHaveBeenCalled();
    });

    it("keeps expense-only entries visible during reconciliation", async () => {
        const { evolu, imported } = setup(true);
        expect(await reconcileEfakturaInboxWithLocalExpenses(evolu as never, "company-a", "bridge-a", [entry])).toEqual([entry]);
        expect(imported).not.toHaveBeenCalled();
    });

    it.each(["wrong-body", "wrong-expense", "wrong-owner"])("keeps an invalid original attachment visible: %s", async (invalid) => {
        const { evolu, state, imported } = setup(true);
        state.expenseAttachment = [{ id: attachmentId, expenseId: invalid === "wrong-expense" ? "another-expense" : expenseId,
            ownerId: invalid === "wrong-owner" ? "foreign-owner" : "app-owner", contentBase64: utf8ToBase64(invalid === "wrong-body" ? "different" : xml) }];
        expect(await reconcileEfakturaInboxWithLocalExpenses(evolu as never, "company-a", "bridge-a", [entry])).toEqual([entry]);
        expect(imported).not.toHaveBeenCalled();
    });

    it("keeps a verified local copy visible if the server import acknowledgment fails", async () => {
        const { evolu, state, imported } = setup(true);
        state.expenseAttachment = [{ id: attachmentId, expenseId, ownerId: "app-owner", contentBase64: utf8ToBase64(xml) }];
        imported.mockRejectedValue(new Error("synthetic_offline"));
        expect(await reconcileEfakturaInboxWithLocalExpenses(evolu as never, "company-a", "bridge-a", [entry])).toEqual([entry]);
        expect(imported).toHaveBeenCalledTimes(1);
    });

    it.each(["wrong-expense", "wrong-owner"])("does not move a colliding stable attachment during repair: %s", async (invalid) => {
        const { evolu, state, imported } = setup(true);
        state.expenseAttachment = [{ id: attachmentId, expenseId: invalid === "wrong-expense" ? "another-expense" : expenseId,
            ownerId: invalid === "wrong-owner" ? "foreign-owner" : "app-owner", contentBase64: utf8ToBase64(xml) }];
        const before = structuredClone(state.expenseAttachment);
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toEqual({ ok: false, error: "original_storage_failed" });
        expect(state.expenseAttachment).toEqual(before);
        expect(imported).not.toHaveBeenCalled();
    });

    it("reconciles only a complete verified original using its digest", async () => {
        const { evolu, state, imported } = setup(true);
        state.expenseAttachment = [{ id: attachmentId, expenseId, ownerId: "app-owner", contentBase64: utf8ToBase64(xml) }];
        expect(await reconcileEfakturaInboxWithLocalExpenses(evolu as never, "company-a", "bridge-a", [entry])).toEqual([]);
        expect(imported).toHaveBeenCalledExactlyOnceWith("bridge-a", entry.inbox_id, digest(xml), custodyToken);
    });
});

describe("financial lock and bridge separation", () => {
    it("releases the lock before detail and confirmation HTTP while retaining locked local writes", async () => {
        const manager = installBankLocks();
        const { evolu, detail, imported } = setup();
        const upsert = evolu.upsert.getMockImplementation()!;
        evolu.upsert.mockImplementation((table, props, options) => {
            if (table === "expense" || table === "expenseAttachment") expect(manager.held).toBe(1);
            return upsert(table, props, options);
        });
        detail.mockImplementation(async () => {
            expect(manager.held).toBe(0);
            await withExpenseReconciliation(evolu as never, () => expect(manager.held).toBe(1));
            return { ...entry, ubl: xml, original_sha256: digest(xml) };
        });
        imported.mockImplementation(async () => {
            expect(manager.held).toBe(0);
            await withExpenseReconciliation(evolu as never, () => expect(manager.held).toBe(1));
        });
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry)).toMatchObject({ ok: true });
        expect(detail).toHaveBeenCalledOnce();
        expect(imported).toHaveBeenCalledOnce();
    });

    it("fails closed before bridge HTTP when a company-context digest cannot be produced", async () => {
        const { evolu, state, detail, imported } = setup();
        vi.spyOn(webcrypto.subtle, "digest").mockRejectedValue(new Error("synthetic digest unavailable"));
        await expect(importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .rejects.toThrow("expense_reconciliation_state_changed");
        expect(detail).not.toHaveBeenCalled();
        expect(imported).not.toHaveBeenCalled();
        expect(state.expense).toHaveLength(0);
    });

    it("rechecks company identity and connection after detail retrieval before making local changes", async () => {
        const { evolu, state, detail, imported } = setup();
        detail.mockImplementation(async () => {
            state.company[0].taxId = "0000000000";
            return { ...entry, ubl: xml, original_sha256: digest(xml) };
        });
        await expect(importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .rejects.toThrow("expense_reconciliation_state_changed");
        expect(state.expense).toHaveLength(0);
        expect(imported).not.toHaveBeenCalled();
    });

    it("does not report success if original proof disappears during confirmation HTTP", async () => {
        const manager = installBankLocks();
        const { evolu, state, imported } = setup();
        imported.mockImplementation(async () => { expect(manager.held).toBe(0); state.expenseAttachment = []; });
        expect(await importEfakturaInboxEntry(evolu as never, "company-a", "bridge-a", entry))
            .toEqual({ ok: false, error: "original_storage_failed" });
    });

    it("releases reconciliation locks for both detail and confirmation HTTP and rechecks proof afterward", async () => {
        const manager = installBankLocks();
        const { evolu, state, detail, imported } = setup(true);
        const missingDigest = { ...entry, original_sha256: null };
        state.expenseAttachment = [{ id: attachmentId, expenseId, ownerId: "app-owner", contentBase64: utf8ToBase64(xml) }];
        detail.mockImplementation(async () => {
            expect(manager.held).toBe(0);
            await withExpenseReconciliation(evolu as never, () => undefined);
            return { ...entry, ubl: xml, original_sha256: digest(xml) };
        });
        imported.mockImplementation(async () => {
            expect(manager.held).toBe(0);
            await withExpenseReconciliation(evolu as never, () => undefined);
            state.expenseAttachment = [];
        });
        expect(await reconcileEfakturaInboxWithLocalExpenses(evolu as never, "company-a", "bridge-a", [missingDigest])).toEqual([missingDigest]);
        expect(detail).toHaveBeenCalledOnce();
        expect(imported).toHaveBeenCalledOnce();
    });
});
