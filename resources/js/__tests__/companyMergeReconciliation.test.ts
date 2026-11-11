import { afterEach, beforeEach, expect, it, vi } from "vitest";
import { mergeDuplicateCompaniesLocal } from "@/evolu/companyMergeLocal";
import { createExpenseWorkerProbe } from "./evoluExpenseWorkerTestSupport";
import { installBankLocks } from "./bankReconciliationTestSupport";
import { clearCompanyShares } from "@/evolu/companyShareRegistry";
import { clearRowOwnerIndex } from "@/evolu/ownerScope";

vi.mock("@/evolu/client", async () => {
    const { createQuery } = await import("@evolu/common/local-first");
    const entries = [
        ["allCompaniesQuery", "company"], ["allCompaniesDetailQuery", "company"], ["allCompanySharesQuery", "companyShare"],
        ["allContactsQuery", "contact"], ["allNumberSeriesQuery", "numberSeries"], ["allInvoiceTemplatesQuery", "invoiceTemplate"],
        ["allDocumentsQuery", "document"], ["allDocumentLinesQuery", "documentLine"], ["allDocumentEventsQuery", "documentEvent"],
        ["allDocumentSnapshotsQuery", "documentSnapshot"], ["allExpensesQuery", "expense"], ["allExpenseAttachmentsQuery", "expenseAttachment"],
        ["allMigrationSourcesBackupQuery", "migrationSource"], ["allMigrationChunksQuery", "migrationChunk"],
        ["allRecurringProfilesQuery", "recurringProfile"], ["allRecurringProfileLinesQuery", "recurringProfileLine"],
        ["allCompanyWarehousesQuery", "companyWarehouse"], ["allCompanyStockItemsQuery", "companyStockItem"],
        ["allCompanyStockBalancesQuery", "companyStockBalance"], ["allCompanyStockMovementsQuery", "companyStockMovement"],
        ["allBankImportBatchesQuery", "bankImportBatch"], ["allBankTransactionsQuery", "bankTransaction"], ["allBankTransactionMatchesQuery", "bankTransactionMatch"],
    ] as const;
    const result = Object.fromEntries(entries.map(([name, table]) => [name, createQuery((db) => db.selectFrom(table).selectAll())]));
    for (const [name, table] of [["Batches", "bankImportBatch"], ["Transactions", "bankTransaction"], ["Matches", "bankTransactionMatch"], ["Documents", "document"]] as const) {
        result[`bankReconciliation${name}Query`] = createQuery((db) => db.selectFrom(table).selectAll().where("id", "is not", null));
    }
    result.bankReconciliationExpensesQuery = createQuery((db) => db.selectFrom("expense").selectAll().where("internalNumber", "is not", null).where("id", "is not", null));
    return result;
});

beforeEach(() => { installBankLocks(); });
afterEach(() => { clearCompanyShares(); clearRowOwnerIndex(); vi.unstubAllGlobals(); });

it.each([false, true])("preflights all merge owners before any prefix mutation (cross-owner=%s)", async (crossOwner) => {
    const probe = createExpenseWorkerProbe(crossOwner);
    probe.state.expense = []; probe.state.bankTransaction = [];
    Object.assign(probe.state.company[0], { legalName: "Same company", registrationNumber: "123" });
    const otherId = crossOwner ? probe.appOwnerId : probe.ownerId;
    probe.state.company.push({ id: otherId, ownerId: probe.appOwnerId, legalName: "Same company", registrationNumber: "123" });
    probe.state.document.push({ id: probe.expense.id, companyId: probe.transaction.companyId, ownerId: crossOwner ? probe.ownerId : probe.appOwnerId,
        title: "Synthetic invoice", documentType: "invoice", status: "issued" });
    probe.state.contact = [{ id: probe.transaction.id, ownerId: probe.appOwnerId, companyId: otherId, name: "Synthetic contact" }];
    const companies = probe.state.company.map((row) => ({ id: String(row.id), legal_name: "Same company", registration_number: "123" }));
    if (crossOwner) {
        const before = structuredClone(probe.state.contact);
        await expect(mergeDuplicateCompaniesLocal(probe.evolu, companies)).rejects.toThrow("expense_reconciliation_owner_unavailable");
        expect(probe.financialMutations()).toHaveLength(0);
        expect(probe.state.contact).toEqual(before);
        expect(probe.state.company.every((row) => row.isDeleted !== 1)).toBe(true);
    } else {
        expect(await mergeDuplicateCompaniesLocal(probe.evolu, companies)).toEqual({ mergedGroups: 1, removedCompanies: 1, failedRemovals: 0 });
        expect(probe.state.contact[0].companyId).toBe(probe.transaction.companyId);
        expect(probe.state.company.find((row) => row.id === otherId)?.isDeleted).toBe(1);
    }
});

it("does not mutate an earlier valid group when a later no-expense group has different owners", async () => {
    const probe = createExpenseWorkerProbe(true);
    probe.state.expense = []; probe.state.bankTransaction = [];
    probe.state.company = [
        { id: probe.expense.id, ownerId: probe.appOwnerId, legalName: "Valid first", registrationNumber: "111" },
        { id: probe.transaction.id, ownerId: probe.appOwnerId, legalName: "Valid first", registrationNumber: "111" },
        { ...probe.state.company[0], legalName: "Invalid later", registrationNumber: "222" },
        { id: probe.appOwnerId, ownerId: probe.appOwnerId, legalName: "Invalid later", registrationNumber: "222" },
    ];
    const before = structuredClone(probe.state.company);
    const companies = probe.state.company.map((row) => ({ id: String(row.id), legal_name: String(row.legalName), registration_number: String(row.registrationNumber) }));
    await expect(mergeDuplicateCompaniesLocal(probe.evolu, companies)).rejects.toThrow("expense_reconciliation_owner_unavailable");
    expect(probe.financialMutations()).toHaveLength(0);
    expect(probe.state.company).toEqual(before);
});
