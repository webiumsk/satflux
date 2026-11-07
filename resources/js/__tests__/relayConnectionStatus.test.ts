import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { defineComponent } from "vue";
import { createRandomBytes, err, idBytesToId, IdBytes, ok, PositiveInt, type CreateWebSocket, type WebSocketOptions, type WebSocketReadyState } from "@evolu/common";
import { createAppOwner, createOwnerSecret, createOwnerWebSocketTransport, createProtocolMessageBuffer, MessageType, ProtocolErrorCode } from "@evolu/common/local-first";
import {
    RELAY_CONNECTION_HEARTBEAT_MS,
    RELAY_CONNECTION_STALE_MS,
    parseRelayConnectionSnapshot,
    type RelayConnectionChannel,
    type RelayConnectionSnapshot,
    type RelayConnections,
} from "../evolu/relayConnectionStatus";
import { createObservedRelayWebSockets } from "../evolu/relayConnectionStatusWorker";
import type { RelayProtocols } from "../evolu/relayProtocolStatus";
import { subscribeRelayConnections } from "../evolu/relayConnectionStatusClient";
import { useEvoluRelayConnections } from "../composables/useEvoluRelayConnections";

const A = "wss://relay-a.example.test";
const B = "wss://relay-b.example.test";

/** In-memory same-origin channel; tests never open a network socket. */
class Channel implements RelayConnectionChannel {
    static all = new Set<Channel>();
    onmessage: ((this: BroadcastChannel, ev: MessageEvent) => unknown) | null = null;
    messages: unknown[] = [];
    constructor() { Channel.all.add(this); }
    /** Deliver cloned test messages to other live channels, matching browser channel isolation. */
    postMessage(message: unknown) {
        this.messages.push(message);
        for (const other of Channel.all) {
            if (other !== this) other.onmessage?.call(other as unknown as BroadcastChannel, { data: structuredClone(message) } as MessageEvent);
        }
    }
    close() { Channel.all.delete(this); }
}

/** Controllable SDK-shaped handles preserve all wrapper forwarding assertions. */
function sockets() {
    const handles: { options: WebSocketOptions; ready: WebSocketReadyState; send: ReturnType<typeof vi.fn>; dispose: ReturnType<typeof vi.fn> }[] = [];
    const create = vi.fn<CreateWebSocket>((_url, options = {}) => {
        const handle = { options, ready: "connecting" as WebSocketReadyState, send: vi.fn(() => ok()), dispose: vi.fn() };
        handles.push(handle);
        return {
            send: handle.send,
            getReadyState: () => handle.ready,
            isOpen: () => handle.ready === "open",
            [Symbol.dispose]: handle.dispose,
        };
    });
    return { create, handles };
}

beforeEach(() => { vi.useFakeTimers(); vi.setSystemTime(1_000); Channel.all.clear(); });
afterEach(() => { vi.useRealTimers(); vi.restoreAllMocks(); vi.unstubAllGlobals(); Channel.all.clear(); });

describe("observed Evolu worker sockets", () => {
    it("observes actual SDK owner transport URLs while redacting owner query data", () => {
        const sdk = sockets();
        const channel = new Channel();
        const observer = createObservedRelayWebSockets(sdk.create, () => channel);
        let observed: RelayConnections = {};
        const subscription = subscribeRelayConnections(value => { observed = value; }, () => new Channel());
        const owner = createAppOwner(createOwnerSecret({ randomBytes: createRandomBytes() }));
        const transport = createOwnerWebSocketTransport({ url: A, ownerId: owner.id });
        const socket = observer.createWebSocket(transport.url);
        expect(observed).toEqual({ [A]: "connecting" });
        sdk.handles[0]!.ready = "open";
        sdk.handles[0]!.options.onOpen?.();
        expect(observed).toEqual({ [A]: "connected" });
        expect(JSON.stringify(channel.messages)).not.toContain("ownerId");
        expect(JSON.stringify(channel.messages)).not.toContain(String(owner.id));
        expect(sdk.create).toHaveBeenCalledWith(transport.url, expect.anything());
        socket[Symbol.dispose]();
        subscription.dispose();
        observer.dispose();
    });

    it("reports two actual connections and transparently forwards callbacks, options, data and disposal", () => {
        const sdk = sockets();
        const channel = new Channel();
        const observer = createObservedRelayWebSockets(sdk.create, () => channel);
        let observed: RelayConnections = {};
        const subscription = subscribeRelayConnections(value => { observed = value; }, () => new Channel());
        const onOpen = vi.fn();
        const onMessage = vi.fn();
        const onClose = vi.fn();
        const onError = vi.fn();
        const retryOptions = { retries: PositiveInt.orThrow(3) };
        const a = observer.createWebSocket(A, { binaryType: "arraybuffer", protocols: ["test"], retryOptions, onOpen, onMessage, onClose, onError });
        const b = observer.createWebSocket(B);
        expect(observed).toEqual({ [A]: "connecting", [B]: "connecting" });
        expect(sdk.create.mock.calls[0]?.[1]).toMatchObject({ binaryType: "arraybuffer", protocols: ["test"], retryOptions });
        sdk.handles[0]!.ready = "open";
        sdk.handles[0]!.options.onOpen?.();
        expect(onOpen).toHaveBeenCalledTimes(1);
        expect(observed[A]).toBe("connected");
        expect(a.isOpen()).toBe(true);
        expect(a.getReadyState()).toBe("open");
        const bytes = new Uint8Array([1, 2, 3]);
        a.send(bytes);
        expect(sdk.handles[0]!.send).toHaveBeenCalledWith(bytes);
        sdk.handles[0]!.options.onMessage?.("synthetic-message");
        expect(onMessage).toHaveBeenCalledWith("synthetic-message");
        const error = { type: "WebSocketConnectError" as const, event: new Event("error") };
        sdk.handles[0]!.options.onError?.(error);
        expect(onError).toHaveBeenCalledWith(error);
        expect(observed[A]).toBe("error");
        const close = new CloseEvent("close");
        sdk.handles[0]!.ready = "closed";
        sdk.handles[0]!.options.onClose?.(close);
        expect(onClose).toHaveBeenCalledWith(close);
        expect(observed[A]).toBe("disconnected");
        expect(JSON.stringify(channel.messages)).not.toContain("synthetic-message");
        a[Symbol.dispose]();
        a[Symbol.dispose]();
        expect(sdk.handles[0]!.dispose).toHaveBeenCalledTimes(1);
        expect(observed[A]).toBeUndefined();
        b[Symbol.dispose]();
        subscription.dispose();
        observer.dispose();
    });

    it("keeps route and owner quota evidence independent of open sockets, other owners and reconnection", () => {
        const sdk = sockets(); const channel = new Channel();
        const observer = createObservedRelayWebSockets(sdk.create, () => channel);
        let protocols: RelayProtocols = {};
        const subscription = subscribeRelayConnections((_connections, values) => { protocols = values; }, () => new Channel());
        const ownerA = idBytesToId(IdBytes.orThrow(new Uint8Array(16).fill(1)));
        const ownerB = idBytesToId(IdBytes.orThrow(new Uint8Array(16).fill(2)));
        const request = (owner: typeof ownerA) => createProtocolMessageBuffer(owner as never, { messageType: MessageType.Request }).unwrap();
        const response = (owner: typeof ownerA, errorCode: 0 | 3) => createProtocolMessageBuffer(owner as never, { messageType: MessageType.Response, errorCode }).unwrap().slice().buffer as ArrayBuffer;
        const a = observer.createWebSocket(A); const b = observer.createWebSocket(B);
        a.send(request(ownerA)); a.send(request(ownerB)); b.send(request(ownerA));
        sdk.handles[0]!.ready = 'open'; sdk.handles[0]!.options.onOpen?.();
        sdk.handles[0]!.options.onMessage?.(response(ownerA, ProtocolErrorCode.QuotaError));
        sdk.handles[0]!.options.onMessage?.(response(ownerB, ProtocolErrorCode.NoError));
        sdk.handles[1]!.options.onMessage?.(response(ownerA, ProtocolErrorCode.NoError));
        expect(protocols[A]?.state).toBe('quota_exceeded');
        expect(protocols[B]?.state).toBe('response_received');
        sdk.handles[0]!.options.onClose?.(new CloseEvent('close'));
        sdk.handles[0]!.options.onOpen?.(); vi.advanceTimersByTime(RELAY_CONNECTION_HEARTBEAT_MS);
        expect(protocols[A]?.state).toBe('quota_exceeded');
        a.send(request(ownerA)); sdk.handles[0]!.options.onMessage?.(response(ownerA, ProtocolErrorCode.NoError));
        expect(protocols[A]?.state).toBe('response_received');
        const publicMessages = JSON.stringify(channel.messages);
        expect(publicMessages.includes(String(ownerA)) || publicMessages.includes(String(ownerB)) || publicMessages.includes('ownerId')).toBe(false);
        expect(Object.keys(protocols[A]!).sort()).toEqual(['observedAt', 'state']);
        a[Symbol.dispose](); b[Symbol.dispose](); subscription.dispose(); observer.dispose();
    });

    it("forwards the identical inbound frame exactly once and cannot observe a failed send or disposed callback", () => {
        const sdk = sockets(); const observer = createObservedRelayWebSockets(sdk.create, () => new Channel());
        let protocols: RelayProtocols = {};
        const subscription = subscribeRelayConnections((_connections, values) => { protocols = values; }, () => new Channel());
        const onMessage = vi.fn(); const socket = observer.createWebSocket(A, { onMessage });
        const owner = idBytesToId(IdBytes.orThrow(new Uint8Array(16).fill(1)));
        const request = createProtocolMessageBuffer(owner as never, { messageType: MessageType.Request }).unwrap();
        const frame = createProtocolMessageBuffer(owner as never, { messageType: MessageType.Response, errorCode: ProtocolErrorCode.QuotaError }).unwrap().slice().buffer as ArrayBuffer;
        sdk.handles[0]!.send.mockReturnValueOnce(err({ type: 'WebSocketSendError' }));
        expect(socket.send(request).ok).toBe(false);
        sdk.handles[0]!.options.onMessage?.(frame);
        expect(protocols[A]?.state).toBe('unknown');
        expect(onMessage).toHaveBeenCalledTimes(1); expect(onMessage.mock.calls[0]?.[0] === frame).toBe(true);
        socket.send(request); sdk.handles[0]!.options.onMessage?.(frame);
        expect(protocols[A]?.state).toBe('quota_exceeded');
        socket[Symbol.dispose](); sdk.handles[0]!.options.onMessage?.(frame);
        expect(protocols[A]).toBeUndefined(); expect(onMessage).toHaveBeenCalledTimes(3);
        subscription.dispose(); observer.dispose();
    });

    it("samples SDK automatic retry state and prevents older handles from overriding another open handle", () => {
        const sdk = sockets();
        const observer = createObservedRelayWebSockets(sdk.create, () => new Channel());
        let observed: RelayConnections = {};
        const subscription = subscribeRelayConnections(value => { observed = value; }, () => new Channel());
        const old = observer.createWebSocket(A);
        observer.createWebSocket(A);
        sdk.handles[0]!.ready = "open";
        sdk.handles[0]!.options.onOpen?.();
        sdk.handles[1]!.ready = "open";
        sdk.handles[1]!.options.onOpen?.();
        old[Symbol.dispose]();
        sdk.handles[0]!.options.onClose?.(new CloseEvent("close"));
        expect(observed[A]).toBe("connected");
        sdk.handles[1]!.ready = "connecting";
        vi.advanceTimersByTime(RELAY_CONNECTION_HEARTBEAT_MS);
        expect(observed[A]).toBe("connecting");
        sdk.handles[1]!.ready = "open";
        vi.advanceTimersByTime(RELAY_CONNECTION_HEARTBEAT_MS);
        expect(observed[A]).toBe("connected");
        subscription.dispose(); observer.dispose();
    });

    it("answers a late page snapshot without opening an additional relay socket", () => {
        const sdk = sockets();
        const observer = createObservedRelayWebSockets(sdk.create, () => new Channel());
        observer.createWebSocket(A);
        sdk.handles[0]!.ready = "open";
        sdk.handles[0]!.options.onOpen?.();
        let observed: RelayConnections = {};
        const subscription = subscribeRelayConnections(value => { observed = value; }, () => new Channel());
        expect(observed[A]).toBe("connected");
        expect(sdk.create).toHaveBeenCalledTimes(1);
        observer.dispose();
        vi.setSystemTime(-90_000); // Wall-clock rollback cannot keep a dead worker green.
        vi.advanceTimersByTime(RELAY_CONNECTION_STALE_MS);
        expect(observed).toEqual({});
        subscription.dispose();
    });

    it("does not publish credentials, paths, parameters or invalid socket addresses", () => {
        const sdk = sockets();
        const channel = new Channel();
        const observer = createObservedRelayWebSockets(sdk.create, () => channel);
        for (const url of ["wss://name:secret@relay.test", "wss://relay.test/path", "invalid"]) {
            observer.createWebSocket(url);
        }
        expect(sdk.create).toHaveBeenCalledTimes(3);
        expect(channel.messages).toEqual([]);
        observer.createWebSocket("wss://relay.test?secret=value#private");
        expect(JSON.stringify(channel.messages)).not.toContain("secret");
        expect(JSON.stringify(channel.messages)).not.toContain("private");
        expect((channel.messages.at(-1) as RelayConnectionSnapshot).connections).toEqual([{ url: "wss://relay.test", state: "connecting", protocol: { state: "unknown", observedAt: 0 } }]);
        observer.dispose();
    });

    it("keeps SDK behavior available when optional channels fail to construct or send", () => {
        const sdk = sockets();
        const observer = createObservedRelayWebSockets(sdk.create, () => { throw new Error("channel unavailable"); });
        const socket = observer.createWebSocket(A);
        expect(socket.send("test").ok).toBe(true);
        observer.dispose();
        const channel = new Channel();
        vi.spyOn(channel, "postMessage").mockImplementation(() => { throw new Error("closed"); });
        const failedSend = createObservedRelayWebSockets(sdk.create, () => channel);
        expect(() => failedSend.createWebSocket(A)).not.toThrow();
        expect(() => vi.advanceTimersByTime(RELAY_CONNECTION_HEARTBEAT_MS)).not.toThrow();
        failedSend.dispose();
    });
});

describe("page connection observations", () => {
    it("requires fresh request correlation and ignores stale source or sequence messages", () => {
        const channel = new Channel();
        let observed: RelayConnections = {};
        const subscription = subscribeRelayConnections(value => { observed = value; }, () => channel);
        const request = channel.messages[0] as { requestId: string };
        const message: RelayConnectionSnapshot = { type: "snapshot", source: "worker-a", startedAt: 1_000, sequence: 1, connections: [{ url: A, state: "connected" }] };
        const receive = (data: unknown) => channel.onmessage?.call(channel as unknown as BroadcastChannel, { data } as MessageEvent);
        receive(message);
        expect(observed).toEqual({});
        receive({ ...message, requestId: request.requestId });
        expect(observed[A]).toBe("connected");
        receive({ ...message, connections: [{ url: A, state: "error" }] });
        expect(observed[A]).toBe("connected");
        subscription.refresh();
        const next = channel.messages[channel.messages.length - 1] as { requestId: string };
        receive({ ...message, source: "older-worker", startedAt: 0, sequence: 99, requestId: next.requestId, connections: [{ url: A, state: "error" }] });
        expect(observed[A]).toBe("connected");
        receive({ ...message, source: "worker-new", startedAt: 2_000, requestId: next.requestId, connections: [{ url: A, state: "connecting" }] });
        expect(observed[A]).toBe("connecting");
        receive({ ...message, sequence: 100, connections: [{ url: A, state: "error" }] });
        expect(observed[A]).toBe("connecting");
        subscription.dispose();
        expect(observed).toEqual({});
        expect(Channel.all.has(channel)).toBe(false);
    });

    it.each([false, true])("rejects a queued pre-suspension snapshot even when a worker was previously observed (%s)", previouslyConnected => {
        let elapsed = 0;
        vi.spyOn(performance, "now").mockImplementation(() => elapsed);
        const channel = new Channel();
        let observed: RelayConnections = {};
        const subscription = subscribeRelayConnections(value => { observed = value; }, () => channel);
        const initial = channel.messages[0] as { requestId: string };
        const message: RelayConnectionSnapshot = {
            type: "snapshot", source: "worker-a", startedAt: 1_000, sequence: 1,
            requestId: initial.requestId, connections: [{ url: A, state: "connected" }],
        };
        const receive = (data: unknown) => channel.onmessage?.call(channel as unknown as BroadcastChannel, { data } as MessageEvent);
        if (previouslyConnected) {
            receive(message);
            expect(observed[A]).toBe("connected");
            subscription.refresh();
        }
        const queued = channel.messages[channel.messages.length - 1] as { requestId: string };
        // Simulate a suspended page: monotonic time passes without running browser intervals.
        elapsed = RELAY_CONNECTION_STALE_MS + 1;
        receive({ ...message, sequence: 2, requestId: queued.requestId });
        expect(observed).toEqual({});
        subscription.refresh();
        const fresh = channel.messages[channel.messages.length - 1] as { requestId: string };
        receive({ ...message, sequence: 3, requestId: fresh.requestId });
        expect(observed[A]).toBe("connected");
        subscription.dispose();
    });

    it("drops protocol evidence with expired workers and treats a connection-only legacy snapshot as protocol unknown", () => {
        let elapsed = 0; vi.spyOn(performance, 'now').mockImplementation(() => elapsed);
        const channel = new Channel(); let protocols: RelayProtocols = {};
        const subscription = subscribeRelayConnections((_connections, values) => { protocols = values; }, () => channel);
        const nonce = (channel.messages.at(-1) as { requestId: string }).requestId;
        const receive = (data: unknown) => channel.onmessage?.call(channel as unknown as BroadcastChannel, { data } as MessageEvent);
        const message = { type: 'snapshot', source: 'worker-a', startedAt: 1000, sequence: 1, requestId: nonce, connections: [{ url: A, state: 'connected', protocol: { state: 'quota_exceeded', observedAt: 1000 } }] };
        receive(message); expect(protocols[A]?.state).toBe('quota_exceeded');
        receive({ ...message, requestId: undefined, sequence: 2, connections: [{ url: A, state: 'connected' }] });
        expect(protocols[A]).toBeUndefined();
        receive({ ...message, requestId: undefined, sequence: 3 });
        expect(protocols[A]?.state).toBe('quota_exceeded');
        elapsed = RELAY_CONNECTION_STALE_MS + 1; subscription.refresh();
        expect(protocols).toEqual({}); subscription.dispose();
    });

    it.each([
        null,
        {},
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: A, state: "connected", protocol: { state: "quota_exceeded", observedAt: 1, ownerId: "forbidden" } }] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: A, state: "connected", protocol: { state: "untrusted raw error", observedAt: 1 } }] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: A, state: "connected", protocol: { state: "quota_exceeded", observedAt: -1 } }] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: A + "?ownerId=synthetic-owner", state: "connected" }] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, privateData: "forbidden", connections: [] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: A, state: "connected", owner: "forbidden" }] },
        { type: "snapshot", source: "invalid source", startedAt: 1, sequence: 1, connections: [] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: A, state: "fully-synchronized" }] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: "wss://user:secret@relay.test", state: "connected" }] },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: Array.from({ length: 17 }, () => ({ url: A, state: "connected" })) },
        { type: "snapshot", source: "worker", startedAt: 1, sequence: 1, connections: [{ url: A, state: "connected" }, { url: A, state: "error" }] },
    ])("rejects malformed or excessive observations (%j)", value => {
        expect(parseRelayConnectionSnapshot(value)).toBeNull();
    });

    it("keeps unknown state when observation is unavailable", () => {
        const onChange = vi.fn();
        const subscription = subscribeRelayConnections(onChange, () => { throw new Error("unsupported"); });
        expect(() => subscription.refresh()).not.toThrow();
        expect(onChange).not.toHaveBeenCalled();
        subscription.dispose();
    });

    it("cleans up component channels and listeners without issuing worker commands", () => {
        vi.stubGlobal("BroadcastChannel", Channel);
        let state: ReturnType<typeof useEvoluRelayConnections> | undefined;
        const wrapper = mount(defineComponent({ setup() { state = useEvoluRelayConnections(); return () => null; } }));
        expect(Channel.all.size).toBe(1);
        state!.refresh();
        const channel = Array.from(Channel.all)[0]!;
        expect(channel.messages.every(message => (message as { type: string }).type === "request")).toBe(true);
        wrapper.unmount();
        expect(Channel.all.size).toBe(0);
        document.dispatchEvent(new Event("visibilitychange"));
        expect(Channel.all.size).toBe(0);
    });
});
