import type { ResetPeriod } from "./schema";

export function currentPeriodKey(resetPeriod: ResetPeriod, date = new Date()): string {
    if (resetPeriod === "monthly") {
        const month = String(date.getMonth() + 1).padStart(2, "0");
        return `${date.getFullYear()}-${month}`;
    }
    if (resetPeriod === "never") return "all";
    return String(date.getFullYear());
}

/** True when format includes a counter token (N run of 2+ or legacy C). */
export function formatHasCounterToken(format: string): boolean {
    const pattern = format.toUpperCase().trim();
    if (pattern.includes("C")) return true;
    return /N{2,}/.test(pattern);
}

export function counterDigitsInFormat(format: string): number {
    const pattern = format.toUpperCase().trim();
    const trailingN = pattern.match(/N{2,}$/);
    if (trailingN) return trailingN[0].length;
    const trailingC = pattern.match(/C+$/);
    if (trailingC) return trailingC[0].length;
    const nRuns = pattern.match(/N{2,}/g) || [];
    if (nRuns.length > 0) {
        return Math.max(...nRuns.map((run) => run.length));
    }
    return Math.max(1, (pattern.match(/C/g) || []).length);
}

function padComponent(value: string, length: number): string {
    if (length <= 0) return "";
    return value.padStart(length, "0").slice(-length);
}

/** Formats a document number from pattern tokens (Y/R/M/N/C runs + literal text). */
export function formatDocumentNumber(pattern: string, counter: number, date = new Date()): string {
    const p = (pattern || "YYYYNNNN").toUpperCase();
    let out = "";
    let i = 0;
    while (i < p.length) {
        const ch = p[i];
        let run = 0;
        while (i < p.length && p[i] === ch) run++, i++;

        if (ch === "M") {
            out += padComponent(String(date.getMonth() + 1), run);
        } else if (ch === "R" || (ch === "Y" && run >= 2)) {
            out += padComponent(String(date.getFullYear()), run);
        } else if (ch === "C" || (ch === "N" && run >= 2)) {
            out += String(Math.max(0, counter)).padStart(run, "0");
        } else {
            out += ch.repeat(run);
        }
    }
    return out;
}

export type NumberSeriesCounterFields = {
    format: string;
    resetPeriod: ResetPeriod;
    periodKey: string | null;
    lastNumber: string | null;
};

export function effectiveLastNumber(series: NumberSeriesCounterFields, date = new Date()): number {
    const key = currentPeriodKey(series.resetPeriod, date);
    if (series.periodKey !== key) return 0;
    return parseInt(series.lastNumber || "0", 10) || 0;
}

export function previewNextNumber(
    series: NumberSeriesCounterFields,
    counterOverride?: number,
    date = new Date(),
): string {
    const counter = counterOverride ?? effectiveLastNumber(series, date) + 1;
    return formatDocumentNumber(series.format, counter, date);
}

export type ParsedDocumentNumber = {
    counter: number;
    year: string | null;
    month: string | null;
};

function escapeRegex(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

/**
 * Parses a number produced by `pattern` back into its parts, or null when
 * the number does not match the pattern at all (foreign / imported numbers,
 * numbers of an earlier format). Matching the WHOLE pattern - not the
 * trailing digits - keeps a year placed after the counter, a widened
 * counter run or an overflowing counter from being misread as the counter.
 * Mirrors DocumentNumberFormatter::parse on the server.
 */
export function parseDocumentNumber(pattern: string, number: string): ParsedDocumentNumber | null {
    const p = (pattern || "").toUpperCase().trim();
    if (!p) return null;

    let regex = "";
    const groups: Array<"counter" | "year" | "month"> = [];
    let i = 0;
    while (i < p.length) {
        const ch = p[i];
        let run = 0;
        while (i < p.length && p[i] === ch) run++, i++;

        if (ch === "M") {
            regex += `(\\d{${run}})`;
            groups.push("month");
        } else if (ch === "R" || (ch === "Y" && run >= 2)) {
            regex += `(\\d{${run}})`;
            groups.push("year");
        } else if (ch === "C" || (ch === "N" && run >= 2)) {
            // padStart never truncates - an overflowing counter is longer.
            regex += `(\\d{${run},})`;
            groups.push("counter");
        } else {
            regex += escapeRegex(ch.repeat(run));
        }
    }

    if (!groups.includes("counter")) return null;
    const match = new RegExp(`^${regex}$`, "i").exec(String(number ?? "").trim());
    if (!match) return null;

    const parts: Record<"counter" | "year" | "month", string | null> = {
        counter: null,
        year: null,
        month: null,
    };
    for (let g = 0; g < groups.length; g++) {
        const value = match[g + 1];
        const name = groups[g];
        // A repeated token must agree with itself.
        if (parts[name] !== null && parts[name] !== value) return null;
        parts[name] = value;
    }

    return {
        counter: parseInt(parts.counter ?? "0", 10),
        year: parts.year,
        month: parts.month,
    };
}

/** Parses an ISO date (YYYY-MM-DD...) without timezone shifts. */
function isoDateParts(value: string | null | undefined): { year: number; month: number } | null {
    const match = /^(\d{4})-(\d{2})/.exec(String(value ?? ""));
    return match ? { year: Number(match[1]), month: Number(match[2]) } : null;
}

/**
 * Counter of `number` when it belongs to the period of `date` under the
 * reset rule, otherwise null - a yearly series must never count a previous
 * year's numbers (the "first invoice of 2027 is 0343" bug). Formats without
 * the period's date token cannot be told apart by the number; the document's
 * issue date decides then, when known. Mirrors
 * DocumentNumberFormatter::counterInPeriod on the server.
 */
export function counterInPeriod(
    pattern: string,
    number: string,
    resetPeriod: ResetPeriod | string,
    date = new Date(),
    documentDate?: string | null,
): number | null {
    const parsed = parseDocumentNumber(pattern, number);
    if (!parsed) return null;
    if (resetPeriod === "never") return parsed.counter;

    if (parsed.year !== null && parsed.year !== padComponent(String(date.getFullYear()), parsed.year.length)) {
        return null;
    }
    if (
        resetPeriod === "monthly"
        && parsed.month !== null
        && parsed.month !== padComponent(String(date.getMonth() + 1), parsed.month.length)
    ) {
        return null;
    }

    const periodFromNumber = resetPeriod === "monthly"
        ? parsed.year !== null && parsed.month !== null
        : parsed.year !== null;
    const docDate = periodFromNumber ? null : isoDateParts(documentDate);
    if (docDate) {
        const sameYear = docDate.year === date.getFullYear();
        const samePeriod = resetPeriod === "monthly"
            ? sameYear && docDate.month === date.getMonth() + 1
            : sameYear;
        if (!samePeriod) return null;
    }

    return parsed.counter;
}

/** Local calendar date (YYYY-MM-DD) - the period the user sees. */
export function localIsoDate(date = new Date()): string {
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    return `${date.getFullYear()}-${month}-${day}`;
}

/**
 * Sortable position of a document number in its sequence: (year, month,
 * counter) parsed with the series format, falling back to the issue date
 * year and the trailing digits for numbers outside the format.
 */
export function documentNumberSortKey(
    pattern: string | null | undefined,
    number: string | null | undefined,
    issueDate?: string | null,
): string {
    const parsed = pattern ? parseDocumentNumber(pattern, String(number ?? "")) : null;
    const docDate = isoDateParts(issueDate);
    const rawYear = parsed?.year ?? null;
    const year = rawYear !== null
        ? (rawYear.length <= 2 ? 2000 + Number(rawYear) : Number(rawYear))
        : (docDate?.year ?? 0);
    const month = parsed?.month != null ? Number(parsed.month) : 0;
    const trailing = /(\d{1,12})$/.exec(String(number ?? ""));
    const counter = parsed?.counter ?? (trailing ? parseInt(trailing[1], 10) : 0);
    return [
        String(year).padStart(4, "0"),
        String(month).padStart(2, "0"),
        parsed ? "1" : "0",
        String(counter).padStart(12, "0"),
        String(issueDate ?? "").slice(0, 10).padEnd(10, "0"),
    ].join("");
}

/** The digits-only rendering of a pattern (what a variable symbol keeps). */
function digitOnlyPattern(pattern: string): string {
    const p = (pattern || "").toUpperCase();
    let out = "";
    let i = 0;
    while (i < p.length) {
        const ch = p[i];
        let run = 0;
        while (i < p.length && p[i] === ch) run++, i++;
        const isToken = ch === "M" || ch === "R" || ch === "C"
            || ((ch === "Y" || ch === "N") && run >= 2);
        if (isToken || /\d/.test(ch)) {
            out += ch.repeat(run);
        }
    }
    return out;
}

/**
 * A draft is rendered with the PREVIEW number: the form pre-fills the
 * variable symbol and title from it, and duplicates / documents derived
 * from a quote inherit the source's. The reserved number can differ
 * (another device, a shared company member, a Woo auto-issue, a cancelled
 * top number), and the explicit VS used to win - bank payments then matched
 * the wrong invoice. Re-derives both from the issued number when they still
 * carry a number of this series (counter within a plausible window, so a
 * user-chosen VS such as an order number is kept).
 */
export function rederiveNumberDerivedFields(
    pattern: string,
    issuedNumber: string,
    issuedCounter: number,
    fields: { title: string | null | undefined; variableSymbol: string | null | undefined },
): { title: string | null; variableSymbol: string | null } {
    const maxCounter = issuedCounter + 50;
    const looksDerived = (counter: number | null | undefined) =>
        counter != null && counter >= 1 && counter <= maxCounter && counter !== issuedCounter;

    let variableSymbol = fields.variableSymbol ?? null;
    const vs = String(variableSymbol ?? "").trim();
    if (vs && /^\d{1,10}$/.test(vs)) {
        const parsedVs = parseDocumentNumber(digitOnlyPattern(pattern), vs);
        if (parsedVs && looksDerived(parsedVs.counter)) {
            const derived = issuedNumber.replace(/\D/g, "").slice(-10);
            variableSymbol = derived || variableSymbol;
        }
    }

    let title = fields.title ?? null;
    if (title) {
        title = title.replace(/[A-Za-z0-9]+/g, (token) => {
            const parsed = parseDocumentNumber(pattern, token);
            return parsed && token !== issuedNumber && looksDerived(parsed.counter) ? issuedNumber : token;
        });
    }

    return { title, variableSymbol };
}
