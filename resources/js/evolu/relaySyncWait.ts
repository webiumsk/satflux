import type { Evolu } from "@evolu/common/local-first";
import { allCompaniesQuery } from "./client";
import type { InvoicingLocalSchema } from "./schema";

const EVOLU_RELAY_PENDING_KEY = "satflux.evolu.relay_pending.v1";

export function markEvoluRelaySyncPending(): void {
    sessionStorage.setItem(EVOLU_RELAY_PENDING_KEY, String(Date.now()));
}

export function clearEvoluRelaySyncPending(): void {
    sessionStorage.removeItem(EVOLU_RELAY_PENDING_KEY);
}

export function isEvoluRelaySyncPending(): boolean {
    return sessionStorage.getItem(EVOLU_RELAY_PENDING_KEY) != null;
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

/** Poll local Evolu DB until companies appear (relay pull) or timeout. */
export async function waitForInvoicingRelayData(
    evolu: Evolu<InvoicingLocalSchema>,
    options?: { timeoutMs?: number; pollMs?: number },
): Promise<boolean> {
    const timeoutMs = options?.timeoutMs ?? 45_000;
    const pollMs = options?.pollMs ?? 750;
    const deadline = Date.now() + timeoutMs;

    while (Date.now() < deadline) {
        const companies = await evolu.loadQuery(allCompaniesQuery);
        if (companies.length > 0) {
            clearEvoluRelaySyncPending();
            return true;
        }
        await sleep(pollMs);
    }

    clearEvoluRelaySyncPending();
    return false;
}
