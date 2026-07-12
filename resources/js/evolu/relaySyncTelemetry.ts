import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";
import { sha256Hex } from "@/utils/sha256";

/**
 * Evidence-based relay sync state (P1 phase 4).
 *
 * Evolu 7.4.1 exposes no public sync-state API, so the app derives an HONEST
 * state from what it can actually observe: recorded successful exchanges
 * (a pull whose fingerprint changed, a push that completed ok), reachability
 * probes against the relay's HTTP usage endpoint, the browser online flag and
 * the Evolu error store. "ok" therefore means "relay reachable, data was
 * exchanged at T" - never an unverified "everything is synced".
 */

export type RelaySyncMeta = {
    lastPullSuccessAt: string | null;
    lastPushSuccessAt: string | null;
    lastProbeAt: string | null;
    lastProbeOk: boolean | null;
};

export type RelaySyncUiState =
    | "local_only"
    | "unknown"
    | "syncing"
    | "ok"
    | "unreachable"
    | "error";

const META_KEY_PREFIX = "satflux.evolu.relay_sync_meta.v1.";

const EMPTY_META: RelaySyncMeta = {
    lastPullSuccessAt: null,
    lastPushSuccessAt: null,
    lastProbeAt: null,
    lastProbeOk: null,
};

export function relaySyncMetaKey(ownerIdHash: string): string {
    return `${META_KEY_PREFIX}${ownerIdHash}`;
}

export function readRelaySyncMeta(ownerIdHash: string | null): RelaySyncMeta {
    if (!ownerIdHash) return { ...EMPTY_META };
    try {
        const raw = localStorage.getItem(relaySyncMetaKey(ownerIdHash));
        if (!raw) return { ...EMPTY_META };
        const parsed = JSON.parse(raw) as Partial<RelaySyncMeta>;
        return {
            lastPullSuccessAt: typeof parsed.lastPullSuccessAt === "string" ? parsed.lastPullSuccessAt : null,
            lastPushSuccessAt: typeof parsed.lastPushSuccessAt === "string" ? parsed.lastPushSuccessAt : null,
            lastProbeAt: typeof parsed.lastProbeAt === "string" ? parsed.lastProbeAt : null,
            lastProbeOk: typeof parsed.lastProbeOk === "boolean" ? parsed.lastProbeOk : null,
        };
    } catch {
        return { ...EMPTY_META };
    }
}

function writeRelaySyncMeta(ownerIdHash: string, patch: Partial<RelaySyncMeta>): RelaySyncMeta {
    const merged = { ...readRelaySyncMeta(ownerIdHash), ...patch };
    try {
        localStorage.setItem(relaySyncMetaKey(ownerIdHash), JSON.stringify(merged));
    } catch {
        // Storage unavailable - state falls back to "unknown" next session.
    }
    return merged;
}

export function recordPullSuccess(ownerIdHash: string, now: Date = new Date()): RelaySyncMeta {
    return writeRelaySyncMeta(ownerIdHash, { lastPullSuccessAt: now.toISOString() });
}

export function recordPushSuccess(ownerIdHash: string, now: Date = new Date()): RelaySyncMeta {
    return writeRelaySyncMeta(ownerIdHash, { lastPushSuccessAt: now.toISOString() });
}

export function recordProbe(ownerIdHash: string, ok: boolean, now: Date = new Date()): RelaySyncMeta {
    return writeRelaySyncMeta(ownerIdHash, { lastProbeAt: now.toISOString(), lastProbeOk: ok });
}

/** Newest recorded successful exchange (pull or push), if any. */
export function lastExchangeAt(meta: RelaySyncMeta): string | null {
    const candidates = [meta.lastPullSuccessAt, meta.lastPushSuccessAt].filter(
        (value): value is string => typeof value === "string",
    );
    if (candidates.length === 0) return null;
    return candidates.sort().at(-1) ?? null;
}

export type DeriveRelaySyncStateInput = {
    relayConfigured: boolean;
    online: boolean;
    pending: boolean;
    hasEvoluError: boolean;
    meta: RelaySyncMeta;
};

/**
 * Pure state derivation - unit tested as a truth table. Precedence:
 * local_only > offline/unreachable evidence > syncing > error > ok > unknown.
 * Unreachable evidence beats "syncing": a sync gate cannot succeed against a
 * provably unreachable relay, and claiming it would be dishonest.
 */
export function deriveRelaySyncState(input: DeriveRelaySyncStateInput): RelaySyncUiState {
    if (!input.relayConfigured) return "local_only";
    if (!input.online) return "unreachable";
    if (input.meta.lastProbeOk === false) return "unreachable";
    if (input.pending) return "syncing";
    if (input.hasEvoluError) return "error";
    if (input.meta.lastProbeOk === true) return "ok";
    return "unknown";
}

/** SHA-256 of the Evolu owner id - the per-owner telemetry key. Never the raw id. */
export async function ownerIdHashFor(evolu: Evolu<InvoicingLocalSchema>): Promise<string | null> {
    try {
        const owner = await evolu.appOwner;
        return owner?.id ? sha256Hex(String(owner.id)) : null;
    } catch {
        return null;
    }
}
