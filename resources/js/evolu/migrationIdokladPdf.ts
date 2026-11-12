import type { MigrationStructuredInvoice, MigrationStructuredLine } from "./migrationIsdoc";
import { readMigrationPdfTextPages, type MigrationPdfTextPage } from "./migrationIsdocPdf";
import { MIGRATION_MAX_FILE_BYTES } from "./migrationArchiveValues";

export type IdokladPdfErrorCode = "idoklad_pdf_invalid" | "idoklad_pdf_unsupported"
    | "idoklad_pdf_totals" | "idoklad_pdf_limit_exceeded" | "idoklad_pdf_unreadable";
export class IdokladPdfError extends Error {
    constructor(readonly code: IdokladPdfErrorCode) { super(code); this.name = "IdokladPdfError"; }
}
export type IdokladPdfInvoice = {
    invoice: MigrationStructuredInvoice;
    dueDate: string;
    deliveryDate: string;
    paidAmount?: string;
    payableAmount?: string;
    settled?: true;
};

type Cell = { text: string; x: number; y: number; width: number; height: number };
type Row = { y: number; cells: Cell[] };
const HEADERS = ["Označenie dodávky", "Počet", "m. j.", "Cena za m.j.", "DPH %", "Bez DPH", "DPH", "Spolu"];
const MAX_MINOR = 99_999_999_999_999n;
const RAW_NET_CENT = 1_000_000n;
const RAW_TAX_CENT = 10_000_000_000n;
const EPSILON = 1.5;
function fail(code: IdokladPdfErrorCode = "idoklad_pdf_unsupported"): never { throw new IdokladPdfError(code); }
function norm(value: string): string { return value.normalize("NFC").replace(/\s+/g, " ").trim(); }
function folded(value: string): string { return norm(value).toLocaleLowerCase("sk"); }
function fieldLabel(value: string): string { return folded(value.replace(/:\s*$/, "")); }
function unique<T>(values: readonly T[]): T { if (values.length !== 1) fail(); return values[0]; }
function round(value: bigint, divisor: bigint): bigint {
    const absolute = value < 0n ? -value : value;
    const result = (absolute + divisor / 2n) / divisor;
    return value < 0n ? -result : result;
}
function fixed(value: bigint, scale: number): string {
    const absolute = value < 0n ? -value : value, divisor = 10n ** BigInt(scale);
    return `${value < 0n ? "-" : ""}${absolute / divisor}.${String(absolute % divisor).padStart(scale, "0")}`;
}
function bounded(value: bigint): bigint { if (value < -MAX_MINOR || value > MAX_MINOR) fail("idoklad_pdf_totals"); return value; }
function decimal(value: string, scale: number): bigint {
    const spaced = norm(value);
    if (!/^-?(?:\d{1,12}|\d{1,3}(?: \d{3}){1,3})(?:[,.]\d{1,12})?$/.test(spaced)) fail("idoklad_pdf_totals");
    const normalized = spaced.replace(/ /g, "");
    if (normalized.includes(",") && normalized.includes(".")) fail("idoklad_pdf_totals");
    const match = /^(-?)(\d{1,12})(?:[,.](\d{1,12}))?$/.exec(normalized);
    if (!match) fail("idoklad_pdf_totals");
    const fraction = (match[3] ?? "").replace(/0+$/, "");
    if (fraction.length > scale) fail("idoklad_pdf_totals");
    const magnitude = BigInt(match[2]) * 10n ** BigInt(scale) + BigInt(fraction.padEnd(scale, "0") || "0");
    return match[1] ? -magnitude : magnitude;
}
function monetary(value: string, currency: string): bigint {
    const normalized = norm(value);
    const match = /^(-?\d[\d\s.,]*)(?:\s*(EUR|€|CZK|Kč|USD))?$/.exec(normalized);
    if (!match) fail("idoklad_pdf_totals");
    if (match[2] && currencyOf(match[2]) !== currency) fail("idoklad_pdf_totals");
    return bounded(decimal(match[1], 2));
}
function currencyOf(value: string): string {
    if (value === "€" || value === "EUR") return "EUR";
    if (value === "Kč" || value === "CZK") return "CZK";
    if (value === "USD") return "USD";
    return fail();
}
function date(value: string): string {
    const match = /^(\d{1,2})\.(\d{1,2})\.(\d{4})$/.exec(value.replace(/\s/g, ""));
    if (!match || Number(match[3]) < 1000) fail("idoklad_pdf_invalid");
    const result = `${match[3]}-${match[2].padStart(2, "0")}-${match[1].padStart(2, "0")}`;
    const parsed = new Date(result + "T00:00:00.000Z");
    if (!Number.isFinite(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== result) fail("idoklad_pdf_invalid");
    return result;
}
function rowsFromItems(page: MigrationPdfTextPage): Row[] {
    if (!page || typeof page !== "object" || !Number.isFinite(page.width) || !Number.isFinite(page.height) || page.width < 500 || page.width > 900
        || page.height < 650 || page.height > 1300 || page.rotation !== 0 || !Array.isArray(page.items)
        || page.items.length > 5000) fail();
    let characters = 0;
    const cells: Cell[] = [];
    for (const item of page.items) {
        if (!item || typeof item !== "object" || typeof item.str !== "string" || item.str.length > 4000 || !Array.isArray(item.transform)
            || item.transform.length !== 6 || !item.transform.every(Number.isFinite)
            || ![item.width, item.height].every(value => Number.isFinite(value) && value >= 0 && value <= 1000)
            || Math.abs(item.transform[1]) > 0.00001 || Math.abs(item.transform[2]) > 0.00001
            || item.transform[0] <= 0 || item.transform[3] <= 0 || item.dir !== "ltr") fail();
        characters += item.str.length;
        if (characters > 50_000) fail("idoklad_pdf_limit_exceeded");
        if (!item.str.trim()) continue;
        if (item.width <= 0 || item.height <= 0) fail();
        const text: string = item.str;
        if (Array.from(text).some(char => { const code = char.charCodeAt(0); return (code < 32 && ![9, 10, 13].includes(code)) || code === 127; })) fail();
        const x = item.transform[4], y = item.transform[5];
        if (x < 0 || y < 0 || x + item.width > page.width + EPSILON || y > page.height + EPSILON) fail();
        cells.push({ text: norm(item.str), x, y, width: item.width, height: item.height });
    }
    const rows: Row[] = [];
    for (const cell of cells.sort((a, b) => b.y - a.y || a.x - b.x)) {
        let row = rows.find(candidate => Math.abs(candidate.y - cell.y) <= EPSILON);
        if (!row) { row = { y: cell.y, cells: [] }; rows.push(row); }
        row.cells.push(cell);
    }
    for (const row of rows) {
        row.cells.sort((a, b) => a.x - b.x);
        for (let i = 1; i < row.cells.length; i++) {
            if (row.cells[i - 1].x + row.cells[i - 1].width > row.cells[i].x + EPSILON) fail();
        }
    }
    return rows;
}
function labelledDate(rows: readonly Row[], label: string): string {
    const found = unique(rows.flatMap(row => row.cells.filter(cell => fieldLabel(cell.text) === fieldLabel(label))));
    const row = unique(rows.filter(candidate => candidate.cells.includes(found)));
    const nextLabel = row.cells.filter(cell => cell.x > found.x && /^Dátum /.test(cell.text)).sort((a, b) => a.x - b.x)[0];
    return date(unique(row.cells.filter(cell => cell.x > found.x + found.width - 2
        && (!nextLabel || cell.x < nextLabel.x) && /^\d{1,2}\./.test(cell.text))).text);
}
function registrationIn(rows: readonly Row[], label: Cell, lower: number, right: number): string {
    const cell = unique(rows.flatMap(row => row.y < label.y - EPSILON && row.y > lower + EPSILON
        ? row.cells.filter(candidate => candidate.x >= label.x - 4 && candidate.x < right && /^IČO\s*:/i.test(candidate.text)) : []));
    const match = /^IČO\s*:\s*([\d\s]+)$/i.exec(cell.text);
    if (!match || !/^\d{8}$/.test(match[1].replace(/\s/g, ""))) fail("idoklad_pdf_invalid");
    return match[1].replace(/\s/g, "");
}
function readPage(page: MigrationPdfTextPage): IdokladPdfInvoice {
    const rows = rowsFromItems(page), all = rows.flatMap(row => row.cells);
    if (all.some(cell => /dobropis|zálohová|prenesenie daňovej|reverse charge|vyúčtovacia|zľava\s*\d/i.test(cell.text))) fail();
    const header = unique(rows.filter(row => row.cells.length === HEADERS.length
        && row.cells.every((cell, index) => folded(cell.text) === folded(HEADERS[index]))));
    const recapHeader = unique(rows.filter(row => row.y < header.y - 10
        && ["Základ", "Výška DPH", "Spolu"].every(label => row.cells.some(cell => fieldLabel(cell.text) === fieldLabel(label)))));
    const recapNet = unique(recapHeader.cells.filter(cell => folded(cell.text) === "základ"));
    const recapGross = unique(recapHeader.cells.filter(cell => folded(cell.text) === "spolu"));
    const totalRow = unique(rows.filter(row => row.y < recapHeader.y - 3 && row.cells.length === 4
        && folded(row.cells[0].text) === "spolu" && row.cells[0].x < recapNet.x));
    const currencyTokens = all.flatMap(cell => (cell.text.match(/(?:^|\s)(?:EUR|CZK|USD|€|Kč)(?=\s|$)/g) ?? []).map(token => token.trim()));
    const currencies = [...new Set(currencyTokens.map(currencyOf))];
    const currency = unique(currencies);
    const [subtotal, taxTotal, total] = totalRow.cells.slice(1).map(cell => monetary(cell.text, currency));
    if (subtotal <= 0n || total <= 0n || taxTotal < 0n || subtotal + taxTotal !== total) fail("idoklad_pdf_totals");
    if (totalRow.cells[1].x > recapNet.x + recapNet.width || totalRow.cells[3].x > recapGross.x + recapGross.width) fail();

    const supplier = unique(all.filter(cell => folded(cell.text) === "dodávateľ"));
    const buyer = unique(all.filter(cell => folded(cell.text) === "odberateľ"));
    if (supplier.x >= buyer.x || supplier.y <= buyer.y || buyer.y <= header.y) fail();
    const issueDate = labelledDate(rows, "Dátum vystavenia");
    const deliveryDate = labelledDate(rows, "Dátum dodania");
    const dueDate = labelledDate(rows, "Dátum splatnosti");
    const dateLabel = unique(all.filter(cell => fieldLabel(cell.text) === "dátum vystavenia"));
    const supplierRegistrationNumber = registrationIn(rows, supplier, buyer.y, buyer.x - 5);
    const buyerRegistrationNumber = registrationIn(rows, buyer, dateLabel.y, page.width);
    const nameRows = rows.filter(row => row.y < buyer.y - 3 && row.y > dateLabel.y
        && row.cells.some(cell => Math.abs(cell.x - buyer.x) < 4 && !/^(IČO|DIČ|IČ DPH)\b/i.test(cell.text)));
    const buyerName = unique(nameRows[0]?.cells.filter(cell => Math.abs(cell.x - buyer.x) < 4) ?? []).text;
    if (!buyerName || buyerName.length > 255) fail();
    const invoiceHeading = unique(rows.filter(row => row.y > buyer.y && row.cells.some(cell => /^Faktúra\b/i.test(cell.text))));
    const documentNumber = unique(invoiceHeading.cells.filter(cell => /^(?=.*\d)[\p{L}\d/_-]{1,64}$/u.test(cell.text))).text;

    const centers = header.cells.map(cell => cell.x + cell.width / 2);
    const boundaries = centers.slice(1, -1).map((center, index) => (center + centers[index + 2]) / 2);
    const quantityLeft = header.cells[1].x - 8;
    const itemRows = rows.filter(row => row.y < header.y - 3 && row.y > recapHeader.y + 3);
    const lines: MigrationStructuredLine[] = [];
    const buckets = new Map<bigint, { net: bigint; tax: bigint; gross: bigint }>();
    let netSum = 0n, taxSum = 0n, grossSum = 0n;
    let lastItemTextY: number | null = null;
    for (const row of itemRows) {
        const amounts = row.cells.filter(cell => cell.x >= quantityLeft);
        if (!amounts.length) {
            if (row.cells.some(cell => /\d/.test(cell.text)) || !lines.length) fail();
            // Only adjacent, left-column text may continue a wrapped item name.
            if (lastItemTextY !== null && lastItemTextY - row.y <= 14) {
                const name = `${lines[lines.length - 1].name} ${row.cells.map(cell => cell.text).join(" ")}`;
                if (name.length > 255) fail();
                lines[lines.length - 1] = { ...lines[lines.length - 1], name };
                lastItemTextY = row.y;
            } else if (lastItemTextY !== null && lastItemTextY - row.y <= 28) fail();
            continue;
        }
        const columns = Array.from({ length: 7 }, () => [] as Cell[]);
        for (const cell of amounts) {
            const center = cell.x + cell.width / 2;
            let index = boundaries.findIndex(boundary => center < boundary);
            if (index === -1) index = 6;
            columns[index].push(cell);
        }
        const names = row.cells.filter(cell => cell.x < quantityLeft);
        const name = names.map(cell => cell.text).join(" ");
        if (!name || name.length > 255 || names.some(cell => cell.x + cell.width > quantityLeft + EPSILON)
            || columns.some((column, index) => index !== 1 && column.length !== 1) || columns[1].length > 1) fail();
        const quantity = decimal(columns[0][0].text, 4), price = decimal(columns[2][0].text, 4);
        const rate = decimal(columns[3][0].text.replace(/%$/, ""), 2);
        const net = monetary(columns[4][0].text, currency), tax = monetary(columns[5][0].text, currency), gross = monetary(columns[6][0].text, currency);
        if (quantity <= 0n || quantity >= 100_000_000_000_000n || rate < 0n || rate > 10_000n) fail();
        const rawNet = quantity * price, rawTax = rawNet * rate;
        if (round(rawNet, RAW_NET_CENT) !== net || round(rawTax, RAW_TAX_CENT) !== tax || net + tax !== gross
            || round(rawNet * 10_000n + rawTax, RAW_TAX_CENT) !== gross) fail("idoklad_pdf_totals");
        netSum = bounded(netSum + net); taxSum = bounded(taxSum + tax); grossSum = bounded(grossSum + gross);
        const bucket = buckets.get(rate) ?? { net: 0n, tax: 0n, gross: 0n };
        bucket.net += net; bucket.tax += tax; bucket.gross += gross;
        buckets.set(rate, bucket);
        const unit = columns[1][0]?.text ?? null;
        if (unit && unit.length > 32) fail();
        lastItemTextY = row.y;
        lines.push({ name, description: null, quantity: fixed(quantity, 4), unit, unitPrice: fixed(price, 4), lineDiscountPercent: "0.00", taxRate: fixed(rate, 2), lineTotal: fixed(gross, 2) });
        if (lines.length > 500) fail("idoklad_pdf_limit_exceeded");
    }
    if (!lines.length || netSum !== subtotal || taxSum !== taxTotal || grossSum !== total) fail("idoklad_pdf_totals");
    const recaps = rows.filter(row => row.y < recapHeader.y - 3 && row.y > totalRow.y + 3);
    const seenRates = new Set<bigint>();
    for (const row of recaps) {
        if (row.cells.length !== 4) fail();
        const rate = decimal(row.cells[0].text.replace(/%$/, ""), 2), bucket = buckets.get(rate);
        if (!bucket || seenRates.has(rate)) fail("idoklad_pdf_totals");
        seenRates.add(rate);
        const [net, tax, gross] = row.cells.slice(1).map(cell => monetary(cell.text, currency));
        // The provider prints sums of individually rounded lines, not a second
        // rounding of the combined raw base or VAT amount.
        if (bucket.net < 0n || bucket.tax < 0n || net !== bucket.net || tax !== bucket.tax || gross !== bucket.gross) fail("idoklad_pdf_totals");
    }
    if (seenRates.size !== buckets.size) fail("idoklad_pdf_totals");
    const result: IdokladPdfInvoice = { invoice: { documentNumber, issueDate, currency, supplierRegistrationNumber,
        buyerRegistrationNumber, buyerName, subtotal: fixed(subtotal, 2), taxTotal: fixed(taxTotal, 2), total: fixed(total, 2), lines }, dueDate, deliveryDate };
    // In this provider profile “Na úhradu” is the original invoice gross,
    // including on later-paid PDFs. It is never a current payment balance.
    const grossLabel = unique(all.filter(cell => folded(cell.text.replace(/:$/, "")) === "na úhradu" && cell.y > header.y));
    const labelCenter = grossLabel.x + grossLabel.width / 2;
    const grossPrint = unique(all.filter(cell => cell.y < grossLabel.y - 5 && cell.y > grossLabel.y - 25
        && Math.abs(cell.x + cell.width / 2 - labelCenter) < 40 && /(?:EUR|€|CZK|Kč|USD)$/.test(cell.text)));
    if (monetary(grossPrint.text, currency) !== total) fail("idoklad_pdf_totals");
    const footerRow = unique(rows.filter(row => row.y < totalRow.y - 3
        && row.cells.some(cell => folded(cell.text.replace(/:$/, "")) === "spolu na úhradu")));
    const footerLabel = unique(footerRow.cells.filter(cell => folded(cell.text.replace(/:$/, "")) === "spolu na úhradu"));
    const footerGross = unique(footerRow.cells.filter(cell => cell.x > footerLabel.x + footerLabel.width - 2));
    if (monetary(footerGross.text, currency) !== total) fail("idoklad_pdf_totals");
    return result;
}

/** Strict, source-specific native text profile; never OCR or arbitrary PDF inference. */
export function parseIdokladPdfText(pages: readonly MigrationPdfTextPage[]): IdokladPdfInvoice[] {
    if (!Array.isArray(pages) || !pages.length || pages.length > 50) fail("idoklad_pdf_limit_exceeded");
    let itemCount = 0, characterCount = 0;
    for (const page of pages) {
        if (!page || !Array.isArray(page.items)) fail();
        itemCount += page.items.length;
        for (const item of page.items) {
            if (!item || typeof item.str !== "string") fail();
            characterCount += item.str.length;
        }
        if (itemCount > 20_000 || characterCount > 200_000) fail("idoklad_pdf_limit_exceeded");
    }
    const result = pages.map(readPage), numbers = new Set<string>();
    for (const item of result) {
        if (numbers.has(item.invoice.documentNumber)) fail("idoklad_pdf_invalid");
        numbers.add(item.invoice.documentNumber);
    }
    return result;
}

export async function readIdokladPdfEvidence(fileName: string, bytes: Uint8Array, options: { signal?: AbortSignal } = {}): Promise<IdokladPdfInvoice[]> {
    if (!/\.pdf$/i.test(fileName)) fail();
    if (!bytes.length || bytes.length > MIGRATION_MAX_FILE_BYTES) fail("idoklad_pdf_limit_exceeded");
    try { return parseIdokladPdfText(await readMigrationPdfTextPages(bytes, options)); }
    catch (error) { if (error instanceof IdokladPdfError) throw error; return fail("idoklad_pdf_unreadable"); }
}
