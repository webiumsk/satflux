/**
 * Runtime relay override (P2 phase 1).
 *
 * createEvolu accepts transports only at creation time, which runs at module
 * init - before auth. This tiny storage module is the synchronous bridge:
 * the profile relay URL (or a device-level "sync off" choice) is cached in
 * localStorage so the NEXT page load boots the Evolu worker against the
 * right relay without a rebuild. Kept dependency-free so evolu/config.ts can
 * import it without cycles.
 */

const KEY = "satflux.evolu.relay_override.v1";

/** Sentinel stored when the user disabled sync on this device. */
const DISABLED_SENTINEL = "off";

export type RelayOverride =
    | { kind: "none" }
    | { kind: "url"; url: string }
    | { kind: "disabled" };

export function readRelayOverride(): RelayOverride {
    try {
        const raw = localStorage.getItem(KEY);
        if (raw === null || raw === "") {
            return { kind: "none" };
        }
        if (raw === DISABLED_SENTINEL) {
            return { kind: "disabled" };
        }
        return { kind: "url", url: raw };
    } catch {
        return { kind: "none" };
    }
}

/** Cache a validated relay base URL (normalized ws(s)/http(s) origin). */
export function writeRelayOverrideUrl(url: string): void {
    try {
        if (url === "") {
            localStorage.removeItem(KEY);
        } else {
            localStorage.setItem(KEY, url);
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
