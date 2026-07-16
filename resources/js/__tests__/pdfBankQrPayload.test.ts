import { describe, expect, it, vi } from "vitest";

// documentCrud transitively imports the real Evolu client (WASM sqlite) via
// ./client - replace it with inert query tokens before importing the module.
vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "allCompaniesDetailQuery",
    allContactsQuery: "allContactsQuery",
    allDocumentLinesQuery: "allDocumentLinesQuery",
    allDocumentsQuery: "allDocumentsQuery",
    allNumberSeriesQuery: "allNumberSeriesQuery",
    allCompanyStockBalancesQuery: "allCompanyStockBalancesQuery",
    allCompanyStockItemsQuery: "allCompanyStockItemsQuery",
    allCompanyStockMovementsQuery: "allCompanyStockMovementsQuery",
    allCompanyWarehousesQuery: "allCompanyWarehousesQuery",
}));

import { payloadFromApiDocument } from "@/evolu/documentCrud";

describe("pdf_bank_qr payload mapping", () => {
    it("carries the per-document bank QR choice through the save payload", () => {
        const payload = payloadFromApiDocument({
            type: "invoice",
            currency: "EUR",
            pdf_locale: "de",
            pdf_bank_qr: "epc",
            lines: [],
        });

        expect(payload.pdf_bank_qr).toBe("epc");
        expect(payload.pdf_locale).toBe("de");
    });

    it("defaults to null (auto) when the document has no choice", () => {
        const payload = payloadFromApiDocument({ type: "invoice", lines: [] });

        expect(payload.pdf_bank_qr).toBeNull();
    });
});
