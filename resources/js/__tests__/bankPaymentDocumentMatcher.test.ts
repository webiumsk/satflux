import { describe, expect, it } from "vitest";
import {
    documentMatchesBankPaymentHints,
    matchesDocumentPaymentReference,
} from "../evolu/bankPaymentDocumentMatcher";
import type { EvoluDocumentRow } from "../evolu/documentMap";

function doc(partial: Partial<EvoluDocumentRow>): EvoluDocumentRow {
    return {
        id: "doc-1" as EvoluDocumentRow["id"],
        companyId: "company-1" as EvoluDocumentRow["companyId"],
        documentType: "invoice",
        status: "issued",
        currency: "EUR",
        total: "100.00",
        ...partial,
    } as EvoluDocumentRow;
}

describe("bankPaymentDocumentMatcher", () => {
    it("matches by bank VS even when reference differs", () => {
        const document = doc({ variableSymbol: "20260042", number: "20260042" });
        expect(
            documentMatchesBankPaymentHints(document, "20260042", "Wire transfer fee note"),
        ).toBe(true);
    });

    it("matches by reference when bank VS is unrelated", () => {
        const document = doc({ variableSymbol: "20260042", number: "20260042" });
        expect(
            documentMatchesBankPaymentHints(document, "99999999", "Payment 20260042"),
        ).toBe(true);
    });

    it("matches reference against invoice number text", () => {
        const document = doc({ variableSymbol: "0042", number: "INV-0042" });
        expect(matchesDocumentPaymentReference(document, "Invoice INV-0042")).toBe(true);
    });
});
