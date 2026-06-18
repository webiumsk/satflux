import { afterEach, describe, expect, it, vi } from "vitest";

describe("evoluTransports", () => {
    afterEach(() => {
        vi.unstubAllEnvs();
        vi.resetModules();
    });

    it("uses primary and secondary when both are set", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://relay.example.com");
        vi.stubEnv("VITE_EVOLU_RELAY_URL_SECONDARY", "wss://free.evoluhq.com");
        const { evoluTransports } = await import("../evolu/config");
        expect(evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://relay.example.com" },
            { type: "WebSocket", url: "wss://free.evoluhq.com" },
        ]);
    });

    it("defaults secondary to free.evoluhq.com when only primary is unset", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "");
        vi.stubEnv("VITE_EVOLU_RELAY_URL_SECONDARY", undefined);
        const { evoluTransports } = await import("../evolu/config");
        expect(evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://free.evoluhq.com" },
        ]);
    });

    it("dedupes identical primary and secondary URLs", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://relay.example.com");
        vi.stubEnv("VITE_EVOLU_RELAY_URL_SECONDARY", "wss://relay.example.com");
        const { evoluTransports } = await import("../evolu/config");
        expect(evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://relay.example.com" },
        ]);
    });

    it("returns empty when both relays are explicitly disabled", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "");
        vi.stubEnv("VITE_EVOLU_RELAY_URL_SECONDARY", "");
        const { evoluTransports } = await import("../evolu/config");
        expect(evoluTransports()).toEqual([]);
    });
});
