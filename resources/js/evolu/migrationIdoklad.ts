import type { DocumentImportMapping } from "@/composables/useDocumentImportFields";
import type { MigrationInvoice } from "./migrationValidation";
import { migrationDecimal, migrationMinor, parseMigrationInvoice } from "./migrationValidation";
import { parseSpreadsheetFile } from "./spreadsheetParse";
import { MIGRATION_MAX_FILE_BYTES } from "./migrationArchiveValues";

export type IdokladMigrationErrorCode = "idoklad_invalid_export" | "idoklad_limit_exceeded"
    | "idoklad_unsupported" | "idoklad_invalid_invoice" | "idoklad_totals"
    | "idoklad_payment_evidence" | "idoklad_unsupported_currency";

export class IdokladMigrationError extends Error {
    constructor(readonly code: IdokladMigrationErrorCode) {
        super(code);
        this.name = "IdokladMigrationError";
    }
}

/** A parsed list row is NOT financial-ready until original details and issuer are joined. */
export type IdokladSourceRecord = {
    row: number; invoice_number: string; kind: "parsed"; invoice: MigrationInvoice;
    sourceSubtotal: string; sourceTax: string;
} | { row: number; invoice_number: string; kind: "invalid"; code: IdokladMigrationErrorCode };

export type IdokladSpreadsheetResult = { records: IdokladSourceRecord[]; invoiceCount: number };

export const IDOKLAD_HEADERS = [
    "Číslo dokladu", "Popis", "Číslo objednávky", "Rad", "Názov/Meno", "IČO", "IČ DPH", "DIČ",
    "Vystavenie", "Splatnosť", "Dodané", "Dátum platby", "Cena s DPH", "Spolu bez DPH", "DPH",
    "Mena", "Stav úhrady", "Uhradená suma", "Variabilný symbol", "Exportované",
    "Odoslané odberateľovi", "Odoslanie účtovníkovi",
] as const;
const MAPPING: DocumentImportMapping = { invoice_number: 0, client_name: 4, client_registration_number: 5,
    client_vat_id: 6, client_tax_id: 7, issue_date: 8, due_date: 9, delivery_date: 10, paid_at: 11,
    amount: 12, currency: 15, payment_status: 16, amount_paid: 17, variable_symbol: 18 };
const TWO_DECIMAL_CURRENCIES = new Set([
    "AUD", "CAD", "CHF", "CNY", "CZK", "DKK", "EUR", "GBP", "HKD", "HUF",
    "ILS", "INR", "MXN", "NOK", "NZD", "PLN", "SEK", "SGD", "USD", "ZAR",
]);
const MAX_EXPANDED_BYTES = 20 * 1024 * 1024;
const MAX_ENTRY_BYTES = 8 * 1024 * 1024;
const MAX_ENTRIES = 128;
const WORKSHEET_NAMESPACE = "http://schemas.openxmlformats.org/spreadsheetml/2006/main";
const RELATIONSHIP_NAMESPACE = "http://schemas.openxmlformats.org/package/2006/relationships";
const OFFICE_RELATIONSHIP_NAMESPACE = "http://schemas.openxmlformats.org/officeDocument/2006/relationships";
const CONTENT_TYPE_NAMESPACE = "http://schemas.openxmlformats.org/package/2006/content-types";
const WORKSHEET_PATH = "xl/worksheets/sheet1.xml";
const PACKAGE_XML = new Map([
    ["[Content_Types].xml", { root: "Types", namespace: CONTENT_TYPE_NAMESPACE }],
    ["_rels/.rels", { root: "Relationships", namespace: RELATIONSHIP_NAMESPACE }],
    ["xl/workbook.xml", { root: "workbook", namespace: WORKSHEET_NAMESPACE }],
    ["xl/_rels/workbook.xml.rels", { root: "Relationships", namespace: RELATIONSHIP_NAMESPACE }],
]);
type ZipEntry = { name: string; method: number; flags: number; crc: number; size: number; compressed: number; local: number; start: number; end: number };

function fail(code: IdokladMigrationErrorCode): never { throw new IdokladMigrationError(code); }

function u16(view: DataView, offset: number): number { return view.getUint16(offset, true); }
function u32(view: DataView, offset: number): number { return view.getUint32(offset, true); }

const CRC_TABLE = Array.from({ length: 256 }, (_, byte) => {
    let result = byte;
    for (let bit = 0; bit < 8; bit++) result = (result >>> 1) ^ (result & 1 ? 0xedb88320 : 0);
    return result >>> 0;
});

function updateCrc(crc: number, bytes: Uint8Array): number {
    for (const byte of bytes) crc = (crc >>> 8) ^ CRC_TABLE[(crc ^ byte) & 255];
    return crc;
}

/** Validate ZIP structure before handing bounded, fully checked input to SheetJS. */
function zipEntries(bytes: Uint8Array): ZipEntry[] {
    if (bytes.length < 22) fail("idoklad_invalid_export");
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    let end = -1;
    for (let offset = bytes.length - 22; offset >= Math.max(0, bytes.length - 65_557); offset--) {
        if (u32(view, offset) === 0x06054b50 && offset + 22 + u16(view, offset + 20) === bytes.length) { end = offset; break; }
    }
    if (end < 0 || u16(view, end + 4) || u16(view, end + 6)) fail("idoklad_invalid_export");
    const count = u16(view, end + 10), central = u32(view, end + 16), size = u32(view, end + 12);
    if (count > MAX_ENTRIES) fail("idoklad_limit_exceeded");
    if (!count || count !== u16(view, end + 8) || central + size !== end) fail("idoklad_invalid_export");
    const names = new Set<string>(), foldedNames = new Set<string>(), entries: ZipEntry[] = [];
    const decoder = new TextDecoder("utf-8", { fatal: true });
    let position = central, expanded = 0;
    for (let i = 0; i < count; i++) {
        if (position + 46 > end || u32(view, position) !== 0x02014b50) fail("idoklad_invalid_export");
        const flags = u16(view, position + 8), method = u16(view, position + 10);
        const compressed = u32(view, position + 20), size = u32(view, position + 24);
        const nameLength = u16(view, position + 28), extraLength = u16(view, position + 30), commentLength = u16(view, position + 32);
        const local = u32(view, position + 42);
        const next = position + 46 + nameLength + extraLength + commentLength;
        if (next > end || !nameLength || flags & ~0x080e || ![0, 8].includes(method)
            || u16(view, position + 34) || local + 30 > central) fail("idoklad_invalid_export");
        if (size > MAX_ENTRY_BYTES || (expanded += size) > MAX_EXPANDED_BYTES) fail("idoklad_limit_exceeded");
        let name: string;
        try { name = decoder.decode(bytes.subarray(position + 46, position + 46 + nameLength)); }
        catch { return fail("idoklad_invalid_export"); }
        if (!/^[A-Za-z0-9_[\]./-]+$/.test(name) || name.startsWith("/") || name.includes("//")
            || name.split("/").some(part => part === ".." || part === ".") || foldedNames.has(name.toLowerCase())) fail("idoklad_invalid_export");
        names.add(name);
        foldedNames.add(name.toLowerCase());
        if (/externalLinks|vbaProject|macrosheets/i.test(name)) fail("idoklad_unsupported");
        if (u32(view, local) !== 0x04034b50 || u16(view, local + 6) !== flags || u16(view, local + 8) !== method
            || u16(view, local + 26) !== nameLength) fail("idoklad_invalid_export");
        const start = local + 30 + nameLength + u16(view, local + 28), dataEnd = start + compressed;
        if (dataEnd > central || start > central) fail("idoklad_invalid_export");
        for (let n = 0; n < nameLength; n++) if (bytes[local + 30 + n] !== bytes[position + 46 + n]) fail("idoklad_invalid_export");
        const crc = u32(view, position + 16);
        let entryEnd = dataEnd;
        if (flags & 8) {
            if (entryEnd + 12 > central) fail("idoklad_invalid_export");
            if (u32(view, entryEnd) === 0x08074b50) entryEnd += 4;
            if (entryEnd + 12 > central || u32(view, entryEnd) !== crc || u32(view, entryEnd + 4) !== compressed
                || u32(view, entryEnd + 8) !== size) fail("idoklad_invalid_export");
            entryEnd += 12;
        } else if (u32(view, local + 14) !== crc || u32(view, local + 18) !== compressed || u32(view, local + 22) !== size) fail("idoklad_invalid_export");
        entries.push({ name, method, flags, crc, size, compressed, local, start, end: entryEnd });
        position = next;
    }
    if (position !== end || !names.has("[Content_Types].xml") || !names.has("xl/workbook.xml")
        || entries.filter(entry => /^xl\/worksheets\/[^/]+\.xml$/.test(entry.name)).length !== 1) fail("idoklad_invalid_export");
    let cursor = 0;
    for (const entry of [...entries].sort((a, b) => a.local - b.local)) {
        if (entry.local !== cursor) fail("idoklad_invalid_export");
        cursor = entry.end;
    }
    if (cursor !== central) fail("idoklad_invalid_export");
    return entries;
}

function worksheetAddress(value: string): { row: number; column: number } {
    const match = /^([A-Z]{1,3})([1-9]\d{0,6})$/.exec(value);
    if (!match) fail("idoklad_invalid_export");
    const column = Array.from(match[1]).reduce((sum, char) => sum * 26 + char.charCodeAt(0) - 64, 0);
    const row = Number(match[2]);
    if (column > 22 || row > 2005) fail("idoklad_limit_exceeded");
    return { row, column };
}

function packageXml(bytes: Uint8Array, rootName: string, namespace: string): Element {
    let xml: string;
    try { xml = new TextDecoder("utf-8", { fatal: true }).decode(bytes); }
    catch { return fail("idoklad_invalid_export"); }
    const declaration = /^<\?xml\b[^?]{0,200}\?>/.exec(xml)?.[0] ?? "";
    if ((/encoding\s*=/.test(declaration) && !/encoding\s*=\s*(["'])utf-8\1/i.test(declaration))
        || /<[!?]/.test(xml.slice(declaration.length))) fail("idoklad_invalid_export");
    let tags = 0;
    for (const char of xml) if (char === "<" && ++tags > 600_000) fail("idoklad_limit_exceeded");
    let document: Document;
    try { document = new DOMParser().parseFromString(xml, "application/xml"); }
    catch { return fail("idoklad_invalid_export"); }
    if (document.getElementsByTagName("parsererror").length) fail("idoklad_invalid_export");
    const root = document.documentElement;
    if (!root || root.localName !== rootName || root.namespaceURI !== namespace) fail("idoklad_invalid_export");
    const stack: { element: Element; depth: number }[] = [{ element: root, depth: 1 }];
    let nodes = 0;
    while (stack.length) {
        const { element, depth } = stack.pop()!;
        if (++nodes > 400_000 || depth > 12) fail("idoklad_limit_exceeded");
        if (element.namespaceURI !== namespace) fail("idoklad_invalid_export");
        for (const child of Array.from(element.children)) stack.push({ element: child, depth: depth + 1 });
    }
    return root;
}

/** The declared range must never hide physical records from SheetJS. */
function validateWorksheet(bytes: Uint8Array): void {
    const root = packageXml(bytes, "worksheet", WORKSHEET_NAMESPACE);
    const dimensions = Array.from(root.getElementsByTagNameNS("*", "dimension"));
    const dataSections = Array.from(root.getElementsByTagNameNS("*", "sheetData"));
    if (dimensions.length > 1 || (dimensions[0] && dimensions[0].parentElement !== root)
        || dataSections.length !== 1 || dataSections[0].parentElement !== root) fail("idoklad_invalid_export");
    let declared: { start: ReturnType<typeof worksheetAddress>; end: ReturnType<typeof worksheetAddress> } | null = null;
    if (dimensions[0]) {
        const endpoints = (dimensions[0].getAttribute("ref") ?? "").split(":");
        if (endpoints.length > 2 || endpoints.some(value => !value)) fail("idoklad_invalid_export");
        const start = worksheetAddress(endpoints[0]), end = worksheetAddress(endpoints[1] ?? endpoints[0]);
        if (start.row > end.row || start.column > end.column) fail("idoklad_invalid_export");
        declared = { start, end };
    }
    const rows = new Set<number>(), cells = new Set<string>();
    const data = dataSections[0];
    const allRows = Array.from(root.getElementsByTagNameNS("*", "row"));
    const allCells = Array.from(root.getElementsByTagNameNS("*", "c"));
    if (allRows.length !== data.childElementCount || allRows.some(row => row.parentElement !== data)) fail("idoklad_invalid_export");
    let cellCount = 0;
    for (const row of allRows) {
        const rowText = row.getAttribute("r") ?? "";
        if (!/^[1-9]\d{0,6}$/.test(rowText)) fail("idoklad_invalid_export");
        const index = Number(rowText);
        if (index > 2005 || row.childElementCount > 22) fail("idoklad_limit_exceeded");
        if ((declared && (index < declared.start.row || index > declared.end.row)) || rows.has(index)) fail("idoklad_invalid_export");
        rows.add(index);
        for (const cell of Array.from(row.children)) {
            if (cell.localName !== "c") fail("idoklad_invalid_export");
            const ref = cell.getAttribute("r") ?? "";
            const position = worksheetAddress(ref);
            if (position.row !== index || (declared && (position.column < declared.start.column || position.column > declared.end.column))
                || cells.has(ref)) fail("idoklad_invalid_export");
            cells.add(ref); cellCount++;
        }
    }
    if (cellCount !== allCells.length) fail("idoklad_invalid_export");
}

function relationships(root: Element): Element[] {
    const ids = new Set<string>();
    return Array.from(root.children).map(relation => {
        const id = relation.getAttribute("Id") ?? "";
        if (relation.localName !== "Relationship" || relation.childElementCount || !/^[A-Za-z0-9_-]{1,128}$/.test(id)
            || ids.has(id) || !relation.getAttribute("Type") || !relation.getAttribute("Target")
            || ![null, "Internal"].includes(relation.getAttribute("TargetMode"))) fail("idoklad_invalid_export");
        ids.add(id);
        return relation;
    });
}

/** Bind the physical sheet we validate to the package's actual active worksheet. */
function validateWorkbookBindings(parts: ReadonlyMap<string, Element>, entries: readonly ZipEntry[]): void {
    if ([...PACKAGE_XML.keys()].some(name => !parts.has(name))) fail("idoklad_invalid_export");
    const rootRelations = relationships(parts.get("_rels/.rels")!);
    const workbooks = rootRelations.filter(relation => relation.getAttribute("Type") === `${OFFICE_RELATIONSHIP_NAMESPACE}/officeDocument`);
    if (workbooks.length !== 1 || !["xl/workbook.xml", "/xl/workbook.xml"].includes(workbooks[0].getAttribute("Target")!)) fail("idoklad_unsupported");
    const workbook = parts.get("xl/workbook.xml")!;
    const groups = Array.from(workbook.getElementsByTagNameNS("*", "sheets"));
    const sheets = Array.from(workbook.getElementsByTagNameNS("*", "sheet"));
    if (groups.length !== 1 || groups[0].parentElement !== workbook || sheets.length !== 1
        || sheets[0].parentElement !== groups[0] || groups[0].childElementCount !== 1) fail("idoklad_invalid_export");
    const activeId = sheets[0].getAttributeNS(OFFICE_RELATIONSHIP_NAMESPACE, "id");
    const sheetRelations = relationships(parts.get("xl/_rels/workbook.xml.rels")!)
        .filter(relation => relation.getAttribute("Type") === `${OFFICE_RELATIONSHIP_NAMESPACE}/worksheet`);
    if (!activeId || sheetRelations.length !== 1 || sheetRelations[0].getAttribute("Id") !== activeId
        || !["worksheets/sheet1.xml", `/${WORKSHEET_PATH}`].includes(sheetRelations[0].getAttribute("Target")!)) fail("idoklad_unsupported");
    const types = parts.get("[Content_Types].xml")!;
    const overrides = new Map<string, string>(), defaults = new Map<string, string>();
    for (const element of Array.from(types.children)) {
        if (!["Default", "Override"].includes(element.localName) || element.childElementCount) fail("idoklad_invalid_export");
        const type = element.getAttribute("ContentType") ?? "";
        if (!/^[A-Za-z0-9!#$&^_.+-]+\/[A-Za-z0-9!#$&^_.+-]{1,200}$/.test(type)) fail("idoklad_invalid_export");
        if (element.localName === "Default") {
            const extension = element.getAttribute("Extension")?.toLowerCase() ?? "";
            if (!/^[a-z0-9]{1,16}$/.test(extension) || defaults.has(extension)) fail("idoklad_invalid_export");
            defaults.set(extension, type);
        } else {
            const path = element.getAttribute("PartName") ?? "";
            if (!/^\/[A-Za-z0-9_[\]./-]+$/.test(path) || path.includes("//")
                || path.split("/").some(segment => segment === "." || segment === "..") || overrides.has(path.toLowerCase())) fail("idoklad_invalid_export");
            overrides.set(path.toLowerCase(), type);
        }
    }
    const prefix = "application/vnd.openxmlformats-officedocument.spreadsheetml.";
    const effective = new Map(entries.filter(entry => entry.name !== "[Content_Types].xml" && !entry.name.endsWith("/"))
        .map(entry => [entry.name, overrides.get(`/${entry.name.toLowerCase()}`) ?? defaults.get(entry.name.split(".").pop()!.toLowerCase())]));
    // Overrides take precedence over defaults. The package metadata file itself is not an OPC part.
    if (effective.get("xl/workbook.xml") !== `${prefix}sheet.main+xml`
        || effective.get(WORKSHEET_PATH) !== `${prefix}worksheet+xml`
        || [...effective.values()].filter(type => /\.main(?:\+xml)?$/.test(type ?? "")).length !== 1
        || [...effective.values()].filter(type => /\.worksheet\+xml$/.test(type ?? "")).length !== 1) fail("idoklad_unsupported");
    const fileNames = new Set(entries.map(entry => `/${entry.name.toLowerCase()}`));
    if ([...overrides].some(([path, type]) => /\.(?:main|worksheet)(?:\+xml)?$/.test(type) && !fileNames.has(path))) fail("idoklad_unsupported");
}

async function validateZip(bytes: Uint8Array): Promise<void> {
    const entries = zipEntries(bytes);
    const parts = new Map<string, Element>();
    let expanded = 0;
    for (const entry of entries) {
        const source = bytes.subarray(entry.start, entry.start + entry.compressed);
        const isWorksheet = entry.name === WORKSHEET_PATH;
        const format = PACKAGE_XML.get(entry.name);
        const chunks: Uint8Array[] = [];
        let crc = 0xffffffff, size = 0;
        if (entry.method === 0) { size = source.length; crc = updateCrc(crc, source); }
        else {
            if (typeof DecompressionStream !== "function") fail("idoklad_unsupported");
            let reader: ReadableStreamDefaultReader<Uint8Array> | undefined;
            try {
                reader = new ReadableStream<BufferSource>({ start(controller) { controller.enqueue(new Uint8Array(source)); controller.close(); } })
                    .pipeThrough(new DecompressionStream("deflate-raw")).getReader();
                for (let chunk = await reader.read(); !chunk.done; chunk = await reader.read()) {
                    size += chunk.value.length;
                    if (size > entry.size || size > MAX_ENTRY_BYTES || expanded + size > MAX_EXPANDED_BYTES) {
                        await reader.cancel(); fail("idoklad_limit_exceeded");
                    }
                    crc = updateCrc(crc, chunk.value);
                    if (isWorksheet || format) chunks.push(new Uint8Array(chunk.value));
                }
            } catch (error) {
                if (error instanceof IdokladMigrationError) throw error;
                fail("idoklad_invalid_export");
            } finally { reader?.releaseLock(); }
        }
        if (size !== entry.size || ((crc ^ 0xffffffff) >>> 0) !== entry.crc) fail("idoklad_invalid_export");
        if (isWorksheet || format) {
            const xml = entry.method === 0 ? source : new Uint8Array(size);
            if (entry.method !== 0) {
                let offset = 0;
                for (const chunk of chunks) { xml.set(chunk, offset); offset += chunk.length; }
            }
            if (isWorksheet) validateWorksheet(xml);
            if (format) parts.set(entry.name, packageXml(xml, format.root, format.namespace));
        }
        expanded += size;
    }
    if (!entries.some(entry => entry.name === WORKSHEET_PATH)) fail("idoklad_unsupported");
    validateWorkbookBindings(parts, entries);
}

function money(value: string, code: IdokladMigrationErrorCode): bigint {
    if (!/^\d{1,12}(?:\.\d{1,2})?$/.test(value)) fail(code);
    return migrationMinor(value);
}

function date(value: string, required: boolean, code: IdokladMigrationErrorCode): string | null {
    if (!value && !required) return null;
    if (!/^[1-9]\d{3}-\d{2}-\d{2}$/.test(value)) fail(code);
    const result = new Date(value + "T00:00:00.000Z");
    if (!Number.isFinite(result.getTime()) || result.toISOString().slice(0, 10) !== value) fail(code);
    return value;
}

function parseRecord(values: string[], types: string[], row: number): IdokladSourceRecord {
    const cells = values.map(value => value.trim());
    const rawNumber = cells[0] ?? "";
    const number = rawNumber.length <= 64 && !Array.from(rawNumber).some(char => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127) ? rawNumber : "";
    try {
        if (cells.length !== IDOKLAD_HEADERS.length || types.some(type => type === "formula")
            || cells.some(value => value.startsWith("="))) fail("idoklad_invalid_invoice");
        for (const index of [12, 13, 14]) if (types[index] !== "n") fail("idoklad_totals");
        if (types[17] !== "n") fail("idoklad_payment_evidence");
        for (const index of [0, 4, 5, 6, 7, 15, 16, 18]) {
            if (cells[index] && types[index] !== "s") fail("idoklad_invalid_invoice");
        }
        for (const index of [8, 9, 10, 11]) {
            if (cells[index] && types[index] !== "d") fail(index === 11 ? "idoklad_payment_evidence" : "idoklad_invalid_invoice");
        }
        for (const index of [0, 4, 5, 6, 7, 18]) {
            if (Array.from(cells[index]).some(char => char.charCodeAt(0) < 32 || char.charCodeAt(0) === 127)) fail("idoklad_invalid_invoice");
        }
        const total = money(cells[12], "idoklad_totals"), subtotal = money(cells[13], "idoklad_totals"), tax = money(cells[14], "idoklad_totals");
        if (total <= 0n || subtotal + tax !== total) fail("idoklad_totals");
        if (!TWO_DECIMAL_CURRENCIES.has(cells[15])) fail("idoklad_unsupported_currency");
        const paid = money(cells[17], "idoklad_payment_evidence");
        const paidAt = date(cells[11], false, "idoklad_payment_evidence");
        let state: "paid" | "partial" | "unpaid";
        if (cells[16] === "Uhradené") {
            if (paid !== total || !paidAt) fail("idoklad_payment_evidence");
            state = "paid";
        } else if (cells[16] === "Čiastočne uhradené") {
            if (paid <= 0n || paid >= total || paidAt) fail("idoklad_payment_evidence");
            state = "partial";
        } else if (["Neuhradené", "Neuhradené a po splatnosti"].includes(cells[16])) {
            if (paid !== 0n || paidAt) fail("idoklad_payment_evidence");
            state = "unpaid";
        } else return fail("idoklad_unsupported");
        date(cells[8], true, "idoklad_invalid_invoice"); date(cells[9], true, "idoklad_invalid_invoice");
        date(cells[10], false, "idoklad_invalid_invoice");
        const normalized = [...cells];
        normalized[16] = state;
        const invoice = parseMigrationInvoice(normalized, MAPPING, row, { defaultCurrency: cells[15], dateFormat: "ymd_dash" });
        return { row, invoice_number: number, kind: "parsed", invoice, sourceSubtotal: migrationDecimal(subtotal), sourceTax: migrationDecimal(tax) };
    } catch (error) {
        return { row, invoice_number: number, kind: "invalid", code: error instanceof IdokladMigrationError ? error.code : "idoklad_invalid_invoice" };
    }
}

/** Parse exact list facts only. A separate original-detail/issuer join must authorize readiness. */
export async function parseIdokladSpreadsheet(bytes: Uint8Array, fileName: string): Promise<IdokladSpreadsheetResult> {
    if (bytes.length > MIGRATION_MAX_FILE_BYTES) fail("idoklad_limit_exceeded");
    if (!bytes.length || !/\.xlsx$/i.test(fileName)) fail("idoklad_invalid_export");
    const source = new Uint8Array(bytes);
    await validateZip(source);
    let parsed: Awaited<ReturnType<typeof parseSpreadsheetFile>>;
    try {
        parsed = await parseSpreadsheetFile({ name: fileName, size: source.length,
            arrayBuffer: async () => source.buffer } as File,
        { maxRows: 2000, maxColumns: 22, maxFileBytes: MIGRATION_MAX_FILE_BYTES, headerRowIndex: 4, singleSheet: true });
    } catch (error) {
        if (error instanceof Error && ["too_many_rows", "too_many_columns", "file_too_large"].includes(error.message)) fail("idoklad_limit_exceeded");
        fail("idoklad_invalid_export");
    }
    if (parsed.headers.length !== IDOKLAD_HEADERS.length || parsed.headers.some((name, index) => name !== IDOKLAD_HEADERS[index])
        || parsed.preambleRows?.[1]?.[0] !== "Zoznam vystavených faktúr"
        || [0, 3].some(index => parsed.preambleRows?.[index]?.some(Boolean))
        || parsed.preambleRows[1].slice(1).some(Boolean) || !parsed.rows.length || !parsed.rowNumbers || !parsed.cellTypes) fail("idoklad_invalid_export");
    const records = parsed.rows.map((row, index) => parseRecord(row, parsed.cellTypes![index], parsed.rowNumbers![index]));
    return { records, invoiceCount: records.length };
}
