import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "@/evolu/schema";

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

import { getStoredAccountMnemonic } from "@/services/accountSeed";
import { isTargetEvoluOwner } from "@/services/evoluOwner";
import { pullInvoicingFromRelay } from "@/evolu/relaySyncWait";

type DocRow = { id: string; companyId: string; documentType?: string };

function createEvoluMock(snapshots: DocRow[][]) {
    let index = 0;
    const evolu = {
        appOwner: Promise.resolve({ mnemonic: "word ".repeat(24).trim() }),
        loadQuery: vi.fn(async () => {
            const docs = snapshots[Math.min(index, snapshots.length - 1)] ?? [];
            if (index < snapshots.length - 1) {
                index += 1;
            }
            return docs;
        }),
    } as unknown as Evolu<InvoicingLocalSchema>;

    return evolu;
}

describe("pullInvoicingFromRelay", () => {
    beforeEach(() => {
        vi.mocked(getStoredAccountMnemonic).mockReturnValue("word ".repeat(24).trim());
        vi.mocked(isTargetEvoluOwner).mockReturnValue(true);
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
            timeoutMs: 500,
        });

        expect(result.ownerStatus).toBe("ok");
        expect(result.changed).toBe(true);
        expect(result.documentCount).toBe(3);
        expect(result.timedOut).toBe(false);
    });

    it("times out when document ids stay the same", async () => {
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
            timeoutMs: 20,
        });

        expect(result.changed).toBe(false);
        expect(result.timedOut).toBe(true);
        expect(result.documentCount).toBe(4);
    });

    it("detects sync under another company profile", async () => {
        const evolu = createEvoluMock([
            [{ id: "doc-1", companyId: "company-a", documentType: "invoice" }],
            [{ id: "doc-1", companyId: "company-a", documentType: "invoice" }],
            [
                { id: "doc-1", companyId: "company-a", documentType: "invoice" },
                { id: "doc-2", companyId: "company-b", documentType: "invoice" },
            ],
        ]);
        const result = await pullInvoicingFromRelay(evolu, {
            companyId: "company-a",
            pollMs: 1,
            timeoutMs: 20,
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
