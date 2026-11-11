import { describe, expect, it } from "vitest";
import { buildLocalActionCenter, type ActionCenterLocalRows } from "@/evolu/actionCenterLocal";

function rows(overrides: Partial<ActionCenterLocalRows> = {}): ActionCenterLocalRows {
    return { transactions: [], matches: [], documents: [], expenses: [], events: [], ...overrides };
}

// Synthetic query rows intentionally supply only fields consumed by this read-only projection.
function fixture<T>(value: unknown): T { return value as T; }
const payment = (id: string, extra: Record<string, unknown> = {}) => ({
    id, ownerId: "owner-a", companyId: "company-a", matchStatus: "unmatched", source: "csv", direction: "credit",
    bookedAt: "2026-11-10", amount: "10.00", currency: "EUR", reference: "Demo payment", dedupeHash: id,
    bankPaymentClaimJson: null, businessExpenseId: null, ...extra,
});
const document = (id: string, extra: Record<string, unknown> = {}) => ({
    id, ownerId: "owner-a", companyId: "company-a", documentType: "invoice", number: "DEMO-1", total: "10.00", currency: "EUR", emailSentAt: null, ...extra,
});
const event = (id: string, documentId: string, action: string, createdAt: string, extra: Record<string, unknown> = {}) => ({
    id, ownerId: "owner-a", documentId, action, createdAt, metadataJson: null, ...extra,
});

describe("local company action center", () => {
    it("does not resurrect an earlier attempt merely because its audit worker completed late", () => {
        const result = buildLocalActionCenter("company-a", "owner-a", rows({
            documents: fixture([document("doc")]),
            events: fixture([
                event("old-failure", "doc", "business_document.email_failed", "2026-11-10T12:02:00Z", {
                    metadataJson: JSON.stringify({ attempt_started_at: "2026-11-10T12:00:00Z" }),
                }),
                event("new-success", "doc", "business_document.email_sent", "2026-11-10T12:01:00Z"),
            ]),
        }));
        expect(result.categories.find(row => row.key === "failed_sends")?.count).toBe(0);
    });
    it("filters company and owner partitions, balance snapshots, ignored records and existing document matches", () => {
        const result = buildLocalActionCenter("company-a", "owner-a", rows({
            transactions: fixture([
                payment("open"), payment("other-company", { companyId: "company-b" }), payment("other-owner", { ownerId: "owner-b" }),
                payment("balance", { reference: "Stav na účte" }), payment("ignored", { matchStatus: "ignored" }), payment("linked"),
            ]),
            documents: fixture([document("doc")]),
            matches: fixture([{ id: "match", ownerId: "owner-a", bankTransactionId: "linked", businessDocumentId: "doc", matchedAmount: "10.00", matchType: "manual", matchedAt: "2026-11-10" }]),
        }));
        const category = result.categories.find((row) => row.key === "unmatched_payments")!;
        expect(category.items.map((item) => item.id)).toEqual(["open"]);
        expect(category.count).toBe(1);
        // Even ignored or matched copies suppress duplicate server alerts.
        expect(result.bankHashes).toContain("ignored");
        expect(result.bankHashes).toContain("linked");
        expect(result.bankHashes).not.toContain("other-owner");
    });

    it("uses latest same-owner email evidence and clears a failure only after a later success", () => {
        const result = buildLocalActionCenter("company-a", "owner-a", rows({
            documents: fixture([document("open"), document("resolved"), document("foreign", { companyId: "company-b" }), document("stamped", { emailSentAt: "2026-11-10T12:00:00Z" })]),
            events: fixture([
                event("failed-open", "open", "business_document.email_failed", "2026-11-10T10:00:00Z"),
                event("foreign-success", "open", "business_document.email_sent", "2026-11-10T11:00:00Z", { ownerId: "owner-b" }),
                event("failed-resolved", "resolved", "business_document.email_failed", "2026-11-10T10:00:00Z"),
                event("success", "resolved", "business_document.email_sent", "2026-11-10T11:00:00Z"),
                event("failed-foreign", "foreign", "business_document.email_failed", "2026-11-10T10:00:00Z"),
                event("failed-stamped", "stamped", "business_document.email_failed", "2026-11-10T10:00:00Z"),
            ]),
        }));
        expect(result.categories.find((row) => row.key === "failed_sends")?.items.map((item) => item.document_id)).toEqual(["open"]);
    });

    it("keeps an exact count while bounding displayed local rows", () => {
        const result = buildLocalActionCenter("company-a", "owner-a", rows({ transactions: fixture(Array.from({ length: 30 }, (_, index) => payment(String(index)))) }));
        const category = result.categories[0];
        expect(category.count).toBe(30);
        expect(category.items).toHaveLength(25);
        expect(category.has_more).toBe(true);
    });

    it("compares actual instants across timestamp offsets and retains ambiguous failure evidence", () => {
        const result = buildLocalActionCenter("company-a", "owner-a", rows({
            documents: fixture([document("resolved"), document("ambiguous")]),
            events: fixture([
                event("failure", "resolved", "business_document.email_failed", "2026-11-10T11:00:00+02:00"),
                event("success", "resolved", "business_document.email_sent", "2026-11-10T10:00:00Z"),
                event("invalid-failure", "ambiguous", "business_document.email_failed", "unknown"),
                event("valid-success", "ambiguous", "business_document.email_sent", "2026-11-10T12:00:00Z"),
            ]),
        }));
        expect(result.categories[1].items.map((item) => item.document_id)).toEqual(["ambiguous"]);
    });
});
