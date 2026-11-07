import { createBuffer, idBytesToId, idBytesTypeValueLength, NonNegativeInt, type IdBytes } from "@evolu/common";
import { decodeNonNegativeInt, MessageType, ProtocolErrorCode, protocolVersion, SubscriptionFlags } from "@evolu/common/local-first";

export type RelayProtocolState = "unknown" | "response_received" | "quota_exceeded" | "write_key_rejected" | "write_failed" | "sync_failed" | "version_mismatch" | "invalid_message";
export type RelayProtocolObservation = Readonly<{ state: RelayProtocolState; observedAt: number }>;
export type RelayProtocols = Readonly<Record<string, RelayProtocolObservation>>;
export const MAX_OBSERVED_RELAY_OWNERS = 64;
const PREFIX_SIZE = 64;
const WRITE_KEY_SIZE = NonNegativeInt.orThrow(16);
const ERROR_STATES = ["quota_exceeded", "write_key_rejected", "write_failed", "sync_failed", "version_mismatch", "invalid_message"];

/** Classify only public enums; no SDK error payload is suitable for broadcasting. */
export function isRelayProtocolError(state: RelayProtocolState): boolean { return ERROR_STATES.includes(state); }

/** Retain actionable errors across owners; an unobserved owner cannot mask an observed response. */
export function preferredRelayProtocolObservation(current: RelayProtocolObservation, candidate: RelayProtocolObservation): RelayProtocolObservation {
    /** Higher ranks preserve unresolved errors without implying successful synchronization. */
    const rank = (state: RelayProtocolState): number => state === "quota_exceeded" ? 3 : isRelayProtocolError(state) ? 2 : state === "response_received" ? 1 : 0;
    const currentRank = rank(current.state);
    const candidateRank = rank(candidate.state);
    return candidateRank > currentRank || candidateRank === currentRank && candidate.observedAt >= current.observedAt ? candidate : current;
}

/** Validate the exact bounded public observation received from another same-origin context. */
export function validRelayProtocolObservation(value: unknown): value is RelayProtocolObservation {
    if (!value || typeof value !== "object") return false;
    const row = value as RelayProtocolObservation;
    return Object.keys(value).length === 2 && Object.keys(value).every(key => key === "state" || key === "observedAt")
        && ["unknown", "response_received", ...ERROR_STATES].includes(row.state)
        && Number.isSafeInteger(row.observedAt) && row.observedAt >= 0;
}

/** Inspect a bounded protocol prefix; never decode encrypted changes or retain key/payload bytes. */
function header(data: unknown) {
    const bytes = data instanceof ArrayBuffer ? new Uint8Array(data)
        : ArrayBuffer.isView(data) ? new Uint8Array(data.buffer, data.byteOffset, data.byteLength) : null;
    if (!bytes) return null;
    const input = createBuffer(bytes.subarray(0, PREFIX_SIZE));
    const version = decodeNonNegativeInt(input);
    const owner = idBytesToId(input.shiftN(idBytesTypeValueLength) as IdBytes);
    return { input, owner, version, length: bytes.length, canonicalVersion: bytes[0] === protocolVersion };
}

/** Keep owner association worker-local and retire it when the SDK unsubscribes or disposes its socket. */
export function createRelayProtocolObserver() {
    const owners = new Map<string, { request: number; errorRequest: number; observation: RelayProtocolObservation }>();
    let unattributed: RelayProtocolObservation | undefined;
    /** Record only a sanitized enum and public wall-clock timestamp. */
    const observation = (state: RelayProtocolState): RelayProtocolObservation => ({ state, observedAt: Date.now() });
    return {
        /** Observe only successful SDK sends; a failed request cannot authorize a later response. */
        sent(data: unknown): void {
            try {
                const parsed = header(data);
                if (!parsed || parsed.version !== protocolVersion || !parsed.canonicalVersion || parsed.input.shift() !== MessageType.Request) return;
                const hasKey = parsed.input.shift();
                if (hasKey !== 0 && hasKey !== 1) return;
                if (hasKey) parsed.input.shiftN(WRITE_KEY_SIZE); // Skip authentication material without retaining it.
                const subscription = parsed.input.shift();
                if (subscription === SubscriptionFlags.Unsubscribe) { owners.delete(parsed.owner); return; }
                if (subscription !== SubscriptionFlags.Subscribe && subscription !== SubscriptionFlags.None) return;
                const current = owners.get(parsed.owner);
                if (current) current.request++;
                else if (owners.size < MAX_OBSERVED_RELAY_OWNERS) owners.set(parsed.owner, { request: 1, errorRequest: 0, observation: observation("unknown") });
                else unattributed = observation("invalid_message"); // Never evict a retained quota to make a route look healthy.
            } catch { /* Observation cannot affect the SDK send result. */ }
        },
        /** A response acknowledges only its own owner; it never proves a complete remote backup. */
        received(data: unknown): void {
            if (!(data instanceof ArrayBuffer)) return; // Match the installed SDK's accepted inbound representation.
            let current: ReturnType<typeof owners.get>;
            try {
                const parsed = header(data);
                if (!parsed) return;
                current = owners.get(parsed.owner);
                if (!current) return; // Unsolicited owners neither allocate memory nor clear another owner's error.
                let state: RelayProtocolState | undefined;
                if (parsed.version !== protocolVersion || !parsed.canonicalVersion) state = "version_mismatch";
                else {
                    const messageType = parsed.input.shift();
                    if (messageType === MessageType.Broadcast) return;
                    if (messageType !== MessageType.Response) state = "invalid_message";
                    else {
                        const code = parsed.input.shift();
                        const errors: Record<number, RelayProtocolState> = {
                            [ProtocolErrorCode.WriteKeyError]: "write_key_rejected", [ProtocolErrorCode.WriteError]: "write_failed",
                            [ProtocolErrorCode.QuotaError]: "quota_exceeded", [ProtocolErrorCode.SyncError]: "sync_failed",
                        };
                        if (code !== ProtocolErrorCode.NoError) state = errors[code] ?? "invalid_message";
                        // Only the canonical complete empty response can be validated without decoding encrypted data/ranges.
                        else if (decodeNonNegativeInt(parsed.input) === 0 && parsed.input.getLength() === 0 && parsed.length <= PREFIX_SIZE
                            && current.request > current.errorRequest) state = "response_received";
                    }
                }
                if (!state) return;
                if (current.observation.state !== "quota_exceeded" || state === "response_received") current.observation = observation(state);
                if (isRelayProtocolError(state)) current.errorRequest = current.request;
            } catch {
                if (current) {
                    if (current.observation.state !== "quota_exceeded") current.observation = observation("invalid_message");
                    current.errorRequest = current.request;
                } else unattributed = observation("invalid_message");
            }
        },
        /** Release worker-local owner associations when their observed socket is retired. */
        dispose(): void { owners.clear(); unattributed = undefined; },
        /** Prefer unresolved errors across owners, even when another owner has a newer successful response. */
        snapshot(): RelayProtocolObservation {
            let result: RelayProtocolObservation = unattributed ?? { state: "unknown", observedAt: 0 };
            for (const owner of owners.values()) result = preferredRelayProtocolObservation(result, owner.observation);
            return result;
        },
    };
}
