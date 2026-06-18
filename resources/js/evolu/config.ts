/** Default Evolu relay for sync/backup (Phase 0 PoC). Override via VITE_EVOLU_RELAY_URL. */
export const EVOLU_RELAY_URL =
    import.meta.env.VITE_EVOLU_RELAY_URL?.trim() || "wss://free.evoluhq.com";

/** Empty transports = local-only SQLite (no sync). */
export function evoluTransports(): { type: "WebSocket"; url: string }[] {
    const url = EVOLU_RELAY_URL.trim();
    if (!url) {
        return [];
    }
    return [{ type: "WebSocket", url }];
}
