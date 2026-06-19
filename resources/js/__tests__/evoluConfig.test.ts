import { afterEach, describe, expect, it, vi } from "vitest";

describe("evoluTransports", () => {
    afterEach(() => {
        vi.unstubAllEnvs();
        vi.resetModules();
    });

    it("defaults to the public relay when no relay URL is configured", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", undefined);

        const { evoluTransports } = await import("../evolu/config");

        expect(evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://free.evoluhq.com" },
        ]);
    });

    it("uses the configured relay URL when set", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", " wss://relay.example.com ");

        const { evoluTransports } = await import("../evolu/config");

        expect(evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://relay.example.com" },
        ]);
    });

    it("returns empty transports when relay sync is explicitly disabled", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "");

        const { evoluTransports } = await import("../evolu/config");

        expect(evoluTransports()).toEqual([]);
    });
});
