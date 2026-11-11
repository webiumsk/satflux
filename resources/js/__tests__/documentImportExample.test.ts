import { describe, expect, it, vi } from "vitest";
vi.mock("@/evolu/client", () => ({ allContactsQuery: "contacts", allDocumentsQuery: "documents", allNumberSeriesQuery: "series" }));
vi.mock("@/evolu/documentCrud", () => ({ calcDocumentTotals: vi.fn() }));
vi.mock("@/evolu/contactCrud", () => ({ insertLocalContactFromForm: vi.fn(), updateLocalContactFromForm: vi.fn() }));
vi.mock("@/evolu/numberSeriesCrud", () => ({ syncNumberSeriesCounterFromDocuments: vi.fn() }));
vi.mock("@/evolu/documentEventLog", () => ({ logDocumentEvent: vi.fn() }));
import { buildDocumentImportExampleCsvBlob, parseDocumentImportCsv, suggestDocumentMapping } from "@/evolu/documentImportLocal";
import { buildMigrationPreview } from "@/evolu/migrationValidation";

describe("downloaded migration example", () => {
    it("prefers the actual SuperFaktura buyer columns and preserves their values in the preview", () => {
        const headers = [
            "IČO", "Číslo dokladu", "Názov / Popis", "VS", "Odberateľ", "Odberateľ IČO",
            "Odberateľ DIČ", "Odberateľ IČ DPH", "Odberateľ telefón", "Odberateľ email",
            "Odberateľ adresa", "Krajina odberateľa", "Dátum vystavenia", "Dátum splatnosti",
            "Dátum dodania", "Suma", "Mena", "Suma v domácej mene", "Domáca mena", "Kurz",
            "Dátum úhrady", "Druh úhrady",
        ];
        const row = [
            "11111111", "SF-001", "Service", "001", "Test Buyer", "22222222",
            "1234567890", "SK1234567890", "+421900000000", "buyer@example.test",
            "Testovacia 42", "SK", "2026-06-15", "2026-06-30",
            "2026-06-15", "123.00", "EUR", "3100.00", "CZK", "25.2", "", "prevod",
        ];
        const mapping = suggestDocumentMapping(headers, "superfaktura");
        expect(mapping.payment_status).toBeNull();
        expect(mapping.amount_paid).toBeNull();
        const result = buildMigrationPreview([row], mapping, {
            companyId: "example", ownerId: "owner", existing: [], defaultCurrency: "EUR", dateFormat: "auto",
        });
        expect(result.rows[0]).toMatchObject({
            kind: "ready",
            invoice: {
                invoice_number: "SF-001", total: "123.00", currency: "EUR",
                contact_attributes: {
                    name: "Test Buyer", registration_number: "22222222", tax_id: "1234567890",
                    vat_id: "SK1234567890", phone: "+421900000000", email: "buyer@example.test",
                    street: "Testovacia 42", country: "SK",
                },
            },
        });
    });

    it("round-trips both unpaid and paid sample rows through the actual new wizard parser", async () => {
        const blob = buildDocumentImportExampleCsvBlob();
        const text = await new Promise<string>((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result)); reader.onerror = reject; reader.readAsText(blob); });
        const parsed = parseDocumentImportCsv(text);
        const result = buildMigrationPreview(parsed.rows, suggestDocumentMapping(parsed.headers), { companyId: "example", ownerId: "owner", existing: [], defaultCurrency: "EUR", dateFormat: "auto" });
        expect(result).toMatchObject({ ready: 2, invalid: 0, conflict: 0 });
        expect(result.rows.map(entry => [entry.invoice?.state, entry.invoice?.amount_paid, entry.invoice?.paid_at])).toEqual([
            ["unpaid", "0.00", null], ["paid", "250.50", "2024-02-10"],
        ]);
    });
});
