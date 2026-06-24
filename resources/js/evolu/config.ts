/** Default Evolu relay for sync/backup (Phase 0 PoC). Override via VITE_EVOLU_RELAY_URL. */
const DEFAULT_EVOLU_RELAY_URL = "wss://free.evoluhq.com";

export type EvoluRelayBuildInfo = {
    url: string;
    enabled: boolean;
    /** How the URL was chosen at `npm run build` time. */
    source: "env" | "default" | "disabled";
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

/** Empty transports = local-only SQLite, no cross-browser sync. */
export function evoluTransports(): { type: "WebSocket"; url: string }[] {
    const { url, enabled } = getEvoluRelayBuildInfo();
    if (!enabled || !url) {
        return [];
    }
    return [{ type: "WebSocket", url }];
}

export function isEvoluRelayEnabledInBuild(): boolean {
    return getEvoluRelayBuildInfo().enabled;
}
