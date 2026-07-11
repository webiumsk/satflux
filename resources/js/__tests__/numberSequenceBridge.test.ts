import { describe, expect, it, vi } from "vitest";
import {
    formatNumberFromStoreCounter,
    localHighCounterForStoreBridge,
} from "@/evolu/numberSequenceBridge";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { EvoluNumberSeriesRow } from "@/evolu/numberSeriesMap";
import type { CompanyId, DocumentId } from "@/evolu/schema";

const companyId = "cmp-test" as CompanyId;

describe("localHighCounterForStoreBridge", () => {
    it("returns highest issued counter suffix for the default series format", () => {
        const series = [
            {
                id: "series-1",
                companyId,
                documentType: "invoice",
                format: "YYYYNNNN",
                isDefault: 1,
            } as EvoluNumberSeriesRow,
        ];
        const documents = [
            {
                id: "d1" as DocumentId,
                companyId,
                documentType: "invoice",
                number: "20260065",
                status: "issued",
            },
            {
                id: "d2" as DocumentId,
                companyId,
                documentType: "invoice",
                number: "20260068",
                status: "paid",
            },
        ] as EvoluDocumentRow[];

        expect(
            localHighCounterForStoreBridge(companyId, "invoice", documents, series),
        ).toBe(68);
    });
});

describe("formatNumberFromStoreCounter", () => {
    it("formats reserved counter with the local Evolu series pattern", () => {
        const series = [
            {
                id: "series-1",
                companyId,
                documentType: "invoice",
                format: "YYYYNNNN",
                isDefault: 1,
            } as EvoluNumberSeriesRow,
        ];

        expect(formatNumberFromStoreCounter(companyId, "invoice", 66, series)).toBe("20260066");
    });
});

describe("reserveNextDocumentNumberFromStore self-healing", () => {
    it("heals a missing server store link (422 store_not_linked) and retries once", async () => {
        vi.resetModules();

        const notLinkedError = Object.assign(new Error("422"), {
            response: { status: 422, data: { error: "store_not_linked" } },
        });
        const reserve = vi
            .fn()
            .mockRejectedValueOnce(notLinkedError)
            .mockResolvedValueOnce({ data: { counter: 71, number: "20260071" } });
        const syncLinkedStoreToServerBridge = vi.fn().mockResolvedValue("synced");

        vi.doMock("@/services/api", () => ({
            invoicingApi: { storeNumberSeries: { reserve, preview: vi.fn() } },
            default: {},
        }));
        vi.doMock("@/evolu/ephemeralBridge", () => ({ syncLinkedStoreToServerBridge }));

        const { reserveNextDocumentNumberFromStore } = await import("@/evolu/numberSequenceBridge");

        const result = await reserveNextDocumentNumberFromStore(
            "store-1",
            "invoice",
            undefined,
            70,
            undefined,
            companyId,
            { legal_name: "ACME s.r.o.", registration_number: "12345678" },
        );

        expect(syncLinkedStoreToServerBridge).toHaveBeenCalledWith(
            { legal_name: "ACME s.r.o.", registration_number: "12345678" },
            "store-1",
        );
        expect(reserve).toHaveBeenCalledTimes(2);
        expect(result).toEqual({ ok: true, value: { number: "20260071", counter: 71 } });

        vi.doUnmock("@/services/api");
        vi.doUnmock("@/evolu/ephemeralBridge");
    });

    it("remembers a no_bridge_company answer and skips the server on later calls", async () => {
        vi.resetModules();

        const notLinkedError = Object.assign(new Error("422"), {
            response: { status: 422, data: { error: "store_not_linked" } },
        });
        const reserve = vi.fn().mockRejectedValue(notLinkedError);
        const preview = vi.fn().mockRejectedValue(notLinkedError);
        const syncLinkedStoreToServerBridge = vi.fn().mockResolvedValue("no_bridge_company");

        vi.doMock("@/services/api", () => ({
            invoicingApi: { storeNumberSeries: { reserve, preview } },
            default: {},
        }));
        vi.doMock("@/evolu/ephemeralBridge", () => ({ syncLinkedStoreToServerBridge }));

        const { previewNextDocumentNumberFromStore, reserveNextDocumentNumberFromStore } =
            await import("@/evolu/numberSequenceBridge");
        const identity = { legal_name: "Local Only s.r.o.", registration_number: null };

        const first = await previewNextDocumentNumberFromStore(
            "store-1",
            "invoice",
            undefined,
            undefined,
            undefined,
            companyId,
            identity,
        );
        expect(first).toBeNull();
        expect(preview).toHaveBeenCalledTimes(1);
        expect(syncLinkedStoreToServerBridge).toHaveBeenCalledTimes(1);

        // Later preview AND reserve for the same company skip the server entirely.
        const second = await previewNextDocumentNumberFromStore(
            "store-1",
            "invoice",
            undefined,
            undefined,
            undefined,
            companyId,
            identity,
        );
        const reserved = await reserveNextDocumentNumberFromStore(
            "store-1",
            "invoice",
            undefined,
            undefined,
            undefined,
            companyId,
            identity,
        );
        expect(second).toBeNull();
        expect(reserved).toEqual({ ok: false, error: "store_not_found" });
        expect(preview).toHaveBeenCalledTimes(1);
        expect(reserve).not.toHaveBeenCalled();
        expect(syncLinkedStoreToServerBridge).toHaveBeenCalledTimes(1);

        vi.doUnmock("@/services/api");
        vi.doUnmock("@/evolu/ephemeralBridge");
    });

    it("does not loop when the heal retry still fails", async () => {
        vi.resetModules();

        const notLinkedError = Object.assign(new Error("422"), {
            response: { status: 422, data: { error: "store_not_linked" } },
        });
        const reserve = vi.fn().mockRejectedValue(notLinkedError);
        const syncLinkedStoreToServerBridge = vi.fn().mockResolvedValue("synced");

        vi.doMock("@/services/api", () => ({
            invoicingApi: { storeNumberSeries: { reserve, preview: vi.fn() } },
            default: {},
        }));
        vi.doMock("@/evolu/ephemeralBridge", () => ({ syncLinkedStoreToServerBridge }));

        const { reserveNextDocumentNumberFromStore } = await import("@/evolu/numberSequenceBridge");

        const result = await reserveNextDocumentNumberFromStore(
            "store-1",
            "invoice",
            undefined,
            70,
            undefined,
            companyId,
            { legal_name: "ACME s.r.o.", registration_number: null },
        );

        expect(reserve).toHaveBeenCalledTimes(2);
        expect(result).toEqual({ ok: false, error: "store_not_found" });

        vi.doUnmock("@/services/api");
        vi.doUnmock("@/evolu/ephemeralBridge");
    });
});
