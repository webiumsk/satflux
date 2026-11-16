import type { Evolu } from "@evolu/common/local-first";
import { sqliteTrue } from "@evolu/common";
import { retainExpenseOriginalArchive, readVerifiedExpenseAttachment } from "./expenseOriginalArchive";
import { encodeExpenseOriginalReference, EXPENSE_ORIGINAL_MAX_BYTES } from "./expenseOriginalReference";
import { assertExpenseCompanyOwner, currentExpenseTarget, withExpenseReconciliation } from "./expenseReconciliation";
import type { ExpenseAttachmentId, ExpenseId, InvoicingLocalSchema } from "./schema";

/** Preserve the caller's stable pending-file identity through known failures and retries. */
export async function saveManualExpenseAttachmentArchive(evolu: Evolu<InvoicingLocalSchema>, input: {
    companyId: string; expenseId: ExpenseId; attachmentId: ExpenseAttachmentId; file: File;
    assertCurrent: () => void;
}): Promise<void> {
    if (input.file.size > EXPENSE_ORIGINAL_MAX_BYTES) throw new Error("file_too_large");
    const bytes = new Uint8Array(await input.file.arrayBuffer());
    input.assertCurrent();
    if (bytes.length !== input.file.size) throw new Error("original_unverified");
    await withExpenseReconciliation(evolu, async scope => {
        const target = currentExpenseTarget(scope, input.expenseId);
        const ownerId = target && Reflect.get(target, "ownerId");
        if (!target || target.companyId !== input.companyId || target.isDeleted === sqliteTrue || typeof ownerId !== "string") {
            throw new Error("expense_unavailable");
        }
        const assertCurrent = () => {
            input.assertCurrent();
            assertExpenseCompanyOwner(scope, input.companyId, ownerId);
            const current = currentExpenseTarget(scope, input.expenseId);
            if (!current || current.companyId !== input.companyId || current.isDeleted === sqliteTrue
                || Reflect.get(current, "ownerId") !== ownerId) throw new Error("expense_unavailable");
        };
        const ref = await retainExpenseOriginalArchive(scope, {
            companyId: input.companyId, ownerId, expenseId: input.expenseId, attachmentId: input.attachmentId,
            filename: input.file.name, mimeType: input.file.type || "application/octet-stream", bytes, assertCurrent,
        });
        assertCurrent();
        // Prove both the durable attachment link and complete archive before the
        // page drops its pending File or announces a successful save.
        await readVerifiedExpenseAttachment(scope.evolu, input.companyId, {
            id: input.attachmentId, expenseId: input.expenseId, originalFilename: input.file.name,
            mimeType: input.file.type || "application/octet-stream", sizeBytes: String(bytes.length),
            contentBase64: encodeExpenseOriginalReference(ref),
        }, { expectedOwnerId: ownerId });
        assertCurrent();
    }, undefined, ["allExpenseAttachmentsQuery"]);
}
