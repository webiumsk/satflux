import { describe, expect, it, vi } from "vitest";
import type { Evolu } from "@evolu/common/local-first";
import type { EvoluDocumentRow } from "@/evolu/documentMap";
import type { CompanyId, DocumentId, InvoicingLocalSchema } from "@/evolu/schema";

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

import { saveLocalDocument, type DocumentSavePayload } from "@/evolu/documentCrud";
import { documentHistoryFromEvents, EVENT_USER_EMAIL_KEY } from "@/evolu/documentEventLog";

const companyId = "cmp-1" as CompanyId;

const payload = {
    type: "invoice",
    title: "Invoice",
    issue_date: "2026-07-22",
    delivery_date: null,
    due_date: null,
    currency: "EUR",
    discount_percent: 0,
    lines: [
        {
            name: "Service",
            description: "",
            quantity: "1",
            unit: "ks",
            unit_price: "100",
            line_discount_percent: "0",
            tax_rate: "19",
        },
    ],
} as unknown as DocumentSavePayload;

function issuedDocument(): EvoluDocumentRow {
    return {
        id: "doc-1" as DocumentId,
        companyId,
        documentType: "invoice",
        status: "issued",
        number: "INV20260001",
    } as unknown as EvoluDocumentRow;
}

const evoluStub = {} as Evolu<InvoicingLocalSchema>;

const baseOptions = {
    defaultVat: 19,
    lineTaxApplies: () => true,
    lineTaxRate: () => 19,
    existingLines: [],
};

describe("GoBD immutability - saveLocalDocument lockIssuedContent", () => {
    it("rejects any save against an issued document when locked", () => {
        const result = saveLocalDocument(evoluStub, companyId, payload, {
            ...baseOptions,
            documentId: "doc-1" as DocumentId,
            existingDocument: issuedDocument(),
            lockIssuedContent: true,
        });
        expect(result.ok).toBe(false);
        if (!result.ok) {
            expect(result.error).toBe("issued_locked");
        }
    });

    it("locked drafts still save (only issued content is immutable)", () => {
        const draft = { ...issuedDocument(), status: "draft", number: null } as EvoluDocumentRow;
        // Reaching past the guard proves drafts are not blocked - the stub
        // evolu then fails, which is fine for this unit test's purpose.
        expect(() =>
            saveLocalDocument(evoluStub, companyId, payload, {
                ...baseOptions,
                documentId: "doc-1" as DocumentId,
                existingDocument: draft,
                lockIssuedContent: true,
            }),
        ).toThrow();
    });

    it("without the lock the issued save path stays reachable (edit + re-freeze)", () => {
        expect(() =>
            saveLocalDocument(evoluStub, companyId, payload, {
                ...baseOptions,
                documentId: "doc-1" as DocumentId,
                existingDocument: issuedDocument(),
            }),
        ).toThrow();
    });
});

describe("GoBD audit attribution - documentHistoryFromEvents", () => {
    it("extracts the actor e-mail from event metadata", () => {
        const entries = documentHistoryFromEvents([
            {
                id: "e1",
                documentId: "doc-1",
                action: "issued",
                metadataJson: JSON.stringify({ number: "INV1", [EVENT_USER_EMAIL_KEY]: "user@firma.sk" }),
                createdAt: "2026-07-22T10:00:00Z",
            },
            {
                id: "e2",
                documentId: "doc-1",
                action: "created",
                metadataJson: null,
                createdAt: "2026-07-22T09:00:00Z",
            },
        ]);

        expect(entries[0].user).toEqual({ email: "user@firma.sk" });
        expect(entries[0].metadata?.number).toBe("INV1");
        // Older events without attribution show no user.
        expect(entries[1].user).toBeNull();
    });
});
