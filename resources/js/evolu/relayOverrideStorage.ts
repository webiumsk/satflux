/**
 * Runtime relay override (P2 phase 1).
 *
 * The synchronous cache bridges pre-auth startup and canonical account settings.
 * Managed owner registrations reconcile live transports after a profile change.
 * A public account identifier associates the cached destinations with their owner;
 * explicit null pauses unverified destinations. No mnemonic or private key is
 * stored here. Kept dependency-free so evolu/config.ts can import it without cycles.
 */

export const EVOLU_RELAY_OVERRIDE_STORAGE_KEY = "satflux.evolu.relay_override.v1";
const KEY = EVOLU_RELAY_OVERRIDE_STORAGE_KEY;

/** Sentinel stored when the user disabled sync on this device. */
const DISABLED_SENTINEL = "off";

export type RelayOverride =
    | { kind: "none" }
    | { kind: "url"; url: string; addDefault?: boolean; accountPublicKey?: string | null }
    | { kind: "disabled" };

/** Read the saved relay selection, retaining legacy URL and device-off formats. */
export function readRelayOverride(): RelayOverride {
    try {
        return parseRelayOverride(localStorage.getItem(KEY));
    } catch {
        return { kind: "none" };
    }
}

/** Parse one atomic cache snapshot without performing another storage read. */
export function parseRelayOverride(raw: string | null): RelayOverride {
    try {
        if (raw === null || raw === "") {
            return { kind: "none" };
        }
        if (raw === DISABLED_SENTINEL) {
            return { kind: "disabled" };
        }
        if (raw.startsWith("{")) {
            const value: unknown = JSON.parse(raw);
            if (typeof value === "object" && value !== null
                && "url" in value && typeof value.url === "string"
                && "addDefault" in value && typeof value.addDefault === "boolean") {
                const selection: RelayOverride = { kind: "url", url: value.url };
                if (value.addDefault) selection.addDefault = true;
                if ("accountPublicKey" in value) {
                    selection.accountPublicKey = typeof value.accountPublicKey === "string"
                        && /^[a-f0-9]{64}$/i.test(value.accountPublicKey)
                        ? value.accountPublicKey.toLowerCase() : null;
                }
                return selection;
            }
            return { kind: "none" };
        }
        return { kind: "url", url: raw };
    } catch {
        return { kind: "none" };
    }
}

/** Atomically cache destinations with their optional public owner association; null means paused. */
export function writeRelayOverrideUrl(url: string, addDefault = false, accountPublicKey?: string | null): void {
    try {
        if (url === "" && accountPublicKey === undefined) {
            localStorage.removeItem(KEY);
        } else {
            localStorage.setItem(KEY, addDefault || accountPublicKey !== undefined
                ? JSON.stringify({ url, addDefault, ...(accountPublicKey !== undefined ? { accountPublicKey } : {}) })
                : url);
        }
    } catch {
        // Storage unavailable - the build default applies on next load.
    }
}

/** Device-level "sync off": empty transports on next load. */
export function writeRelayOverrideDisabled(): void {
    try {
        localStorage.setItem(KEY, DISABLED_SENTINEL);
    } catch {
        // Storage unavailable.
    }
}

export function clearRelayOverride(): void {
    try {
        localStorage.removeItem(KEY);
    } catch {
        // Storage unavailable.
    }
}
