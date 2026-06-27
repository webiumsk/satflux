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
