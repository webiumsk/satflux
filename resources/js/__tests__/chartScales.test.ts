import { describe, expect, it } from "vitest";
import {
    formatCompact,
    niceTicks,
    percentDelta,
    xPercent,
    yPercent,
} from "../components/charts/chartScales";

describe("chartScales", () => {
    it("produces nice rounded ticks including zero", () => {
        expect(niceTicks(0)).toEqual([0, 1]);
        expect(niceTicks(97)).toEqual([0, 50, 100]);
        expect(niceTicks(40)).toEqual([0, 10, 20, 30, 40]);
        expect(niceTicks(4)).toEqual([0, 1, 2, 3, 4]);
        const big = niceTicks(123_456);
        expect(big[0]).toBe(0);
        expect(big[big.length - 1]).toBeGreaterThanOrEqual(123_456);
    });

    it("spreads x positions edge to edge", () => {
        expect(xPercent(0, 7)).toBe(0);
        expect(xPercent(6, 7)).toBe(100);
        expect(xPercent(0, 1)).toBe(50);
    });

    it("maps values to a top-down y scale", () => {
        expect(yPercent(0, 100)).toBe(100);
        expect(yPercent(100, 100)).toBe(0);
        expect(yPercent(50, 100)).toBe(50);
        expect(yPercent(5, 0)).toBe(100);
    });

    it("formats compact numbers", () => {
        expect(formatCompact(950)).toBe("950");
        expect(formatCompact(1200)).toBe("1.2k");
        expect(formatCompact(3_400_000)).toBe("3.4M");
        expect(formatCompact(2000)).toBe("2k");
        expect(formatCompact(0)).toBe("0");
    });

    it("computes percent deltas with an undefined base as null", () => {
        expect(percentDelta(110, 100)).toBeCloseTo(10);
        expect(percentDelta(50, 100)).toBeCloseTo(-50);
        expect(percentDelta(10, 0)).toBeNull();
    });
});
