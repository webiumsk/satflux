import api from './api';
import i18n from '../i18n';
import { useAuthStore } from '../store/auth';

type ChoralaWidgetSettings = {
    theme?: 'light' | 'dark';
    primaryColor?: string;
    position?: 'bottom-left' | 'bottom-right';
    mode?: 'floating' | 'inline' | 'manual';
};

declare global {
    interface Window {
        Chorala?: ((command: string, ...args: unknown[]) => void) & { q?: unknown[][] };
    }
}

let choralaLoaded = false;
let choralaScriptLoading: Promise<void> | null = null;
let lastSyncedUserId: string | null = null;
let widgetSettingsPromise: Promise<ChoralaWidgetSettings> | null = null;

function getSatfluxWidgetOverrides(): ChoralaWidgetSettings {
    return {
        position: 'bottom-left',
        mode: 'manual',
    };
}

async function fetchWidgetSettings(): Promise<ChoralaWidgetSettings> {
    if (!widgetSettingsPromise) {
        widgetSettingsPromise = api.get<{ settings: ChoralaWidgetSettings }>('/chorala/widget-settings')
            .then((response) => ({
                ...response.data.settings,
                ...getSatfluxWidgetOverrides(),
            }))
            .catch(() => getSatfluxWidgetOverrides());
    }

    return widgetSettingsPromise;
}

function getChoralaConfig(): { key: string; widgetUrl: string } | null {
    const key = document.querySelector('meta[name="satflux-chorala-key"]')?.getAttribute('content')?.trim();
    const widgetUrl = document.querySelector('meta[name="satflux-chorala-widget-url"]')?.getAttribute('content')?.trim()
        || 'https://chorala.com';

    if (!key) {
        return null;
    }

    return { key, widgetUrl: widgetUrl.replace(/\/$/, '') };
}

function shouldUseChoralaProxy(): boolean {
    const meta = document.querySelector('meta[name="satflux-chorala-use-proxy"]')?.getAttribute('content')?.trim();
    if (meta === 'true') {
        return true;
    }
    if (meta === 'false') {
        return false;
    }

    const host = window.location.hostname;
    return host === 'localhost' || host === '127.0.0.1';
}

function getChoralaApiUrl(): string | undefined {
    if (!shouldUseChoralaProxy()) {
        return undefined;
    }

    return `${window.location.origin}/api/chorala-proxy/v1`;
}

function getAppVersion(): string | undefined {
    const fromEnv = import.meta.env.VITE_APP_VERSION;
    if (typeof fromEnv === 'string' && fromEnv.trim() !== '') {
        return fromEnv.trim();
    }

    return undefined;
}

async function queueChoralaInit(config: { key: string }): Promise<void> {
    const appVersion = getAppVersion();
    const apiUrl = getChoralaApiUrl();
    const settings = await fetchWidgetSettings();

    const initOptions = {
        projectKey: config.key,
        locale: i18n.global.locale.value,
        settings,
        ...(appVersion ? { appVersion } : {}),
        ...(apiUrl ? { apiUrl } : {}),
    };

    if (typeof window.Chorala !== 'function') {
        const queue: unknown[][] = [];
        window.Chorala = (...args: unknown[]) => {
            queue.push(args);
        };
        window.Chorala.q = queue;
    }

    window.Chorala('init', initOptions);
}

function clearChoralaIdentity(): void {
    if (!choralaLoaded || typeof window.Chorala !== 'function') {
        return;
    }

    window.Chorala('identify', {});
}

function loadChoralaScript(config: { key: string; widgetUrl: string }): Promise<void> {
    if (choralaLoaded) {
        return Promise.resolve();
    }

    if (choralaScriptLoading) {
        return choralaScriptLoading;
    }

    choralaScriptLoading = new Promise((resolve, reject) => {
        void queueChoralaInit(config)
            .then(() => {
                const script = document.createElement('script');
                script.async = true;
                script.src = `${config.widgetUrl}/widget.js`;
                script.setAttribute('data-chorala-key', config.key);

                script.onload = () => {
                    choralaLoaded = true;
                    resolve();
                };
                script.onerror = () => {
                    choralaScriptLoading = null;
                    reject(new Error('Failed to load Chorala widget script'));
                };

                document.head.appendChild(script);
            })
            .catch((error) => {
                choralaScriptLoading = null;
                reject(error instanceof Error ? error : new Error('Failed to initialize Chorala widget'));
            });
    });

    return choralaScriptLoading;
}

export function isChoralaConfigured(): boolean {
    return getChoralaConfig() !== null;
}

export async function openChoralaWidget(): Promise<void> {
    if (!isChoralaConfigured()) {
        return;
    }

    const config = getChoralaConfig();
    if (!config) {
        return;
    }

    await loadChoralaScript(config);

    if (typeof window.Chorala === 'function') {
        window.Chorala('open');
    }
}

export async function loadChoralaWidget(): Promise<void> {
    const config = getChoralaConfig();
    if (!config) {
        return;
    }

    try {
        await loadChoralaScript(config);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn('[chorala] Widget script failed to load.', error);
        }
    }
}

/** Sync Chorala SSO once per user id. Never embeds JWT in the initial widget init. */
export async function syncChoralaIdentity(): Promise<void> {
    const config = getChoralaConfig();
    if (!config) {
        return;
    }

    const authStore = useAuthStore();
    const userId = authStore.user ? String(authStore.user.id) : null;

    if (userId === lastSyncedUserId) {
        return;
    }

    if (!userId) {
        lastSyncedUserId = null;
        clearChoralaIdentity();
        return;
    }

    await loadChoralaWidget();

    try {
        const response = await api.get<{ jwt: string }>('/chorala/widget-token');
        const jwt = response.data?.jwt?.trim();
        if (!jwt) {
            lastSyncedUserId = null;
            clearChoralaIdentity();
            return;
        }

        if (typeof window.Chorala === 'function') {
            window.Chorala('identify', { jwt });
            lastSyncedUserId = userId;
        }
    } catch {
        lastSyncedUserId = null;
        clearChoralaIdentity();
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
