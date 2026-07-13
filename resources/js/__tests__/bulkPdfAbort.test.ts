import { beforeEach, describe, expect, it, vi } from "vitest";
import { isAbortError } from "../utils/abortError";

const postMock = vi.hoisted(() => vi.fn());

vi.mock("@/services/api", () => ({
    default: { post: postMock, get: vi.fn() },
    invoicingApi: { companies: { list: vi.fn() } },
}));

// ephemeralBridge transitively imports the real Evolu client - replace it
// with an inert query token before importing.
vi.mock("@/evolu/client", () => ({
    allDocumentSnapshotsQuery: "allDocumentSnapshotsQuery",
}));

import { downloadEphemeralPdfZip, type EphemeralBulkRequestBody } from "../evolu/ephemeralBridge";

const body = { company: {}, documents: [] } as unknown as EphemeralBulkRequestBody;

describe("isAbortError", () => {
    it("recognizes axios cancel and DOM abort errors only", () => {
        expect(isAbortError({ code: "ERR_CANCELED" })).toBe(true);
        expect(isAbortError({ name: "CanceledError" })).toBe(true);
        expect(isAbortError({ name: "AbortError" })).toBe(true);
        expect(isAbortError(new Error("boom"))).toBe(false);
        expect(isAbortError(null)).toBe(false);
        expect(isAbortError("ERR_CANCELED")).toBe(false);
    });
});

describe("bulk PDF abort (P2 phase 4)", () => {
    beforeEach(() => {
        postMock.mockReset();
    });

    it("a user abort rethrows immediately and never tries the fallback route", async () => {
        const cancel = Object.assign(new Error("canceled"), { code: "ERR_CANCELED" });
        postMock.mockRejectedValueOnce(cancel);

        await expect(
            downloadEphemeralPdfZip(body, "bridge-company-1", { signal: new AbortController().signal }),
        ).rejects.toBe(cancel);
        expect(postMock).toHaveBeenCalledTimes(1);
    });

    it("a 404 still falls back to the company-scoped route with the same signal", async () => {
        const abort = new AbortController();
        postMock
            .mockRejectedValueOnce({ response: { status: 404 } })
            .mockResolvedValueOnce({ data: new Blob(["zip"]) });

        // jsdom: stub the download side effects.
        const createUrl = vi.spyOn(URL, "createObjectURL").mockReturnValue("blob:x");
        const revokeUrl = vi.spyOn(URL, "revokeObjectURL").mockImplementation(() => {});
        const click = vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(() => {});

        await downloadEphemeralPdfZip(body, "bridge-company-1", { signal: abort.signal });

        expect(postMock).toHaveBeenCalledTimes(2);
        expect(postMock.mock.calls[0][2]).toMatchObject({ responseType: "blob", signal: abort.signal });
        expect(postMock.mock.calls[1][2]).toMatchObject({ responseType: "blob", signal: abort.signal });

        createUrl.mockRestore();
        revokeUrl.mockRestore();
        click.mockRestore();
    });
});
