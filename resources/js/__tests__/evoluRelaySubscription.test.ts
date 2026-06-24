import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "@/evolu/schema";

vi.mock("@/evolu/config", () => ({
    evoluTransports: vi.fn(() => [{ type: "WebSocket", url: "wss://relay.example.com" }]),
}));

import { evoluTransports } from "@/evolu/config";
import {
    ensureEvoluRelaySubscription,
    isEvoluRelayConfigured,
    refreshEvoluRelaySubscription,
    releaseEvoluRelaySubscription,
} from "@/evolu/evoluRelaySubscription";

describe("evoluRelaySubscription", () => {
    beforeEach(() => {
        releaseEvoluRelaySubscription();
        vi.mocked(evoluTransports).mockReturnValue([
            { type: "WebSocket", url: "wss://relay.example.com" },
        ]);
    });

    it("registers app owner once", async () => {
        const unuse = vi.fn();
        const evolu = {
            appOwner: Promise.resolve({
                id: "owner-1",
                writeKey: new Uint8Array([1]),
            }),
            useOwner: vi.fn(() => unuse),
        } as unknown as Evolu<InvoicingLocalSchema>;

        expect(isEvoluRelayConfigured()).toBe(true);
        await ensureEvoluRelaySubscription(evolu);
        await ensureEvoluRelaySubscription(evolu);

        expect(evolu.useOwner).toHaveBeenCalledTimes(1);
    });

    it("refresh drops and re-registers owner", async () => {
        const unuse = vi.fn();
        const evolu = {
            appOwner: Promise.resolve({
                id: "owner-1",
                writeKey: new Uint8Array([1]),
            }),
            useOwner: vi.fn(() => unuse),
        } as unknown as Evolu<InvoicingLocalSchema>;

        await ensureEvoluRelaySubscription(evolu);
        await refreshEvoluRelaySubscription(evolu);

        expect(unuse).toHaveBeenCalledTimes(1);
        expect(evolu.useOwner).toHaveBeenCalledTimes(2);
    });

    it("returns false when relay URL is empty", async () => {
        vi.mocked(evoluTransports).mockReturnValue([]);
        const evolu = {
            appOwner: Promise.resolve({ id: "owner-1", writeKey: new Uint8Array([1]) }),
            useOwner: vi.fn(),
        } as unknown as Evolu<InvoicingLocalSchema>;

        expect(await ensureEvoluRelaySubscription(evolu)).toBe(false);
        expect(evolu.useOwner).not.toHaveBeenCalled();
    });
});
