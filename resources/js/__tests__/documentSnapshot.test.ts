import { describe, expect, it, vi } from "vitest";
import {
    ISSUED_SNAPSHOT_FORMAT_VERSION,
    buildIssuedSnapshotContentV1,
    deterministicStringify,
    insertIssuedDocumentSnapshot,
    latestSnapshotRowForDocument,
    parseIssuedSnapshotRow,
    validateIssuedSnapshotV1,
    type EvoluDocumentSnapshotRow,
} from "@/evolu/documentSnapshotCrud";
import type { DocumentId, InvoicingLocalSchema } from "@/evolu/schema";
import type { Evolu } from "@evolu/common/local-first";

const company = {
    legal_name: "ACME s.r.o.",
    registration_number: "12345678",
    tax_id: "1234567890",
    vat_number: "SK1234567890",
    street: "Hlavna 1",
    city: "Bratislava",
    postal_code: "81101",
    country: "SK",
    iban: "SK3112000000198742637541",
    vat_payer: true,
    vat_rate_default: 20,
};

const contact = {
    name: "Customer Ltd",
    email: "billing@customer.example",
    street: "Side Street 2",
    city: "Kosice",
    country: "SK",
};

const document = {
    type: "invoice",
    title: "Invoice",
    number: "20260071",
    variable_symbol: "20260071",
    issue_date: "2026-07-11",
    due_date: "2026-07-25",
    currency: "EUR",
    subtotal: "100.00",
    tax_total: "20.00",
    discount_percent: "0.00",
    total: "120.00",
    pdf_show_signature: true,
    pdf_show_payment_info: true,
    payment_bank_enabled: true,
    payment_btc_enabled: false,
};

const lines = [
    {
        name: "Work",
        description: null,
        quantity: "1.0000",
        unit: "ks",
        unit_price: "100.0000",
        line_discount_percent: "0.00",
        tax_rate: "20.00",
        line_total: "120.00",
    },
];

function buildValid() {
    const result = buildIssuedSnapshotContentV1({ company, contact, document, lines });
    if (!result.ok) throw new Error(`expected ok, got ${result.error}`);
    return result.value;
}

describe("buildIssuedSnapshotContentV1", () => {
    it("freezes supplier, buyer, header and lines with decimal strings", () => {
        const value = buildValid();
        expect(value.format_version).toBe(ISSUED_SNAPSHOT_FORMAT_VERSION);
        expect(value.company.legal_name).toBe("ACME s.r.o.");
        expect(value.company.vat_rate_default).toBe("20");
        expect(value.contact?.name).toBe("Customer Ltd");
        expect(value.document.number).toBe("20260071");
        expect(value.document.total).toBe("120.00");
        expect(value.lines[0].unit_price).toBe("100.0000");
    });

    it("rejects a document without number, supplier or lines", () => {
        expect(
            buildIssuedSnapshotContentV1({ company, contact, document: { ...document, number: null }, lines }),
        ).toEqual({ ok: false, error: "snapshot_number_missing" });
        expect(
            buildIssuedSnapshotContentV1({ company: { legal_name: "" }, contact, document, lines }),
        ).toEqual({ ok: false, error: "snapshot_company_missing" });
        expect(
            buildIssuedSnapshotContentV1({ company, contact, document, lines: [] }),
        ).toEqual({ ok: false, error: "snapshot_lines_missing" });
    });

    it("allows a missing buyer (contact null)", () => {
        const result = buildIssuedSnapshotContentV1({ company, contact: null, document, lines });
        expect(result.ok).toBe(true);
        if (result.ok) expect(result.value.contact).toBeNull();
    });
});

describe("deterministicStringify", () => {
    it("produces identical bytes regardless of key insertion order", () => {
        const a = { b: 1, a: { d: [1, 2], c: "x" } };
        const b = { a: { c: "x", d: [1, 2] }, b: 1 };
        expect(deterministicStringify(a)).toBe(deterministicStringify(b));
    });
});

describe("validateIssuedSnapshotV1", () => {
    it("accepts built content round-tripped through JSON", () => {
        const value = buildValid();
        const parsed = JSON.parse(deterministicStringify(value));
        expect(validateIssuedSnapshotV1(parsed).ok).toBe(true);
    });

    it("rejects float money values", () => {
        const value = JSON.parse(deterministicStringify(buildValid()));
        value.document.total = 120.0;
        expect(validateIssuedSnapshotV1(value)).toEqual({
            ok: false,
            error: "snapshot_money_not_decimal_string",
        });
    });

    it("rejects unsupported format versions", () => {
        const value = JSON.parse(deterministicStringify(buildValid()));
        value.format_version = "2";
        expect(validateIssuedSnapshotV1(value)).toEqual({
            ok: false,
            error: "snapshot_version_unsupported",
        });
    });
});

describe("snapshot rows", () => {
    const row = (id: string, documentId: string, createdAt: string): EvoluDocumentSnapshotRow => ({
        id,
        documentId,
        formatVersion: ISSUED_SNAPSHOT_FORMAT_VERSION,
        payloadJson: deterministicStringify(buildValid()),
        backfilled: null,
        createdAt,
    });

    it("picks the newest snapshot version for a document", () => {
        const rows = [
            row("s1", "doc-1", "2026-07-01T00:00:00Z"),
            row("s2", "doc-2", "2026-07-02T00:00:00Z"),
            row("s3", "doc-1", "2026-07-03T00:00:00Z"),
        ];
        expect(latestSnapshotRowForDocument(rows, "doc-1")?.id).toBe("s3");
        expect(latestSnapshotRowForDocument(rows, "doc-3")).toBeNull();
    });

    it("parses and validates a stored row", () => {
        const parsed = parseIssuedSnapshotRow(row("s1", "doc-1", "2026-07-01T00:00:00Z"));
        expect(parsed.ok).toBe(true);
        if (parsed.ok) {
            expect(parsed.value.document.number).toBe("20260071");
            expect(parsed.backfilled).toBe(false);
        }
    });

    it("rejects corrupted payload JSON", () => {
        const bad = { ...row("s1", "doc-1", "2026-07-01T00:00:00Z"), payloadJson: "{oops" };
        expect(parseIssuedSnapshotRow(bad)).toEqual({ ok: false, error: "snapshot_json_invalid" });
    });
});

describe("insertIssuedDocumentSnapshot", () => {
    it("inserts a validated, deterministically serialized row", () => {
        const insert = vi.fn().mockReturnValue({ ok: true, value: { id: "snap-1" } });
        const evolu = { insert } as unknown as Evolu<InvoicingLocalSchema>;

        const result = insertIssuedDocumentSnapshot(
            evolu,
            "doc-1" as DocumentId,
            buildValid(),
        );

        expect(result).toEqual({ ok: true });
        expect(insert).toHaveBeenCalledTimes(1);
        const [table, rowArg] = insert.mock.calls[0];
        expect(table).toBe("documentSnapshot");
        expect(rowArg.documentId).toBe("doc-1");
        expect(rowArg.formatVersion).toBe(ISSUED_SNAPSHOT_FORMAT_VERSION);
        expect(rowArg.backfilled).toBeNull();
        expect(JSON.parse(rowArg.payloadJson).document.number).toBe("20260071");
    });

    it("marks backfilled snapshots and reports persist failures", () => {
        const insert = vi.fn().mockReturnValue({ ok: false, error: "boom" });
        const evolu = { insert } as unknown as Evolu<InvoicingLocalSchema>;

        const result = insertIssuedDocumentSnapshot(
            evolu,
            "doc-1" as DocumentId,
            buildValid(),
            { backfilled: true },
        );

        expect(result).toEqual({ ok: false, error: "snapshot_persist_failed" });
        expect(insert.mock.calls[0][1].backfilled).toBe(1);
    });

    it("refuses invalid content without touching the database", () => {
        const insert = vi.fn();
        const evolu = { insert } as unknown as Evolu<InvoicingLocalSchema>;
        const broken = { ...buildValid(), lines: [] };

        const result = insertIssuedDocumentSnapshot(evolu, "doc-1" as DocumentId, broken);

        expect(result.ok).toBe(false);
        expect(insert).not.toHaveBeenCalled();
    });
});
