import { beforeEach, describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "@/evolu/schema";

vi.mock("@/services/evoluRelayPreference", () => ({
    isEvoluRelayConfiguredAtRuntime: vi.fn(() => true),
    getResolvedEvoluRelayUrl: vi.fn(() => "wss://relay.example.com"),
}));

vi.mock("@/evolu/evoluRelayOwner", () => ({
    createSyncOwnerForRelay: vi.fn(async (evolu: Evolu<InvoicingLocalSchema>) => {
        const owner = await evolu.appOwner;
        return {
            ...owner,
            transports: [{ type: "WebSocket", url: "wss://relay.example.com" }],
        };
    }),
}));

import { isEvoluRelayConfiguredAtRuntime } from "@/services/evoluRelayPreference";
import {
    ensureEvoluRelaySubscription,
    isEvoluRelayConfigured,
    refreshEvoluRelaySubscription,
    releaseEvoluRelaySubscription,
} from "@/evolu/evoluRelaySubscription";

describe("evoluRelaySubscription", () => {
    beforeEach(() => {
        releaseEvoluRelaySubscription();
        vi.mocked(isEvoluRelayConfiguredAtRuntime).mockReturnValue(true);
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
        vi.mocked(isEvoluRelayConfiguredAtRuntime).mockReturnValue(false);
        const evolu = {
            appOwner: Promise.resolve({ id: "owner-1", writeKey: new Uint8Array([1]) }),
            useOwner: vi.fn(),
        } as unknown as Evolu<InvoicingLocalSchema>;

        expect(await ensureEvoluRelaySubscription(evolu)).toBe(false);
        expect(evolu.useOwner).not.toHaveBeenCalled();
    });
});
