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

function createEvoluMock(counts: number[]) {
    let index = 0;
    const evolu = {
        appOwner: Promise.resolve({ mnemonic: "word ".repeat(24).trim() }),
        loadQuery: vi.fn(async () => {
            const count = counts[Math.min(index, counts.length - 1)];
            if (index < counts.length - 1) {
                index += 1;
            }
            return Array.from({ length: count }, (_, i) => ({
                id: `doc-${i}`,
                companyId: "company-1",
            }));
        }),
    } as unknown as Evolu<InvoicingLocalSchema>;

    return evolu;
}

describe("pullInvoicingFromRelay", () => {
    beforeEach(() => {
        vi.mocked(getStoredAccountMnemonic).mockReturnValue("word ".repeat(24).trim());
        vi.mocked(isTargetEvoluOwner).mockReturnValue(true);
    });

    it("returns changed when document count increases", async () => {
        const evolu = createEvoluMock([2, 2, 3, 3]);
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

    it("times out when count stays the same", async () => {
        const evolu = createEvoluMock([4]);
        const result = await pullInvoicingFromRelay(evolu, {
            companyId: "company-1",
            pollMs: 1,
            timeoutMs: 20,
        });

        expect(result.changed).toBe(false);
        expect(result.timedOut).toBe(true);
        expect(result.documentCount).toBe(4);
    });

    it("reports missing recovery phrase", async () => {
        vi.mocked(getStoredAccountMnemonic).mockReturnValue(null);
        const evolu = createEvoluMock([1]);
        const result = await pullInvoicingFromRelay(evolu);

        expect(result.ownerStatus).toBe("no_phrase");
        expect(result.changed).toBe(false);
    });
});
