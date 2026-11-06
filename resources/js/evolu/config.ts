/** Default Evolu relay for sync/backup (Phase 0 PoC). Override via VITE_EVOLU_RELAY_URL. */
import { MAX_EVOLU_RELAYS, MAX_EVOLU_RELAY_URL_BYTES, readRelayOverride } from "./relayOverrideStorage";
export { MAX_EVOLU_RELAYS, MAX_EVOLU_RELAY_URL_BYTES } from "./relayOverrideStorage";

export const DEFAULT_EVOLU_RELAY_URL = "wss://evolu.satflux.io";
export const SECONDARY_EVOLU_RELAY_URL = "wss://free.evoluhq.com";

export type EvoluRelayConfiguration = { url: string; enabled: boolean };
export type EvoluRelayPreference = {
    evolu_relays?: readonly EvoluRelayConfiguration[] | null;
    evolu_relay_url?: string | null;
    evolu_relay_add_default?: boolean;
};

export type EvoluRelayBuildInfo = {
    url: string;
    enabled: boolean;
    /** How the URL was chosen at `npm run build` time. */
    source: "env" | "default" | "disabled";
};

export type EvoluRelayRuntimeInfo = {
    url: string;
    enabled: boolean;
    /** Where the active relay URL came from. */
    source: "profile" | "build" | "default" | "disabled";
    build: EvoluRelayBuildInfo;
};

/** Relay URL baked into the Vite bundle (not read from Laravel .env at runtime). */
export function getEvoluRelayBuildInfo(): EvoluRelayBuildInfo {
    const raw = import.meta.env.VITE_EVOLU_RELAY_URL;
    if (raw === undefined || raw === null) {
        return {
            url: DEFAULT_EVOLU_RELAY_URL,
            enabled: true,
            source: "default",
        };
    }
    // Normalize (and validate) at the source: an invalid VITE_EVOLU_RELAY_URL
    // must yield a DISABLED config, never { url: "", enabled: true }.
    const url = normalizeEvoluRelayBaseUrl(String(raw));
    if (!url) {
        return { url: "", enabled: false, source: "disabled" };
    }
    return { url, enabled: true, source: "env" };
}

export const EVOLU_RELAY_URL = getEvoluRelayBuildInfo().url;

/**
 * Normalize user/build relay URL to WebSocket base (no path, no query).
 * A value that does not parse as a ws(s)/http(s) URL is rejected (empty
 * string) with a console warning - a malformed relay URL must fall back to
 * local-only mode instead of half-configuring the transport (P1 phase 6).
 */
export function normalizeEvoluRelayBaseUrl(raw: string): string {
    const trimmed = raw.trim();
    if (!trimmed) {
        return "";
    }
    const candidates = trimmed.includes("://") ? [trimmed] : [`wss://${trimmed}`];
    for (const candidate of candidates) {
        try {
            const parsed = new URL(candidate);
            if (!/^(wss?|https?):$/.test(parsed.protocol)) {
                console.warn(`[evolu] relay URL has unsupported protocol "${parsed.protocol}" - sync disabled`);
                return "";
            }
            return `${parsed.protocol}//${parsed.host}`;
        } catch {
            // Fall through to the rejection below.
        }
    }
    console.warn("[evolu] relay URL is not a valid URL - sync disabled");
    return "";
}

/** Resolve relay URL: profile override, then build-time, then public default. */
export function resolveEvoluRelayUrl(profileRelayUrl?: string | null): EvoluRelayRuntimeInfo {
    const build = getEvoluRelayBuildInfo();
    const profile = normalizeEvoluRelayBaseUrl(String(profileRelayUrl ?? ""));

    if (profile) {
        return { url: profile, enabled: true, source: "profile", build };
    }

    if (build.enabled && build.url) {
        return {
            url: normalizeEvoluRelayBaseUrl(build.url),
            enabled: true,
            source: build.source === "default" ? "default" : "build",
            build,
        };
    }

    if (build.source === "disabled") {
        return { url: "", enabled: false, source: "disabled", build };
    }

    return {
        url: DEFAULT_EVOLU_RELAY_URL,
        enabled: true,
        source: "default",
        build,
    };
}

/**
 * Empty transports = local-only SQLite, no cross-browser sync.
 *
 * Runtime ladder (P2 phase 1): device override (cached profile relay or a
 * device-level "sync off") -> build env -> public default. The override is
 * read synchronously because createEvolu runs at module init, before auth;
 * saving a relay in Profile writes the cache and prompts a reload.
 */
export function evoluTransports(): { type: "WebSocket"; url: string }[] {
    const override = readRelayOverride();
    if (override.kind === "disabled") return [];
    const urls = override.kind === "relays"
        ? resolveSelectedEvoluRelayUrls({ evolu_relays: override.relays })
        : resolveEvoluRelayUrls(
            override.kind === "url" ? override.url : null,
            override.kind === "url" && override.addDefault === true,
        );
    return urls.map((url) => ({ type: "WebSocket", url }));
}

export function isEvoluRelayEnabledInBuild(): boolean {
    return getEvoluRelayBuildInfo().enabled;
}

/** Reject user settings that would be silently retargeted by origin normalization. */
export function normalizeCustomEvoluRelayUrl(raw: string): string {
    const value = raw.trim();
    // Validate the original shape before URL() removes dot-segments or rewrites IPs.
    const match = /^wss:\/\/(\[[0-9a-f:]+\]|[a-z0-9][a-z0-9.-]*)(?::([0-9]{1,5}))?\/?$/i.exec(value);
    if (!match || value.length > 512) return "";
    const host = match[1].toLowerCase();
    const numericHost = host.replace(/\.$/, "");
    if (match[2] && (Number(match[2]) < 1 || Number(match[2]) > 65535)) return "";
    if (!host.startsWith("[")) {
        const labels = host.replace(/\.$/, "").split(".");
        if (host.length > 253 || labels.some(label => label.length > 63
            || !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i.test(label))) return "";
    }
    try {
        const url = new URL(value);
        if (/(?:^|\.)(?:[0-9]+|0x[0-9a-f]*)$/i.test(numericHost) && url.hostname !== numericHost) return "";
        if (url.protocol !== "wss:" || !url.hostname || url.username || url.password
            || (url.pathname !== "" && url.pathname !== "/")) return "";
        return url.origin;
    } catch {
        return "";
    }
}

/** One ordered, deduplicated selection for boot, app-owner and shared-owner sync. */
export function resolveEvoluRelayUrls(profileRelayUrl?: string | null, addDefault = false): string[] {
    const primary = resolveEvoluRelayUrl(profileRelayUrl);
    if (!primary.enabled || !primary.url) return [];
    if (primary.source !== "profile") return getDefaultEvoluRelayUrls();
    return [...new Set([primary.url, ...(addDefault ? getDefaultEvoluRelayUrls() : [])])];
}

/** Validate a complete canonical list atomically; invalid entries never fall back to another relay. */
export function normalizeEvoluRelayConfigurations(value: unknown): EvoluRelayConfiguration[] | null {
    if (!Array.isArray(value) || value.length > MAX_EVOLU_RELAYS) return null;
    const normalized: EvoluRelayConfiguration[] = [];
    const seen = new Set<string>();
    let urlBytes = 0;
    for (const entry of value) {
        if (typeof entry !== "object" || entry === null || Object.keys(entry).length !== 2
            || Object.keys(entry).some(key => key !== "url" && key !== "enabled") || typeof entry.url !== "string"
            || typeof entry.enabled !== "boolean") return null;
        const url = normalizeCustomEvoluRelayUrl(entry.url);
        if (!url || seen.has(url)) return null;
        seen.add(url);
        urlBytes += url.length;
        if (urlBytes > MAX_EVOLU_RELAY_URL_BYTES) return null;
        normalized.push({ url, enabled: entry.enabled });
    }
    return normalized;
}

/** Resolve every configured row, preserving legacy defaults only before a canonical list exists. */
export function resolveConfiguredEvoluRelays(profile: EvoluRelayPreference): EvoluRelayConfiguration[] {
    if (profile.evolu_relays !== undefined && profile.evolu_relays !== null) {
        return normalizeEvoluRelayConfigurations(profile.evolu_relays) ?? [];
    }
    return resolveEvoluRelayUrls(profile.evolu_relay_url, profile.evolu_relay_add_default === true)
        .map(url => ({ url, enabled: true }));
}

/** Select only enabled canonical rows; an explicit empty list never re-enables the default. */
export function resolveSelectedEvoluRelayUrls(profile: EvoluRelayPreference): string[] {
    return resolveConfiguredEvoluRelays(profile).filter(relay => relay.enabled).map(relay => relay.url);
}

/** Use both independent defaults unless the build explicitly disables default synchronization. */
export function getDefaultEvoluRelayUrls(): string[] {
    const build = getEvoluRelayBuildInfo();
    // The WebSocket constructor already treats HTTPS as WSS. Materialize the
    // equivalent secure origin so legacy HTTPS builds remain editable via the
    // canonical WSS-only account API without changing their destination.
    const primary = build.url.replace(/^https:/, "wss:");
    return build.enabled && primary ? [...new Set([primary, SECONDARY_EVOLU_RELAY_URL])] : [];
}

/** Materialize default rows for account settings without persisting or connecting automatically. */
export function getDefaultEvoluRelayConfigurations(): EvoluRelayConfiguration[] {
    return getDefaultEvoluRelayUrls().map(url => ({ url, enabled: true }));
}
