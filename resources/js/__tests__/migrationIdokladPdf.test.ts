import { afterEach, describe, expect, it, vi } from "vitest";
import { IdokladPdfError, parseIdokladPdfText, readIdokladPdfEvidence } from "@/evolu/migrationIdokladPdf";
import { readMigrationPdfTextPages } from "@/evolu/migrationIsdocPdf";
import type { MigrationPdfTextItem, MigrationPdfTextPage } from "@/evolu/migrationIsdocPdf";
import { syntheticIdokladPage, textItem } from "./fixtures/migration-idoklad-pdf/textPage";
// Real PDF bytes and the owned PDF.js worker are covered by
// integration/pdf/migrationIdokladPdf.spec.ts.
vi.mock("@/evolu/migrationIsdocPdf", () => ({ readMigrationPdfTextPages: vi.fn() }));
const read = (page = syntheticIdokladPage()) => parseIdokladPdfText([page]);
function edit(change: (items: MigrationPdfTextItem[]) => void): MigrationPdfTextPage {
    const page = syntheticIdokladPage(); const items = page.items.map(item => ({ ...item, transform: [...item.transform] }));
    change(items); return { ...page, items };
}
function replace(items: MigrationPdfTextItem[], x: number, y: number, str: string): void {
    const item = items.find(candidate => candidate.transform[4] === x && candidate.transform[5] === y);
    if (!item) throw Error("Missing synthetic field");
    Object.assign(item, { str });
}
afterEach(() => vi.restoreAllMocks());

describe("iDoklad native PDF strict text profile", () => {
    it("preserves complete invoice parties, dates, line and explicit recap amounts", () => {
        expect(read()).toEqual([{ dueDate: "2026-11-15", deliveryDate: "2026-11-01", invoice: {
            documentNumber: "FIX-TEST-1", issueDate: "2026-11-01", currency: "EUR",
            supplierRegistrationNumber: "00012345", buyerRegistrationNumber: "00054321", buyerName: "Synthetic Buyer",
            subtotal: "100.00", taxTotal: "23.00", total: "123.00", lines: [{
                name: "Synthetic service", description: null, quantity: "1.0000", unit: null,
                unitPrice: "100.0000", lineDiscountPercent: "0.00", taxRate: "23.00", lineTotal: "123.00",
            }],
        } }]);
    });
    it("accepts the provider's terminal colons on date headings without relaxing date values", () => {
        expect(read(edit(items => {
            for (const item of items) if (item.str.startsWith("Dátum ")) item.str += ":";
        }))[0]).toMatchObject({ dueDate: "2026-11-15", deliveryDate: "2026-11-01", invoice: { issueDate: "2026-11-01" } });
        expect(() => read(edit(items => replace(items, 44, 561, "Dátum: vystavenia")))).toThrow();
    });
    it("does not infer current payments from invoice-gross Na úhradu labels", () => {
        const result = read()[0];
        expect(result.paidAmount).toBeUndefined(); expect(result.payableAmount).toBeUndefined(); expect(result.settled).toBeUndefined();
    });
    it("preserves multiple independent one-page invoices", () => {
        expect(parseIdokladPdfText([syntheticIdokladPage("FIX-TEST-1"), syntheticIdokladPage("FIX-TEST-2")])).toHaveLength(2);
        expect(() => parseIdokladPdfText([syntheticIdokladPage(), syntheticIdokladPage()])).toThrow("idoklad_pdf_invalid");
    });
    it("accepts an explicit unit and preserves leading-zero registrations", () => {
        const invoice = read(edit(items => items.push(textItem("h", 269, 398, 7))))[0].invoice;
        expect(invoice.lines[0].unit).toBe("h"); expect(invoice.supplierRegistrationNumber).toBe("00012345");
    });
    it.each([
        [326, 398, "99,00"], [373, 398, "22"], [418, 398, "99,99"],
        [475, 398, "22,99"], [529, 398, "122,99"], [375, 272, "99,99 EUR"],
        [445, 272, "22,99 EUR"], [507, 272, "122,99 EUR"],
        [375, 254, "99,99 EUR"], [445, 254, "22,99 EUR"], [507, 254, "122,99 EUR"],
        [398, 476, "0,00 EUR"],
    ])("rejects inconsistent source money at %i/%i", (x, y, value) => {
        expect(() => read(edit(items => replace(items, x, y, value)))).toThrow();
    });
    it.each([
        [247, 398, "0"], [247, 398, "-1"], [247, 398, "0,00001"],
        [326, 398, "1e2"], [326, 398, "1 0 0"], [373, 398, "-1"], [373, 398, "101"],
        [112, 561, "31.02.2026"], [339, 632, "IČO: not-a-registration"],
    ])("rejects unsupported values at %i/%i", (x, y, value) => {
        expect(() => read(edit(items => replace(items, x, y, value)))).toThrow(IdokladPdfError);
    });
    it("rounds VAT from raw quantity times price rather than the rounded net", () => {
        const result = read(edit(items => {
            for (const [x, value] of [[247, "0,4"], [326, "0,06"], [418, "0,02"], [475, "0,01"], [529, "0,03"]] as const) replace(items, x, 398, value);
            for (const y of [272, 254]) for (const [x, value] of [[375, "0,02 EUR"], [445, "0,01 EUR"], [507, "0,03 EUR"]] as const) replace(items, x, y, value);
            replace(items, 398, 476, "0,03 EUR"); replace(items, 498, 206, "0,03 EUR");
        }))[0];
        expect(result.invoice).toMatchObject({ subtotal: "0.02", taxTotal: "0.01", total: "0.03" });
        expect(result.invoice.lines[0]).toMatchObject({ quantity: "0.4000", unitPrice: "0.0600", taxRate: "23.00", lineTotal: "0.03" });
    });
    it("preserves signed deductions with reconciled rate and invoice totals", () => {
        const result = read(edit(items => {
            items.push(textItem("Synthetic deduction", 45, 380, 120), textItem("1", 247, 380, 17),
                textItem("-10,00", 326, 380, 24), textItem("23", 373, 380, 12),
                textItem("-10,00", 418, 380, 27), textItem("-2,30", 475, 380, 28), textItem("-12,30", 529, 380, 35));
            for (const y of [272, 254]) for (const [x, value] of [[375, "90,00 EUR"], [445, "20,70 EUR"], [507, "110,70 EUR"]] as const) replace(items, x, y, value);
            replace(items, 398, 476, "110,70 EUR"); replace(items, 498, 206, "110,70 EUR");
        }))[0];
        expect(result.invoice).toMatchObject({ subtotal: "90.00", taxTotal: "20.70", total: "110.70" });
        expect(result.invoice.lines[1]).toMatchObject({ unitPrice: "-10.0000", lineTotal: "-12.30" });
    });
    it("preserves independently reconciled VAT-rate buckets", () => {
        const result = read(edit(items => {
            items.push(textItem("Synthetic lower VAT service", 45, 380, 160), textItem("1", 247, 380, 17),
                textItem("20,00", 326, 380, 24), textItem("10", 373, 380, 12),
                textItem("20,00", 418, 380, 27), textItem("2,00", 475, 380, 28), textItem("22,00", 529, 380, 35));
            for (const item of items) if (item.transform[5] === 254) item.transform = [...item.transform.slice(0, 5), 236];
            items.push(textItem("10 %", 285, 254, 30), textItem("20,00 EUR", 375, 254, 70), textItem("2,00 EUR", 445, 254, 60), textItem("22,00 EUR", 507, 254, 70));
            for (const [x, value] of [[375, "120,00 EUR"], [445, "25,00 EUR"], [507, "145,00 EUR"]] as const) replace(items, x, 236, value);
            replace(items, 398, 476, "145,00 EUR"); replace(items, 498, 206, "145,00 EUR");
        }))[0];
        expect(result.invoice).toMatchObject({ subtotal: "120.00", taxTotal: "25.00", total: "145.00" });
        expect(result.invoice.lines.map(line => line.taxRate)).toEqual(["23.00", "10.00"]);
    });
    it.each([
        { mode: "VAT", price: "1,49", rate: "23", net: "1,49", tax: "0,34", gross: "1,83", count: 2, totalNet: "2,98", totalTax: "0,68", totalGross: "3,66" },
        { mode: "fractional net", price: "0,014", rate: "0", net: "0,01", tax: "0,00", gross: "0,01", count: 3, totalNet: "0,03", totalTax: "0,00", totalGross: "0,03" },
    ])("preserves provider per-line $mode rounding in VAT recaps", example => {
        const page = edit(items => {
            for (const [x, value] of [[247, "1"], [326, example.price], [373, example.rate], [418, example.net], [475, example.tax], [529, example.gross]] as const) replace(items, x, 398, value);
            for (let index = 1; index < example.count; index++) {
                const y = 398 - index * 18;
                items.push(textItem(`Synthetic rounded item ${index + 1}`, 45, y, 150), textItem("1", 247, y, 17),
                    textItem(example.price, 326, y, 24), textItem(example.rate, 373, y, 12),
                    textItem(example.net, 418, y, 27), textItem(example.tax, 475, y, 28), textItem(example.gross, 529, y, 35));
            }
            replace(items, 285, 272, `${example.rate} %`);
            for (const y of [272, 254]) for (const [x, value] of [[375, example.totalNet], [445, example.totalTax], [507, example.totalGross]] as const) replace(items, x, y, `${value} EUR`);
            replace(items, 398, 476, `${example.totalGross} EUR`); replace(items, 498, 206, `${example.totalGross} EUR`);
        });
        const result = read(page)[0];
        expect(result.invoice).toMatchObject({ subtotal: example.totalNet.replace(",", "."), taxTotal: example.totalTax.replace(",", "."), total: example.totalGross.replace(",", ".") });
        expect(result.invoice.lines).toHaveLength(example.count);
        const tampered = { ...page, items: page.items.map(item => item.transform[4] === 445 && item.transform[5] === 272
            ? { ...item, str: "999,99 EUR" } : item) };
        expect(() => read(tampered)).toThrow("idoklad_pdf_totals");
    });
    it("rejects a negative VAT bucket even when another rate makes the invoice positive", () => {
        expect(() => read(edit(items => {
            items.push(textItem("Synthetic unsupported deduction", 45, 380, 175), textItem("1", 247, 380, 17),
                textItem("-10,00", 326, 380, 24), textItem("10", 373, 380, 12),
                textItem("-10,00", 418, 380, 27), textItem("-1,00", 475, 380, 28), textItem("-11,00", 529, 380, 35));
            for (const item of items) if (item.transform[5] === 254) item.transform = [...item.transform.slice(0, 5), 236];
            items.push(textItem("10 %", 285, 254, 30), textItem("-10,00 EUR", 375, 254, 70), textItem("-1,00 EUR", 445, 254, 60), textItem("-11,00 EUR", 507, 254, 70));
            for (const [x, value] of [[375, "90,00 EUR"], [445, "22,00 EUR"], [507, "112,00 EUR"]] as const) replace(items, x, 236, value);
            replace(items, 398, 476, "112,00 EUR"); replace(items, 498, 206, "112,00 EUR");
        }))).toThrow("idoklad_pdf_totals");
    });
    it("rejects per-line raw-gross inconsistency even when bucket and invoice totals balance", () => {
        expect(() => read(edit(items => {
            for (const [x, value] of [[247, "0,4"], [326, "0,01"], [373, "30"], [418, "0,00"], [475, "0,00"], [529, "0,00"]] as const) replace(items, x, 398, value);
            items.push(textItem("Synthetic balancing item", 45, 380, 140), textItem("0,6", 247, 380, 17),
                textItem("0,01", 326, 380, 24), textItem("30", 373, 380, 12),
                textItem("0,01", 418, 380, 27), textItem("0,00", 475, 380, 28), textItem("0,01", 529, 380, 35));
            replace(items, 285, 272, "30 %");
            for (const y of [272, 254]) for (const [x, value] of [[375, "0,01 EUR"], [445, "0,00 EUR"], [507, "0,01 EUR"]] as const) replace(items, x, y, value);
            replace(items, 398, 476, "0,01 EUR"); replace(items, 498, 206, "0,01 EUR");
        }))).toThrow("idoklad_pdf_totals");
    });
    it("rejects a missing numeric cell or an extra duplicate table cell", () => {
        expect(() => read(edit(items => items.splice(items.findIndex(item => item.transform[4] === 475 && item.transform[5] === 398), 1)))).toThrow();
        expect(() => read(edit(items => items.push(textItem("23", 373, 398, 12))))).toThrow();
    });
    it("does not silently ignore an incomplete additional numeric line", () => {
        expect(() => read(edit(items => items.push(textItem("Unconsumed line", 45, 370, 100), textItem("1", 247, 370, 10))))).toThrow();
    });
    it("rejects duplicate/conflicting identity, recap and table header candidates", () => {
        expect(() => read(edit(items => items.push(textItem("IČO: 00099999", 45, 740, 60))))).toThrow();
        expect(() => read(edit(items => items.push(textItem("Spolu", 287, 235, 35), textItem("100,00 EUR", 375, 235, 70), textItem("23,00 EUR", 445, 235, 60), textItem("123,00 EUR", 507, 235, 70))))).toThrow();
        expect(() => read(edit(items => items.push(...items.filter(item => item.transform[5] === 417).map(item => ({ ...item, transform: [...item.transform.slice(0, 5), 430] })))))).toThrow();
    });
    it("rejects duplicate rate summaries and unsupported currencies", () => {
        expect(() => read(edit(items => items.push(...items.filter(item => item.transform[5] === 272).map(item => ({ ...item, transform: [...item.transform.slice(0, 5), 280] })))))).toThrow();
        expect(() => read(edit(items => items.push(textItem("USD", 45, 100, 20))))).toThrow();
    });
    it.each(["Dobropis", "Zálohová faktúra", "Reverse charge", "Prenesenie daňovej povinnosti", "Zľava 10 %"])("rejects unsupported metadata %s", label => {
        expect(() => read(edit(items => items.push(textItem(label, 45, 100, 120))))).toThrow();
    });
    it("rejects rotated, skewed, overlapping, nonfinite or out-of-page text", () => {
        expect(() => read({ ...syntheticIdokladPage(), rotation: 90 })).toThrow();
        for (const mutate of [
            (item: MigrationPdfTextItem) => ({ ...item, transform: [8, 1, 0, 8, 45, 398] }),
            (item: MigrationPdfTextItem) => ({ ...item, transform: [8, 0, 0, 8, NaN, 398] }),
            (item: MigrationPdfTextItem) => ({ ...item, width: 800 }),
            (item: MigrationPdfTextItem) => ({ ...item, width: 0 }),
            (item: MigrationPdfTextItem) => ({ ...item, dir: "rtl" }),
        ]) expect(() => read(edit(items => { items[0] = mutate(items[0]); }))).toThrow();
    });
    it("bounds page, decoded item and character resources", () => {
        expect(() => parseIdokladPdfText([])).toThrow("idoklad_pdf_limit_exceeded");
        expect(() => parseIdokladPdfText(Array.from({ length: 51 }, () => syntheticIdokladPage()))).toThrow("idoklad_pdf_limit_exceeded");
        expect(() => read({ ...syntheticIdokladPage(), items: Array.from({ length: 5001 }, () => textItem("a", 45, 100)) })).toThrow();
        expect(() => read(edit(items => items.push(textItem("x".repeat(4001), 45, 100, 10))))).toThrow();
    });
    it("does not return parser details or source strings in controlled errors", () => {
        try { read(edit(items => replace(items, 326, 398, "SYNTHETIC-PRIVATE-SECRET"))); expect.fail("Expected rejection"); }
        catch (error) { expect(error).toBeInstanceOf(IdokladPdfError); expect(String(error)).not.toContain("SYNTHETIC-PRIVATE-SECRET"); }
    });
    it("delegates file reads to the isolated worker and forwards cancellation", async () => {
        const signal = new AbortController().signal; const bytes = new Uint8Array([1, 2]);
        vi.mocked(readMigrationPdfTextPages).mockResolvedValueOnce([syntheticIdokladPage()]);
        expect(await readIdokladPdfEvidence("synthetic.pdf", bytes, { signal })).toHaveLength(1);
        expect(readMigrationPdfTextPages).toHaveBeenCalledWith(bytes, { signal });
        vi.mocked(readMigrationPdfTextPages).mockRejectedValueOnce(Error("private source error"));
        await expect(readIdokladPdfEvidence("synthetic.pdf", bytes)).rejects.toThrow("idoklad_pdf_unreadable");
        await expect(readIdokladPdfEvidence("synthetic.txt", bytes)).rejects.toThrow("idoklad_pdf_unsupported");
    });
});

// Exercise the shared worker boundary independently of the pure template parser.
describe("bounded shared native PDF text reader", () => {
    async function harness(options: { ready?: boolean; pending?: boolean; count?: number; items?: MigrationPdfTextItem[]; workerUrl?: string } = {}) {
        vi.resetModules();
        const ports: { terminate: ReturnType<typeof vi.fn>; dispatchEvent: (event: Event) => boolean }[] = [];
        class Port extends EventTarget {
            terminate = vi.fn();
            constructor() {
                super(); ports.push(this);
                if (options.ready !== false) queueMicrotask(() => this.dispatchEvent(new MessageEvent("message", {
                    data: { action: "ready", sourceName: "worker", targetName: "main" },
                })));
            }
        }
        vi.stubGlobal("Worker", Port);
        const page = {
            rotate: 0, cleanup: vi.fn(), getViewport: () => ({ width: 595, height: 842 }),
            streamTextContent: vi.fn(() => new ReadableStream({ start(controller) {
                controller.enqueue({ items: options.items ?? syntheticIdokladPage().items }); controller.close();
            } })),
        };
        const document = { numPages: options.count ?? 1, getPage: vi.fn(async () => page), getAttachments: vi.fn(async () => []) };
        const task = { destroyed: false,
            promise: options.pending ? new Promise<never>(() => undefined) : Promise.resolve(document),
            destroy: vi.fn(async () => { task.destroyed = true; }),
        };
        const worker = { destroy: vi.fn() };
        const getDocument = vi.fn(() => task);
        vi.doMock("pdfjs-dist/legacy/build/pdf.mjs", () => ({ getDocument, PDFWorker: { create: () => worker }, VerbosityLevel: { ERRORS: 0 } }));
        vi.doMock("pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url", () => ({ default: options.workerUrl ?? "/synthetic-pdf-worker.js" }));
        const actual = await vi.importActual<typeof import("@/evolu/migrationIsdocPdf")>("@/evolu/migrationIsdocPdf");
        return { actual, ports, page, document, task, worker, getDocument };
    }
    afterEach(() => {
        vi.useRealTimers(); vi.unstubAllGlobals();
        vi.doUnmock("pdfjs-dist/legacy/build/pdf.mjs");
        vi.doUnmock("pdfjs-dist/legacy/build/pdf.worker.min.mjs?worker&url");
    });
    it("uses an owned same-origin worker, copied bytes, disabled external features and unconditional teardown", async () => {
        const h = await harness(); const bytes = new Uint8Array([1, 2, 3]);
        expect(await h.actual.readMigrationPdfTextPages(bytes)).toEqual([syntheticIdokladPage()]);
        const args = h.getDocument.mock.calls[0] as unknown as [{ data: Uint8Array } & Record<string, unknown>];
        expect(args[0]).toMatchObject({ disableWorkerRendering: true, disableFontFace: true, enableXfa: false,
            isEvalSupported: false, stopAtErrors: true, useWasm: false, useWorkerFetch: false });
        expect(args[0].data).not.toBe(bytes); expect(args[0].data).toEqual(bytes);
        expect(h.ports[0].terminate).toHaveBeenCalledOnce(); expect(h.worker.destroy).toHaveBeenCalledOnce(); expect(h.task.destroy).toHaveBeenCalledOnce();
    });
    it("leaves existing ISDOC attachment-only behavior unchanged", async () => {
        const h = await harness();
        expect(await h.actual.readMigrationIsdocEvidence("synthetic.pdf", new Uint8Array([1]))).toBeNull();
        expect(h.document.getAttachments).toHaveBeenCalledOnce(); expect(h.document.getPage).not.toHaveBeenCalled();
        expect(h.ports[0].terminate).toHaveBeenCalledOnce();
    });
    it("rejects foreign worker URLs before creating a port", async () => {
        const h = await harness({ workerUrl: "https://external.invalid/pdf-worker.js" });
        await expect(h.actual.readMigrationPdfTextPages(new Uint8Array([1]))).rejects.toThrow("migration_isdoc_unreadable");
        expect(h.ports).toHaveLength(0);
    });
    it("bounds declared pages and decoded text items", async () => {
        for (const options of [{ count: 51 }, { items: Array.from({ length: 5001 }, () => textItem("a", 45, 100)) },
            { items: [textItem("x".repeat(4001), 45, 100, 10)] }]) {
            const h = await harness(options);
            await expect(h.actual.readMigrationPdfTextPages(new Uint8Array([1]))).rejects.toThrow("migration_isdoc_unreadable");
            expect(h.ports[0].terminate).toHaveBeenCalledOnce();
        }
    });
    it("rejects pre-aborted reads without initializing an untrusted parser", async () => {
        const h = await harness(); const controller = new AbortController(); controller.abort();
        await expect(h.actual.readMigrationPdfTextPages(new Uint8Array([1]), { signal: controller.signal })).rejects.toThrow("migration_isdoc_unreadable");
        expect(h.getDocument).not.toHaveBeenCalled(); expect(h.ports).toHaveLength(0);
    });
    it("terminates a live pending document on cancellation", async () => {
        const h = await harness({ pending: true }); const controller = new AbortController();
        const promise = h.actual.readMigrationPdfTextPages(new Uint8Array([1]), { signal: controller.signal });
        const rejected = expect(promise).rejects.toThrow("migration_isdoc_unreadable");
        await vi.waitFor(() => expect(h.getDocument).toHaveBeenCalled()); controller.abort();
        await rejected; expect(h.ports[0].terminate).toHaveBeenCalledOnce(); expect(h.task.destroy).toHaveBeenCalledOnce();
    });
    it("terminates an initialized worker whose untrusted read exceeds six seconds", async () => {
        vi.useFakeTimers(); const h = await harness({ pending: true });
        const promise = h.actual.readMigrationPdfTextPages(new Uint8Array([1]));
        const rejected = expect(promise).rejects.toThrow("migration_isdoc_unreadable");
        await vi.waitFor(() => expect(h.getDocument).toHaveBeenCalled());
        await vi.advanceTimersByTimeAsync(6001); await rejected;
        expect(h.ports[0].terminate).toHaveBeenCalledOnce(); expect(h.task.destroy).toHaveBeenCalledOnce();
    });
    it("terminates a worker that never signals trusted readiness within fifteen seconds", async () => {
        vi.useFakeTimers(); const h = await harness({ ready: false });
        const promise = h.actual.readMigrationPdfTextPages(new Uint8Array([1]));
        const rejected = expect(promise).rejects.toThrow("migration_isdoc_unreadable");
        await vi.waitFor(() => expect(h.ports).toHaveLength(1));
        await vi.advanceTimersByTimeAsync(15_001); await rejected;
        expect(h.getDocument).not.toHaveBeenCalled(); expect(h.ports[0].terminate).toHaveBeenCalledOnce();
    });
});
