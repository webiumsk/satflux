/**
 * True when the error comes from an aborted request (AbortController) -
 * axios raises CanceledError with code ERR_CANCELED, the Fetch/DOM spec an
 * AbortError. A user-initiated cancel must never surface as a failure.
 */
export function isAbortError(error: unknown): boolean {
    if (!error || typeof error !== "object") return false;
    const candidate = error as { code?: unknown; name?: unknown };
    return (
        candidate.code === "ERR_CANCELED"
        || candidate.name === "CanceledError"
        || candidate.name === "AbortError"
    );
}
