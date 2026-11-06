import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import {
    evoluTransports, getDefaultEvoluRelayConfigurations, getDefaultEvoluRelayUrls,
    normalizeEvoluRelayConfigurations, resolveConfiguredEvoluRelays, resolveSelectedEvoluRelayUrls,
    type EvoluRelayPreference,
} from "@/evolu/config";
import {
    EVOLU_RELAY_OVERRIDE_STORAGE_KEY, parseRelayOverride, readRelayOverride,
    writeRelayOverrideDisabled, writeRelayOverrideRelays, writeRelayOverrideUrl,
} from "@/evolu/relayOverrideStorage";
import { getEvoluRelayRuntimeInfo, getResolvedEvoluRelayUrls } from "@/services/evoluRelayPreference";

const auth = vi.hoisted(() => ({ user: null as EvoluRelayPreference | null }));
vi.mock("@/store/auth", () => ({ useAuthStore: () => auth }));

beforeEach(() => {
    localStorage.clear();
    auth.user = null;
    vi.stubEnv("VITE_EVOLU_RELAY_URL", undefined);
});
afterEach(() => { vi.unstubAllEnvs(); localStorage.clear(); });

const defaults = ["wss://evolu.satflux.io", "wss://free.evoluhq.com"];
const rows = [
    { url: "wss://one.example.test", enabled: true },
    { url: "wss://two.example.test", enabled: false },
    { url: "wss://three.example.test", enabled: true },
];

describe("canonical Evolu relay lists", () => {
    it("materializes both requested defaults, deduplicates the public default, and honors explicit build disablement", () => {
        expect(getDefaultEvoluRelayUrls()).toEqual(defaults);
        expect(getDefaultEvoluRelayConfigurations()).toEqual(defaults.map(url => ({ url, enabled: true })));
        expect(resolveConfiguredEvoluRelays({})).toEqual(getDefaultEvoluRelayConfigurations());
        vi.stubEnv("VITE_EVOLU_RELAY_URL", defaults[1]);
        expect(getDefaultEvoluRelayUrls()).toEqual([defaults[1]]);
        vi.stubEnv("VITE_EVOLU_RELAY_URL", "");
        expect(getDefaultEvoluRelayUrls()).toEqual([]);
        expect(resolveSelectedEvoluRelayUrls({})).toEqual([]);
    });

    it("keeps legacy custom-only selection and adds both defaults only for the old opt-in", () => {
        expect(resolveSelectedEvoluRelayUrls({ evolu_relay_url: rows[0].url })).toEqual([rows[0].url]);
        expect(resolveSelectedEvoluRelayUrls({ evolu_relay_url: rows[0].url, evolu_relay_add_default: true }))
            .toEqual([rows[0].url, ...defaults]);
    });

    it("preserves full row order while selecting only enabled URLs without appending a default", () => {
        const profile = { evolu_relays: rows, evolu_relay_url: defaults[0], evolu_relay_add_default: true };
        expect(resolveConfiguredEvoluRelays(profile)).toEqual(rows);
        expect(resolveSelectedEvoluRelayUrls(profile)).toEqual([rows[0].url, rows[2].url]);
        auth.user = profile;
        expect(getResolvedEvoluRelayUrls()).toEqual([rows[0].url, rows[2].url]);
        expect(getEvoluRelayRuntimeInfo()).toMatchObject({ url: rows[0].url, enabled: true, source: "profile" });
    });

    it.each([[[]], [rows.map(row => ({ ...row, enabled: false }))]])("keeps an explicit empty or fully offline list local-only", relays => {
        auth.user = { evolu_relays: relays, evolu_relay_url: defaults[0] };
        expect(getResolvedEvoluRelayUrls()).toEqual([]);
        expect(getEvoluRelayRuntimeInfo()).toMatchObject({ url: "", enabled: false });
        writeRelayOverrideRelays(relays, "a".repeat(64));
        auth.user = null;
        expect(evoluTransports()).toEqual([]);
        expect(getResolvedEvoluRelayUrls()).toEqual([]);
    });

    it("normalizes roots before detecting duplicate or invalid entries, including disabled rows", () => {
        expect(normalizeEvoluRelayConfigurations([{ url: " wss://ONE.example.test/ ", enabled: true }]))
            .toEqual([rows[0]]);
        expect(normalizeEvoluRelayConfigurations([rows[0], { url: "wss://ONE.example.test/", enabled: false }])).toBeNull();
        expect(normalizeEvoluRelayConfigurations([{ url: "wss://host.test/path", enabled: false }])).toBeNull();
        expect(normalizeEvoluRelayConfigurations([{ url: rows[0].url, enabled: "true" }])).toBeNull();
        expect(normalizeEvoluRelayConfigurations(null)).toBeNull();
    });

    it("enforces total row and normalized URL-size limits without truncating the list", () => {
        expect(normalizeEvoluRelayConfigurations(Array.from({ length: 9 }, (_, i) => ({ url: `wss://${i}.test`, enabled: true })))).toBeNull();
        const longRows = Array.from({ length: 8 }, (_, i) => ({
            url: `wss://${"a".repeat(63)}.${"b".repeat(63)}.${"c".repeat(63)}.${"d".repeat(50)}${i}.test:8443`, enabled: true,
        }));
        expect(normalizeEvoluRelayConfigurations(longRows.slice(0, 7))).not.toBeNull();
        expect(normalizeEvoluRelayConfigurations(longRows)).toBeNull();
    });

    it("uses canonical rows over another account's cache while retaining device-off precedence", () => {
        writeRelayOverrideRelays(rows, "a".repeat(64));
        auth.user = { evolu_relays: [{ url: defaults[0], enabled: true }] };
        expect(getResolvedEvoluRelayUrls()).toEqual([defaults[0]]);
        writeRelayOverrideDisabled();
        expect(getResolvedEvoluRelayUrls()).toEqual([]);
        expect(evoluTransports()).toEqual([]);
    });

    it("atomically retains disabled rows and public association across cached startup", () => {
        writeRelayOverrideRelays(rows, "a".repeat(64));
        expect(readRelayOverride()).toEqual({ kind: "relays", relays: rows, accountPublicKey: "a".repeat(64) });
        expect(evoluTransports().map(t => t.url)).toEqual([rows[0].url, rows[2].url]);
        expect(getResolvedEvoluRelayUrls()).toEqual([rows[0].url, rows[2].url]);
        expect(localStorage.getItem(EVOLU_RELAY_OVERRIDE_STORAGE_KEY)).toBe(JSON.stringify({ relays: rows, accountPublicKey: "a".repeat(64) }));
        writeRelayOverrideUrl(rows[0].url);
        expect(readRelayOverride()).toEqual({ kind: "url", url: rows[0].url });
    });

    it.each([
        { relays: "invalid" }, { relays: [null] }, { relays: [{ url: rows[0].url, enabled: "true" }] },
        { relays: [{ url: "wss://relay.test/path", enabled: true }] },
    ])("fails closed for malformed recognized canonical cache values", cache => {
        localStorage.setItem(EVOLU_RELAY_OVERRIDE_STORAGE_KEY, JSON.stringify(cache));
        expect(evoluTransports()).toEqual([]);
        expect(getResolvedEvoluRelayUrls()).toEqual([]);
        expect(readRelayOverride()).toMatchObject({ kind: "relays", accountPublicKey: null });
    });

    it("keeps unassociated and truncated JSON lists paused rather than invoking a default", () => {
        expect(parseRelayOverride(JSON.stringify({ relays: rows }))).toMatchObject({ accountPublicKey: null });
        localStorage.setItem(EVOLU_RELAY_OVERRIDE_STORAGE_KEY, '{"relays":[');
        expect(evoluTransports()).toEqual([]);
        expect(readRelayOverride()).toEqual({ kind: "relays", relays: [], accountPublicKey: null });
    });
});
