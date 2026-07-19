import { describe, expect, it } from "vitest";
import {
    isDashboardPeriodPreset,
    resolveDashboardRange,
} from "../composables/useDashboardPeriod";

// Fixed reference "now": Wednesday 2026-07-15 (local time).
const NOW = new Date(2026, 6, 15, 12, 0, 0);

describe("useDashboardPeriod", () => {
    it("resolves rolling presets inclusively", () => {
        expect(resolveDashboardRange("7d", {}, NOW)).toEqual({ from: "2026-07-09", to: "2026-07-15" });
        expect(resolveDashboardRange("30d", {}, NOW)).toEqual({ from: "2026-06-16", to: "2026-07-15" });
        expect(resolveDashboardRange("90d", {}, NOW)).toEqual({ from: "2026-04-17", to: "2026-07-15" });
    });

    it("resolves calendar presets", () => {
        expect(resolveDashboardRange("this_month", {}, NOW)).toEqual({ from: "2026-07-01", to: "2026-07-15" });
        expect(resolveDashboardRange("last_month", {}, NOW)).toEqual({ from: "2026-06-01", to: "2026-06-30" });
        expect(resolveDashboardRange("this_year", {}, NOW)).toEqual({ from: "2026-01-01", to: "2026-07-15" });
    });

    it("last_month handles the january wrap", () => {
        const january = new Date(2026, 0, 10);
        expect(resolveDashboardRange("last_month", {}, january)).toEqual({ from: "2025-12-01", to: "2025-12-31" });
    });

    it("custom uses the provided pair, swaps a reversed one and falls back to 30d", () => {
        expect(resolveDashboardRange("custom", { from: "2026-05-01", to: "2026-05-10" }, NOW))
            .toEqual({ from: "2026-05-01", to: "2026-05-10" });
        expect(resolveDashboardRange("custom", { from: "2026-05-10", to: "2026-05-01" }, NOW))
            .toEqual({ from: "2026-05-01", to: "2026-05-10" });
        expect(resolveDashboardRange("custom", {}, NOW))
            .toEqual({ from: "2026-06-16", to: "2026-07-15" });
    });

    it("guards preset values from the URL", () => {
        expect(isDashboardPeriodPreset("30d")).toBe(true);
        expect(isDashboardPeriodPreset("custom")).toBe(true);
        expect(isDashboardPeriodPreset("bogus")).toBe(false);
        expect(isDashboardPeriodPreset(undefined)).toBe(false);
    });
});
