import type { Evolu } from "@evolu/common/local-first";
import { getResolvedEvoluRelayUrl, isEvoluRelayConfiguredAtRuntime } from "@/services/evoluRelayPreference";
import { isEvoluRelayEnabledInBuild } from "./config";
import type { InvoicingLocalSchema } from "./schema";
import { createSyncOwnerForRelay } from "./evoluRelayOwner";

let activeUnuseOwner: (() => void) | null = null;

/** @deprecated Use isEvoluRelayConfiguredAtRuntime - respects profile relay URL. */
export function isEvoluRelayConfigured(): boolean {
    if (isEvoluRelayConfiguredAtRuntime()) {
        return true;
    }
    return isEvoluRelayEnabledInBuild() && getResolvedEvoluRelayUrl().length > 0;
}

/**
 * Keep the app owner subscribed on the Evolu relay WebSocket.
 * Uses per-owner transports so profile relay URL overrides build default.
 */
export async function ensureEvoluRelaySubscription(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<boolean> {
    if (!isEvoluRelayConfiguredAtRuntime()) {
        return false;
    }

    const owner = await createSyncOwnerForRelay(evolu);
    if (!owner.writeKey) {
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
    if (!isEvoluRelayConfiguredAtRuntime()) {
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
