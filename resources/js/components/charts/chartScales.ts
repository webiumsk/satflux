/**
 * Shared scale/format helpers for the hand-rolled SVG/HTML charts
 * (components/charts/*). Kept dependency-free on purpose - the app ships no
 * chart library and the CSP forbids external assets.
 */

/** Fixed series colors. Single-series charts use the brand indigo. */
export const CHART_PRIMARY = "#6366f1";

let chartUid = 0;

/** Unique id fragment for per-instance SVG defs (gradients). */
export function nextChartUid(): number {
    return ++chartUid;
}

/**
 * Categorical palette for the payment-source breakdown, validated for the
 * dark surface (CVD-safe adjacent pairs, >= 3:1 contrast; dataviz
 * validate_palette.js run 2026-07). Color follows the ENTITY: the mapping is
 * fixed per source and never re-assigned when filters change the set.
 */
export const SOURCE_COLORS: Record<string, string> = {
    pos: "#3987e5",
    pay_button: "#008300",
    ln_address: "#d55181",
    tickets: "#c98500",
    api: "#199e70",
    other: "#d95926",
};

/** "Nice" rounded axis max + tick values (0 included) for a linear scale. */
export function niceTicks(maxValue: number, tickCount = 4): number[] {
    if (!Number.isFinite(maxValue) || maxValue <= 0) {
        return [0, 1];
    }
    const roughStep = maxValue / tickCount;
    const magnitude = Math.pow(10, Math.floor(Math.log10(roughStep)));
    const residual = roughStep / magnitude;
    const step = (residual > 5 ? 10 : residual > 2 ? 5 : residual > 1 ? 2 : 1) * magnitude;

    const ticks: number[] = [];
    for (let v = 0; v < maxValue + step; v += step) {
        ticks.push(Number(v.toFixed(10)));
    }

    return ticks;
}

/** X position (0-100) for index i of n points, spread edge-to-edge. */
export function xPercent(index: number, total: number): number {
    if (total <= 1) {
        return 50;
    }

    return (index / (total - 1)) * 100;
}

/** Y position (0-100, SVG top-down) for a value on a 0..max scale. */
export function yPercent(value: number, max: number): number {
    if (max <= 0) {
        return 100;
    }

    return 100 - Math.min(100, (value / max) * 100);
}

/** Compact number for axis ticks and labels: 950, 1.2k, 3.4M. */
export function formatCompact(value: number): string {
    const abs = Math.abs(value);
    if (abs >= 1_000_000) {
        return trimZero((value / 1_000_000).toFixed(1)) + "M";
    }
    if (abs >= 1_000) {
        return trimZero((value / 1_000).toFixed(1)) + "k";
    }
    if (abs >= 100 || Number.isInteger(value)) {
        return String(Math.round(value));
    }

    return trimZero(value.toFixed(1));
}

function trimZero(s: string): string {
    return s.endsWith(".0") ? s.slice(0, -2) : s;
}

/**
 * Delta vs a previous-period value for KPI badges. Returns null when there is
 * no meaningful base (previous 0 -> percent change is undefined, the UI shows
 * "new" instead of a bogus number).
 */
export function percentDelta(current: number, previous: number): number | null {
    if (!Number.isFinite(previous) || previous === 0) {
        return null;
    }

    return ((current - previous) / previous) * 100;
}
