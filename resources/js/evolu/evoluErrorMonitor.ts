import { ref, type Ref } from "vue";
import type { Evolu } from "@evolu/common/local-first";
import type { InvoicingLocalSchema } from "./schema";

/**
 * Single global wiring of evolu.subscribeError (P1 phase 4). The latest
 * unhandled Evolu error is exposed as a reactive ref for the sync state
 * (phase 4) and storage-quota classification (phase 5). Never logs error
 * payloads - they may reference row content.
 */

const lastEvoluError: Ref<string | null> = ref(null);
let subscribed = false;

export function evoluErrorRef(): Ref<string | null> {
    return lastEvoluError;
}

export function ensureEvoluErrorMonitor(evolu: Evolu<InvoicingLocalSchema>): void {
    if (subscribed) return;
    subscribed = true;
    try {
        evolu.subscribeError(() => {
            const error = evolu.getError();
            lastEvoluError.value = error ? describeEvoluError(error) : null;
        });
        const initial = evolu.getError();
        if (initial) {
            lastEvoluError.value = describeEvoluError(initial);
        }
    } catch {
        subscribed = false;
    }
}

/** Short machine-ish description - type/name only, never row content. */
function describeEvoluError(error: unknown): string {
    if (typeof error === "object" && error !== null) {
        const typed = error as { type?: unknown; name?: unknown };
        if (typeof typed.type === "string") return typed.type;
        if (typeof typed.name === "string") return typed.name;
    }
    return "unknown_error";
}

/** True when the error looks like a storage-quota failure (phase 5 banner). */
export function isQuotaLikeEvoluError(description: string | null): boolean {
    if (!description) return false;
    return /quota|storage|disk/i.test(description);
}

/** Test hook. */
export function resetEvoluErrorMonitorForTests(): void {
    subscribed = false;
    lastEvoluError.value = null;
}
