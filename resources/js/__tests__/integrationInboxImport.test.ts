import { describe, expect, it } from "vitest";
import { isPaidWooCommercePayload } from "@/evolu/integrationInboxPaid";

describe("isPaidWooCommercePayload", () => {
    it("returns true when is_paid flag is set", () => {
        expect(isPaidWooCommercePayload({ is_paid: true })).toBe(true);
        expect(isPaidWooCommercePayload({ is_paid: "1" })).toBe(true);
    });

    it("returns true for BTCPay payment methods", () => {
        expect(isPaidWooCommercePayload({ payment_method: "btcpaygf_satoshi_tickets" })).toBe(true);
        expect(isPaidWooCommercePayload({ payment_method: "satflux_bitcoin" })).toBe(true);
    });

    it("returns false for unpaid bank transfer orders", () => {
        expect(isPaidWooCommercePayload({ payment_method: "bacs", is_paid: false })).toBe(false);
    });
});
