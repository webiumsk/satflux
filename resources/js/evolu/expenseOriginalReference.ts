import { createIdFromString, maxLength, NonEmptyString } from "@evolu/common";

/** Versioned non-base64 content: legacy readers must never mistake a reference for a file. */
export const EXPENSE_ORIGINAL_PREFIX = "satflux:expense-original:v1:";
export const EXPENSE_ORIGINAL_SOURCE_KIND = maxLength(32)(NonEmptyString).orThrow("efaktura-original");
export const EXPENSE_ORIGINAL_MAX_BYTES = 10 * 1024 * 1024;
export const EXPENSE_ORIGINAL_CHUNK_BYTES = 256 * 1024;

export type ExpenseOriginalReference = Readonly<{
    version: 1;
    companyId: string;
    receiptId: string;
    expenseId: string;
    attachmentId: string;
    sourceId: string;
    sha256: string;
    sizeBytes: number;
    chunkCount: number;
}>;
type ReferenceInput = Pick<ExpenseOriginalReference, "companyId" | "receiptId" | "expenseId" | "attachmentId" | "sha256" | "sizeBytes">;

/** Includes unsupported versions and malformed markers so consumers fail closed. */
export function isExpenseOriginalReference(value: unknown): value is `satflux:expense-original:${string}` {
    return typeof value === "string" && value.startsWith("satflux:expense-original:");
}

export function createExpenseOriginalReference(input: ReferenceInput): ExpenseOriginalReference {
    for (const value of [input.companyId, input.receiptId, input.expenseId, input.attachmentId]) {
        if (typeof value !== "string" || !/^[A-Za-z0-9_-]{1,128}$/.test(value)) throw new Error("original_unverified");
    }
    if (!/^[a-f0-9]{64}$/.test(input.sha256) || !Number.isSafeInteger(input.sizeBytes)
        || input.sizeBytes < 1 || input.sizeBytes > EXPENSE_ORIGINAL_MAX_BYTES) throw new Error("original_unverified");
    const sourceId = createIdFromString(`satflux.expense-original.source.v1.${JSON.stringify([
        input.companyId, input.receiptId, input.expenseId, input.attachmentId, input.sha256,
    ])}`);
    return Object.freeze({ version: 1, companyId: input.companyId, receiptId: input.receiptId,
        expenseId: input.expenseId, attachmentId: input.attachmentId, sourceId, sha256: input.sha256,
        sizeBytes: input.sizeBytes, chunkCount: Math.ceil(input.sizeBytes / EXPENSE_ORIGINAL_CHUNK_BYTES) });
}

export function encodeExpenseOriginalReference(reference: ExpenseOriginalReference): string {
    const canonical = createExpenseOriginalReference(reference);
    if (JSON.stringify(canonical) !== JSON.stringify(reference)) throw new Error("original_unverified");
    return EXPENSE_ORIGINAL_PREFIX + JSON.stringify(canonical);
}

/** null means ordinary inline content; a claimed but invalid archive always throws. */
export function parseExpenseOriginalReference(value: unknown): ExpenseOriginalReference | null {
    if (!isExpenseOriginalReference(value)) return null;
    if (!value.startsWith(EXPENSE_ORIGINAL_PREFIX) || value.length > 2048) throw new Error("original_unverified");
    try {
        const parsed: unknown = JSON.parse(value.slice(EXPENSE_ORIGINAL_PREFIX.length));
        if (!parsed || typeof parsed !== "object" || Array.isArray(parsed)) throw new Error("original_unverified");
        const canonical = createExpenseOriginalReference(parsed as ReferenceInput);
        if (EXPENSE_ORIGINAL_PREFIX + JSON.stringify(canonical) !== value) throw new Error("original_unverified");
        return canonical;
    } catch {
        throw new Error("original_unverified");
    }
}

export function expenseOriginalChunkId(sourceId: string, index: number): string {
    if (!/^[A-Za-z0-9_-]{22}$/.test(sourceId) || !Number.isSafeInteger(index) || index < 0 || index >= 40) throw new Error("original_unverified");
    return createIdFromString(`satflux.expense-original.chunk.v1.${sourceId}.${index}`);
}
