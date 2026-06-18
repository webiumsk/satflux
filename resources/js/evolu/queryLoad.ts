import type { Evolu, Query, QueryRows, Row } from "@evolu/common/local-first";
import { withTimeout } from "./asyncTimeout";

/** Default wait for Evolu SQLite worker to answer a loadQuery call. */
export const EVOLU_QUERY_LOAD_TIMEOUT_MS = 30_000;

export class EvoluQueryLoadError extends Error {
    constructor(readonly code: "timeout" | "failed") {
        super(code === "timeout" ? "evolu_query_timeout" : "evolu_query_failed");
        this.name = "EvoluQueryLoadError";
    }
}

/** loadQuery with a timeout so the UI does not hang forever when the worker stalls. */
export function loadEvoluQueryWithTimeout<S extends Record<string, unknown>, R extends Row>(
    evolu: Evolu<S>,
    query: Query<R>,
    timeoutMs = EVOLU_QUERY_LOAD_TIMEOUT_MS,
): Promise<QueryRows<R>> {
    return withTimeout(
        evolu.loadQuery(query),
        timeoutMs,
        "evolu_query_timeout",
    ).catch((error: unknown) => {
        if (error instanceof Error && error.message === "evolu_query_timeout") {
            throw new EvoluQueryLoadError("timeout");
        }
        throw new EvoluQueryLoadError("failed");
    });
}
