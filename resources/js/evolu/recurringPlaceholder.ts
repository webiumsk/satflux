import { normalizeVariableSymbol } from "./bankSymbolNormalizer";
import { variableSymbolFromNumber } from "./documentNumber";

const MONTH_NAMES_SK: Record<number, string> = {
    1: "január",
    2: "február",
    3: "marec",
    4: "apríl",
    5: "máj",
    6: "jún",
    7: "júl",
    8: "august",
    9: "september",
    10: "október",
    11: "november",
    12: "december",
};

function parseIssueDate(issueDate: string): Date {
    return new Date(`${issueDate.slice(0, 10)}T12:00:00`);
}

export function resolveRecurringPlaceholders(
    text: string | null | undefined,
    issueDate: string,
    documentNumber = "",
    variableSymbol?: string | null,
): string | null {
    if (text == null || text === "") return text ?? null;

    const date = parseIssueDate(issueDate);
    const prevMonth = new Date(date);
    prevMonth.setMonth(prevMonth.getMonth() - 1);
    const nextYear = new Date(date);
    nextYear.setFullYear(nextYear.getFullYear() + 1);

    const vs = normalizeVariableSymbol(variableSymbol)
        ?? variableSymbolFromNumber(documentNumber)
        ?? "";
    const month = date.getMonth() + 1;

    const map: Record<string, string> = {
        "#INVOICE_NUMBER#": documentNumber,
        "#VARIABLE_SYMBOL#": vs,
        "#DAY#": String(date.getDate()).padStart(2, "0"),
        "#WEEK#": isoWeek(date),
        "#MONTH#": String(month).padStart(2, "0"),
        "#MONTH_NAME#": MONTH_NAMES_SK[month] ?? String(month).padStart(2, "0"),
        "#PREVIOUS_MONTH#": String(prevMonth.getMonth() + 1).padStart(2, "0"),
        "#YEAR#": String(date.getFullYear()),
        "#NEXT_YEAR#": String(nextYear.getFullYear()),
    };

    let out = text;
    for (const [token, value] of Object.entries(map)) {
        out = out.split(token).join(value);
    }
    return out;
}

function isoWeek(date: Date): string {
    const target = new Date(date.valueOf());
    const dayNr = (date.getDay() + 6) % 7;
    target.setDate(target.getDate() - dayNr + 3);
    const firstThursday = target.valueOf();
    target.setMonth(0, 1);
    if (target.getDay() !== 4) {
        target.setMonth(0, 1 + ((4 - target.getDay() + 7) % 7));
    }
    return String(1 + Math.ceil((firstThursday - target.valueOf()) / 604800000)).padStart(2, "0");
}
