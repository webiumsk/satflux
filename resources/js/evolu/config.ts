/** Primary Evolu relay (self-hosted). Override via VITE_EVOLU_RELAY_URL at build time. */
export const EVOLU_RELAY_URL =
    import.meta.env.VITE_EVOLU_RELAY_URL?.trim() || "";

/** Secondary Evolu relay for resilience. Set to empty string to disable. */
export const EVOLU_RELAY_URL_SECONDARY = (() => {
    const raw = import.meta.env.VITE_EVOLU_RELAY_URL_SECONDARY;
    if (raw === undefined || raw === null) {
        return "wss://free.evoluhq.com";
    }
    return String(raw).trim();
})();

/** Empty transports = local-only SQLite (no sync). */
export function evoluTransports(): { type: "WebSocket"; url: string }[] {
    const urls = [EVOLU_RELAY_URL, EVOLU_RELAY_URL_SECONDARY].filter(Boolean);
    const unique = [...new Set(urls)];
    if (unique.length === 0) {
        return [];
    }
    return unique.map((url) => ({ type: "WebSocket", url }));
}

/** First configured relay URL (for dev tooling / logging). */
export const EVOLU_PRIMARY_RELAY_URL = EVOLU_RELAY_URL || EVOLU_RELAY_URL_SECONDARY;
