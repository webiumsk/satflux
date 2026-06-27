import { afterEach, describe, expect, it, vi } from "vitest";

describe("resolveEvoluRelayUrl", () => {
    afterEach(() => {
        vi.unstubAllEnvs();
        vi.resetModules();
    });

    it("prefers profile URL over build default", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://build.example.com");
        const { resolveEvoluRelayUrl } = await import("../evolu/config");

        const resolved = resolveEvoluRelayUrl("wss://profile.example.com/path");

        expect(resolved.url).toBe("wss://profile.example.com");
        expect(resolved.source).toBe("profile");
        expect(resolved.enabled).toBe(true);
    });

    it("falls back to build URL when profile is empty", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://evolu.satflux.io");
        const { resolveEvoluRelayUrl } = await import("../evolu/config");

        const resolved = resolveEvoluRelayUrl(null);

        expect(resolved.url).toBe("wss://evolu.satflux.io");
        expect(resolved.source).toBe("build");
    });
});
