import { useAuthStore } from "@/store/auth";
import {
    getEvoluRelayBuildInfo,
    normalizeEvoluRelayBaseUrl,
    resolveEvoluRelayUrl,
    resolveEvoluRelayUrls,
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
    const profile = auth.user
        ? auth.user.evolu_relay_url
        : (override.kind === "url" ? override.url : null);
    return resolveEvoluRelayUrl(profile);
}

export function getResolvedEvoluRelayUrl(): string {
    const { url, enabled } = getEvoluRelayRuntimeInfo();
    return enabled ? normalizeEvoluRelayBaseUrl(url) : "";
}

export function isEvoluRelayConfiguredAtRuntime(): boolean {
    return getResolvedEvoluRelayUrl().length > 0;
}

/** All selected relays; legacy URL consumers continue to describe the first relay. */
export function getResolvedEvoluRelayUrls(): string[] {
    const override = readRelayOverride();
    if (override.kind === "disabled") return [];
    const auth = useAuthStore();
    return auth.user
        ? resolveEvoluRelayUrls(auth.user.evolu_relay_url, auth.user.evolu_relay_add_default === true)
        : resolveEvoluRelayUrls(
            override.kind === "url" ? override.url : null,
            override.kind === "url" && override.addDefault === true,
        );
}
