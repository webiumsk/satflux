import { ref, type Ref } from "vue";

/**
 * Shared browser online/offline flag (P1 phase 4). Module singleton: one
 * pair of window listeners, every consumer shares the same ref.
 */
const online: Ref<boolean> = ref(typeof navigator === "undefined" ? true : navigator.onLine !== false);
let wired = false;

export function useOnlineStatus(): Ref<boolean> {
    if (!wired && typeof window !== "undefined") {
        wired = true;
        window.addEventListener("online", () => {
            online.value = true;
        });
        window.addEventListener("offline", () => {
            online.value = false;
        });
    }
    return online;
}
