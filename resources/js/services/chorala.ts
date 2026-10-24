import api from './api';
import i18n from '../i18n';
import { useAuthStore } from '../store/auth';
import { asApiError } from '../utils/apiError';
import widgetScriptUrl from '../../vendor/chorala/widget.js?url';

type ChoralaWidgetSettings = {
    theme?: 'light' | 'dark';
    primaryColor?: string;
    position?: 'bottom-left' | 'bottom-right';
    mode?: 'floating' | 'inline' | 'manual';
};

declare global {
    interface Window {
        Chorala?: ((command: string, ...args: unknown[]) => void) & {
            q?: unknown[][];
            supportsIdentityRefresh?: boolean;
        };
    }
}

const IDENTITY_REFRESH_MARGIN_MS = 5 * 60_000;
const IDENTITY_RETRY_MS = 60_000;
const SCRIPT_TIMEOUT_MS = 20_000;
const FEEDBACK_ERROR_EVENT = 'satflux:feedback-unavailable';

let choralaLoaded = false;
let choralaScriptLoading: Promise<void> | null = null;
let widgetSettingsPromise: Promise<ChoralaWidgetSettings> | null = null;
let observedUserId: string | null = null;
let identityRevision = 0;
let lastSyncedUserId: string | null = null;
let identityExpiresAt = 0;
let identityLoading: { userId: string; revision: number; promise: Promise<void> } | null = null;
let refreshTimer: ReturnType<typeof setTimeout> | null = null;
let listenersAttached = false;

function getSatfluxWidgetOverrides(): ChoralaWidgetSettings {
    return { position: 'bottom-left', mode: 'manual' };
}

async function fetchWidgetSettings(): Promise<ChoralaWidgetSettings> {
    if (!widgetSettingsPromise) {
        widgetSettingsPromise = api.get<{ settings: ChoralaWidgetSettings }>('/chorala/widget-settings', {
            timeout: 8000,
            skipErrorFlash: true,
        })
            .then((response) => ({ ...response.data.settings, ...getSatfluxWidgetOverrides() }))
            .catch(() => getSatfluxWidgetOverrides());
    }
    return widgetSettingsPromise;
}

function getChoralaConfig(): { key: string; widgetUrl: string } | null {
    const key = document.querySelector('meta[name="satflux-chorala-key"]')?.getAttribute('content')?.trim();
    const widgetUrl = document.querySelector('meta[name="satflux-chorala-widget-url"]')?.getAttribute('content')?.trim()
        || 'https://chorala.com';
    return key ? { key, widgetUrl: widgetUrl.replace(/\/$/, '') } : null;
}

function getAppVersion(): string | undefined {
    const version = import.meta.env.VITE_APP_VERSION;
    return typeof version === 'string' && version.trim() ? version.trim() : undefined;
}

async function queueChoralaInit(config: { key: string; widgetUrl: string }): Promise<void> {
    const appVersion = getAppVersion();
    const settings = await fetchWidgetSettings();
    if (typeof window.Chorala !== 'function') {
        const queue: unknown[][] = [];
        window.Chorala = (...args: unknown[]) => { queue.push(args); };
        window.Chorala.q = queue;
    }
    window.Chorala('init', {
        projectKey: config.key,
        locale: i18n.global.locale.value,
        settings,
        // The SDK uses native fetch without Laravel CSRF headers. Always use
        // its direct transport, including when old pages still advertise proxy=true.
        apiUrl: new URL('/api/v1', config.widgetUrl).toString(),
        ...(appVersion ? { appVersion } : {}),
    });
}

function clearChoralaIdentity(): void {
    if (choralaLoaded && typeof window.Chorala === 'function') window.Chorala('identify', {});
}

function loadChoralaScript(config: { key: string; widgetUrl: string }): Promise<void> {
    if (choralaLoaded) return Promise.resolve();
    if (choralaScriptLoading) return choralaScriptLoading;
    choralaScriptLoading = new Promise((resolve, reject) => {
        let finished = false;
        let script: HTMLScriptElement | null = null;
        const fail = () => {
            if (finished) return;
            finished = true;
            clearTimeout(timeout);
            script?.remove();
            choralaScriptLoading = null;
            reject(new Error('Feedback could not be loaded.'));
        };
        const timeout = setTimeout(fail, SCRIPT_TIMEOUT_MS);
        void queueChoralaInit(config).then(() => {
            if (finished) return;
            script = document.createElement('script');
            script.async = true;
            script.src = widgetScriptUrl;
            script.setAttribute('data-chorala-key', config.key);
            script.onload = () => {
                if (finished) return;
                // Never fall back to destructive upstream identify for refreshes.
                if (window.Chorala?.supportsIdentityRefresh !== true) {
                    fail();
                    return;
                }
                finished = true;
                clearTimeout(timeout);
                choralaLoaded = true;
                resolve();
            };
            script.onerror = fail;
            document.head.appendChild(script);
        }).catch(fail);
    });
    return choralaScriptLoading;
}

function currentUserId(): string | null {
    const user = useAuthStore().user;
    return user ? String(user.id) : null;
}

function cancelRefresh(): void {
    if (refreshTimer !== null) clearTimeout(refreshTimer);
    refreshTimer = null;
}

function observeIdentity(): { userId: string | null; revision: number } {
    const userId = currentUserId();
    if (userId !== observedUserId) {
        observedUserId = userId;
        identityRevision += 1;
        lastSyncedUserId = null;
        identityExpiresAt = 0;
        cancelRefresh();
        clearChoralaIdentity();
    }
    return { userId, revision: identityRevision };
}

function stillCurrent(userId: string, revision: number): boolean {
    const current = observeIdentity();
    return current.userId === userId && current.revision === revision;
}

function scheduleRefresh(delay: number): void {
    cancelRefresh();
    refreshTimer = setTimeout(() => {
        refreshTimer = null;
        void syncChoralaIdentity();
    }, Math.max(1000, delay));
}

function attachIdentityListeners(): void {
    if (listenersAttached) return;
    listenersAttached = true;
    window.addEventListener('focus', () => { void syncChoralaIdentity(); });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') void syncChoralaIdentity();
    });
}

export function isChoralaConfigured(): boolean {
    return getChoralaConfig() !== null;
}

export async function openChoralaWidget(): Promise<void> {
    const config = getChoralaConfig();
    if (!config) return;
    await loadChoralaScript(config);
    await syncChoralaIdentity({ throwOnError: true });
    // A switch during the request must never open with a previous account's identity.
    if (currentUserId() !== lastSyncedUserId) throw new Error('Feedback identity changed. Please try again.');
    window.Chorala?.('open');
}

export async function loadChoralaWidget(): Promise<void> {
    const config = getChoralaConfig();
    if (!config) return;
    try {
        await loadChoralaScript(config);
    } catch {
        // The launcher owns visible retry feedback; background loading is optional.
    }
}

/** Refresh the short-lived SSO token without recreating the widget or its draft. */
export async function syncChoralaIdentity(options: { throwOnError?: boolean } = {}): Promise<void> {
    const config = getChoralaConfig();
    if (!config) return;
    attachIdentityListeners();
    const { userId, revision } = observeIdentity();
    if (!userId) return;
    const refreshDelay = identityExpiresAt - Date.now() - IDENTITY_REFRESH_MARGIN_MS;
    if (lastSyncedUserId === userId && refreshDelay > 0) {
        // A timer can fire before the refresh window after a clock adjustment.
        // Keep an existing retry, or schedule the next check instead of stopping.
        if (refreshTimer === null) scheduleRefresh(refreshDelay);
        return;
    }
    let loading = identityLoading;
    if (!loading || loading.userId !== userId || loading.revision !== revision) {
        const promise = (async () => {
            try {
                await loadChoralaScript(config);
                if (!stillCurrent(userId, revision)) return;
                const response = await api.get<{ jwt: string; expires_at: number }>('/chorala/widget-token', {
                    timeout: 10000,
                    skipErrorFlash: true,
                });
                if (!stillCurrent(userId, revision)) return;
                const jwt = response.data?.jwt?.trim();
                const expiresAt = Number(response.data?.expires_at) * 1000;
                if (!jwt || !Number.isFinite(expiresAt) || expiresAt <= Date.now() + IDENTITY_REFRESH_MARGIN_MS) {
                    throw new Error('Feedback identity could not be refreshed.');
                }
                if (window.Chorala?.supportsIdentityRefresh !== true) {
                    throw new Error('Feedback identity refresh is unavailable.');
                }
                window.Chorala(lastSyncedUserId === userId ? 'refreshIdentity' : 'identify', { jwt });
                lastSyncedUserId = userId;
                identityExpiresAt = expiresAt;
                scheduleRefresh(expiresAt - Date.now() - IDENTITY_REFRESH_MARGIN_MS);
            } catch (rawError) {
                if (!stillCurrent(userId, revision)) return;
                const error = asApiError(rawError);
                // SSO is optional. A deliberately unconfigured secret keeps the
                // public widget available without borrowing an old identity.
                if (error.response?.status === 404
                    && error.response.data?.message === 'Chorala widget SSO is not configured.') {
                    if (window.Chorala?.supportsIdentityRefresh !== true) {
                        throw new Error('Feedback identity refresh is unavailable.');
                    }
                    window.Chorala('refreshIdentity', {});
                    lastSyncedUserId = userId;
                    identityExpiresAt = Date.now() + 15 * 60_000;
                    scheduleRefresh(10 * 60_000);
                    return;
                }
                // Keep the existing same-user client, token and draft on a
                // transient failure. An expired token can fail a submit safely;
                // clearing identity here would rebuild the UI and erase its draft.
                scheduleRefresh(IDENTITY_RETRY_MS);
                window.dispatchEvent(new Event(FEEDBACK_ERROR_EVENT));
                throw new Error('Feedback identity could not be refreshed.');
            }
        })();
        loading = { userId, revision, promise };
        identityLoading = loading;
    }
    try {
        await loading.promise;
    } catch (error) {
        if (options.throwOnError) throw error;
    } finally {
        if (identityLoading === loading) identityLoading = null;
    }
}

export async function initChorala(): Promise<void> {
    await loadChoralaWidget();
    await syncChoralaIdentity();
}

/** @deprecated Use syncChoralaIdentity() */
export async function identifyChoralaUser(): Promise<void> {
    await syncChoralaIdentity();
}
