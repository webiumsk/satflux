import { describe, expect, it } from "vitest";
import { isPaidWooCommercePayload } from "@/evolu/integrationInboxPaid";
import {
    IntegrationInboxPathError,
    resolveIntegrationInboxBasePath,
} from "@/evolu/integrationInboxPaths";
import { isServerUuid } from "@/utils/serverIds";

describe("integration inbox API path", () => {
    it("rejects Evolu company ids without a linked store", () => {
        expect(() => resolveIntegrationInboxBasePath("nxRecZSp10VG-cMf3SA_qw", null)).toThrow(
            IntegrationInboxPathError,
        );
    });

    it("uses store-scoped path when linked store is set", () => {
        expect(
            resolveIntegrationInboxBasePath(
                "nxRecZSp10VG-cMf3SA_qw",
                "019d34df-271f-7004-9e3c-7e27aa1d57e5",
            ),
        ).toBe("/invoicing/stores/019d34df-271f-7004-9e3c-7e27aa1d57e5/integration-inbox");
    });

    it("accepts Satflux store UUIDs", () => {
        expect(
            isServerUuid("019d34df-271f-7004-9e3c-7e27aa1d57e5"),
        ).toBe(true);
        expect(isServerUuid("nxRecZSp10VG-cMf3SA_qw")).toBe(false);
    });
});

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
