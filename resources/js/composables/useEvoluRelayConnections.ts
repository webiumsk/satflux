import type { RelayProtocols } from "@/evolu/relayProtocolStatus";
import { onMounted, onUnmounted, readonly, shallowRef } from "vue";
import { subscribeRelayConnections } from "@/evolu/relayConnectionStatusClient";
import type { RelayConnections } from "@/evolu/relayConnectionStatus";

/** Actual worker WebSocket state; an absent/faded observation remains unknown, never "synced". */
export function useEvoluRelayConnections() {
    const connections = shallowRef<RelayConnections>({});
    const protocols = shallowRef<RelayProtocols>({});
    let subscription: ReturnType<typeof subscribeRelayConnections> | undefined;

    /** Request a current worker snapshot after foregrounding or an explicit UI action. */
    function refresh(): void { subscription?.refresh(); }

    onMounted(() => {
        subscription = subscribeRelayConnections((value, protocolValues) => { connections.value = value; protocols.value = protocolValues; });
        document.addEventListener("visibilitychange", refresh);
        window.addEventListener("pageshow", refresh);
    });
    onUnmounted(() => {
        subscription?.dispose();
        document.removeEventListener("visibilitychange", refresh);
        window.removeEventListener("pageshow", refresh);
    });

    return { connections: readonly(connections), protocols: readonly(protocols), refresh };
}
