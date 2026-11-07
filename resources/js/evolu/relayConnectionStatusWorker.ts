import { createRelayProtocolObserver, preferredRelayProtocolObservation, type RelayProtocolObservation } from "./relayProtocolStatus";
import { createWebSocket, type CreateWebSocket, type WebSocket as EvoluWebSocket } from "@evolu/common";
import {
    MAX_RELAY_CONNECTIONS,
    RELAY_CONNECTION_HEARTBEAT_MS,
    openRelayConnectionChannel,
    relayConnectionOrigin,
    relayObservationId,
    validRequestId,
    type ObservedRelayConnectionState,
    type RelayConnectionChannelFactory,
    type RelayConnectionRequest,
    type RelayConnectionSnapshot,
} from "./relayConnectionStatus";

type Handle = { url: string; state: ObservedRelayConnectionState; socket?: EvoluWebSocket; protocol: ReturnType<typeof createRelayProtocolObserver> };

/** Observe the SDK's real sockets while leaving their protocol, retries and ownership untouched. */
export function createObservedRelayWebSockets(
    createSocket: CreateWebSocket = createWebSocket,
    channelFactory: RelayConnectionChannelFactory = openRelayConnectionChannel,
) {
    let channel: ReturnType<RelayConnectionChannelFactory> = null;
    try { channel = channelFactory(); } catch { /* Observation is optional. */ }
    const source = relayObservationId();
    const startedAt = Date.now();
    const handles = new Set<Handle>();
    let sequence = 0;
    let stopped = false;

    /** Publish only current origin/state aggregates, never socket payloads or owner identifiers. */
    function publish(requestId?: string): void {
        if (!channel || stopped) return;
        const rows = new Map<string, ObservedRelayConnectionState>();
        const protocols = new Map<string, RelayProtocolObservation>();
        const rank: Record<ObservedRelayConnectionState, number> = { disconnected: 0, error: 1, connecting: 2, connected: 3 };
        for (const handle of handles) {
            const current = handle.protocol.snapshot();
            const previousProtocol = protocols.get(handle.url);
            protocols.set(handle.url, previousProtocol ? preferredRelayProtocolObservation(previousProtocol, current) : current);
            const previous = rows.get(handle.url);
            if (previous === undefined || rank[handle.state] > rank[previous]) rows.set(handle.url, handle.state);
        }
        const message: RelayConnectionSnapshot = {
            type: "snapshot", source, startedAt, sequence: ++sequence,
            ...(requestId === undefined ? {} : { requestId }),
            connections: Array.from(rows, ([url, state]) => ({ url, state, protocol: protocols.get(url)! })).slice(0, MAX_RELAY_CONNECTIONS),
        };
        try { channel.postMessage(message); } catch { /* Lost telemetry cannot change synchronization. */ }
    }

    /** Sampling catches automatic retries that do not expose a separate connecting callback. */
    function sample(): void {
        for (const handle of handles) {
            try {
                const ready = handle.socket?.getReadyState();
                if (ready === "open") handle.state = "connected";
                else if (ready === "connecting") handle.state = "connecting";
                else if (ready && handle.state !== "error") handle.state = "disconnected";
            } catch {
                handle.state = "error";
            }
        }
    }

    if (channel) {
        channel.onmessage = event => {
            const message = event.data as Partial<RelayConnectionRequest> | null;
            if (message?.type !== "request" || !validRequestId(message.requestId)) return;
            sample();
            publish(message.requestId);
        };
    }
    const timer = channel ? setInterval(() => { sample(); publish(); }, RELAY_CONNECTION_HEARTBEAT_MS) : null;

    /** Preserve the public SDK handle while tracking its own callback/disposal generation. */
    const observedCreateWebSocket: CreateWebSocket = (url, options = {}) => {
        const origin = relayConnectionOrigin(url);
        if (!origin || stopped) return createSocket(url, options);
        const { onOpen, onClose, onError, onMessage } = options;
        const handle: Handle = { url: origin, state: "connecting", protocol: createRelayProtocolObserver() };
        handles.add(handle);
        publish();
        let disposed = false;
        let socket: EvoluWebSocket;
        try {
            socket = createSocket(url, {
                ...options,
                onMessage: data => {
                    try {
                        if (!disposed && !stopped) {
                            const before = handle.protocol.snapshot().state;
                            handle.protocol.received(data);
                            if (handle.protocol.snapshot().state !== before) publish();
                        }
                    } catch { /* Observation must not interrupt SDK processing. */ }
                    onMessage?.(data);
                },
                onOpen: () => {
                    if (!disposed) { handle.state = "connected"; publish(); }
                    onOpen?.();
                },
                onClose: event => {
                    if (!disposed) { handle.state = "disconnected"; publish(); }
                    onClose?.(event);
                },
                onError: error => {
                    if (!disposed) { handle.state = "error"; publish(); }
                    onError?.(error);
                },
            });
        } catch (error) {
            handles.delete(handle);
            publish();
            throw error;
        }
        handle.socket = socket;
        return {
            send: data => {
                const result = socket.send(data);
                if (result.ok && !disposed && !stopped) {
                    try {
                        const before = handle.protocol.snapshot().state;
                        handle.protocol.sent(data);
                        if (handle.protocol.snapshot().state !== before) publish();
                    } catch { /* Preserve the SDK send result. */ }
                }
                return result;
            },
            getReadyState: () => socket.getReadyState(),
            isOpen: () => socket.isOpen(),
            /** Retire only this observed handle before delegating SDK socket disposal once. */
            [Symbol.dispose]() {
                if (disposed) return;
                disposed = true;
                handles.delete(handle);
                handle.protocol.dispose();
                publish();
                socket[Symbol.dispose]();
            },
        };
    };

    return {
        createWebSocket: observedCreateWebSocket,
        /** Stop observation only; the SDK remains responsible for its own socket lifetimes. */
        dispose(): void {
            stopped = true;
            if (timer !== null) clearInterval(timer);
            for (const handle of handles) handle.protocol.dispose();
            handles.clear();
            try { channel?.close(); } catch { /* Optional channel cleanup. */ }
            channel = null;
        },
    };
}
