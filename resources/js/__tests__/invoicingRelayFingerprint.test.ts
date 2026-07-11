import { describe, expect, it } from "vitest";
import {
    filterInvoicingSnapshotByCompany,
    fingerprintInvoicingSnapshot,
} from "@/evolu/invoicingRelayFingerprintCore";
import type { InvoicingDataSnapshot } from "@/evolu/invoicingSnapshot";

const EMPTY_INVOICING_SNAPSHOT: InvoicingDataSnapshot = {
    company: [],
    contact: [],
    numberSeries: [],
    document: [],
    documentLine: [],
    documentEvent: [],
    documentSnapshot: [],
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

describe("invoicingRelayFingerprint", () => {
    it("includes contacts and expenses in the global fingerprint", () => {
        const base = fingerprintInvoicingSnapshot(EMPTY_INVOICING_SNAPSHOT);
        const withContact = fingerprintInvoicingSnapshot({
            ...EMPTY_INVOICING_SNAPSHOT,
            contact: [{ id: "c-1", companyId: "co-1", name: "Buyer" }],
        });
        const withExpense = fingerprintInvoicingSnapshot({
            ...EMPTY_INVOICING_SNAPSHOT,
            expense: [{ id: "e-1", companyId: "co-1", internalNumber: "1" }],
        });

        expect(withContact).not.toBe(base);
        expect(withExpense).not.toBe(base);
        expect(withContact).not.toBe(withExpense);
    });

    it("scopes fingerprint to one company including settings row", () => {
        const snapshot = {
            ...EMPTY_INVOICING_SNAPSHOT,
            company: [
                { id: "co-a", legalName: "A", appSettingsJson: '{"runs_eshop":true}' },
                { id: "co-b", legalName: "B", appSettingsJson: "{}" },
            ],
            contact: [
                { id: "c-a", companyId: "co-a" },
                { id: "c-b", companyId: "co-b" },
            ],
            expense: [{ id: "e-a", companyId: "co-a" }],
        };

        const scoped = filterInvoicingSnapshotByCompany(snapshot, "co-a");
        expect(scoped.company).toHaveLength(1);
        expect(scoped.contact).toHaveLength(1);
        expect(scoped.expense).toHaveLength(1);

        const fpA = fingerprintInvoicingSnapshot(scoped);
        const fpB = fingerprintInvoicingSnapshot(
            filterInvoicingSnapshotByCompany(snapshot, "co-b"),
        );
        expect(fpA).not.toBe(fpB);
    });
});
