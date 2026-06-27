import type { BankTransactionDirection } from "./schema";

const DEFAULT_DEBIT_HINTS = [
    "debet na",
    "debetna",
    "debet ",
    "debet.",
    "debit",
    "dbit",
    "odchod",
    "odch.",
    "odchodz",
    "vydaj",
    "výdaj",
    "nakup pos",
    "nákup pos",
    "eur nakup",
    "eur nákup",
    "pos nakup",
    "pos nákup",
    "transakcna dan",
    "transakčná daň",
    "poplatok",
    "vyber",
    "výber",
    "platba kartou",
    "platba prevodom",
    "smerom von",
];

const DEFAULT_CREDIT_HINTS = [
    "kredit na",
    "kredit ",
    "credit",
    "prijem",
    "príjem",
    "prijatie",
    "vklad",
    "pripis",
    "pripisan",
    "pripísan",
];

function containsHint(haystack: string, needles: string[]): boolean {
    for (const needle of needles) {
        if (haystack.includes(needle)) return true;
    }
    return false;
}

export function guessDirectionFromAmountAndHints(
    amount: number,
    ...hints: (string | null | undefined)[]
): BankTransactionDirection {
    const haystack = hints
        .filter((h): h is string => h != null && h.trim() !== "")
        .join(" ")
        .toLowerCase()
        .trim();

    if (haystack !== "" && containsHint(haystack, DEFAULT_DEBIT_HINTS)) {
        return "debit";
    }

    if (haystack !== "" && containsHint(haystack, DEFAULT_CREDIT_HINTS)) {
        return "credit";
    }

    if (haystack.includes("obrat na") && !haystack.includes("debet")) {
        return "credit";
    }

    return amount < 0 ? "debit" : "credit";
}

export function resolvedBankDirection(row: {
    direction: BankTransactionDirection;
    amount: string | null;
    reference: string | null;
    counterpartyName: string | null;
}): BankTransactionDirection {
    const hasHints =
        (row.reference?.trim() ?? "") !== "" || (row.counterpartyName?.trim() ?? "") !== "";

    if (hasHints) {
        const amount = parseFloat(row.amount || "0");
        return guessDirectionFromAmountAndHints(
            amount,
            row.reference,
            row.counterpartyName,
        );
    }

    return row.direction;
}
