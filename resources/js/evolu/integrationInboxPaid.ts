/**
 * Settle plan for an imported inbox entry (P3 auto-issue convergence).
 *
 * - Auto-issued entries carry payload.number: the local document applies the
 *   reserved number (no second allocation) and, when the order was paid,
 *   must ALSO be marked paid - otherwise the merchant's dataset would show
 *   an issued-but-unpaid invoice for an order the customer already paid.
 * - Entries without a number keep the original behavior: paid orders issue
 *   locally through the allocator and get marked paid.
 */
export function resolveImportSettleActions(payload: Record<string, unknown>): {
    reservedNumber: string | null;
    issueLocally: boolean;
    markPaid: boolean;
} {
    const reservedNumber = String(payload.number ?? "").trim() || null;
    const paid = isPaidWooCommercePayload(payload);

    return {
        reservedNumber,
        issueLocally: reservedNumber === null && paid,
        markPaid: paid,
    };
}

export function isPaidWooCommercePayload(payload: Record<string, unknown>): boolean {
    if (payload.is_paid === true || payload.is_paid === 1 || payload.is_paid === "1") {
        return true;
    }
    const method = String(payload.payment_method ?? "").toLowerCase();
    return (
        method.includes("btcpay")
        || method.includes("satflux")
        || method.includes("bitcoin")
        || method.includes("satoshi")
    );
}
