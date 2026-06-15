import { normalizeVariableSymbol } from "./bankSymbolNormalizer";

function localName(el: Element, name: string): Element | null {
    for (const child of el.children) {
        if (child.localName === name) return child;
    }
    return null;
}

function localNameValue(parent: Element, name: string, childName?: string): string {
    const node = localName(parent, name);
    if (!node) return "";
    if (childName) {
        const child = localName(node, childName);
        return child ? child.textContent?.trim() ?? "" : "";
    }
    return node.textContent?.trim() ?? "";
}

function elementsByLocalName(root: Element | Document, name: string): Element[] {
    const out: Element[] = [];
    const walk = (node: Element) => {
        if (node.localName === name) out.push(node);
        for (const child of node.children) walk(child);
    };
    if (root instanceof Document) {
        if (root.documentElement) walk(root.documentElement);
    } else {
        walk(root);
    }
    return out;
}

function extractSymbols(ntry: Element): { vs: string | null; ks: string | null; ss: string | null } {
    let vs: string | null = null;
    let ks: string | null = null;
    let ss: string | null = null;

    for (const ref of elementsByLocalName(ntry, "CdtrRefInf")) {
        const tp = localNameValue(ref, "Tp", "Cd").toUpperCase();
        const value = normalizeVariableSymbol(localNameValue(ref, "Ref"));
        if (!value) continue;
        if (tp === "KS") ks = value;
        else if (tp === "SS") ss = value;
        else vs = value;
    }

    if (!vs) {
        for (const e2e of elementsByLocalName(ntry, "EndToEndId")) {
            const candidate = normalizeVariableSymbol(e2e.textContent);
            if (candidate) {
                vs = candidate;
                break;
            }
        }
    }

    return { vs, ks, ss };
}

function extractReference(ntry: Element): string | null {
    const parts: string[] = [];
    for (const u of elementsByLocalName(ntry, "Ustrd")) {
        const text = u.textContent?.trim() ?? "";
        if (text) parts.push(text);
    }
    return parts.length > 0 ? parts.join(" ") : null;
}

function extractCounterparty(ntry: Element): string | null {
    for (const party of ["Dbtr", "Cdtr"]) {
        for (const el of elementsByLocalName(ntry, party)) {
            const name = localNameValue(el, "Nm");
            if (name) return name;
        }
    }
    return null;
}

function parseEntry(ntry: Element): ParsedBankRow | null {
    const amtNode = localName(ntry, "Amt");
    if (!amtNode) return null;

    const amount = parseFloat(amtNode.textContent || "0");
    if (!amount) return null;

    const currency = (amtNode.getAttribute("Ccy") || "EUR").toUpperCase();
    const cdtDbt = localNameValue(ntry, "CdtDbtInd").toUpperCase();
    const direction = cdtDbt === "DBIT" ? "debit" : "credit";

    const booked =
        localNameValue(ntry, "BookgDt", "Dt") || localNameValue(ntry, "ValDt", "Dt");
    if (!booked) return null;

    const bookedAt = Date.parse(booked)
        ? new Date(booked).toISOString()
        : `${booked}T12:00:00.000Z`;

    const symbols = extractSymbols(ntry);

    return {
        bookedAt,
        amount: Math.abs(amount),
        currency,
        direction,
        variableSymbol: symbols.vs,
        constantSymbol: symbols.ks,
        specificSymbol: symbols.ss,
        counterpartyName: extractCounterparty(ntry),
        counterpartyIban: null,
        reference: extractReference(ntry),
        bankTransactionId: localNameValue(ntry, "NtryRef") || null,
    };
}

export function supportsCamt053File(filename: string, contents: string): boolean {
    const lower = filename.toLowerCase();
    if (lower.endsWith(".xml")) {
        return contents.includes("BkToCstmrStmt") || contents.includes("Camt.053");
    }
    return contents.trimStart().startsWith("<?xml") && contents.includes("Ntry");
}

export function parseCamt053BankStatement(contents: string): ParsedBankRow[] {
    const parser = new DOMParser();
    const doc = parser.parseFromString(contents, "application/xml");
    if (doc.querySelector("parsererror")) {
        throw new Error("Invalid CAMT.053 XML file.");
    }

    const entries = elementsByLocalName(doc, "Ntry");
    const parsed: ParsedBankRow[] = [];
    for (const entry of entries) {
        const row = parseEntry(entry);
        if (row) parsed.push(row);
    }

    if (parsed.length === 0) {
        throw new Error("No transactions found in CAMT.053 file.");
    }

    return parsed;
}

export type { ParsedBankRow };
