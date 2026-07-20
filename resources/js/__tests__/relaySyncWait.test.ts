import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "@/evolu/schema";
import type { InvoicingDataSnapshot } from "@/evolu/invoicingSnapshot";

const EMPTY_INVOICING_SNAPSHOT: InvoicingDataSnapshot = {
    company: [],
    contact: [],
    numberSeries: [],
    document: [],
    documentLine: [],
    documentEvent: [],
    documentSnapshot: [],
    invoiceTemplate: [],
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

vi.mock("@/evolu/client", () => ({
    allCompaniesQuery: { id: "companies" },
    allDocumentsQuery: { id: "documents" },
    allNumberSeriesQuery: { id: "series" },
}));

vi.mock("@/services/accountSeed", () => ({
    getStoredAccountMnemonic: vi.fn(() => "word ".repeat(24).trim()),
}));

vi.mock("@/services/evoluOwner", () => ({
    isTargetEvoluOwner: vi.fn(() => true),
}));

vi.mock("@/evolu/invoicingRelayFingerprint", () => ({
    refreshAllInvoicingLocalQueries: vi.fn(async () => {}),
    loadInvoicingRelayFingerprint: vi.fn(),
}));

vi.mock("@/evolu/evoluRelaySubscription", () => ({
    isEvoluRelayConfigured: vi.fn(() => true),
    ensureEvoluRelaySubscription: vi.fn(async () => true),
    refreshEvoluRelaySubscription: vi.fn(async () => true),
}));

import { getStoredAccountMnemonic } from "@/services/accountSeed";
import { isTargetEvoluOwner } from "@/services/evoluOwner";
import {
    filterInvoicingSnapshotByCompany,
    fingerprintInvoicingSnapshot,
} from "@/evolu/invoicingRelayFingerprintCore";
import {
    loadInvoicingRelayFingerprint,
    refreshAllInvoicingLocalQueries,
} from "@/evolu/invoicingRelayFingerprint";
import { pullInvoicingFromRelay } from "@/evolu/relaySyncWait";

type DocRow = { id: string; companyId: string; documentType?: string };

function snapshotFromDocs(docs: DocRow[]) {
    return {
        ...EMPTY_INVOICING_SNAPSHOT,
        document: docs,
    };
}

function createEvoluMock(snapshots: DocRow[][]) {
    let index = 0;
    const currentDocs = () => snapshots[Math.min(index, snapshots.length - 1)] ?? [];

    vi.mocked(refreshAllInvoicingLocalQueries).mockImplementation(async () => {
        if (index < snapshots.length - 1) {
            index += 1;
        }
    });

    const evolu = {
        appOwner: Promise.resolve({ mnemonic: "word ".repeat(24).trim() }),
        loadQuery: vi.fn(async () => currentDocs()),
    } as unknown as Evolu<InvoicingLocalSchema>;

    vi.mocked(loadInvoicingRelayFingerprint).mockImplementation(async (_evolu, companyId?) => {
        const snapshot = snapshotFromDocs(currentDocs());
        const scoped = companyId
            ? filterInvoicingSnapshotByCompany(snapshot, companyId)
            : snapshot;
        return fingerprintInvoicingSnapshot(scoped);
    });

    return evolu;
}

describe("pullInvoicingFromRelay", () => {
    beforeEach(() => {
        vi.mocked(getStoredAccountMnemonic).mockReturnValue("word ".repeat(24).trim());
        vi.mocked(isTargetEvoluOwner).mockReturnValue(true);
        vi.clearAllMocks();
    });

    it("returns changed when document ids change for the company", async () => {
        const evolu = createEvoluMock([
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
            ],
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
            ],
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
                { id: "doc-3", companyId: "company-1", documentType: "invoice" },
            ],
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
                { id: "doc-3", companyId: "company-1", documentType: "invoice" },
            ],
        ]);
        const result = await pullInvoicingFromRelay(evolu, {
            companyId: "company-1",
            pollMs: 1,
            timeoutMs: 5_000,
            subscriptionWarmupMs: 0,
        });

        expect(result.ownerStatus).toBe("ok");
        expect(result.changed).toBe(true);
        expect(result.documentCount).toBe(3);
        expect(result.timedOut).toBe(false);
    });

    it("times out when invoicing data stays the same", async () => {
        const docs = [
            { id: "doc-1", companyId: "company-1", documentType: "invoice" },
            { id: "doc-2", companyId: "company-1", documentType: "invoice" },
            { id: "doc-3", companyId: "company-1", documentType: "invoice" },
            { id: "doc-4", companyId: "company-1", documentType: "invoice" },
        ];
        const evolu = createEvoluMock([docs, docs, docs]);
        const result = await pullInvoicingFromRelay(evolu, {
            companyId: "company-1",
            pollMs: 1,
            timeoutMs: 50,
            subscriptionWarmupMs: 0,
        });

        expect(result.changed).toBe(false);
        expect(result.timedOut).toBe(true);
        expect(result.documentCount).toBe(4);
    });

    it("detects proforma sync while polling from the invoice list tab", async () => {
        const evolu = createEvoluMock([
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
            ],
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
            ],
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
                { id: "doc-pf", companyId: "company-1", documentType: "proforma" },
            ],
            [
                { id: "doc-1", companyId: "company-1", documentType: "invoice" },
                { id: "doc-2", companyId: "company-1", documentType: "invoice" },
                { id: "doc-pf", companyId: "company-1", documentType: "proforma" },
            ],
        ]);
        const result = await pullInvoicingFromRelay(evolu, {
            companyId: "company-1",
            documentType: "invoice",
            pollMs: 1,
            timeoutMs: 5_000,
            subscriptionWarmupMs: 0,
        });

        expect(result.changed).toBe(true);
        expect(result.syncedOtherDocumentType).toBe(true);
        expect(result.documentCount).toBe(2);
    });

    it("detects sync under another company profile", async () => {
        const evolu = createEvoluMock([
            [{ id: "doc-1", companyId: "company-a", documentType: "invoice" }],
            [{ id: "doc-1", companyId: "company-a", documentType: "invoice" }],
            [{ id: "doc-1", companyId: "company-a", documentType: "invoice" }],
            [{ id: "doc-1", companyId: "company-a", documentType: "invoice" }],
            [
                { id: "doc-1", companyId: "company-a", documentType: "invoice" },
                { id: "doc-2", companyId: "company-b", documentType: "invoice" },
            ],
            [
                { id: "doc-1", companyId: "company-a", documentType: "invoice" },
                { id: "doc-2", companyId: "company-b", documentType: "invoice" },
            ],
        ]);
        const result = await pullInvoicingFromRelay(evolu, {
            companyId: "company-a",
            pollMs: 1,
            timeoutMs: 50,
            subscriptionWarmupMs: 0,
        });

        expect(result.changed).toBe(true);
        expect(result.syncedElsewhere).toBe(true);
    });

    it("reports missing recovery phrase", async () => {
        vi.mocked(getStoredAccountMnemonic).mockReturnValue(null);
        const evolu = createEvoluMock([
            [{ id: "doc-1", companyId: "company-1", documentType: "invoice" }],
        ]);
        const result = await pullInvoicingFromRelay(evolu);

        expect(result.ownerStatus).toBe("no_phrase");
        expect(result.changed).toBe(false);
    });
});
