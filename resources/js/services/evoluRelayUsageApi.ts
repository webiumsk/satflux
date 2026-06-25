/** HTTP usage API exposed by self-hosted Evolu relay (see docs/EVOLU_RELAY_USAGE_API.md). */

export type EvoluRelayUsageResponse = {
    ownerId: string;
    storedBytes: number;
    quotaBytes: number;
    firstActivityAt: string | null;
    lastActivityAt: string | null;
};

export function relayUsageHttpUrl(relayWssUrl: string, ownerId: string): string {
    const trimmed = relayWssUrl.trim();
    const httpBase = trimmed
        .replace(/^wss:\/\//i, "https://")
        .replace(/^ws:\/\//i, "http://")
        .replace(/\/$/, "");
    return `${httpBase}/usage/${encodeURIComponent(ownerId)}`;
}

export async function fetchEvoluRelayUsage(
    relayWssUrl: string,
    ownerId: string,
    options?: { timeoutMs?: number },
): Promise<EvoluRelayUsageResponse | null> {
    const url = relayUsageHttpUrl(relayWssUrl, ownerId);
    const controller = new AbortController();
    const timeout = window.setTimeout(
        () => controller.abort(),
        options?.timeoutMs ?? 8_000,
    );

    try {
        const response = await fetch(url, {
            method: "GET",
            credentials: "omit",
            signal: controller.signal,
            headers: { Accept: "application/json" },
        });
        if (!response.ok) {
            return null;
        }
        const data = (await response.json()) as EvoluRelayUsageResponse;
        if (typeof data.storedBytes !== "number") {
            return null;
        }
        return data;
    } catch {
        return null;
    } finally {
        window.clearTimeout(timeout);
    }
}

export function relayUsagePercent(storedBytes: number, quotaBytes: number): number {
    if (!quotaBytes || quotaBytes <= 0) {
        return 0;
    }
    return Math.min(100, Math.round((storedBytes / quotaBytes) * 100));
}
