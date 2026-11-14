import { describe, expect, it, vi } from "vitest";
import { defineComponent, nextTick, ref } from "vue";
import { mount } from "@vue/test-utils";
import type { EvoluCompanyRow } from "@/evolu/companyMap";
import type { EvoluContactRow } from "@/evolu/contactMap";
import type { EvoluDocumentLineRow, EvoluDocumentRow } from "@/evolu/documentMap";
import type { CompanyId, DocumentId } from "@/evolu/schema";
import type { DocumentSavePayload } from "@/evolu/documentCrud";

const mocks = vi.hoisted(() => ({ support: {} as Record<string, unknown> }));
vi.mock("@/evolu/client", () => ({
    allCompaniesDetailQuery: "companies", allContactsQuery: "contacts", allDocumentLinesQuery: "lines",
    allDocumentsQuery: "documents", allNumberSeriesQuery: "series", allDocumentSnapshotsQuery: "snapshots",
    allDocumentEventsQuery: "events", allCompanyStockBalancesQuery: "balances", allCompanyStockItemsQuery: "items",
    allCompanyStockMovementsQuery: "movements", allCompanyWarehousesQuery: "warehouses",
}));
vi.mock("@/evolu/numberAllocatorBridge", () => ({ reserveIssueNumber: vi.fn(), confirmIssueNumber: vi.fn() }));
vi.mock("@/evolu/flags", () => ({ isInvoicingLocalFirst: () => true }));
vi.mock("@/composables/useLocalInvoiceDocument", () => ({ useLocalInvoiceDocumentSupport: () => mocks.support }));
vi.mock("@/store/stores", () => ({ useStoresStore: () => ({ stores: [], fetchStores: vi.fn() }) }));
vi.mock("@/evolu/companySettingsCrud", () => ({ resolveLocalEmailSettingsForBridge: () => ({}) }));
vi.mock("vue-router", () => ({
    useRoute: () => ({ params: { companyId: "synthetic-company", documentId: "document-1" }, meta: { documentKind: "invoice" }, name: "invoicing-invoice-show" }),
    useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));
vi.mock("@/services/api", () => ({ default: { get: vi.fn(), post: vi.fn() }, invoicingApi: {} }));

import { applyReservedNumberToLocalDocument, createLocalCreditNoteFromInvoice, saveLocalDocument } from "@/evolu/documentCrud";
import { evoluDocumentToApi } from "@/evolu/documentMap";
import { evoluCompanyToApi } from "@/evolu/companyMap";
import { evoluContactToApi } from "@/evolu/contactMap";
import { buildEphemeralSnapshot } from "@/evolu/ephemeralBridge";
import { useInvoiceDocument } from "@/composables/useInvoiceDocument";
import { useCompanyVatPolicy } from "@/composables/useCompanyVatPolicy";

const companyId = "synthetic-company" as CompanyId;
const company = {
    id: companyId, legalName: "Synthetic Supplier", jurisdiction: "us", vatPayer: 1,
    vatStatus: "payer", vatRateDefault: "8.875", country: "US", appSettingsJson: "{}",
} as EvoluCompanyRow;
const payload: DocumentSavePayload = {
    type: "invoice", company_contact_id: "", store_id: "", title: "Synthetic invoice",
    issue_date: "2026-11-14", delivery_date: "", due_date: "2026-11-28",
    variable_symbol: "", constant_symbol: "", specific_symbol: "", currency: "USD",
    discount_percent: 0, note_above_lines: "", note_footer: "", internal_note: "", pdf_locale: "en",
    pdf_show_signature: false, pdf_show_payment_info: false, payment_bank_enabled: true,
    payment_btc_enabled: false, tags: [], lines: [100, -20].map((unit_price, index) => ({
        name: index ? "Deduction" : "Service", description: null, quantity: 1,
        unit: "pcs", unit_price, line_discount_percent: 0, tax_rate: 8.875,
        company_stock_item_id: null, company_warehouse_id: null,
    })),
};
const options = {
    defaultVat: 8.875, lineTaxApplies: () => true, lineTaxRate: (line: { tax_rate: number }) => line.tax_rate,
    existingLines: [] as EvoluDocumentLineRow[],
    refreezeContext: { company: evoluCompanyToApi(company) as unknown as Record<string, unknown>, contact: null },
};

function fixture(supplier = company, buyer: EvoluContactRow | null = null) {
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
    const query = (name: unknown): unknown[] => ({
        documents: rows.document, lines: rows.documentLine, snapshots: rows.documentSnapshot, companies: [supplier], contacts: buyer ? [buyer] : [],
    })[String(name)] ?? [];
    const evolu = { insert: put, update: put, upsert: put, getQueryRows: query, loadQuery: async (name: unknown) => query(name) };
    const supplierApi = evoluCompanyToApi(supplier);
    const buyerApi = buyer ? evoluContactToApi(buyer) : null;
    const vatPolicy = useCompanyVatPolicy();
    const save = saveLocalDocument(evolu as never, companyId, { ...payload, company_contact_id: buyer?.id ?? "" }, {
        ...options,
        lineTaxApplies: () => vatPolicy.calculatesVatAmounts(supplierApi, buyerApi),
        lineTaxRate: line => vatPolicy.resolveLineTaxRate(supplierApi, buyerApi, line.tax_rate),
    });
    if (!save.ok) throw new Error(String(save.error));
    const document = rows.document[0] as unknown as EvoluDocumentRow;
    const lines = rows.documentLine as unknown as EvoluDocumentLineRow[];
    expect(applyReservedNumberToLocalDocument(evolu as never, document.id, companyId, "invoice", "20260071", [], null,
        { company: supplier, doc: document, lines, allDocuments: [document], contact: buyer }).ok).toBe(true);
    return { evolu, rows, document, lines, mutations, supplier, buyer };
}

async function editor(historical = false, source = fixture()) {
    if (historical) {
        Object.assign(source.document, { migrationFingerprint: "synthetic-history", total: "87.09" });
        source.lines[0].lineTotal = "108.86";
    }
    mocks.support = {
        evolu: source.evolu,
        documentRows: ref([source.document]),
        documentApi: (id: DocumentId) => evoluDocumentToApi(source.document, source.lines.filter(line => line.documentId === id)),
        refreshAll: vi.fn(),
    };
    let state!: ReturnType<typeof useInvoiceDocument>;
    const wrapper = mount(defineComponent({ setup() { state = useInvoiceDocument(); return () => null; } }));
    state.company.value = { ...evoluCompanyToApi(source.supplier), legal_name: "Later live supplier" };
    state.contacts.value = source.buyer ? [evoluContactToApi(source.buyer)] : [];
    await nextTick();
    await state.reloadDocument();
    return { state, source, wrapper };
}

describe("PR443 frontend source and snapshot regressions", () => {
    it("preserves 8.875% through local storage, issue and re-freeze", () => {
        const { rows, document, evolu, lines } = fixture();
        expect(document).toMatchObject({ subtotal: "80.00", taxTotal: "7.10", total: "87.10" });
        expect(lines.map(line => line.taxRate)).toEqual(["8.8750", "8.8750"]);
        const saved = saveLocalDocument(evolu as never, companyId, payload, {
            ...options, documentId: document.id, existingDocument: document, existingLines: lines,
        });
        expect(saved.ok).toBe(true);
        const snapshots = rows.documentSnapshot.map(row => JSON.parse(String(row.payloadJson)));
        expect(snapshots).toHaveLength(2);
        for (const snapshot of snapshots) {
            expect(snapshot.lines.map((line: { tax_rate: string }) => line.tax_rate)).toEqual(["8.8750", "8.8750"]);
            expect(snapshot.document.total).toBe("87.10");
        }
    });

    it("derives an issuable credit note with equivalent signed quantities and unchanged source", () => {
        const { rows, document, evolu, lines } = fixture();
        const original = JSON.stringify({ document, lines, snapshots: rows.documentSnapshot });
        const credit = createLocalCreditNoteFromInvoice(evolu as never, document.id, [document], lines, options);
        expect(credit.ok).toBe(true);
        if (!credit.ok) throw new Error(String(credit.error));
        const creditDocument = rows.document.find(row => row.id === credit.value.id) as unknown as EvoluDocumentRow;
        const creditLines = rows.documentLine.filter(row => row.documentId === credit.value.id) as unknown as EvoluDocumentLineRow[];
        expect(creditDocument).toMatchObject({ sourceDocumentId: document.id, subtotal: "80.00", taxTotal: "7.10", total: "87.10" });
        expect(creditLines[1]).toMatchObject({ quantity: "-1.0000", unitPrice: "20.0000", lineTotal: "-21.78" });
        expect(applyReservedNumberToLocalDocument(evolu as never, creditDocument.id, companyId, "credit_note", "CN20260001", [], null,
            { company, doc: creditDocument, lines: creditLines, allDocuments: [document, creditDocument], contact: null }).ok).toBe(true);
        expect(JSON.stringify({ document, lines: lines.filter(line => line.documentId === document.id), snapshots: rows.documentSnapshot.filter(row => row.documentId === document.id) })).toBe(original);
    });

    it.each(["company_stock_item_id", "company_warehouse_id"] as const)("rejects negative-price %s before outgoing privacy projection", field => {
        const linked = payload.lines.map(line => line.unit_price < 0 ? { ...line, [field]: "stock-link" } : line);
        expect(() => buildEphemeralSnapshot(options.refreezeContext.company, null, { type: "invoice" }, linked)).toThrow("deduction_stock_not_supported");
    });

    it("builds every current snapshot field from unsaved signed editor changes", async () => {
        const { state, wrapper } = await editor();
        state.form.lines[0].unit_price = 120;
        state.form.lines[1].name = "Edited deduction";
        state.form.discount_percent = 10;
        state.form.payment_bank_enabled = false;
        state.form.payment_btc_enabled = true;
        state.form.store_id = "new-store";
        const snapshot = state.buildCurrentEphemeralSnapshot();
        expect(snapshot.lines[0].unit_price).toBe(120);
        expect(snapshot.lines[1].name).toBe("Edited deduction");
        expect(snapshot.document).toMatchObject({ discount_percent: 10, payment_bank_enabled: false, payment_btc_enabled: true });
        expect(snapshot.store_id).toBe("new-store");
        expect(snapshot.company.legal_name).toBe("Synthetic Supplier");
        expect(snapshot.source_totals).toEqual({ subtotal: "90.00", tax_total: "7.98", total: "97.98", line_totals: ["130.65", "-21.78"] });
        expect(state.previewTotals.value.total).toBe(97.98);
        wrapper.unmount();
    });

    it("preserves unchanged migrated amounts and recalculates after source edits", async () => {
        const { state, wrapper } = await editor(true);
        expect(state.previewTotals.value.total).toBe(87.09);
        expect(state.previewForm.value.lines[0].historical_line_total).toBe(108.86);
        expect(state.buildCurrentEphemeralSnapshot().source_totals?.total).toBe("87.09");
        state.form.note_footer = "Unsaved note";
        state.form.payment_bank_enabled = false;
        expect(state.buildCurrentEphemeralSnapshot().document).toMatchObject({ note_footer: "Unsaved note", payment_bank_enabled: false });
        expect(state.buildCurrentEphemeralSnapshot().source_totals?.total).toBe("87.09");
        state.form.lines[0].unit_price = 120;
        expect(state.previewTotals.value.total).toBe(108.87);
        expect(state.previewForm.value.lines[0].historical_line_total).toBeUndefined();
        expect(state.lineTotalDisplay(state.form.lines[0])).toBe(130.65);
        expect(state.buildCurrentEphemeralSnapshot().source_totals?.total).toBe("108.87");
        wrapper.unmount();
    });

    it("calculates current signed source amounts against the forwarded frozen supplier VAT profile", async () => {
        const source = fixture({ ...company, jurisdiction: "eu_sk", country: "SK" });
        const { state, wrapper } = await editor(false, source);
        state.company.value = { ...state.company.value!, vat_payer: false, vat_status: "none" };
        state.form.lines[0].unit_price = 120;
        const snapshot = state.buildCurrentEphemeralSnapshot();
        expect(snapshot.company).toMatchObject({ jurisdiction: "eu_sk", vat_payer: true, vat_status: "payer" });
        expect(snapshot.source_totals).toEqual({ subtotal: "100.00", tax_total: "8.87", total: "108.87", line_totals: ["130.65", "-21.78"] });
        wrapper.unmount();
    });

    it("keeps frozen buyer reverse-charge policy when the live buyer becomes domestic", async () => {
        const buyer = { id: "synthetic-buyer", companyId, name: "Synthetic EU buyer", country: "CZ", vatId: "CZ12345678" } as EvoluContactRow;
        const source = fixture({ ...company, jurisdiction: "eu_sk", country: "SK" }, buyer);
        const { state, wrapper } = await editor(false, source);
        state.contacts.value = [{ ...evoluContactToApi(buyer), country: "SK", vat_id: null }];
        state.form.lines[0].unit_price = 120;
        const snapshot = state.buildCurrentEphemeralSnapshot();
        expect(snapshot.company).toMatchObject({ jurisdiction: "eu_sk", vat_status: "payer" });
        expect(snapshot.contact).toMatchObject({ country: "CZ", vat_id: "CZ12345678" });
        expect(snapshot.source_totals).toEqual({ subtotal: "100.00", tax_total: "0.00", total: "100.00", line_totals: ["120.00", "-20.00"] });
        wrapper.unmount();
    });
});
