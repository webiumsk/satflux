import { useAuthStore } from "@/store/auth";
import {
    getEvoluRelayBuildInfo,
    normalizeEvoluRelayBaseUrl,
    resolveEvoluRelayUrl,
    resolveEvoluRelayUrls,
    resolveSelectedEvoluRelayUrls,
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
    if (auth.user?.evolu_relays != null || (!auth.user && override.kind === "relays")) {
        const urls = auth.user ? resolveSelectedEvoluRelayUrls(auth.user)
            : resolveSelectedEvoluRelayUrls({ evolu_relays: override.kind === "relays" ? override.relays : [] });
        return {
            url: urls[0] ?? "", enabled: urls.length > 0,
            source: urls.length > 0 ? "profile" : "disabled", build: getEvoluRelayBuildInfo(),
        };
    }
    return resolveEvoluRelayUrl(auth.user
        ? auth.user.evolu_relay_url : (override.kind === "url" ? override.url : null));
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
    if (auth.user) return resolveSelectedEvoluRelayUrls(auth.user);
    if (override.kind === "relays") return resolveSelectedEvoluRelayUrls({ evolu_relays: override.relays });
    return resolveEvoluRelayUrls(
        override.kind === "url" ? override.url : null,
        override.kind === "url" && override.addDefault === true,
    );
}
