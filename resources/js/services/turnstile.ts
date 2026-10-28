export interface TurnstileOptions {
    sitekey: string;
    action: "register";
    theme: "dark";
    size: "compact";
    language: string;
    retry: "never";
    "refresh-expired": "manual";
    "refresh-timeout": "manual";
    callback: (token: string) => void;
    "expired-callback": () => void;
    "error-callback": () => boolean;
    "timeout-callback": () => void;
}

export interface TurnstileApi {
    render: (container: HTMLElement, options: TurnstileOptions) => string;
    remove: (widgetId: string) => void;
    ready: (callback: () => void) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
        [callbackName: `satfluxTurnstileLoaded${number}`]: (() => void) | undefined;
    }
}

let loading: Promise<TurnstileApi> | null = null;
let loadedApi: TurnstileApi | null = null;
let loadAttempt = 0;

/** Load only the fixed registration challenge script, sharing concurrent loads. */
export function loadTurnstile(): Promise<TurnstileApi> {
    if (loading) return loading;
    if (loadedApi && window.turnstile === loadedApi) return Promise.resolve(loadedApi);

    const pending = new Promise<TurnstileApi>((resolve, reject) => {
        let script: HTMLScriptElement | null = null;
        let settled = false;
        let callbackName: `satfluxTurnstileLoaded${number}`;
        do { callbackName = `satfluxTurnstileLoaded${++loadAttempt}`; } while (callbackName in window);

        const cleanup = () => {
            window.clearTimeout(timeout);
            if (window[callbackName] === onSdkLoad) delete window[callbackName];
            if (script) {
                script.onerror = null;
            }
        };
        const fail = () => {
            if (settled) return;
            settled = true;
            cleanup();
            script?.remove();
            reject(new Error("Registration verification is unavailable."));
        };
        const timeout = window.setTimeout(fail, 15_000);
        // Async SDKs reject ready(); the documented onload query callback signals readiness.
        const onSdkLoad = () => {
            if (settled) return;
            const api = window.turnstile;
            if (!api || typeof api.render !== "function" || typeof api.remove !== "function") {
                fail();
                return;
            }
            settled = true;
            loadedApi = api;
            cleanup();
            resolve(api);
        };
        window[callbackName] = onSdkLoad;
        script = document.createElement("script");
        script.src = `https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=${callbackName}`;
        script.async = true;
        script.defer = true;
        script.dataset.satfluxTurnstile = "true";
        script.onerror = fail;
        document.head.appendChild(script);
    });
    loading = pending;
    const release = () => { if (loading === pending) loading = null; };
    void pending.then(release, release);
    return pending;
}
