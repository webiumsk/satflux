import { beforeEach, describe, expect, it } from "vitest";
import {
    deriveRelaySyncState,
    lastExchangeAt,
    readRelaySyncMeta,
    recordProbe,
    recordPullSuccess,
    recordPushSuccess,
    relaySyncMetaKey,
    type RelaySyncMeta,
} from "@/evolu/relaySyncTelemetry";

const emptyMeta: RelaySyncMeta = {
    lastPullSuccessAt: null,
    lastPushSuccessAt: null,
    lastProbeAt: null,
    lastProbeOk: null,
};

beforeEach(() => {
    localStorage.clear();
});

describe("deriveRelaySyncState", () => {
    const base = {
        relayConfigured: true,
        online: true,
        pending: false,
        hasEvoluError: false,
        meta: emptyMeta,
    };

    it("reports local_only when no relay is configured, regardless of anything else", () => {
        expect(
            deriveRelaySyncState({ ...base, relayConfigured: false, hasEvoluError: true, pending: true }),
        ).toBe("local_only");
    });

    it("reports unreachable when offline or the last probe failed", () => {
        expect(deriveRelaySyncState({ ...base, online: false })).toBe("unreachable");
        expect(
            deriveRelaySyncState({ ...base, meta: { ...emptyMeta, lastProbeOk: false } }),
        ).toBe("unreachable");
    });

    it("reports syncing while a sync gate is pending", () => {
        expect(deriveRelaySyncState({ ...base, pending: true })).toBe("syncing");
    });

    it("lets unreachable evidence beat pending and error signals", () => {
        expect(
            deriveRelaySyncState({ ...base, pending: true, meta: { ...emptyMeta, lastProbeOk: false } }),
        ).toBe("unreachable");
        expect(
            deriveRelaySyncState({
                ...base,
                hasEvoluError: true,
                meta: { ...emptyMeta, lastProbeOk: false },
            }),
        ).toBe("unreachable");
    });

    it("reports error on an unhandled Evolu error", () => {
        expect(deriveRelaySyncState({ ...base, hasEvoluError: true })).toBe("error");
    });

    it("reports ok ONLY with positive probe evidence, otherwise unknown", () => {
        expect(deriveRelaySyncState(base)).toBe("unknown");
        expect(
            deriveRelaySyncState({ ...base, meta: { ...emptyMeta, lastProbeOk: true } }),
        ).toBe("ok");
    });
});

describe("telemetry persistence", () => {
    it("records pull/push/probe per owner hash", () => {
        recordPullSuccess("owner-a", new Date("2026-07-12T10:00:00Z"));
        recordPushSuccess("owner-a", new Date("2026-07-12T11:00:00Z"));
        recordProbe("owner-a", true, new Date("2026-07-12T12:00:00Z"));

        const meta = readRelaySyncMeta("owner-a");
        expect(meta.lastPullSuccessAt).toBe("2026-07-12T10:00:00.000Z");
        expect(meta.lastPushSuccessAt).toBe("2026-07-12T11:00:00.000Z");
        expect(meta.lastProbeOk).toBe(true);

        expect(readRelaySyncMeta("owner-b")).toEqual(emptyMeta);
        expect(readRelaySyncMeta(null)).toEqual(emptyMeta);
    });

    it("survives corrupted storage entries", () => {
        localStorage.setItem(relaySyncMetaKey("owner-x"), "{broken");
        expect(readRelaySyncMeta("owner-x")).toEqual(emptyMeta);
    });

    it("lastExchangeAt picks the newest of pull and push", () => {
        expect(lastExchangeAt(emptyMeta)).toBeNull();
        expect(
            lastExchangeAt({
                ...emptyMeta,
                lastPullSuccessAt: "2026-07-10T00:00:00.000Z",
                lastPushSuccessAt: "2026-07-12T00:00:00.000Z",
            }),
        ).toBe("2026-07-12T00:00:00.000Z");
    });
});
