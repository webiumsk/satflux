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
    }
}

let loading: Promise<TurnstileApi> | null = null;

/** Load only the fixed registration challenge script, sharing concurrent loads. */
export function loadTurnstile(): Promise<TurnstileApi> {
    if (loading) return loading;

    const pending = new Promise<TurnstileApi>((resolve, reject) => {
        let script: HTMLScriptElement | null = null;
        let settled = false;

        const cleanup = () => {
            window.clearTimeout(timeout);
            if (script) {
                script.onload = null;
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
        const waitForReady = (api: TurnstileApi) => {
            try {
                api.ready(() => {
                    if (settled) return;
                    settled = true;
                    cleanup();
                    resolve(api);
                });
            } catch {
                fail();
            }
        };
        if (window.turnstile) {
            waitForReady(window.turnstile);
            return;
        }
        script = document.createElement("script");
        script.src = "https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit";
        script.async = true;
        script.defer = true;
        script.dataset.satfluxTurnstile = "true";
        script.onerror = fail;
        script.onload = () => {
            if (window.turnstile) waitForReady(window.turnstile);
            else fail();
        };
        document.head.appendChild(script);
    });
    loading = pending;
    const release = () => { if (loading === pending) loading = null; };
    void pending.then(release, release);
    return pending;
}
