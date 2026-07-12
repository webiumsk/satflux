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

describe("normalizeEvoluRelayBaseUrl validation (P1 phase 6)", () => {
    afterEach(() => {
        vi.restoreAllMocks();
        vi.resetModules();
    });

    it("keeps valid ws/wss/http/https origins and strips paths", async () => {
        const { normalizeEvoluRelayBaseUrl } = await import("../evolu/config");
        expect(normalizeEvoluRelayBaseUrl("wss://relay.example.com/path?x=1")).toBe(
            "wss://relay.example.com",
        );
        expect(normalizeEvoluRelayBaseUrl("https://relay.example.com:8443/")).toBe(
            "https://relay.example.com:8443",
        );
    });

    it("prefixes wss:// for scheme-less host input", async () => {
        const { normalizeEvoluRelayBaseUrl } = await import("../evolu/config");
        expect(normalizeEvoluRelayBaseUrl("relay.example.com")).toBe("wss://relay.example.com");
    });

    it("rejects unsupported protocols and garbage with a warning (sync disabled)", async () => {
        const warn = vi.spyOn(console, "warn").mockImplementation(() => {});
        const { normalizeEvoluRelayBaseUrl } = await import("../evolu/config");
        expect(normalizeEvoluRelayBaseUrl("ftp://relay.example.com")).toBe("");
        expect(normalizeEvoluRelayBaseUrl("::not a url::")).toBe("");
        expect(warn).toHaveBeenCalled();
    });
});
