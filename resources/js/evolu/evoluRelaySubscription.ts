import type { Evolu } from "@evolu/common/local-first";
import { isEvoluRelayEnabledInBuild } from "./config";
import type { InvoicingLocalSchema } from "./schema";

let activeUnuseOwner: (() => void) | null = null;

export function isEvoluRelayConfigured(): boolean {
    return isEvoluRelayEnabledInBuild();
}

/**
 * Keep the app owner subscribed on the Evolu relay WebSocket.
 * Worker init calls useOwner once; this mirrors it on the client and allows refresh.
 */
export async function ensureEvoluRelaySubscription(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<boolean> {
    if (!isEvoluRelayConfigured()) {
        return false;
    }

    const owner = await evolu.appOwner;
    if (!owner?.writeKey) {
        return false;
    }

    if (!activeUnuseOwner) {
        activeUnuseOwner = evolu.useOwner(owner);
    }

    return true;
}

/** Drop and re-acquire relay subscription (forces Subscribe on an open WebSocket). */
export async function refreshEvoluRelaySubscription(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<boolean> {
    if (!isEvoluRelayConfigured()) {
        return false;
    }

    activeUnuseOwner?.();
    activeUnuseOwner = null;
    return ensureEvoluRelaySubscription(evolu);
}

export function releaseEvoluRelaySubscription(): void {
    activeUnuseOwner?.();
    activeUnuseOwner = null;
}
