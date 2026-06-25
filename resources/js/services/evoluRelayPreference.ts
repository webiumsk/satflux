import { useAuthStore } from "@/store/auth";
import {
    normalizeEvoluRelayBaseUrl,
    resolveEvoluRelayUrl,
    type EvoluRelayRuntimeInfo,
} from "@/evolu/config";

/** Active relay URL (profile override wins over build default). */
export function getEvoluRelayRuntimeInfo(): EvoluRelayRuntimeInfo {
    const auth = useAuthStore();
    return resolveEvoluRelayUrl(auth.user?.evolu_relay_url ?? null);
}

export function getResolvedEvoluRelayUrl(): string {
    const { url, enabled } = getEvoluRelayRuntimeInfo();
    return enabled ? normalizeEvoluRelayBaseUrl(url) : "";
}

export function isEvoluRelayConfiguredAtRuntime(): boolean {
    return getResolvedEvoluRelayUrl().length > 0;
}
