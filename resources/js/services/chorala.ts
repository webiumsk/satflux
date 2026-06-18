import api from './api';
import i18n from '../i18n';
import { useAuthStore } from '../store/auth';

declare global {
    interface Window {
        Chorala?: ((command: string, ...args: unknown[]) => void) & { q?: unknown[][] };
        choralaSettings?: {
            projectKey?: string;
            appVersion?: string;
            locale?: string;
            apiUrl?: string;
            user?: { jwt?: string };
        };
    }
}

let choralaLoaded = false;
let choralaScriptLoading: Promise<void> | null = null;

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

function buildInitOptions(config: { key: string }, jwt?: string) {
    const appVersion = getAppVersion();
    const apiUrl = getChoralaApiUrl();

    return {
        projectKey: config.key,
        locale: i18n.global.locale.value,
        ...(appVersion ? { appVersion } : {}),
        ...(apiUrl ? { apiUrl } : {}),
        ...(jwt ? { user: { jwt } } : {}),
    };
}

function queueChoralaInit(config: { key: string }, jwt?: string): void {
    const initOptions = buildInitOptions(config, jwt);

    if (typeof window.Chorala !== 'function') {
        const queue: unknown[][] = [];
        window.Chorala = (...args: unknown[]) => {
            queue.push(args);
        };
        window.Chorala.q = queue;
    }

    window.Chorala('init', initOptions);
}

function loadChoralaScript(config: { key: string; widgetUrl: string }, jwt?: string): Promise<void> {
    if (choralaLoaded) {
        return Promise.resolve();
    }

    if (choralaScriptLoading) {
        return choralaScriptLoading;
    }

    choralaScriptLoading = new Promise((resolve, reject) => {
        queueChoralaInit(config, jwt);

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
    });

    return choralaScriptLoading;
}

export async function loadChoralaWidget(jwt?: string): Promise<void> {
    const config = getChoralaConfig();
    if (!config) {
        return;
    }

    try {
        await loadChoralaScript(config, jwt);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn('[chorala] Widget script failed to load.', error);
        }
    }
}

export async function identifyChoralaUser(): Promise<void> {
    const config = getChoralaConfig();
    if (!config) {
        return;
    }

    const authStore = useAuthStore();
    if (!authStore.user) {
        return;
    }

    try {
        const response = await api.get<{ jwt: string }>('/chorala/widget-token');
        const jwt = response.data?.jwt;
        if (!jwt) {
            return;
        }

        if (choralaLoaded && typeof window.Chorala === 'function') {
            window.Chorala('identify', { jwt });
            return;
        }

        await loadChoralaWidget(jwt);
    } catch (error) {
        if (import.meta.env.DEV) {
            console.warn('[chorala] SSO identify failed; loading anonymous widget.', error);
        }
        if (!choralaLoaded) {
            await loadChoralaWidget();
        }
    }
}

export async function initChorala(): Promise<void> {
    const authStore = useAuthStore();
    if (authStore.user) {
        await identifyChoralaUser();
        return;
    }

    await loadChoralaWidget();
}
