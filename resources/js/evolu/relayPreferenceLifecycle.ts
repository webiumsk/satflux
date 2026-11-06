import { createOwnerWebSocketTransport } from "@evolu/common";
import type { Evolu, SyncOwner, UnuseOwner } from "@evolu/common/local-first";
import { deriveRecoveryPublicKeyHex } from "@/services/accountSeed";
import { normalizeEvoluRelayBaseUrl, resolveEvoluRelayUrls } from "./config";
import { EVOLU_RELAY_OVERRIDE_STORAGE_KEY, parseRelayOverride, writeRelayOverrideUrl, type RelayOverride } from "./relayOverrideStorage";
import type { InvoicingLocalSchema } from "./schema";

type Client = Evolu<InvoicingLocalSchema>;
type Selection = { urls: readonly string[]; accountPublicKey?: string | null };
type Binding = { evolu: Client; owner: SyncOwner; accountPublicKey?: string | null; unuse?: UnuseOwner; signature?: string; removed: boolean };
const bindings = new Set<Binding>();
const publicKeys = new WeakMap<Client, string | null>();
const ownerPublicKeyCache = new WeakMap<SyncOwner, string>();
let selection: Selection | undefined;
let observing = false;
let suspended = false;
let channel: BroadcastChannel | undefined;
const CHANNEL = "satflux.evolu.relay_preference.v1";

/** Read the atomic public destination association, failing closed when storage is unavailable. */
function cacheSnapshot(): { selection: Selection; override: RelayOverride; available: boolean } {
    try {
        const cached = parseRelayOverride(localStorage.getItem(EVOLU_RELAY_OVERRIDE_STORAGE_KEY));
        return {
            override: cached,
            available: true,
            selection: {
                urls: cached.kind === "disabled" ? [] : resolveEvoluRelayUrls(cached.kind === "url" ? cached.url : null,
                    cached.kind === "url" && cached.addDefault === true),
                accountPublicKey: cached.kind === "url" ? cached.accountPublicKey : undefined,
            },
        };
    } catch {
        return { override: { kind: "none" }, available: false, selection: { urls: [], accountPublicKey: null } };
    }
}

/** Resolve public URLs and their account guard from the same atomic snapshot. */
function cachedSelection(): Selection { return cacheSnapshot().selection; }

/** Cache successful public derivations by weak owner identity without retaining its mnemonic. */
function ownerPublicKey(owner: SyncOwner): string | null {
    const cached = ownerPublicKeyCache.get(owner);
    if (cached) return cached;
    try {
        const key = "mnemonic" in owner && typeof owner.mnemonic === "string"
            ? deriveRecoveryPublicKeyHex(owner.mnemonic) : null;
        if (key) ownerPublicKeyCache.set(owner, key);
        return key;
    } catch {
        return null;
    }
}

/** Replace one owned registration; empty transports deliberately disable every relay. */
function applyBinding(binding: Binding): void {
    if (binding.removed || suspended) return;
    const desired = selection ?? cachedSelection();
    const permitted = desired.accountPublicKey === undefined
        || (desired.accountPublicKey !== null && desired.accountPublicKey === binding.accountPublicKey);
    const urls = permitted ? desired.urls : [];
    const signature = JSON.stringify(urls);
    if (binding.signature === signature) return;
    const oldUnuse = binding.unuse;
    if (urls.length === 0) oldUnuse?.();
    const nextUnuse = binding.evolu.useOwner({
        ...binding.owner,
        transports: urls.map(url => createOwnerWebSocketTransport({ url, ownerId: binding.owner.id })),
    });
    if (urls.length > 0) oldUnuse?.();
    binding.unuse = nextUnuse;
    binding.signature = signature;
}

/** Apply a single current selection to all AppOwner and SharedOwner registrations in this tab. */
function applySelection(next: Selection): void {
    selection = next;
    for (const binding of bindings) applyBinding(binding);
}

/** Honor device-off independently of the canonical account preference. */
function deviceSelection(next: Selection): Selection {
    const cached = cacheSnapshot();
    return !cached.available ? { urls: [], accountPublicKey: null }
        : cached.override.kind === "disabled" ? { ...next, urls: [] } : next;
}

/** Read the latest atomic value instead of trusting a possibly delayed storage event payload. */
function applyCachedSelection(): void {
    const snapshot = cacheSnapshot();
    const next = snapshot.selection;
    // An old client cannot authorize a new destination with an unassociated raw URL.
    if (next.accountPublicKey === undefined && selection
        && (selection.accountPublicKey !== undefined || JSON.stringify(next.urls) !== JSON.stringify(selection.urls))) {
        next.accountPublicKey = null;
        const cached = snapshot.override;
        if (cached.kind !== "disabled") {
            writeRelayOverrideUrl(cached.kind === "url" ? cached.url : "", cached.kind === "url" && cached.addDefault === true, null);
        }
    }
    applySelection(next);
}

/** Reconcile only relevant storage changes through the guarded cache adoption path. */
function onStorage(event: StorageEvent): void {
    if (event.key === EVOLU_RELAY_OVERRIDE_STORAGE_KEY || event.key === null) applyCachedSelection();
}

/** Drop this tab's references before a reload/close so another tab's worker cannot retain them. */
function onPageHide(): void {
    suspended = true;
    for (const binding of bindings) {
        binding.unuse?.();
        binding.unuse = undefined;
        binding.signature = undefined;
    }
}

/** Restore registrations after back-forward cache resume using the newest persisted selection. */
function onPageShow(): void {
    suspended = false;
    applyCachedSelection();
}

/** Compare public cache state so delayed notifications cannot overwrite a newer persisted preference. */
function selectionFingerprint(value: Selection): string {
    return JSON.stringify(value);
}

/** Observe other tabs without initializing Evolu or transporting any private owner material. */
function observePreferences(): void {
    if (observing || typeof window === "undefined") return;
    observing = true;
    window.addEventListener("storage", onStorage);
    window.addEventListener("pagehide", onPageHide);
    window.addEventListener("pageshow", onPageShow);
    if (typeof window.BroadcastChannel === "function") {
        try {
            channel = new window.BroadcastChannel(CHANNEL);
        } catch {
            // Optional cross-tab messaging must not make a canonical auth fetch fail.
            return;
        }
        channel.onmessage = (event: MessageEvent<unknown>) => {
            const value = event.data as (Partial<Selection> & { persisted?: unknown }) | null;
            if (!value || !Array.isArray(value.urls) || value.urls.length > 2
                || !value.urls.every(url => typeof url === "string" && normalizeEvoluRelayBaseUrl(url) === url)
                || (value.accountPublicKey !== undefined && value.accountPublicKey !== null
                    && (typeof value.accountPublicKey !== "string" || !/^[a-f0-9]{64}$/i.test(value.accountPublicKey)))) return;
            if (value.persisted === true) {
                applyCachedSelection();
            } else if (value.persisted === false) {
                // Failed writes have no durable ordering. A late notice may pause,
                // but it must never re-enable a removed destination in another tab.
                applySelection({ urls: [], accountPublicKey: null });
            }
        };
    }
}

/** Reconcile canonical destinations locally and across tabs even when the cache write failed. */
export function reconcileEvoluRelayPreference(urls: readonly string[], accountPublicKey?: string | null): void {
    observePreferences();
    const next = deviceSelection({ urls, accountPublicKey });
    applySelection(next);
    const cached = cacheSnapshot();
    try {
        channel?.postMessage({ ...next, persisted: cached.available && selectionFingerprint(cached.selection) === selectionFingerprint(next) });
    } catch {
        // Same-tab reconciliation and storage-event fallback remain available.
    }
}

/** Register an owner through tracked public SDK handles so later preference changes can release every relay. */
export function registerEvoluRelayOwner(evolu: Client, owner: SyncOwner): UnuseOwner {
    observePreferences();
    selection ??= cachedSelection();
    const binding: Binding = { evolu, owner, removed: false, accountPublicKey: publicKeys.get(evolu) };
    bindings.add(binding);
    const publicKey = ownerPublicKey(owner);
    if (publicKey) {
        publicKeys.set(evolu, publicKey);
        binding.accountPublicKey = publicKey;
    }
    applyBinding(binding);
    void Promise.resolve(evolu.appOwner).then(appOwner => {
        if (binding.removed) return;
        const key = appOwner ? ownerPublicKey(appOwner) : null;
        if (!publicKeys.has(evolu)) publicKeys.set(evolu, key);
        // This association belongs to the registration, not a later restored owner on the same client.
        binding.accountPublicKey ??= key;
        applyBinding(binding);
    }).catch(() => {
        if (!publicKeys.has(evolu)) publicKeys.set(evolu, null);
        if (!binding.removed) applyBinding(binding);
    });
    return () => {
        binding.removed = true;
        binding.unuse?.();
        bindings.delete(binding);
    };
}
