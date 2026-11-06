import { createOwnerWebSocketTransport } from "@evolu/common";
import type { Evolu, SyncOwner } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import { getResolvedEvoluRelayUrls } from "@/services/evoluRelayPreference";

/** Build the app owner's candidate transports without changing its identity or encryption keys. */
export async function createSyncOwnerForRelay(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<SyncOwner> {
    const owner = await evolu.appOwner;
    return {
        ...owner,
        transports: getResolvedEvoluRelayUrls().map((url) => createOwnerWebSocketTransport({
            url,
            ownerId: owner.id,
        })),
    };
}
