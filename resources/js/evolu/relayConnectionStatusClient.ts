import type { RelayProtocols } from "./relayProtocolStatus";
import {
    RELAY_CONNECTION_HEARTBEAT_MS,
    RELAY_CONNECTION_STALE_MS,
    openRelayConnectionChannel,
    parseRelayConnectionSnapshot,
    relayObservationId,
    relayObservationTime,
    type RelayConnectionChannelFactory,
    type RelayConnections,
} from "./relayConnectionStatus";

/** Subscribe to fresh worker-only observations; the channel never controls synchronization. */
export function subscribeRelayConnections(
    onChange: (connections: RelayConnections, protocols: RelayProtocols) => void,
    channelFactory: RelayConnectionChannelFactory = openRelayConnectionChannel,
) {
    let channel: ReturnType<RelayConnectionChannelFactory> = null;
    try { channel = channelFactory(); } catch { /* Unsupported observation stays unknown. */ }
    let active: { source: string; startedAt: number; sequence: number; seenAt: number } | null = null;
    const pending = new Map<string, number>();
    let disposed = false;

    /** Expire observations and their request nonces after worker death or page suspension. */
    function expire(): void {
        const now = relayObservationTime();
        for (const [requestId, requestedAt] of pending) {
            if (now - requestedAt >= RELAY_CONNECTION_STALE_MS) pending.delete(requestId);
        }
        if (active && now - active.seenAt >= RELAY_CONNECTION_STALE_MS) {
            active = null;
            pending.clear();
            onChange({}, {});
        }
    }

    /** Fresh request nonces prevent queued messages from reviving an old worker's state. */
    function refresh(): void {
        if (disposed) return;
        expire();
        if (!channel) return;
        const requestId = relayObservationId();
        pending.set(requestId, relayObservationTime());
        while (pending.size > 2) pending.delete(pending.keys().next().value!);
        try { channel.postMessage({ type: "request", requestId }); } catch { /* Keep only fresh evidence already received. */ }
    }

    if (channel) {
        channel.onmessage = event => {
            if (disposed) return;
            expire();
            const message = parseRelayConnectionSnapshot(event.data);
            if (!message) return;
            const requested = message.requestId !== undefined && pending.has(message.requestId);
            if (message.requestId !== undefined && !requested) return;
            if (!active || active.source !== message.source) {
                // An unsolicited old/foreign worker cannot claim the initial or a newer connection.
                if (!requested || (active && message.startedAt <= active.startedAt)) return;
            } else if (message.sequence <= active.sequence) return;
            active = {
                source: message.source,
                startedAt: message.startedAt,
                sequence: message.sequence,
                seenAt: relayObservationTime(),
            };
            if (message.requestId !== undefined) pending.delete(message.requestId);
            onChange(Object.fromEntries(message.connections.map(row => [row.url, row.state])),
                Object.fromEntries(message.connections.filter(row => row.protocol !== undefined).map(row => [row.url, row.protocol!])));
        };
    }
    const timer = channel ? setInterval(refresh, RELAY_CONNECTION_HEARTBEAT_MS) : null;
    refresh();

    return {
        refresh,
        /** Release page observation without sending any command to the Evolu worker. */
        dispose(): void {
            if (disposed) return;
            disposed = true;
            if (timer !== null) clearInterval(timer);
            pending.clear();
            active = null;
            try { channel?.close(); } catch { /* Optional observation cleanup. */ }
            channel = null;
            onChange({}, {});
        },
    };
}
