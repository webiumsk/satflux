import { guessDirectionFromAmountAndHints } from "./bankDirectionGuesser";
import {
    normalizeConstantSymbol,
    normalizeSpecificSymbol,
    normalizeVariableSymbol,
} from "./bankSymbolNormalizer";
import type { BankTransactionDirection } from "./schema";

export type ParsedBankRow = {
    bookedAt: string;
    amount: number;
    currency: string;
    direction: BankTransactionDirection;
    variableSymbol: string | null;
    constantSymbol: string | null;
    specificSymbol: string | null;
    counterpartyName: string | null;
    counterpartyIban: string | null;
    reference: string | null;
    bankTransactionId: string | null;
};

const CSV_PROFILES: Record<string, Record<string, string[]>> = {
    generic: {
        date: ["datum", "date", "booking", "booked", "dátum"],
        amount: ["suma", "amount", "částka", "castka", "hodnota"],
        currency: ["mena", "currency", "ccy"],
        variable_symbol: ["vs", "variabilny", "variabilný", "variable"],
        constant_symbol: ["ks", "konstantny", "konstantný", "constant"],
        specific_symbol: ["ss", "specificky", "specifický", "specific"],
        counterparty: ["partner", "protistrana", "name", "názov", "nazov", "popis"],
        reference: ["referencia", "reference", "poznámka", "poznamka", "note"],
        direction: ["typ", "type", "smer", "direction"],
    },
    tatra: {
        date: ["datum zauctovania", "dátum zaúčtovania", "datum"],
        amount: ["suma", "amount"],
        variable_symbol: ["vs", "variabilny symbol"],
        counterparty: ["nazov protistrany", "názov protistrany"],
        reference: ["informacia pre prijemcu", "informácia pre príjemcu"],
    },
};

function normalizeHeader(header: string): string {
    return header
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "");
}

function detectDelimiter(contents: string): string {
    const first = contents.split(/\r?\n/)[0] ?? "";
    const semicolon = (first.match(/;/g) ?? []).length;
    const comma = (first.match(/,/g) ?? []).length;
    return semicolon >= comma ? ";" : ",";
}

function parseCsvLine(line: string, delimiter: string): string[] {
    const result: string[] = [];
    let current = "";
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (ch === '"') {
            if (inQuotes && line[i + 1] === '"') {
                current += '"';
                i++;
            } else {
                inQuotes = !inQuotes;
            }
            continue;
        }
        if (ch === delimiter && !inQuotes) {
            result.push(current);
            current = "";
            continue;
        }
        current += ch;
    }
    result.push(current);
    return result;
}

function resolveProfile(headers: string[]): string {
    const joined = headers.join(" ");
    if (joined.includes("zauctovania") || joined.includes("protistrany")) {
        return "tatra";
    }
    return "generic";
}

function columnMap(headers: string[], profile: string): Record<string, number> {
    const needles = CSV_PROFILES[profile] ?? CSV_PROFILES.generic;
    const map: Record<string, number> = {};

    for (const [field, candidates] of Object.entries(needles)) {
        for (let index = 0; index < headers.length; index++) {
            const header = headers[index];
            for (const candidate of candidates) {
                const c = normalizeHeader(candidate);
                if (header === c || header.includes(c)) {
                    map[field] = index;
                    break;
                }
            }
            if (map[field] !== undefined) break;
        }
    }

    if (map.date === undefined || map.amount === undefined) {
        throw new Error("CSV must include date and amount columns.");
    }

    return map;
}

function col(cols: string[], map: Record<string, number>, field: string): string | null {
    const index = map[field];
    if (index === undefined) return null;
    const value = cols[index];
    return value != null ? value.trim() : null;
}

function parseAmount(raw: string): number {
    const normalized = raw.replace(/[\s\u00a0]/g, "").replace(",", ".");
    const cleaned = normalized.replace(/[^0-9.-]/g, "");
    return parseFloat(cleaned) || 0;
}

function parseDate(raw: string): string {
    const trimmed = raw.trim();
    const formats = [
        /^(\d{2})\.(\d{2})\.(\d{4})$/,
        /^(\d{2})\.(\d{2})\.(\d{2})$/,
        /^(\d{4})-(\d{2})-(\d{2})$/,
        /^(\d{2})\/(\d{2})\/(\d{4})$/,
        /^(\d{4})\/(\d{2})\/(\d{2})$/,
    ];

    for (const re of formats) {
        const m = trimmed.match(re);
        if (!m) continue;
        if (re.source.startsWith("^(\\d{2})\\.")) {
            const year = m[3].length === 2 ? `20${m[3]}` : m[3];
            return `${year}-${m[2]}-${m[1]}T12:00:00.000Z`;
        }
        if (re.source.startsWith("^(\\d{4})")) {
            return `${m[1]}-${m[2]}-${m[3]}T12:00:00.000Z`;
        }
        if (re.source.includes("\\/")) {
            return `${m[3]}-${m[2]}-${m[1]}T12:00:00.000Z`;
        }
    }

    const parsed = Date.parse(trimmed);
    if (!Number.isNaN(parsed)) {
        return new Date(parsed).toISOString();
    }

    throw new Error(`Unrecognized date: ${raw}`);
}

function parseRow(cols: string[], map: Record<string, number>): ParsedBankRow | null {
    const amountRaw = col(cols, map, "amount");
    if (!amountRaw) return null;

    const amount = parseAmount(amountRaw);
    if (amount === 0) return null;

    const directionRaw = col(cols, map, "direction");
    const counterparty = col(cols, map, "counterparty");
    const reference = col(cols, map, "reference");
    const direction = guessDirectionFromAmountAndHints(
        amount,
        directionRaw,
        counterparty,
        reference,
    );

    const dateRaw = col(cols, map, "date");
    if (!dateRaw) return null;

    const currency = (col(cols, map, "currency") || "EUR").toUpperCase();

    return {
        bookedAt: parseDate(dateRaw),
        amount: Math.abs(amount),
        currency,
        direction,
        variableSymbol: normalizeVariableSymbol(col(cols, map, "variable_symbol")),
        constantSymbol: normalizeConstantSymbol(col(cols, map, "constant_symbol")),
        specificSymbol: normalizeSpecificSymbol(col(cols, map, "specific_symbol")),
        counterpartyName: counterparty,
        counterpartyIban: null,
        reference,
        bankTransactionId: null,
    };
}

export function supportsCsvBankFile(filename: string): boolean {
    const lower = filename.toLowerCase();
    return lower.endsWith(".csv") || lower.endsWith(".txt");
}

function isAsciiText(text: string): boolean {
    for (let i = 0; i < text.length; i++) {
        if (text.charCodeAt(i) > 0x7f) return false;
    }
    return true;
}

export function parseCsvBankStatement(contents: string): ParsedBankRow[] {
    let text = contents;
    if (!isAsciiText(text)) {
        try {
            const bytes = new Uint8Array([...text].map((c) => c.charCodeAt(0)));
            text = new TextDecoder("iso-8859-2").decode(bytes);
        } catch {
            // keep original
        }
    }

    const delimiter = detectDelimiter(text);
    const lines = text.trim().split(/\r\n|\r|\n/).filter((l) => l.trim() !== "");
    if (lines.length === 0) {
        throw new Error("CSV file is empty.");
    }

    const headerLine = lines.shift()!;
    const headers = parseCsvLine(headerLine, delimiter).map(normalizeHeader);
    const profile = resolveProfile(headers);
    const map = columnMap(headers, profile);

    const parsed: ParsedBankRow[] = [];
    for (const line of lines) {
        const cols = parseCsvLine(line, delimiter);
        const row = parseRow(cols, map);
        if (row) parsed.push(row);
    }

    if (parsed.length === 0) {
        throw new Error("No transactions parsed from CSV. Check column headers.");
    }

    return parsed;
}
