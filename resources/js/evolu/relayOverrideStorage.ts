/**
 * Runtime relay override (P2 phase 1).
 *
 * The synchronous cache bridges pre-auth startup and canonical account settings.
 * Managed owner registrations reconcile live transports after a profile change.
 * A public account identifier associates the cached destinations with their owner;
 * explicit null pauses unverified destinations. No mnemonic or private key is
 * stored here. Kept dependency-free so evolu/config.ts can import it without cycles.
 */

import type { EvoluRelayConfiguration } from "./config";

export const MAX_EVOLU_RELAYS = 8;
export const MAX_EVOLU_RELAY_URL_BYTES = 2048;

export const EVOLU_RELAY_OVERRIDE_STORAGE_KEY = "satflux.evolu.relay_override.v1";
const KEY = EVOLU_RELAY_OVERRIDE_STORAGE_KEY;

/** Sentinel stored when the user disabled sync on this device. */
const DISABLED_SENTINEL = "off";

export type RelayOverride =
    | { kind: "none" }
    | { kind: "relays"; relays: EvoluRelayConfiguration[]; accountPublicKey: string | null }
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
        if (raw.trimStart().startsWith("{")) {
            const value: unknown = JSON.parse(raw);
            if (typeof value === "object" && value !== null && "relays" in value) {
                const paused: RelayOverride = { kind: "relays", relays: [], accountPublicKey: null };
                if (!Array.isArray(value.relays) || value.relays.length > MAX_EVOLU_RELAYS) return paused;
                let urlBytes = 0;
                const relays: EvoluRelayConfiguration[] = [];
                for (const entry of value.relays) {
                    if (typeof entry !== "object" || entry === null || Object.keys(entry).length !== 2
                        || Object.keys(entry).some(key => key !== "url" && key !== "enabled") || typeof entry.url !== "string"
                        || typeof entry.enabled !== "boolean") return paused;
                    urlBytes += entry.url.length;
                    if (urlBytes > MAX_EVOLU_RELAY_URL_BYTES) return paused;
                    relays.push({ url: entry.url, enabled: entry.enabled });
                }
                const accountPublicKey = "accountPublicKey" in value && typeof value.accountPublicKey === "string"
                    && /^[a-f0-9]{64}$/i.test(value.accountPublicKey) ? value.accountPublicKey.toLowerCase() : null;
                return { kind: "relays", relays, accountPublicKey };
            }
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
            return { kind: "relays", relays: [], accountPublicKey: null };
        }
        return { kind: "url", url: raw };
    } catch {
        return raw?.trimStart().startsWith("{")
            ? { kind: "relays", relays: [], accountPublicKey: null } : { kind: "none" };
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

/** Persist a complete ordered list and its public owner association in one atomic cache value. */
export function writeRelayOverrideRelays(relays: readonly EvoluRelayConfiguration[], accountPublicKey: string | null = null): void {
    try {
        localStorage.setItem(KEY, JSON.stringify({ relays, accountPublicKey }));
    } catch {
        // Active owners still reconcile locally; other tabs fail closed after a failed write notice.
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
