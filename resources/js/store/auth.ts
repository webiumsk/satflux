import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import axios from 'axios';
import api from '../services/api';
import { useStoresStore } from './stores';
import {
    clearStoredGuestMnemonic,
    getStoredGuestMnemonic,
    guestRecoveryMessage,
    guestRecoveryPublicKeyHexFromMnemonic,
    signGuestRecoveryMessage,
} from '../services/guestRecovery';

export interface User {
    id: number;
    email: string;
    is_guest?: boolean;
    guest_recovery_enrolled?: boolean;
    email_verified_at?: string;
    role?: string;
    name?: string;
    plan?: {
        code: string;
        name: string;
        max_stores: number | null;
        max_api_keys: number | null;
        max_ln_addresses: number | null;
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
    };
    has_lightning_login?: boolean;
    has_nostr_login?: boolean;
}

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const loading = ref(false);
    let autoRestoreInFlight = false;

    const isAuthenticated = computed(() => user.value !== null);

    /** Drop in-memory user + tenant store selection (same as logout’s local cleanup; no API call). */
    function clearLocalAuthAndTenantState() {
        user.value = null;
        const storesStore = useStoresStore();
        storesStore.stores = [];
        storesStore.currentStore = null;
    }

    async function fetchUser() {
        try {
            // Ensure session/CSRF cookie is set first (same-origin request).
            // Required after full page reload so the session cookie is sent on /api/user.
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
            const response = await api.get('/user');
            user.value = response.data;
            if (!user.value?.is_guest) {
                clearStoredGuestMnemonic();
            }
        } catch (error: any) {
            const status = error?.response?.status ?? error?.status;
            if (status === 401 || status === 403) {
                user.value = null;
                await tryAutoRestoreGuestFromStoredSeed();
                return;
            }
        }
    }

    async function tryAutoRestoreGuestFromStoredSeed() {
        if (autoRestoreInFlight) return;
        const mnemonic = getStoredGuestMnemonic();
        if (!mnemonic) return;

        autoRestoreInFlight = true;
        try {
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
            const chRes = await api.post('/auth/guest/recovery/challenge');
            const { challenge_id, nonce } = chRes.data.data;
            const message = guestRecoveryMessage(challenge_id, nonce);
            const pk = guestRecoveryPublicKeyHexFromMnemonic(mnemonic);
            const signature = signGuestRecoveryMessage(mnemonic, message);
            const response = await api.post('/auth/guest/recovery', {
                challenge_id,
                recovery_public_key: pk,
                signature,
            });
            user.value = response.data.user;
        } catch {
            // Keep user unauthenticated when auto-restore fails; manual restore remains available.
        } finally {
            autoRestoreInFlight = false;
        }
    }

    async function login(email: string, password: string, remember = false) {
        loading.value = true;
        try {
            // Ensure CSRF cookie is set before login
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });

            const response = await api.post('/auth/login', {
                email,
                password,
                remember,
            });
            user.value = response.data.user;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function register(email: string, password: string, password_confirmation: string) {
        loading.value = true;
        try {
            // Ensure CSRF cookie is set before register
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });

            const response = await api.post('/auth/register', {
                email,
                password,
                password_confirmation,
            });
            // Session is not created until email is verified; do not treat as logged in.
            clearLocalAuthAndTenantState();
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function continueAsGuest(recoveryPublicKeyHex?: string) {
        loading.value = true;
        try {
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
            const response = await api.post('/auth/guest', {
                ...(recoveryPublicKeyHex
                    ? { recovery_public_key: recoveryPublicKeyHex }
                    : {}),
            });
            user.value = response.data.user;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    /** Link recovery public key while already logged in as guest (e.g. from Profile). */
    async function enrollGuestRecoveryPublicKey(recoveryPublicKeyHex: string) {
        loading.value = true;
        try {
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
            const response = await api.post('/auth/guest', {
                recovery_public_key: recoveryPublicKeyHex,
            });
            if (response.data?.user) {
                user.value = response.data.user;
            }
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function restoreGuestFromMnemonic(mnemonic: string) {
        loading.value = true;
        try {
            await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
            const chRes = await api.post('/auth/guest/recovery/challenge');
            const { challenge_id, nonce } = chRes.data.data;
            const message = guestRecoveryMessage(challenge_id, nonce);
            const pk = guestRecoveryPublicKeyHexFromMnemonic(mnemonic);
            const signature = signGuestRecoveryMessage(mnemonic, message);
            const response = await api.post('/auth/guest/recovery', {
                challenge_id,
                recovery_public_key: pk,
                signature,
            });
            user.value = response.data.user;
            return response.data;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            await api.post('/auth/logout');
        } finally {
            clearLocalAuthAndTenantState();
        }
    }

    return {
        user,
        loading,
        isAuthenticated,
        fetchUser,
        login,
        register,
        continueAsGuest,
        enrollGuestRecoveryPublicKey,
        restoreGuestFromMnemonic,
        logout,
    };
});




