import { createOwnerWebSocketTransport } from "@evolu/common";
import type { Evolu, SyncOwner } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import { getResolvedEvoluRelayUrl } from "@/services/evoluRelayPreference";

export async function createSyncOwnerForRelay(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<SyncOwner> {
    const owner = await evolu.appOwner;
    const relayUrl = getResolvedEvoluRelayUrl();
    if (!relayUrl) {
        return owner;
    }

    return {
        ...owner,
        transports: [
            createOwnerWebSocketTransport({
                url: relayUrl,
                ownerId: owner.id,
            }),
        ],
    };
}
