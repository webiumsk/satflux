import { describe, expect, it, vi } from "vitest";

vi.mock("@/evolu/client", () => ({
    allCompaniesQuery: { id: "companies" },
    allDocumentsQuery: { id: "documents" },
}));

import {
    formatByteSize,
    formatRelayError,
} from "@/evolu/invoicingLocalStats";
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
