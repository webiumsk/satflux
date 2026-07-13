import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
    clearRelayOverride,
    readRelayOverride,
    writeRelayOverrideDisabled,
    writeRelayOverrideUrl,
} from "@/evolu/relayOverrideStorage";

beforeEach(() => {
    localStorage.clear();
});

afterEach(() => {
    vi.unstubAllEnvs();
    vi.resetModules();
});

describe("relayOverrideStorage", () => {
    it("round-trips a cached relay URL and clears via empty write", () => {
        expect(readRelayOverride()).toEqual({ kind: "none" });
        writeRelayOverrideUrl("wss://relay.example.com");
        expect(readRelayOverride()).toEqual({ kind: "url", url: "wss://relay.example.com" });
        writeRelayOverrideUrl("");
        expect(readRelayOverride()).toEqual({ kind: "none" });
    });

    it("stores and clears the device-level sync-off sentinel", () => {
        writeRelayOverrideDisabled();
        expect(readRelayOverride()).toEqual({ kind: "disabled" });
        clearRelayOverride();
        expect(readRelayOverride()).toEqual({ kind: "none" });
    });
});

describe("evoluTransports runtime ladder (P2 phase 1)", () => {
    it("prefers the cached override URL over the build env", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://build.example.com");
        writeRelayOverrideUrl("wss://profile.example.com");

        const { evoluTransports } = await import("../evolu/config");
        expect(evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://profile.example.com" },
        ]);
    });

    it("returns empty transports when sync is disabled on this device", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://build.example.com");
        writeRelayOverrideDisabled();

        const { evoluTransports } = await import("../evolu/config");
        expect(evoluTransports()).toEqual([]);
    });

    it("falls back to the build env without an override, and ignores an invalid cached URL", async () => {
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "wss://build.example.com");
        const warn = vi.spyOn(console, "warn").mockImplementation(() => {});

        const clean = await import("../evolu/config");
        expect(clean.evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://build.example.com" },
        ]);

        vi.resetModules();
        writeRelayOverrideUrl("::garbage::");
        const withGarbage = await import("../evolu/config");
        expect(withGarbage.evoluTransports()).toEqual([
            { type: "WebSocket", url: "wss://build.example.com" },
        ]);
        warn.mockRestore();
    });
});
