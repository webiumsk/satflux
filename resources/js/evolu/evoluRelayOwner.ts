import { createOwnerWebSocketTransport } from "@evolu/common";
import type { Evolu, SyncOwner } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import { withTimeout } from "./asyncTimeout";
import { EVOLU_QUERY_LOAD_TIMEOUT_MS } from "./queryLoad";
import { getResolvedEvoluRelayUrls } from "@/services/evoluRelayPreference";

/** Build the app owner's candidate transports without changing its identity or encryption keys. */
export async function createSyncOwnerForRelay(
    evolu: Evolu<InvoicingLocalSchema>,
): Promise<SyncOwner> {
    const owner = await withTimeout(evolu.appOwner, EVOLU_QUERY_LOAD_TIMEOUT_MS, "evolu_query_timeout");
    return {
        ...owner,
        transports: getResolvedEvoluRelayUrls().map((url) => createOwnerWebSocketTransport({
            url,
            ownerId: owner.id,
        })),
    };
}
