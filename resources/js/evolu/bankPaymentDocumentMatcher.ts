import type { EvoluDocumentRow } from "./documentMap";
import { normalizeVariableSymbol } from "./bankSymbolNormalizer";

export function hasBankPaymentMatchHints(
    bankVariableSymbol: string | null | undefined,
    bankReference: string | null | undefined,
): boolean {
    if (normalizeVariableSymbol(bankVariableSymbol)) {
        return true;
    }
    return Boolean(bankReference?.trim());
}

export function matchesDocumentSymbol(
    document: EvoluDocumentRow,
    symbol: string | null | undefined,
): boolean {
    if (!symbol) return false;
    if (document.variableSymbol === symbol) return true;
    const fromNumber = (document.number || "").replace(/\D/g, "");
    return fromNumber !== "" && fromNumber === symbol;
}

function referenceMatchesToken(reference: string, token: string): boolean {
    if (!token) return false;
    if (reference.localeCompare(token, undefined, { sensitivity: "accent" }) === 0) {
        return true;
    }
    if (reference.toLowerCase().includes(token.toLowerCase())) {
        return true;
    }
    const refDigits = reference.replace(/\D/g, "");
    const tokenDigits = token.replace(/\D/g, "");
    return tokenDigits !== "" && refDigits !== "" && refDigits.includes(tokenDigits);
}

export function matchesDocumentPaymentReference(
    document: EvoluDocumentRow,
    reference: string | null | undefined,
): boolean {
    if (!reference?.trim()) return false;
    const trimmed = reference.trim();

    const docVs = document.variableSymbol?.trim() ?? "";
    if (docVs && referenceMatchesToken(trimmed, docVs)) {
        return true;
    }

    const number = document.number?.trim() ?? "";
    if (number && referenceMatchesToken(trimmed, number)) {
        return true;
    }

    const digits = normalizeVariableSymbol(trimmed);
    return digits !== null && matchesDocumentSymbol(document, digits);
}

export function documentMatchesBankPaymentHints(
    document: EvoluDocumentRow,
    bankVariableSymbol: string | null | undefined,
    bankReference: string | null | undefined,
): boolean {
    const bankVs = normalizeVariableSymbol(bankVariableSymbol);
    if (bankVs && matchesDocumentSymbol(document, bankVs)) {
        return true;
    }
    return matchesDocumentPaymentReference(document, bankReference);
}

export function bankPaymentMatchReason(
    document: EvoluDocumentRow,
    bankVariableSymbol: string | null | undefined,
    bankReference: string | null | undefined,
): "variable_symbol" | "payment_reference" | null {
    const bankVs = normalizeVariableSymbol(bankVariableSymbol);
    if (bankVs && matchesDocumentSymbol(document, bankVs)) {
        return "variable_symbol";
    }
    if (matchesDocumentPaymentReference(document, bankReference)) {
        return "payment_reference";
    }
    return null;
}
