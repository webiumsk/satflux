import type { ResetPeriod } from "./schema";

export function currentPeriodKey(resetPeriod: ResetPeriod, date = new Date()): string {
    if (resetPeriod === "monthly") {
        const month = String(date.getMonth() + 1).padStart(2, "0");
        return `${date.getFullYear()}-${month}`;
    }
    if (resetPeriod === "never") return "all";
    return String(date.getFullYear());
}

export function counterDigitsInFormat(format: string): number {
    const pattern = format.toUpperCase().trim();
    const trailing = pattern.match(/C+$/);
    if (trailing) return trailing[0].length;
    return Math.max(1, (pattern.match(/C/g) || []).length);
}

/** Formats a document number from pattern tokens (R/M/C runs + literal text). */
export function formatDocumentNumber(pattern: string, counter: number, date = new Date()): string {
    const p = (pattern || "RRRRCCCC").toUpperCase();
    let out = "";
    let i = 0;
    while (i < p.length) {
        const ch = p[i];
        if (ch === "R" || ch === "M" || ch === "C") {
            let run = 0;
            while (i < p.length && p[i] === ch) run++, i++;
            if (ch === "R") {
                const y = String(date.getFullYear()).padStart(run, "0").slice(-run);
                out += y;
            } else if (ch === "M") {
                const m = String(date.getMonth() + 1).padStart(run, "0").slice(-run);
                out += m;
            } else {
                out += String(Math.max(0, counter)).padStart(run, "0");
            }
        } else {
            out += ch;
            i++;
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
