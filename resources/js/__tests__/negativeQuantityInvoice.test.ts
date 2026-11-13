import { describe, expect, it, vi } from "vitest";
import { mount } from "@vue/test-utils";
import { createI18n } from "vue-i18n";
import type { EvoluCompanyRow } from "@/evolu/companyMap";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "@/evolu/documentMap";
import type { CompanyId, DocumentId } from "@/evolu/schema";
import type { DocumentSavePayload } from "@/evolu/documentCrud";
import { clearManualDeductionWarehouse, signedInvoiceError } from "@/evolu/signedInvoiceLines";
import InvoiceLivePreview from "@/components/invoicing/InvoiceLivePreview.vue";
import sk from "@/locales/sk.json";

vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "companies", allContactsQuery: "contacts", allDocumentLinesQuery: "lines",
    allDocumentsQuery: "documents", allNumberSeriesQuery: "series", allDocumentSnapshotsQuery: "snapshots",
    allCompanyStockBalancesQuery: "balances", allCompanyStockItemsQuery: "items",
    allCompanyStockMovementsQuery: "movements", allCompanyWarehousesQuery: "warehouses",
}));
const reserve = vi.hoisted(() => vi.fn());
vi.mock("@/evolu/numberAllocatorBridge", () => ({ reserveIssueNumber: reserve, confirmIssueNumber: vi.fn() }));
import { applyReservedNumberToLocalDocument, createLocalCreditNoteFromInvoice, issueLocalDocumentAsync, payloadFromApiDocument, saveLocalDocument } from "@/evolu/documentCrud";
import { evoluDocumentToApi } from "@/evolu/documentMap";
import { buildEphemeralSnapshot } from "@/evolu/ephemeralBridge";

const companyId = "synthetic-company" as CompanyId;
const company = {
    id: companyId, legalName: "Synthetic Supplier", jurisdiction: "eu_sk", vatPayer: 1,
    vatStatus: "payer", vatRateDefault: "20", country: "SK", appSettingsJson: "{}",
} as EvoluCompanyRow;
const payload: DocumentSavePayload = {
    type: "invoice", company_contact_id: "", store_id: "", title: "Synthetic invoice",
    issue_date: "2026-11-13", delivery_date: "", due_date: "2026-11-27",
    variable_symbol: "", constant_symbol: "", specific_symbol: "", currency: "EUR",
    discount_percent: 0, note_above_lines: "", note_footer: "", internal_note: "", pdf_locale: "sk",
    pdf_show_signature: false, pdf_show_payment_info: false, payment_bank_enabled: true,
    payment_btc_enabled: false, tags: [], lines: [100, 15].map((unit_price, index) => ({
        name: index ? "Deduction" : "Service", description: null, quantity: index ? -1 : 1,
        unit: "pcs", unit_price, line_discount_percent: 0, tax_rate: 20,
        company_stock_item_id: null, company_warehouse_id: null,
    })),
};
const options = {
    defaultVat: 20, lineTaxApplies: () => true, lineTaxRate: (line: { tax_rate: number }) => line.tax_rate,
    existingLines: [] as EvoluDocumentLineRow[],
    refreezeContext: { company: { legal_name: "Synthetic Supplier", jurisdiction: "eu_sk", vat_status: "payer", vat_payer: true }, contact: null },
};

function memoryEvolu() {
    const rows: Record<string, Record<string, unknown>[]> = { document: [], documentLine: [], documentSnapshot: [] };
    const mutations: string[] = [];
    const put = (table: string, value: Record<string, unknown>) => {
        mutations.push(table);
        const list = rows[table] ??= [];
        const id = String(value.id ?? `${table}-${list.length + 1}`);
        const existing = list.find(row => row.id === id);
        if (existing) Object.assign(existing, value);
        else list.push({ ...value, id });
        return { ok: true as const, value: { id } };
    };
    const query = (name: unknown): unknown[] => {
        const sources: Record<string, unknown[]> = { documents: rows.document, lines: rows.documentLine, snapshots: rows.documentSnapshot, companies: [company] };
        return sources[String(name)] ?? [];
    };
    return { rows, mutations, insert: put, update: put, upsert: put, getQueryRows: query, loadQuery: async (name: unknown) => query(name) };
}

describe("negative quantity deduction invoices", () => {
    it("saves -1 x 15, issues a frozen document and forwards exact source totals to PDF/eFaktura", () => {
        const evolu = memoryEvolu();
        const saved = saveLocalDocument(evolu as never, companyId, payload, options);
        expect(saved.ok).toBe(true);
        if (!saved.ok) throw new Error(String(saved.error));
        const id = saved.value.id as DocumentId;
        const document = evolu.rows.document[0] as unknown as EvoluDocumentRow;
        const lines = evolu.rows.documentLine as unknown as EvoluDocumentLineRow[];
        expect(document).toMatchObject({ subtotal: "85.00", taxTotal: "17.00", total: "102.00" });
        expect(lines[1]).toMatchObject({ quantity: "-1.0000", unitPrice: "15.0000", lineTotal: "-18.00", companyStockItemId: null, companyWarehouseId: null });
        const issued = applyReservedNumberToLocalDocument(evolu as never, id, companyId, "invoice", "20260071", [], null,
            { company, doc: document, lines, allDocuments: [document], contact: null });
        expect(issued.ok).toBe(true);
        expect(document.status).toBe("issued");
        const frozen = JSON.parse(String(evolu.rows.documentSnapshot[0].payloadJson));
        expect(frozen.lines[1]).toMatchObject({ quantity: "-1.0000", unit_price: "15.0000", line_total: "-18.00" });
        const api = evoluDocumentToApi(document, lines);
        const snapshot = buildEphemeralSnapshot(frozen.company, frozen.contact, { ...api, ...frozen.document, lines: frozen.lines }, payload.lines);
        expect(snapshot.lines[1]).toMatchObject({ quantity: -1, unit_price: 15 });
        expect(snapshot.source_totals).toEqual({ subtotal: "85.00", tax_total: "17.00", total: "102.00", line_totals: ["120.00", "-18.00"] });
        expect(snapshot.company.app_settings).toMatchObject({ rounding_method: "per_line", us_sales_tax_provider: "manual" });
        expect(evolu.rows.companyStockMovement ?? []).toEqual([]);
    });

    it.each(["company_stock_item_id", "company_warehouse_id"] as const)("rejects raw negative quantity linked through %s before save or snapshot projection", field => {
        const evolu = memoryEvolu();
        const linked = { ...payload, lines: [payload.lines[0], { ...payload.lines[1], [field]: "linked-stock" }] };
        expect(saveLocalDocument(evolu as never, companyId, linked, options)).toEqual({ ok: false, error: "deduction_stock_not_supported" });
        expect(evolu.mutations).toEqual([]);
        expect(() => buildEphemeralSnapshot(options.refreezeContext.company, null, { type: "invoice" }, linked.lines)).toThrow("deduction_stock_not_supported");
    });

    it("clears an inherited warehouse for manual input but preserves actual stock links", () => {
        const manual = { ...payload.lines[1], company_warehouse_id: "default-warehouse" };
        clearManualDeductionWarehouse(manual, -1);
        expect(manual.company_warehouse_id).toBeNull();
        expect(saveLocalDocument(memoryEvolu() as never, companyId, { ...payload, lines: [payload.lines[0], manual] }, options).ok).toBe(true);
        const stock = { ...payload.lines[1], company_stock_item_id: "stock-item", company_warehouse_id: "default-warehouse" };
        clearManualDeductionWarehouse(stock, -1);
        expect(stock.company_stock_item_id).toBe("stock-item");
        expect(stock.company_warehouse_id).toBe("default-warehouse");
    });

    it("creates and issues a credit note for the deduction invoice without rewriting its source", () => {
        const evolu = memoryEvolu();
        const saved = saveLocalDocument(evolu as never, companyId, payload, options);
        if (!saved.ok) throw new Error(String(saved.error));
        const source = evolu.rows.document[0] as unknown as EvoluDocumentRow;
        const sourceLines = evolu.rows.documentLine as unknown as EvoluDocumentLineRow[];
        expect(applyReservedNumberToLocalDocument(evolu as never, source.id, companyId, "invoice", "20260071", [], null,
            { company, doc: source, lines: sourceLines, allDocuments: [source], contact: null }).ok).toBe(true);
        const originalDocument = JSON.stringify(source);
        const originalLines = JSON.stringify(sourceLines.filter(line => line.documentId === source.id));
        const originalSnapshot = String(evolu.rows.documentSnapshot[0].payloadJson);

        const copied = createLocalCreditNoteFromInvoice(evolu as never, source.id, [source], sourceLines, options);
        expect(copied.ok).toBe(true);
        if (!copied.ok) throw new Error(String(copied.error));
        const credit = evolu.rows.document.find(row => row.id === copied.value.id) as unknown as EvoluDocumentRow;
        const lines = (evolu.rows.documentLine as unknown as EvoluDocumentLineRow[]).filter(line => line.documentId === credit.id);
        expect(credit).toMatchObject({ documentType: "credit_note", sourceDocumentId: source.id, total: "102.00", paymentBtcEnabled: 0 });
        expect(lines[1]).toMatchObject({ quantity: "-1.0000", unitPrice: "15.0000", lineTotal: "-18.00" });
        expect(applyReservedNumberToLocalDocument(evolu as never, credit.id, companyId, "credit_note", "CN20260001", [], null,
            { company, doc: credit, lines, allDocuments: [source, credit], contact: null }).ok).toBe(true);
        const frozen = JSON.parse(String(evolu.rows.documentSnapshot.find(row => row.documentId === credit.id)?.payloadJson));
        const api = evoluDocumentToApi(credit, lines, [source, credit]);
        const bridge = buildEphemeralSnapshot(frozen.company, frozen.contact, { ...api, ...frozen.document, lines: frozen.lines }, payloadFromApiDocument(api).lines);
        expect(bridge.document.type).toBe("credit_note");
        expect(bridge.lines[1]).toMatchObject({ quantity: -1, unit_price: 15 });
        expect(bridge.source_totals).toEqual({ subtotal: "85.00", tax_total: "17.00", total: "102.00", line_totals: ["120.00", "-18.00"] });
        expect(JSON.stringify(source)).toBe(originalDocument);
        expect(JSON.stringify(sourceLines.filter(line => line.documentId === source.id))).toBe(originalLines);
        expect(String(evolu.rows.documentSnapshot[0].payloadJson)).toBe(originalSnapshot);
    });

    it("keeps credit-note quantities nonzero and negative unit prices unsupported", () => {
        const evolu = memoryEvolu();
        expect(saveLocalDocument(evolu as never, companyId, { ...payload, type: "credit_note", lines: [{ ...payload.lines[0], quantity: 0 }] }, options))
            .toEqual({ ok: false, error: "nonzero_quantity_required" });
        expect(saveLocalDocument(evolu as never, companyId, { ...payload, type: "credit_note", lines: [payload.lines[0], { ...payload.lines[1], quantity: 1, unit_price: -15 }] }, options))
            .toEqual({ ok: false, error: "unsupported_negative_lines" });
        expect(evolu.mutations).toEqual([]);
    });

    it.each([[0, 15, "nonzero_quantity_required"], [-1, -15, "unsupported_negative_lines"], [-1, 0, "unsupported_negative_lines"]] as const)(
        "rejects quantity %s with price %s", (quantity, unit_price, error) => {
            const evolu = memoryEvolu();
            expect(saveLocalDocument(evolu as never, companyId, { ...payload, lines: [{ ...payload.lines[1], quantity, unit_price }] }, options)).toEqual({ ok: false, error });
            expect(evolu.mutations).toEqual([]);
        });

    it("rejects stock links from persisted rows before a number is reserved", async () => {
        const evolu = memoryEvolu();
        saveLocalDocument(evolu as never, companyId, payload, options);
        evolu.rows.documentLine[1].companyWarehouseId = "warehouse";
        const result = await issueLocalDocumentAsync(evolu as never, evolu.rows.document[0].id as DocumentId, company);
        expect(result).toEqual({ ok: false, error: "deduction_stock_not_supported" });
        expect(reserve).not.toHaveBeenCalled();
        expect(evolu.rows.documentSnapshot).toEqual([]);
    });

    it("still refuses nonpositive totals and negative VAT buckets at issue or issued edit", () => {
        const line = { quantity: -1, unitPrice: 15, discount: 0, taxRate: 20 };
        expect(signedInvoiceError("invoice", [line], options.refreezeContext.company, { subtotal: -15, taxTotal: -3, total: -18 })).toBe("positive_total_required");
        expect(signedInvoiceError("invoice", [{ quantity: 1, unitPrice: 100, discount: 0, taxRate: 0 }, line], options.refreezeContext.company,
            { subtotal: 85, taxTotal: -3, total: 82 })).toBe("positive_total_required");
        const evolu = memoryEvolu();
        saveLocalDocument(evolu as never, companyId, payload, options);
        const document = evolu.rows.document[0] as unknown as EvoluDocumentRow;
        document.status = "issued";
        expect(saveLocalDocument(evolu as never, companyId, { ...payload, lines: [payload.lines[1]] },
            { ...options, documentId: document.id, existingDocument: document })).toEqual({ ok: false, error: "positive_total_required" });
    });

    it("renders negative quantity and canonical row rounding in the live preview", () => {
        const wrapper = mount(InvoiceLivePreview, {
            props: {
                company: { legal_name: "Supplier", jurisdiction: "eu_sk", vat_status: "payer", vat_payer: true }, selectedContact: null,
                form: { ...payload, number: "20260071", lines: [
                    { ...payload.lines[0], name: "Fractional service", description: "", quantity: 0.5, unit_price: 1.01, tax_rate: 23 },
                    { ...payload.lines[1], name: "Deduction", description: "", quantity: -1, unit_price: 0.01, tax_rate: 23 },
                ] }, documentStatus: "draft", totals: { subtotal: 0.50, tax: 0.12, total: 0.62 },
            },
            global: { plugins: [createI18n({ legacy: false, locale: "sk", messages: { sk } })] },
        });
        expect(wrapper.findAll("tr").find(row => row.text().includes("Fractional service"))?.text()).toContain("0,63");
        expect(wrapper.findAll("tr").find(row => row.text().includes("Deduction"))?.text()).toContain("-1");
    });
});
