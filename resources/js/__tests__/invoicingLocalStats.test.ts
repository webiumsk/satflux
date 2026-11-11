import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

vi.mock("@/evolu/client", () => ({
    allCompaniesQuery: { id: "companies" },
    allDocumentsQuery: { id: "documents" },
}));

vi.mock("@/evolu/invoicingSnapshot", () => ({ snapshotInvoicingData: vi.fn() }));

import {
    formatByteSize,
    formatRelayError,
    loadInvoicingLocalStats,
} from "@/evolu/invoicingLocalStats";
import { snapshotInvoicingData } from "@/evolu/invoicingSnapshot";
import { allCompaniesQuery, allDocumentsQuery } from "@/evolu/client";
import {
    relayUsageHttpUrl,
    relayUsagePercent,
} from "@/services/evoluRelayUsageApi";

describe("invoicingLocalStats helpers", () => {
    it("formats byte sizes", () => {
        expect(formatByteSize(500)).toBe("500 B");
        expect(formatByteSize(2048)).toBe("2.0 KB");
        expect(formatByteSize(3_457_280)).toBe("3.30 MB");
    });

    it("formats relay errors", () => {
        expect(formatRelayError({ type: "ProtocolQuotaError" })).toBe("ProtocolQuotaError");
        expect(formatRelayError(null)).toBeNull();
    });
});

describe("evoluRelayUsageApi", () => {
    it("builds HTTPS usage URL from wss relay", () => {
        expect(relayUsageHttpUrl("wss://evolu.satflux.io", "abc123")).toBe(
            "https://evolu.satflux.io/usage/abc123",
        );
    });

    it("calculates usage percent capped at 100", () => {
        expect(relayUsagePercent(5_000_000, 50_000_000)).toBe(10);
        expect(relayUsagePercent(60_000_000, 50_000_000)).toBe(100);
    });
});

function statsFixture() {
    const documents = [
        { id: "invoice", companyId: "company", documentType: "invoice", status: "paid" },
        { id: "quote", companyId: "company", documentType: "quote", status: "issued" },
    ];
    const snapshot = {
        company: [{ id: "company", appSettingsJson: JSON.stringify({ satfluxRelayPushAt: 100 }) }],
        contact: [{ id: "contact", companyId: "company" }],
        numberSeries: [],
        document: documents,
        documentLine: [],
        documentEvent: [],
        expense: [],
        expenseAttachment: [],
        recurringProfile: [],
    };
    const loadQuery = vi.fn(async (query: unknown): Promise<Record<string, unknown>[]> => {
        if (query === allCompaniesQuery) return [{ id: "company", legalName: "Synthetic company", tradeName: null }];
        if (query === allDocumentsQuery) return documents;
        throw new Error("Unexpected query");
    });
    const evolu = {
        appOwner: Promise.resolve({ id: "synthetic-owner" }),
        loadQuery,
        exportDatabase: vi.fn(async () => new Uint8Array(123)),
        getError: () => ({ type: "ProtocolQuotaError" }),
    };
    vi.mocked(snapshotInvoicingData).mockResolvedValue(snapshot as never);
    return { evolu, loadQuery };
}

beforeEach(() => {
    vi.mocked(snapshotInvoicingData).mockReset();
});
afterEach(() => {
    vi.useRealTimers();
});

it("loads local counts without reading migration originals or exporting the database", async () => {
    const { evolu } = statsFixture();
    const result = await loadInvoicingLocalStats(evolu as never);
    expect(snapshotInvoicingData).toHaveBeenCalledWith(evolu, {
        includeMigrationOriginals: false,
        queryTimeoutMs: 30_000,
    });
    expect(evolu.exportDatabase).not.toHaveBeenCalled();
    expect(result.counts).toMatchObject({ company: 1, contact: 1, document: 2 });
    expect(result.companies).toEqual([{ id: "company", name: "Synthetic company", invoices: 1, documents: 2, contacts: 1 }]);
    expect(result.relayError).toBe("ProtocolQuotaError");
    expect(result.localDbBytes).toBeNull();
});

it("bounds an unresponsive local owner", async () => {
    vi.useFakeTimers();
    const { evolu } = statsFixture();
    evolu.appOwner = new Promise(() => undefined);
    const rejected = expect(loadInvoicingLocalStats(evolu as never, { timeoutMs: 20 }))
        .rejects.toThrow("evolu_stats_owner_timeout");
    await vi.advanceTimersByTimeAsync(20);
    await rejected;
});

it("bounds a missing local query response instead of returning zero counts", async () => {
    vi.useFakeTimers();
    const { evolu, loadQuery } = statsFixture();
    loadQuery.mockImplementation(() => new Promise(() => undefined));
    const rejected = expect(loadInvoicingLocalStats(evolu as never, { timeoutMs: 20 }))
        .rejects.toThrow("evolu_query_timeout");
    await vi.advanceTimersByTimeAsync(20);
    await rejected;
});

it("retains verified counts when an explicitly requested export times out", async () => {
    vi.useFakeTimers();
    const { evolu } = statsFixture();
    evolu.exportDatabase.mockImplementation(() => new Promise(() => undefined));
    const pending = loadInvoicingLocalStats(evolu as never, { includeDbExport: true, timeoutMs: 20 });
    await vi.advanceTimersByTimeAsync(20);
    const result = await pending;
    expect(result.counts.document).toBe(2);
    expect(result.localDbBytes).toBeNull();
    expect(evolu.exportDatabase).toHaveBeenCalledOnce();
});
