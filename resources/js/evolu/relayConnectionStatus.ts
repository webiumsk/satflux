/** Connection observations contain no owners, protocol messages or recovery material. */
export type RelayConnectionState = "unknown" | "connecting" | "connected" | "disconnected" | "error";
export type ObservedRelayConnectionState = Exclude<RelayConnectionState, "unknown">;
export type RelayConnections = Readonly<Record<string, RelayConnectionState>>;

export const RELAY_CONNECTION_CHANNEL = "satflux.evolu.relay_connections.v1";
export const RELAY_CONNECTION_HEARTBEAT_MS = 5_000;
export const RELAY_CONNECTION_STALE_MS = 15_000;
export const MAX_RELAY_CONNECTIONS = 16;

export type RelayConnectionSnapshot = {
    type: "snapshot";
    source: string;
    startedAt: number;
    sequence: number;
    requestId?: string;
    connections: { url: string; state: ObservedRelayConnectionState }[];
};

export type RelayConnectionRequest = { type: "request"; requestId: string };
export type RelayConnectionChannel = Pick<BroadcastChannel, "postMessage" | "close" | "onmessage">;
export type RelayConnectionChannelFactory = () => RelayConnectionChannel | null;

/** Optional observation must never prevent a database or a page from starting. */
export function openRelayConnectionChannel(): RelayConnectionChannel | null {
    try {
        return typeof BroadcastChannel === "undefined" ? null : new BroadcastChannel(RELAY_CONNECTION_CHANNEL);
    } catch {
        return null;
    }
}

/** Redact the SDK owner query to its origin; received snapshots must already contain this canonical form. */
export function relayConnectionOrigin(value: unknown): string | null {
    if (typeof value !== "string" || value.length > 512) return null;
    try {
        const url = new URL(value);
        if (!/^(?:wss?|https?):$/.test(url.protocol) || !url.hostname || url.username || url.password
            || (url.pathname !== "" && url.pathname !== "/")) return null;
        return url.origin;
    } catch {
        return null;
    }
}

/** Accept only the bounded public snapshot schema, ignoring arbitrary channel data. */
export function parseRelayConnectionSnapshot(value: unknown): RelayConnectionSnapshot | null {
    if (!value || typeof value !== "object") return null;
    const item = value as Partial<RelayConnectionSnapshot>;
    if (Object.keys(value).some(key => !["type", "source", "startedAt", "sequence", "requestId", "connections"].includes(key))) return null;
    if (item.type !== "snapshot" || typeof item.source !== "string" || !/^[a-zA-Z0-9-]{1,80}$/.test(item.source)
        || !Number.isSafeInteger(item.startedAt) || item.startedAt! < 0
        || !Number.isSafeInteger(item.sequence) || item.sequence! < 1
        || (item.requestId !== undefined && !validRequestId(item.requestId))
        || !Array.isArray(item.connections) || item.connections.length > MAX_RELAY_CONNECTIONS) return null;
    const seen = new Set<string>();
    for (const row of item.connections) {
        if (!row || typeof row !== "object" || Object.keys(row).some(key => key !== "url" && key !== "state")
            || relayConnectionOrigin(row.url) !== row.url
            || !["connecting", "connected", "disconnected", "error"].includes(row.state) || seen.has(row.url)) return null;
        seen.add(row.url);
    }
    return item as RelayConnectionSnapshot;
}

/** A bounded nonce associates initial/new-worker snapshots with an actual page request. */
export function validRequestId(value: unknown): value is string {
    return typeof value === "string" && /^[a-zA-Z0-9-]{1,80}$/.test(value);
}

/** Public observation identifier; it is neither an authentication token nor a database identifier. */
export function relayObservationId(): string {
    try {
        if (typeof crypto !== "undefined" && typeof crypto.randomUUID === "function") return crypto.randomUUID();
    } catch { /* An observation identifier must not prevent database initialization. */ }
    return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

/** Monotonic receipt time keeps a clock adjustment from preserving a dead worker's open state. */
export function relayObservationTime(): number {
    return typeof performance === "undefined" ? Date.now() : performance.now();
}
