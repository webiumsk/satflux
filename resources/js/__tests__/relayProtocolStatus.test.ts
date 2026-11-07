import { describe, expect, it, vi, afterEach } from "vitest";
import { idBytesToId, IdBytes, NonNegativeInt } from "@evolu/common";
import { createProtocolMessageBuffer, MessageType, ProtocolErrorCode, SubscriptionFlags } from "@evolu/common/local-first";
import { createRelayProtocolObserver, MAX_OBSERVED_RELAY_OWNERS } from "../evolu/relayProtocolStatus";

const A = idBytesToId(IdBytes.orThrow(new Uint8Array(16).fill(1)));
const B = idBytesToId(IdBytes.orThrow(new Uint8Array(16).fill(2)));
/** Use the actual installed SDK encoder; no crypto, database or network is initialized. */
function request(owner = A, subscriptionFlag: 0 | 1 | 2 = SubscriptionFlags.Subscribe) {
    return createProtocolMessageBuffer(owner as never, { messageType: MessageType.Request, subscriptionFlag }).unwrap();
}
/** Return an isolated inbound frame exactly as the SDK's ArrayBuffer socket callback receives it. */
function response(owner = A, errorCode: 0 | 1 | 2 | 3 | 4 = ProtocolErrorCode.NoError, version = 1): ArrayBuffer {
    return createProtocolMessageBuffer(owner as never, { messageType: MessageType.Response, errorCode, version: NonNegativeInt.orThrow(version) }).unwrap().slice().buffer as ArrayBuffer;
}
afterEach(() => { vi.restoreAllMocks(); });

describe("worker-local relay protocol evidence", () => {
    it("retains SDK quota until a fresh request and complete empty success for that same owner", () => {
        const observer = createRelayProtocolObserver();
        observer.sent(request()); observer.sent(request(B));
        observer.received(response(A, ProtocolErrorCode.QuotaError));
        expect(observer.snapshot().state).toBe("quota_exceeded");
        observer.received(response(B));
        expect(observer.snapshot().state).toBe("quota_exceeded");
        observer.received(response()); // No fresh request after quota.
        expect(observer.snapshot().state).toBe("quota_exceeded");
        observer.sent(request());
        observer.received(response());
        expect(observer.snapshot().state).toBe("response_received");
    });

    it("does not let an unknown owner mask another owner's observed response at the same timestamp", () => {
        vi.spyOn(Date, 'now').mockReturnValue(1000);
        const observer = createRelayProtocolObserver(); observer.sent(request()); observer.sent(request(B));
        observer.received(response());
        expect(observer.snapshot().state).toBe('response_received');
        observer.received(response(B, ProtocolErrorCode.QuotaError));
        observer.received(new Uint8Array([1]).buffer);
        expect(observer.snapshot().state).toBe('quota_exceeded');
    });

    it("does not treat broadcasts, header-only success or nonempty responses as quota recovery", () => {
        const observer = createRelayProtocolObserver(); observer.sent(request());
        observer.received(response(A, ProtocolErrorCode.QuotaError)); observer.sent(request());
        const broadcast = createProtocolMessageBuffer(A as never, { messageType: MessageType.Broadcast }).unwrap();
        observer.received(broadcast.slice().buffer as ArrayBuffer);
        expect(observer.snapshot().state).toBe("quota_exceeded");
        // A nonempty body is never decoded a second time by observation.
        const nonempty = new Uint8Array(response()); nonempty[nonempty.length - 1] = 1;
        observer.received(nonempty.buffer);
        expect(observer.snapshot().state).toBe("quota_exceeded");
        const truncated = response().slice(0, -1);
        observer.received(truncated);
        expect(observer.snapshot().state).toBe("quota_exceeded");
        observer.received(response()); // Malformed response consumed the current recovery request.
        expect(observer.snapshot().state).toBe("quota_exceeded");
        observer.sent(request()); observer.received(response());
        expect(observer.snapshot().state).toBe("response_received");
    });

    it("keeps truly ownerless malformed responses sticky until the socket observer is retired", () => {
        const observer = createRelayProtocolObserver(); observer.sent(request());
        observer.received(new Uint8Array([1, 2]).buffer);
        observer.sent(request()); observer.received(response());
        expect(observer.snapshot().state).toBe("invalid_message");
    });

    it.each([[ProtocolErrorCode.WriteKeyError, "write_key_rejected"], [ProtocolErrorCode.WriteError, "write_failed"], [ProtocolErrorCode.SyncError, "sync_failed"]] as const)("classifies SDK error %s", (code, expected) => {
        const observer = createRelayProtocolObserver(); observer.sent(request()); observer.received(response(A, code));
        expect(observer.snapshot().state).toBe(expected);
    });

    it("recognizes SDK requests with write keys without retaining or publishing authentication bytes", () => {
        const observer = createRelayProtocolObserver();
        const encoded = createProtocolMessageBuffer(A as never, { messageType: MessageType.Request, writeKey: new Uint8Array(16).fill(9) as never, subscriptionFlag: SubscriptionFlags.Subscribe }).unwrap();
        const padded = new Uint8Array(encoded.length + 4); padded.set(encoded, 2);
        observer.sent(padded.subarray(2, encoded.length + 2));
        observer.received(response(A, ProtocolErrorCode.QuotaError));
        expect(observer.snapshot().state).toBe('quota_exceeded');
        expect(Object.keys(observer.snapshot()).sort()).toEqual(['observedAt', 'state']);
        observer.dispose();
        expect(observer.snapshot().state).toBe('unknown');
    });

    it("reports incompatible versions without claiming recovery and ignores unsolicited owners", () => {
        const observer = createRelayProtocolObserver(); observer.sent(request());
        observer.received(response(B, ProtocolErrorCode.QuotaError));
        expect(observer.snapshot().state).toBe("unknown");
        observer.received(response(A, ProtocolErrorCode.NoError, 2));
        expect(observer.snapshot().state).toBe("version_mismatch");
        observer.sent(request()); observer.received(response(A, ProtocolErrorCode.NoError, 8));
        expect(observer.snapshot().state).toBe("version_mismatch");
        observer.sent(request()); observer.received(response());
        expect(observer.snapshot().state).toBe("response_received");
    });

    it("retires unsubscribed owners and fails conservatively rather than evicting a quota at its owner bound", () => {
        const observer = createRelayProtocolObserver(); observer.sent(request()); observer.received(response(A, ProtocolErrorCode.QuotaError));
        observer.sent(request(A, SubscriptionFlags.Unsubscribe)); observer.received(response(A, ProtocolErrorCode.QuotaError));
        expect(observer.snapshot().state).toBe("unknown");
        observer.sent(request()); observer.received(response(A, ProtocolErrorCode.QuotaError));
        for (let index = 2; index <= MAX_OBSERVED_RELAY_OWNERS + 1; index++) observer.sent(request(idBytesToId(IdBytes.orThrow(new Uint8Array(16).fill(index)))));
        expect(observer.snapshot().state).toBe("quota_exceeded");
        observer.sent(request()); observer.received(response());
        expect(observer.snapshot().state).toBe("invalid_message");
    });

    it("never copies a whole oversized payload or reads beyond its bounded prefix", () => {
        const observer = createRelayProtocolObserver(); observer.sent(request());
        const frame = new Uint8Array(2_000_000); frame.set(new Uint8Array(response(A, ProtocolErrorCode.QuotaError)));
        observer.received(frame.buffer);
        expect(observer.snapshot().state).toBe("quota_exceeded");
        observer.sent(request()); frame.set(new Uint8Array(response())); observer.received(frame.buffer);
        expect(observer.snapshot().state).toBe("quota_exceeded");
        expect(Object.keys(observer.snapshot()).sort()).toEqual(["observedAt", "state"]);
    });
});
