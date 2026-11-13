export type PaidPaymentMethod = "manual" | "bank_transfer" | "card" | "cash" | "btcpay" | "other";

/** Gateway evidence is used only after the existing import flow confirms payment. */
export function wooCommercePaidPaymentMethod(value: unknown): PaidPaymentMethod | null {
    const gateway = String(value ?? "").trim().toLowerCase();
    if (!gateway) return null;
    if (gateway === "bacs") return "bank_transfer";
    if (gateway === "cod") return "cash";
    if (["stripe", "stripe_cc", "woocommerce_payments", "square_credit_card", "authorize_net_cim_credit_card"].includes(gateway)) return "card";
    if (["btcpay", "btcpay_greenfield", "btcpaygf_satoshi_tickets", "satflux_bitcoin", "satflux", "bitcoin", "satoshi"].includes(gateway)) return "btcpay";
    return "other";
}

export function paidPaymentMethodTranslationKey(method: unknown): string {
    switch (method) {
        case "manual": return "invoicing.payment_method_manual";
        case "bank_transfer": return "invoicing.payment_method_bank_transfer";
        case "card": return "invoicing.payment_method_card";
        case "cash": return "invoicing.payment_method_cash";
        case "btcpay": return "invoicing.payment_method_btcpay";
        case "other": return "invoicing.payment_method_other";
        default: return "invoicing.payment_method_unspecified";
    }
}
