import { createSharedWebWorker, evoluWebDeps } from "@evolu/web";
import type { CreateDbWorker, DbWorkerInput, DbWorkerOutput } from "@evolu/common/local-first";

/** Keep the SDK shared-worker name and storage lock while using the public observable worker entry. */
const createDbWorker: CreateDbWorker = name => createSharedWebWorker<DbWorkerInput, DbWorkerOutput>(
    name,
    () => new Worker(new URL("./Db.worker.ts", import.meta.url), { type: "module" }),
);

const EVOLU_ALLOW_PAGE_RELOAD_KEY = "satflux.evolu.allow_page_reload.v1";

/** Call immediately before an intentional `evolu.reloadApp()` (Profile restore, server migration). */
export function allowEvoluPageReload(): void {
    sessionStorage.setItem(EVOLU_ALLOW_PAGE_RELOAD_KEY, "1");
}

/**
 * Evolu worker `onReset` with `reload: true` calls `reloadApp` directly.
 * Block that during automatic bootstrap so a failed WASM/owner bind cannot loop reloads.
 */
export function createEvoluWebDepsWithReloadGuard() {
    return {
        ...evoluWebDeps,
        createDbWorker,
        reloadApp: (url: string) => {
            if (sessionStorage.getItem(EVOLU_ALLOW_PAGE_RELOAD_KEY) !== "1") {
                if (import.meta.env.DEV) {
                    console.warn("[evolu] Blocked automatic page reload to", url);
                }
                return;
            }
            sessionStorage.removeItem(EVOLU_ALLOW_PAGE_RELOAD_KEY);
            evoluWebDeps.reloadApp(url);
        },
    };
}
