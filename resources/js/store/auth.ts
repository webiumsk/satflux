import type { EffectiveAccess, PurchaseAvailability } from '../types/access';
import { asApiError } from "../utils/apiError";
import { defineStore } from 'pinia';
import { ref, computed, onScopeDispose } from 'vue';
import api from '../services/api';
import { ensureCsrfCookie } from '../services/csrf';
import { useStoresStore } from './stores';
import {
    clearStoredGuestMnemonic,
    getStoredGuestMnemonic,
    guestRecoveryMessage,
    guestRecoveryPublicKeyHexFromMnemonic,
    hydrateAccountMnemonicSession,
    signGuestRecoveryMessage,
    storeGuestMnemonic,
} from '../services/guestRecovery';
import { isInvoicingLocalFirst } from '../evolu/flags';
import { ensureEvoluBoundToAccountSeed } from '../evolu/bootstrap';
import { EVOLU_RELAY_OVERRIDE_STORAGE_KEY, parseRelayOverride, writeRelayOverrideUrl, type RelayOverride } from '@/evolu/relayOverrideStorage';
import { normalizeEvoluRelayBaseUrl, resolveEvoluRelayUrls } from '@/evolu/config';
import { reconcileEvoluRelayPreference } from '@/evolu/relayPreferenceLifecycle';

function isEmailNotVerifiedResponse(error: { response?: { data?: unknown } } | null): boolean {
    const data = error?.response?.data as { code?: unknown } | undefined;
    return data?.code === 'email_not_verified';
}

function scheduleChoralaSync(): void {
    void import('../services/chorala').then(({ syncChoralaIdentity }) => syncChoralaIdentity());
}

/** Staged 6-digit email code (never the hash or payload) - see EmailCodeChallengeService. */
export interface EmailChallengeSummary {
    purpose: string;
    email: string;
    expires_at: string;
    resend_available_at: string;
    attempts_left: number;
    sends_left?: number;
}

export interface User {
    id: number;
    email: string;
    is_guest?: boolean;
    allows_satflux_email_changes?: boolean;
    guest_recovery_enrolled?: boolean;
    recovery_public_key?: string | null;
    guest_upgrade_email_only?: boolean;
    requires_recovery_migration?: boolean;
    can_use_password_login?: boolean;
    pending_email_challenge?: EmailChallengeSummary | null;
    email_verified_at?: string;
    role?: string;
    /** Set once the one-time Pro trial was activated - null/absent = trial still available. */
    trial_consumed_at?: string | null;
    name?: string;
    plan?: {
        code: string;
        name: string;
        max_stores: number | null;
        max_api_keys: number | null;
        max_ln_addresses: number | null;
        max_companies?: number | null;
        included_companies?: number | null;
        extra_company_slots?: number;
        companies_unlimited?: boolean;
        features: string[];
    };
    subscription?: {
        status: string;
        expires_at: string | null;
        grace_ends_at: string | null;
    };
    plan_features?: {
        advanced_stats: boolean;
        automatic_exports: boolean;
        offline_payment_methods: boolean;
        business_invoicing?: boolean;
    };
    effective_access?: EffectiveAccess;
    purchase_availability?: PurchaseAvailability;
    evolu_relay_url?: string | null;
    evolu_relay_add_default?: boolean;
}

/** Distinguish a confirmed signed-out session from an unavailable session check. */
export type AuthFetchOutcome = 'loaded' | 'guest' | 'unavailable';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const loading = ref(false);
    let logoutGeneration = 0;
    let disposed = false;
    const logoutSignalKey = 'satflux.auth.logout.v1';
    let lastLogoutSignal: string | null = null;
    try {
        lastLogoutSignal = sessionStorage.getItem(logoutSignalKey);
    } catch {
        // Browser storage may be unavailable.
    }

    const isAuthenticated = computed(() => user.value !== null);

    const requiresRecoveryMigration = computed(
        () => user.value?.requires_recovery_migration === true,
    );

    /** Drop in-memory user + tenant store selection (same as logout’s local cleanup; no API call). */
    function clearLocalAuthAndTenantState() {
        user.value = null;
        const storesStore = useStoresStore();
        storesStore.stores = [];
        storesStore.currentStore = null;
    }

    function readLogoutSignal(): string | null {
        try {
            return localStorage.getItem(logoutSignalKey);
        } catch {
            return lastLogoutSignal;
        }
    }

    function applyLogoutSignal(signal: string | null): void {
        lastLogoutSignal = signal;
        logoutGeneration++;
        try {
            if (signal === null) sessionStorage.removeItem(logoutSignalKey);
            else sessionStorage.setItem(logoutSignalKey, signal);
        } catch {
            // The in-memory generation still discards late profile responses.
        }
        clearLocalAuthAndTenantState();
        try {
            clearStoredGuestMnemonic();
        } catch {
            // Storage restrictions must not prevent the server logout request.
        }
        scheduleChoralaSync();
    }

    function synchronizeLogout(): boolean {
        const signal = readLogoutSignal();
        if (signal === lastLogoutSignal) return false;
        applyLogoutSignal(signal);
        return true;
    }

    function publishLogout(): void {
        try {
            // Only a random logout marker crosses tabs, never recovery material.
            localStorage.setItem(logoutSignalKey, crypto.randomUUID());
        } catch {
            // Local cleanup and explicit-only recovery still work without storage.
        }
        applyLogoutSignal(readLogoutSignal());
    }

    function handleRemoteLogout(): void {
        if (!synchronizeLogout()) return;
        const generation = logoutGeneration;
        void import('../router').then(({ default: router }) => {
            if (!disposed && generation === logoutGeneration && !user.value) {
                void router.replace({ name: 'login' });
            }
        });
    }

    const onStorage = (event: StorageEvent) => {
        if (event.key === logoutSignalKey || event.key === null) handleRemoteLogout();
    };
    let lastAccessRefresh = 0;
    const refreshAccess = () => {
        handleRemoteLogout();
        if (!user.value || disposed || Date.now() - lastAccessRefresh < 30_000) return;
        lastAccessRefresh = Date.now();
        void fetchUser();
    };
    const onVisibility = () => {
        if (document.visibilityState === 'visible') refreshAccess();
    };
    synchronizeLogout();
    window.addEventListener('storage', onStorage);
    window.addEventListener('focus', refreshAccess);
    document.addEventListener('visibilitychange', onVisibility);
    onScopeDispose(() => {
        disposed = true;
        window.removeEventListener('storage', onStorage);
        window.removeEventListener('focus', refreshAccess);
        document.removeEventListener('visibilitychange', onVisibility);
    });

    function assertCurrentAuthOperation(generation: number): void {
        synchronizeLogout();
        if (generation !== logoutGeneration) throw new Error('Authentication cancelled by logout.');
    }

    function normalizeUserPayload(data: User): User {
        return { ...data };
    }

    async function syncAccountSeedAfterAuth(mnemonic: string): Promise<void> {
        storeGuestMnemonic(mnemonic);
        try {
            await ensureEvoluBoundToAccountSeed();
        } catch {
            // Evolu init is best-effort; invoicing layout may retry on first visit.
        }
    }

    /**
     * Cache guarded destinations for pre-auth startup and reconcile live owners.
     * Public account identity gates retargeting; an unknown canonical identity pauses
     * until the owner is confirmed. Device-level sync-off is never overwritten.
     */
    function cacheRelayOverrideFromProfile(profileRelayUrl: string | null, addDefault = false, accountPublicKey?: string | null): void {
        if (!isInvoicingLocalFirst()) return;
        let override: RelayOverride;
        try {
            override = parseRelayOverride(localStorage.getItem(EVOLU_RELAY_OVERRIDE_STORAGE_KEY));
        } catch {
            reconcileEvoluRelayPreference([], null);
            return;
        }
        if (override.kind === 'disabled') {
            reconcileEvoluRelayPreference([], accountPublicKey ?? null);
            return;
        }
        const normalized = normalizeEvoluRelayBaseUrl(profileRelayUrl ?? '');
        const urls = resolveEvoluRelayUrls(normalized, addDefault);
        const association = accountPublicKey ?? null;
        writeRelayOverrideUrl(normalized, addDefault, association);
        reconcileEvoluRelayPreference(urls, association);
    }

    /** Load the canonical session once while retaining guest, failure and stale-generation distinctions. */
    async function fetchUserWithOutcome(): Promise<AuthFetchOutcome> {
        synchronizeLogout();
        const generation = logoutGeneration;
        try {
            await ensureCsrfCookie();
            synchronizeLogout();
            if (generation !== logoutGeneration) return 'unavailable';
            hydrateAccountMnemonicSession();
            const response = await api.get('/user');
            synchronizeLogout();
            if (generation !== logoutGeneration) return 'unavailable';
            const previousUserId = user.value?.id ?? null;
            user.value = normalizeUserPayload(response.data);
            if ((user.value?.id ?? null) !== previousUserId) {
                scheduleChoralaSync();
            }
            cacheRelayOverrideFromProfile(user.value?.evolu_relay_url ?? null, user.value?.evolu_relay_add_default === true,
                user.value?.recovery_public_key ?? null);
            const mnemonic = getStoredGuestMnemonic();
            if (mnemonic && isInvoicingLocalFirst()) {
                void ensureEvoluBoundToAccountSeed();
            }
            return 'loaded';
        } catch (rawError) {
            synchronizeLogout();
            if (generation !== logoutGeneration) return 'unavailable';
            const error = asApiError(rawError);
            const status = error?.response?.status ?? error?.status;
            if (status === 403 && isEmailNotVerifiedResponse(error)) {
                // Authenticated but unverified: keep whatever user we have so
                // the "check your email" screen can render instead of the
                // login form (GET /user itself no longer 403s; belt and braces).
                return 'unavailable';
            }
            if (status === 401 || status === 403) {
                clearLocalAuthAndTenantState();
                scheduleChoralaSync();
                // A retained phrase must never turn logout/expiry into a login.
                // Recovery authentication requires an explicit user action.
                return 'guest';
            }
            return 'unavailable';
        }
    }

    /** Preserve existing callers' true-only-on-loaded contract without issuing a second request. */
    async function fetchUser(): Promise<boolean> {
        return (await fetchUserWithOutcome()) === 'loaded';
    }

    async function login(email: string, password: string, remember = false) {
        synchronizeLogout();
        const generation = logoutGeneration;
        loading.value = true;
        try {
            // Ensure CSRF cookie is set before login
            await ensureCsrfCookie();
            assertCurrentAuthOperation(generation);

            const response = await api.post('/auth/login', {
                email,
                password,
                remember,
            });
            assertCurrentAuthOperation(generation);
            // Auth responses carry a raw user without the computed /user
            // payload fields (guest_recovery_enrolled, can_use_password_login,
            // plan_features, subscription) - load the canonical payload so the
            // UI renders correctly without a hard refresh. fetchUser also
            // schedules the Chorala identity sync on the id transition.
            void response;
            await fetchUser();
            assertCurrentAuthOperation(generation);
            if (getStoredGuestMnemonic() && isInvoicingLocalFirst()) {
                void ensureEvoluBoundToAccountSeed();
            }
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function completeLegacyRecoveryMigration(payload: {
        recoveryPublicKeyHex: string;
        mnemonic: string;
    }) {
        synchronizeLogout();
        const generation = logoutGeneration;
        loading.value = true;
        try {
            await enrollGuestRecoveryPublicKey(payload.recoveryPublicKeyHex);
            assertCurrentAuthOperation(generation);
            await syncAccountSeedAfterAuth(payload.mnemonic);
            assertCurrentAuthOperation(generation);
            await fetchUser();
            assertCurrentAuthOperation(generation);
        } finally {
            loading.value = false;
        }
    }

    async function register(
        email: string,
        password: string,
        password_confirmation: string,
        consents?: { privacy_consent: boolean; terms_accepted: boolean },
    ) {
        loading.value = true;
        try {
            // Ensure CSRF cookie is set before register
            await ensureCsrfCookie();

            const response = await api.post('/auth/register', {
                email,
                password,
                password_confirmation,
                privacy_consent: consents?.privacy_consent ?? false,
                terms_accepted: consents?.terms_accepted ?? false,
            });
            // Session is not created until email is verified; do not treat as logged in.
            clearLocalAuthAndTenantState();
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function continueAsGuest(recoveryPublicKeyHex?: string, turnstileToken?: string) {
        synchronizeLogout();
        const generation = logoutGeneration;
        loading.value = true;
        try {
            await ensureCsrfCookie();
            assertCurrentAuthOperation(generation);
            const response = await api.post('/auth/guest', {
                ...(recoveryPublicKeyHex
                    ? { recovery_public_key: recoveryPublicKeyHex }
                    : {}),
                ...(turnstileToken ? { turnstile_token: turnstileToken } : {}),
            });
            assertCurrentAuthOperation(generation);
            await fetchUser();
            assertCurrentAuthOperation(generation);
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Link a recovery public key to the signed-in account (Profile backup,
     * legacy email migration). Dedicated endpoint: /auth/guest never attaches
     * a key to an existing session's account.
     */
    async function enrollGuestRecoveryPublicKey(recoveryPublicKeyHex: string) {
        synchronizeLogout();
        const generation = logoutGeneration;
        // Capture before awaiting CSRF setup: another tab can switch the shared
        // session while this tab still displays the original account.
        const expectedUserId = user.value?.id;
        if (expectedUserId == null) {
            throw new Error('Recovery enrollment requires a signed-in account.');
        }
        loading.value = true;
        try {
            await ensureCsrfCookie();
            assertCurrentAuthOperation(generation);
            const response = await api.post('/account/recovery-key', {
                expected_user_id: expectedUserId,
                recovery_public_key: recoveryPublicKeyHex,
            });
            assertCurrentAuthOperation(generation);
            if (response.data?.user) {
                // Same user id - fetchUser will not re-trigger the Chorala sync.
                await fetchUser();
                assertCurrentAuthOperation(generation);
                scheduleChoralaSync();
            }
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function restoreGuestFromMnemonic(mnemonic: string) {
        synchronizeLogout();
        const generation = logoutGeneration;
        loading.value = true;
        try {
            await ensureCsrfCookie();
            assertCurrentAuthOperation(generation);
            const chRes = await api.post('/auth/guest/recovery/challenge');
            assertCurrentAuthOperation(generation);
            const { challenge_id, nonce } = chRes.data.data;
            const message = guestRecoveryMessage(challenge_id, nonce);
            const pk = guestRecoveryPublicKeyHexFromMnemonic(mnemonic);
            const signature = signGuestRecoveryMessage(mnemonic, message);
            const response = await api.post('/auth/guest/recovery', {
                challenge_id,
                recovery_public_key: pk,
                signature,
            });
            assertCurrentAuthOperation(generation);
            await fetchUser();
            assertCurrentAuthOperation(generation);
            await syncAccountSeedAfterAuth(mnemonic);
            assertCurrentAuthOperation(generation);
            const storesStore = useStoresStore();
            await storesStore.fetchStores();
            assertCurrentAuthOperation(generation);
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        publishLogout();
        try {
            await api.post('/auth/logout');
        } finally {
            // Also invalidate profile reads started while the server logout was pending.
            publishLogout();
        }
    }

    return {
        user,
        loading,
        isAuthenticated,
        requiresRecoveryMigration,
        fetchUser,
        fetchUserWithOutcome,
        login,
        completeLegacyRecoveryMigration,
        register,
        continueAsGuest,
        enrollGuestRecoveryPublicKey,
        restoreGuestFromMnemonic,
        logout,
    };
});



