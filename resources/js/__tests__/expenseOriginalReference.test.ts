import { describe, expect, it } from "vitest";
import { attachmentContentBlob, insertLocalExpenseAttachmentFromBase64, LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES, readFileAsAttachmentBase64 } from "@/evolu/expenseAttachmentCrud";
import { createExpenseOriginalReference, createManualExpenseOriginalReference, encodeExpenseOriginalReference, expenseOriginalChunkId, EXPENSE_ORIGINAL_CHUNK_BYTES,
    EXPENSE_ORIGINAL_MAX_BYTES, EXPENSE_ORIGINAL_PREFIX, isExpenseOriginalReference, parseExpenseOriginalReference } from "@/evolu/expenseOriginalReference";

const input = { companyId: "company-a", receiptId: "receipt-a", expenseId: "expense-a", attachmentId: "attachment-a", sha256: "a".repeat(64), sizeBytes: 400_000 };

describe("expense original references", () => {
    it("round-trips the manual namespace without a receipt and binds MIME to its canonical source", () => {
        const manual = { companyId: input.companyId, expenseId: input.expenseId, attachmentId: input.attachmentId,
            sha256: input.sha256, sizeBytes: input.sizeBytes, mimeType: "application/pdf" };
        const ref = createManualExpenseOriginalReference(manual);
        const encoded = encodeExpenseOriginalReference(ref);
        expect(encoded.startsWith("satflux:expense-original:v2:")).toBe(true);
        expect(ref).not.toHaveProperty("receiptId");
        expect(parseExpenseOriginalReference(encoded)).toEqual(ref);
        expect(ref.sourceId).not.toBe(createExpenseOriginalReference(input).sourceId);
        expect(createManualExpenseOriginalReference({ ...manual, mimeType: "image/png" }).sourceId).not.toBe(ref.sourceId);
        for (const changed of [{ ...ref, mimeType: "text/html" }, { ...ref, receiptId: "fake-receipt" }, { ...ref, version: 1 }]) {
            expect(() => parseExpenseOriginalReference("satflux:expense-original:v2:" + JSON.stringify(changed))).toThrow("original_unverified");
        }
        for (const mimeType of ["", "text/html; charset=utf-8", "text/html\n", "x".repeat(129)]) {
            expect(() => createManualExpenseOriginalReference({ ...manual, mimeType })).toThrow("original_unverified");
        }
    });
    it("round-trips a canonical bounded manifest and binds all receipt identities without an owner key", () => {
        const ref = createExpenseOriginalReference(input);
        const marker = encodeExpenseOriginalReference(ref);
        expect(parseExpenseOriginalReference(marker)).toEqual(ref);
        expect(ref.chunkCount).toBe(2);
        expect(() => atob(marker)).toThrow();
        for (const field of ["companyId", "receiptId", "expenseId", "attachmentId", "sha256"] as const) {
            const changed = { ...input, [field]: field === "sha256" ? "b".repeat(64) : "other" };
            expect(createExpenseOriginalReference(changed).sourceId).not.toBe(ref.sourceId);
        }
        expect(marker).not.toContain("owner");
        expect(expenseOriginalChunkId(ref.sourceId, 0)).toBe(expenseOriginalChunkId(ref.sourceId, 0));
        expect(expenseOriginalChunkId(ref.sourceId, 1)).not.toBe(expenseOriginalChunkId(ref.sourceId, 0));
    });

    it.each([1, EXPENSE_ORIGINAL_CHUNK_BYTES, EXPENSE_ORIGINAL_MAX_BYTES])("accepts the exact byte boundary %s", sizeBytes => {
        const ref = createExpenseOriginalReference({ ...input, sizeBytes });
        expect(ref.chunkCount).toBe(Math.ceil(sizeBytes / EXPENSE_ORIGINAL_CHUNK_BYTES));
        expect(parseExpenseOriginalReference(encodeExpenseOriginalReference(ref))).toEqual(ref);
    });

    it.each([0, -1, 0.5, EXPENSE_ORIGINAL_MAX_BYTES + 1, Number.NaN])("rejects invalid source size %s", sizeBytes => {
        expect(() => createExpenseOriginalReference({ ...input, sizeBytes })).toThrow("original_unverified");
    });

    it("distinguishes ordinary inline data from every malformed or unsupported claimed reference", () => {
        expect(parseExpenseOriginalReference(btoa("ordinary"))).toBeNull();
        expect(parseExpenseOriginalReference(null)).toBeNull();
        const ref = createExpenseOriginalReference(input);
        const invalid = ["satflux:expense-original:v2:{}", EXPENSE_ORIGINAL_PREFIX + "{", EXPENSE_ORIGINAL_PREFIX + "null",
            EXPENSE_ORIGINAL_PREFIX + JSON.stringify({ ...ref, sourceId: "different" }),
            EXPENSE_ORIGINAL_PREFIX + JSON.stringify({ ...ref, companyId: "foreign" }),
            EXPENSE_ORIGINAL_PREFIX + JSON.stringify({ ...ref, version: 2 }),
            EXPENSE_ORIGINAL_PREFIX + JSON.stringify({ ...ref, chunkCount: 1 }),
            EXPENSE_ORIGINAL_PREFIX + JSON.stringify({ ...ref, unexpected: true }),
            EXPENSE_ORIGINAL_PREFIX + JSON.stringify(ref, null, 2)];
        for (const marker of invalid) {
            expect(isExpenseOriginalReference(marker)).toBe(true);
            expect(() => parseExpenseOriginalReference(marker)).toThrow("original_unverified");
        }
    });

    it("keeps ordinary picker/base64 limits and prevents legacy blob readers from decoding a marker", async () => {
        const marker = encodeExpenseOriginalReference(createExpenseOriginalReference(input));
        expect(attachmentContentBlob({ contentBase64: marker } as never)).toBeNull();
        await expect(readFileAsAttachmentBase64({ size: LOCAL_EXPENSE_ATTACHMENT_MAX_BYTES + 1 } as File)).rejects.toThrow("file_too_large");
        const store = { upsert: () => { throw new Error("unexpected write"); } };
        expect(insertLocalExpenseAttachmentFromBase64(store as never, "expense" as never,
            { filename: "original.xml", mimeType: "application/xml", contentBase64: "x".repeat(524289) }).ok).toBe(false);
    });
});
