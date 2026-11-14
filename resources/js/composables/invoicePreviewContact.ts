/** An explicit null in an issued snapshot is historical evidence too. */
export function resolveInvoicePreviewContact<T>(
    status: string,
    liveContact: T | null,
    frozen: { contact: T | null } | null,
    serverBuyer: T | null,
): T | null {
    if (status === "draft") return liveContact;
    if (frozen) return frozen.contact;
    return serverBuyer ?? liveContact;
}
