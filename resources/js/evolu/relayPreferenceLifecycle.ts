import { createOwnerWebSocketTransport } from "@evolu/common";
import type { Evolu, SyncOwner, UnuseOwner } from "@evolu/common/local-first";
import { deriveRecoveryPublicKeyHex } from "@/services/accountSeed";
import { MAX_EVOLU_RELAYS, normalizeEvoluRelayBaseUrl, resolveEvoluRelayUrls, resolveSelectedEvoluRelayUrls } from "./config";
import { EVOLU_RELAY_OVERRIDE_STORAGE_KEY, parseRelayOverride, type RelayOverride } from "./relayOverrideStorage";
import type { InvoicingLocalSchema } from "./schema";

type Client = Evolu<InvoicingLocalSchema>;
type Selection = { urls: readonly string[]; accountPublicKey?: string | null };
type Binding = { evolu: Client; owner: SyncOwner; isAppOwner: boolean; urls: readonly string[]; accountPublicKey?: string | null; unuse?: UnuseOwner; signature?: string; removed: boolean };
const bindings = new Set<Binding>();
const activeUrlListeners = new Set<() => void>();
let activeUrlSignature = "[]";

/** Observe only destinations actually permitted for this tab's AppOwner registrations. */
export function getActiveEvoluRelayUrls(): readonly string[] {
    return suspended ? [] : [...new Set([...bindings]
        .filter(binding => !binding.removed && binding.isAppOwner).flatMap(binding => binding.urls))];
}

/** Subscribe without publishing owner identifiers or private material to the UI. */
export function subscribeActiveEvoluRelayUrls(listener: () => void): () => void {
    activeUrlListeners.add(listener);
    return () => { activeUrlListeners.delete(listener); };
}

/** Notify presentation consumers only after the actual guarded registration set changes. */
function notifyActiveUrls(): void {
    const signature = JSON.stringify(getActiveEvoluRelayUrls());
    if (signature === activeUrlSignature) return;
    activeUrlSignature = signature;
    for (const listener of activeUrlListeners) {
        try { listener(); } catch {
            // Presentation listeners must not interrupt owner reconciliation.
        }
    }
}
const publicKeys = new WeakMap<Client, string | null>();
const ownerPublicKeyCache = new WeakMap<SyncOwner, string>();
let selection: Selection | undefined;
let canonicalSelectionConfirmed = false;
let preferenceRevision = 0;
let revalidator: (() => Promise<unknown>) | undefined;
let requestedRevision: number | undefined;
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
                urls: cached.kind === "disabled" ? [] : cached.kind === "relays"
                    ? resolveSelectedEvoluRelayUrls({ evolu_relays: cached.relays })
                    : resolveEvoluRelayUrls(cached.kind === "url" ? cached.url : null,
                        cached.kind === "url" && cached.addDefault === true),
                // No cache proves no account association. Retained OPFS owners must
                // wait for canonical authentication before using new defaults.
                accountPublicKey: cached.kind === "url" || cached.kind === "relays" ? cached.accountPublicKey : null,
            },
        };
    } catch {
        return { override: { kind: "none" }, available: false, selection: { urls: [], accountPublicKey: null } };
    }
}

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
    const desired = selection ?? { urls: [], accountPublicKey: null };
    const permitted = canonicalSelectionConfirmed && desired.accountPublicKey != null
        && desired.accountPublicKey === binding.accountPublicKey;
    const urls = permitted ? desired.urls : [];
    const signature = JSON.stringify(urls);
    if (binding.signature === signature) return;
    const oldUnuse = binding.unuse;
    if (urls.length === 0) {
        oldUnuse?.();
        binding.urls = [];
    }
    const nextUnuse = binding.evolu.useOwner({
        ...binding.owner,
        transports: urls.map(url => createOwnerWebSocketTransport({ url, ownerId: binding.owner.id })),
    });
    if (urls.length > 0) oldUnuse?.();
    binding.unuse = nextUnuse;
    binding.signature = signature;
    binding.urls = urls;
}

/** Apply a single current selection to all AppOwner and SharedOwner registrations in this tab. */
function applySelection(next: Selection): void {
    selection = next;
    for (const binding of bindings) applyBinding(binding);
    notifyActiveUrls();
}

/** Honor device-off independently of the canonical account preference. */
function deviceSelection(next: Selection): Selection {
    const cached = cacheSnapshot();
    return !cached.available ? { urls: [], accountPublicKey: null }
        : cached.override.kind === "disabled" ? { ...next, urls: [] } : next;
}

/** Identify invalidation boundaries without treating persisted data as fresh destination authority. */
export function getEvoluRelayPreferenceRevision(): number { return preferenceRevision; }

/** Ask auth for canonical proof once per revision; completion never overrides newer confirmation. */
function requestCanonicalRevalidation(): void {
    if (!revalidator || requestedRevision === preferenceRevision) return;
    const revision = preferenceRevision;
    requestedRevision = revision;
    try {
        void revalidator().catch(() => {
            // References were already paused; an unavailable profile cannot authorize cached routes.
        }).finally(() => {
            if (requestedRevision === revision) requestedRevision = undefined;
        });
    } catch {
        if (requestedRevision === revision) requestedRevision = undefined;
    }
}

/** Register an auth callback without importing the database or initiating standalone lifecycle networking. */
export function setEvoluRelayPreferenceRevalidator(callback: () => Promise<unknown>): () => void {
    if (revalidator && revalidator !== callback) invalidateEvoluRelayPreference(false);
    revalidator = callback;
    if (bindings.size > 0 && !canonicalSelectionConfirmed) requestCanonicalRevalidation();
    return () => {
        if (revalidator !== callback) return;
        revalidator = undefined;
        requestedRevision = undefined;
        invalidateEvoluRelayPreference(false);
    };
}

/** Pause first, then require a new canonical read before any cached destination could be enabled. */
export function invalidateEvoluRelayPreference(requestRevalidation = true): void {
    preferenceRevision++;
    pauseEvoluRelayPreference();
    if (requestRevalidation) requestCanonicalRevalidation();
}

/** Reconcile only relevant storage changes through the guarded cache adoption path. */
function onStorage(event: StorageEvent): void {
    if (event.key === EVOLU_RELAY_OVERRIDE_STORAGE_KEY || event.key === null) invalidateEvoluRelayPreference();
}

/** Drop this tab's references before a reload/close so another tab's worker cannot retain them. */
function onPageHide(): void {
    suspended = true;
    invalidateEvoluRelayPreference(false);
    for (const binding of bindings) {
        binding.unuse?.();
        binding.unuse = undefined;
        binding.signature = undefined;
        binding.urls = [];
    }
    notifyActiveUrls();
}

/** Resume locally only after a fresh canonical profile confirms the current destinations. */
function onPageShow(): void {
    suspended = false;
    invalidateEvoluRelayPreference();
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
            if (!value || !Array.isArray(value.urls) || value.urls.length > MAX_EVOLU_RELAYS
                || !value.urls.every(url => typeof url === "string" && normalizeEvoluRelayBaseUrl(url) === url)
                || (value.accountPublicKey !== undefined && value.accountPublicKey !== null
                    && (typeof value.accountPublicKey !== "string" || !/^[a-f0-9]{64}$/i.test(value.accountPublicKey)))) return;
            if (value.persisted === true || value.persisted === false) invalidateEvoluRelayPreference();
        };
    }
}

/** Pause this tab's owned references while a changed cross-tab preference is revalidated. */
export function pauseEvoluRelayPreference(): void {
    canonicalSelectionConfirmed = false;
    applySelection({ urls: [], accountPublicKey: null });
}

/** Reconcile canonical destinations locally and across tabs even when the cache write failed. */
export function reconcileEvoluRelayPreference(
    urls: readonly string[], accountPublicKey?: string | null,
    options: { announce?: boolean; revision?: number } = {},
): void {
    observePreferences();
    if (options.revision !== undefined && options.revision !== preferenceRevision) return;
    const next = deviceSelection({ urls, accountPublicKey: accountPublicKey ?? null });
    canonicalSelectionConfirmed = true;
    applySelection(next);
    if (options.announce === false) return;
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
    if (!canonicalSelectionConfirmed) requestCanonicalRevalidation();
    const binding: Binding = { evolu, owner, removed: false, isAppOwner: false, urls: [], accountPublicKey: publicKeys.get(evolu) };
    bindings.add(binding);
    const publicKey = ownerPublicKey(owner);
    if (publicKey) {
        binding.isAppOwner = true;
        publicKeys.set(evolu, publicKey);
        binding.accountPublicKey = publicKey;
    }
    applyBinding(binding);
    notifyActiveUrls();
    void Promise.resolve(evolu.appOwner).then(appOwner => {
        if (binding.removed) return;
        binding.isAppOwner = appOwner?.id === owner.id;
        const key = appOwner ? ownerPublicKey(appOwner) : null;
        if (!publicKeys.has(evolu)) publicKeys.set(evolu, key);
        // This association belongs to the registration, not a later restored owner on the same client.
        binding.accountPublicKey ??= key;
        applyBinding(binding);
        notifyActiveUrls();
    }).catch(() => {
        if (!publicKeys.has(evolu)) publicKeys.set(evolu, null);
        if (!binding.removed) {
            applyBinding(binding);
            notifyActiveUrls();
        }
    });
    return () => {
        binding.removed = true;
        binding.unuse?.();
        bindings.delete(binding);
        notifyActiveUrls();
    };
}
