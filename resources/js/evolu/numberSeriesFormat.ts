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
