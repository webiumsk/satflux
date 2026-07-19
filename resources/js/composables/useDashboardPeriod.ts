import { computed, ref } from "vue";

/**
 * Date-range presets for the dashboard analytics filter. Pure date math -
 * URL sync stays in the consuming component. `now` is injectable for tests.
 */

export type DashboardPeriodPreset =
    | "7d"
    | "30d"
    | "90d"
    | "this_month"
    | "last_month"
    | "this_year"
    | "custom";

export const DASHBOARD_PERIOD_PRESETS: DashboardPeriodPreset[] = [
    "7d",
    "30d",
    "90d",
    "this_month",
    "last_month",
    "this_year",
    "custom",
];

export type DashboardRange = { from: string; to: string };

function iso(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, "0");
    const d = String(date.getDate()).padStart(2, "0");

    return `${y}-${m}-${d}`;
}

function daysAgo(now: Date, days: number): Date {
    const d = new Date(now);
    d.setDate(d.getDate() - days);

    return d;
}

/** Resolve a preset (or custom pair) to a from/to range in local time. */
export function resolveDashboardRange(
    preset: DashboardPeriodPreset,
    custom: Partial<DashboardRange>,
    now: Date = new Date(),
): DashboardRange {
    switch (preset) {
        case "7d":
            return { from: iso(daysAgo(now, 6)), to: iso(now) };
        case "30d":
            return { from: iso(daysAgo(now, 29)), to: iso(now) };
        case "90d":
            return { from: iso(daysAgo(now, 89)), to: iso(now) };
        case "this_month":
            return { from: iso(new Date(now.getFullYear(), now.getMonth(), 1)), to: iso(now) };
        case "last_month": {
            const first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            const last = new Date(now.getFullYear(), now.getMonth(), 0);

            return { from: iso(first), to: iso(last) };
        }
        case "this_year":
            return { from: iso(new Date(now.getFullYear(), 0, 1)), to: iso(now) };
        case "custom": {
            const from = custom.from || iso(daysAgo(now, 29));
            const to = custom.to || iso(now);

            return from <= to ? { from, to } : { from: to, to: from };
        }
    }
}

export function isDashboardPeriodPreset(value: unknown): value is DashboardPeriodPreset {
    return typeof value === "string" && (DASHBOARD_PERIOD_PRESETS as string[]).includes(value);
}

export function useDashboardPeriod(now: () => Date = () => new Date()) {
    const preset = ref<DashboardPeriodPreset>("30d");
    const customFrom = ref<string>("");
    const customTo = ref<string>("");

    const range = computed<DashboardRange>(() =>
        resolveDashboardRange(preset.value, { from: customFrom.value, to: customTo.value }, now()),
    );

    return { preset, customFrom, customTo, range };
}
