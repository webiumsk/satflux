const SERVER_UUID_RE =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

/** PostgreSQL / Satflux server UUID (not Evolu local-first ids). */
export function isServerUuid(value: unknown): value is string {
    return typeof value === "string" && SERVER_UUID_RE.test(value.trim());
}
