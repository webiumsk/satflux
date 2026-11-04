import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { createId, createRandomBytes } from "@evolu/common";
import { DocumentId } from "@/evolu/schema";
import { manualMatchLocalTransaction, importInboundServerTransactionsAsync } from "@/evolu/bankCrud";
import { BankReconciliationUnknownError } from "@/evolu/bankReconciliation";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { createExpenseWorkerProbe, type DataRow } from "./evoluExpenseWorkerTestSupport";

vi.mock("@/evolu/client", async () => {
    const { createQuery } = await import("@evolu/common/local-first");
    return {
        allBankImportBatchesQuery: createQuery((db) => db.selectFrom("bankImportBatch").selectAll()),
        allBankTransactionsQuery: createQuery((db) => db.selectFrom("bankTransaction").selectAll()),
        allBankTransactionMatchesQuery: createQuery((db) => db.selectFrom("bankTransactionMatch").selectAll()),
        allDocumentsQuery: createQuery((db) => db.selectFrom("document").selectAll()),
        allExpensesQuery: createQuery((db) => db.selectFrom("expense").selectAll()),
        bankReconciliationBatchesQuery: createQuery((db) => db.selectFrom("bankImportBatch").selectAll().where("id", "is not", null)),
        bankReconciliationTransactionsQuery: createQuery((db) => db.selectFrom("bankTransaction").selectAll().where("id", "is not", null)),
        bankReconciliationMatchesQuery: createQuery((db) => db.selectFrom("bankTransactionMatch").selectAll().where("id", "is not", null)),
        bankReconciliationDocumentsQuery: createQuery((db) => db.selectFrom("document").selectAll().where("id", "is not", null)),
        bankReconciliationExpensesQuery: createQuery((db) => db.selectFrom("expense").selectAll().where("internalNumber", "is not", null).where("id", "is not", null)),
    };
});
vi.mock("@/evolu/documentCrud", () => ({
    markLocalDocumentPaidWithAmount: vi.fn((evolu, id, _documents, amount: number) =>
        evolu.update("document", { id, status: "paid", amountPaid: amount.toFixed(2), paidAt: "2026-11-04" })),
    unmarkLocalDocumentPaid: vi.fn(),
}));

function fixture(shared = false) {
    const probe = createExpenseWorkerProbe(shared);
    const documentId = DocumentId.orThrow(createId({ randomBytes: createRandomBytes() }));
    const document = { id: documentId, companyId: probe.transaction.companyId, title: "Synthetic invoice", documentType: "invoice", status: "issued",
        total: "19.00", currency: "EUR", variableSymbol: "00019", amountPaid: null, paidAt: null };
    probe.state.document.push({ ...document, ownerId: shared ? probe.ownerId : probe.appOwnerId });
    probe.ctx.documents.push(document as never);
    Object.assign(probe.state.bankTransaction[0], { direction: "credit", recordedDirectionEvidence: "credit" });
    Object.assign(probe.transaction, { direction: "credit", recordedDirectionEvidence: "credit" });
    let rejectInsert = true; let throwInsert = false;
    const evolu = new Proxy(probe.evolu, {
        get(target, key) {
            if (key !== "insert") return Reflect.get(target, key);
            return (table: string, props: DataRow, options?: Parameters<typeof target.insert>[2]) => {
                if (table === "bankTransactionMatch" && throwInsert) throw new Error("synthetic_transport_failure");
                // Delegate malformed input to the real Evolu validator. Its null
                // sentinel aborts the microtask batch; this is not a fake Result.
                return target.insert(table as never, (table === "bankTransactionMatch" && rejectInsert
                    ? { ...props, matchedAmount: "x".repeat(33) } : props) as never, options);
            };
        },
    });
    return { ...probe, evolu, documentId, allowInsert: () => { rejectInsert = false; }, throwInsert: () => { throwInsert = true; } };
}

beforeEach(() => { installBankLocks(); });
afterEach(() => { clearRowOwnerIndex(); clearCompanyShares(); vi.unstubAllGlobals(); });

it.each([false, true])("rejects a real manual insert validation failure without financial writes, then releases the gate (shared=%s)", async (shared) => {
    const probe = fixture(shared); const before = structuredClone(probe.ctx);
    await expect(manualMatchLocalTransaction(probe.evolu, probe.transaction.id as never, probe.documentId, probe.ctx as never))
        .rejects.toThrow("expense_mutation_batch_rejected");
    expect(probe.ctx).toEqual(before);
    expect(probe.financialMutations()).toHaveLength(0);
    expect(probe.state.bankTransaction[0].matchStatus).toBe("unmatched");
    expect(probe.state.document[0].status).toBe("issued");
    expect(probe.state.bankTransactionMatch).toHaveLength(0);
    probe.allowInsert();
    await manualMatchLocalTransaction(probe.evolu, probe.transaction.id as never, probe.documentId, probe.ctx as never);
    expect(probe.state.bankTransactionMatch).toHaveLength(1);
    expect(probe.state.bankTransaction[0].matchStatus).toBe("matched");
    expect(probe.state.document[0].status).toBe("paid");
    expect(probe.ctx.matches).toHaveLength(1);
});

it.each([false, true])("does not count a rejected automatic insert as matched (shared=%s)", async (shared) => {
    const probe = fixture(shared);
    const result = await importInboundServerTransactionsAsync(probe.evolu, probe.transaction.companyId, [{
        booked_at: "2026-11-04", amount: "19.00", currency: "EUR", direction: "credit", recorded_direction: "credit",
        variable_symbol: "00019", dedupe_hash: "synthetic-auto-credit",
    }]);
    expect(result).toEqual({ imported: 1, auto_matched: 0 });
    expect(probe.state.bankImportBatch[0]).toMatchObject({ importedCount: "1", autoMatchedCount: "0" });
    expect(probe.state.bankTransactionMatch).toHaveLength(0);
    expect(probe.state.bankTransaction.every((row) => row.matchStatus === "unmatched")).toBe(true);
    expect(probe.state.document[0].status).toBe("issued");
});

it.each([false, true])("matches a newly imported credit with a worker-fresh parent owner (shared=%s)", async (shared) => {
    const probe = fixture(shared); probe.allowInsert();
    expect(await importInboundServerTransactionsAsync(probe.evolu, probe.transaction.companyId, [{
        booked_at: "2026-11-04", amount: "19.00", currency: "EUR", direction: "credit", recorded_direction: "credit",
        variable_symbol: "00019", dedupe_hash: "synthetic-successful-credit",
    }])).toEqual({ imported: 1, auto_matched: 1 });
    const transaction = probe.state.bankTransaction.find((row) => row.dedupeHash === "synthetic-successful-credit")!;
    expect(transaction.matchStatus).toBe("matched");
    expect(probe.state.bankTransactionMatch).toMatchObject([{ bankTransactionId: transaction.id, businessDocumentId: probe.documentId }]);
    expect(probe.state.bankTransactionMatch[0].ownerId).toBe(shared ? probe.ownerId : probe.appOwnerId);
    expect(probe.state.document[0].status).toBe("paid");
    expect(probe.state.bankImportBatch[0].autoMatchedCount).toBe("1");
});

it("keeps an actual thrown mutation on the Unknown/quarantined path", async () => {
    const locks = installBankLocks(); const probe = fixture(); probe.throwInsert();
    const before = structuredClone(probe.ctx);
    await expect(manualMatchLocalTransaction(probe.evolu, probe.transaction.id as never, probe.documentId, probe.ctx as never))
        .rejects.toBeInstanceOf(BankReconciliationUnknownError);
    expect(probe.ctx).toEqual(before);
    expect(probe.financialMutations()).toHaveLength(0);
    expect(locks.held).toBe(1);
    await expect(manualMatchLocalTransaction(probe.evolu, probe.transaction.id as never, probe.documentId, probe.ctx as never))
        .rejects.toBeInstanceOf(BankReconciliationUnknownError);
});
