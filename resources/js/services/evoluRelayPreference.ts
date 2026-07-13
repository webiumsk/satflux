import { useAuthStore } from "@/store/auth";
import {
    getEvoluRelayBuildInfo,
    normalizeEvoluRelayBaseUrl,
    resolveEvoluRelayUrl,
    type EvoluRelayRuntimeInfo,
} from "@/evolu/config";
import { readRelayOverride } from "@/evolu/relayOverrideStorage";

/**
 * Active relay URL. Ladder (P2 phase 1): device-level "sync off" override ->
 * profile override -> cached profile (pre-auth) -> build env -> default.
 */
export function getEvoluRelayRuntimeInfo(): EvoluRelayRuntimeInfo {
    const override = readRelayOverride();
    if (override.kind === "disabled") {
        return {
            url: "",
            enabled: false,
            source: "disabled",
            build: getEvoluRelayBuildInfo(),
        };
    }
    const auth = useAuthStore();
    const profile =
        auth.user?.evolu_relay_url
        ?? (override.kind === "url" ? override.url : null);
    return resolveEvoluRelayUrl(profile);
}

export function getResolvedEvoluRelayUrl(): string {
    const { url, enabled } = getEvoluRelayRuntimeInfo();
    return enabled ? normalizeEvoluRelayBaseUrl(url) : "";
}

export function isEvoluRelayConfiguredAtRuntime(): boolean {
    return getResolvedEvoluRelayUrl().length > 0;
}
