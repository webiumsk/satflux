import { beforeEach, describe, expect, it, vi } from "vitest";
import api from "@/services/api";
import {
    clearServerMigrationCompleted,
    importServerInvoicingToEvolu,
    isServerMigrationCompleted,
    markServerMigrationCompleted,
    ServerMigrationError,
} from "@/evolu/serverMigration";
import { restoreInvoicingSnapshotDetailedAsync } from "@/evolu/invoicingSnapshot";
import { sanitizeLocalStoreReferences } from "@/evolu/sanitizeStoreReferences";

vi.mock("@/services/api", () => ({
    default: {
        get: vi.fn(),
    },
}));

vi.mock("@/services/accountSeed", () => ({
    getStoredAccountMnemonic: vi.fn(() => "seed words"),
    initEvoluFromAccountSeedIfNeeded: vi.fn(async () => "existing"),
}));

vi.mock("@/evolu/client", () => ({
    allCompaniesQuery: { table: "company" },
}));

vi.mock("@/evolu/invoicingSnapshot", async () => {
    const actual = await vi.importActual<typeof import("@/evolu/invoicingSnapshot")>(
        "@/evolu/invoicingSnapshot",
    );

    return {
        ...actual,
        restoreInvoicingSnapshotDetailedAsync: vi.fn(),
    };
});

vi.mock("@/evolu/sanitizeStoreReferences", () => ({
    sanitizeLocalStoreReferences: vi.fn(async () => ({
        clearedCompanyLinks: 0,
        clearedDocumentStores: 0,
        clearedRecurringStores: 0,
    })),
}));

vi.mock("@/evolu/serverSnapshotPrepare", () => ({
    prepareServerSnapshotForEvolu: vi.fn((snapshot) => snapshot),
}));

vi.mock("@/evolu/serverSnapshotSanitize", () => ({
    dropRowsWithoutId: vi.fn((snapshot) => snapshot),
    sanitizeSnapshotRowsForUpsert: vi.fn((snapshot) => snapshot),
}));

const snapshotWithCompany = {
    company: [{ id: "company-1", legalName: "Example LLC" }],
    contact: [],
    numberSeries: [],
    document: [],
    documentLine: [],
    documentEvent: [],
    expense: [],
    expenseAttachment: [],
    recurringProfile: [],
    recurringProfileLine: [],
    companyWarehouse: [],
    companyStockItem: [],
    companyStockBalance: [],
    companyStockMovement: [],
    bankImportBatch: [],
    bankTransaction: [],
    bankTransactionMatch: [],
};

describe("server migration import", () => {
    beforeEach(() => {
        vi.clearAllMocks();
        clearServerMigrationCompleted();
    });

    it("does not mark migration complete when any snapshot rows fail to upsert", async () => {
        markServerMigrationCompleted();
        (api.get as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
            data: {
                data: snapshotWithCompany,
                meta: { warnings: [], counts: { company: 1 } },
            },
        });
        (restoreInvoicingSnapshotDetailedAsync as ReturnType<typeof vi.fn>).mockResolvedValueOnce({
            upserted: 1,
            failed: [{ table: "documentLine", id: "line-1", error: new Error("invalid") }],
        });

        await expect(
            importServerInvoicingToEvolu({} as never, new Set()),
        ).rejects.toBeInstanceOf(ServerMigrationError);

        expect(isServerMigrationCompleted()).toBe(false);
        expect(sanitizeLocalStoreReferences).not.toHaveBeenCalled();
    });
});
