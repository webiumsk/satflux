/** Default Evolu relay for sync/backup (Phase 0 PoC). Override via VITE_EVOLU_RELAY_URL. */
export const DEFAULT_EVOLU_RELAY_URL = "wss://free.evoluhq.com";

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
    const url = String(raw).trim();
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

/** Empty transports = local-only SQLite, no cross-browser sync. */
export function evoluTransports(): { type: "WebSocket"; url: string }[] {
    const { url, enabled } = getEvoluRelayBuildInfo();
    if (!enabled || !url) {
        return [];
    }
    return [{ type: "WebSocket", url: normalizeEvoluRelayBaseUrl(url) }];
}

export function isEvoluRelayEnabledInBuild(): boolean {
    return getEvoluRelayBuildInfo().enabled;
}
