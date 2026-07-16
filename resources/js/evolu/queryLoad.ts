import type { Evolu, Query, QueryRows, Row } from "@evolu/common/local-first";
import { withTimeout } from "./asyncTimeout";

/**
 * The single deliberate bridge from Evolu's branded QueryRows to the app-level
 * row types (Evolu*Row in *Map.ts): both describe the same SQLite rows, but
 * the branded column types do not overlap structurally, so a direct `as`
 * cast is a TS2352 at every call site. Centralizing the cast keeps call
 * sites cast-free and gives grep one place to audit.
 */
export function toAppRows<TRow>(rows: readonly unknown[]): TRow[] {
    return rows as unknown as TRow[];
}

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
