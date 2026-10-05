import { describe, expect, it } from "vitest";
import {
    counterInPeriod,
    dateForPeriodKey,
    parseDocumentNumber,
    rederiveNumberDerivedFields,
} from "@/evolu/numberSeriesFormat";

describe("parseDocumentNumber", () => {
    it("parses with the full pattern", () => {
        expect(parseDocumentNumber("INVYYYYNNNN", "INV20260066")).toEqual({ counter: 66, year: "2026", month: null });
        expect(parseDocumentNumber("FNNNNYYYY", "F00122026")?.counter).toBe(12);
        expect(parseDocumentNumber("INVYYYYNNNN", "INV202610000")?.counter).toBe(10000);
        expect(parseDocumentNumber("FVYYYYNNNNN", "INV20260012")).toBeNull();
    });
});

describe("counterInPeriod", () => {
    const jan2027 = new Date(2027, 0, 2);

    it("drops numbers of an earlier year for a yearly series", () => {
        expect(counterInPeriod("INVYYYYNNNN", "INV20260342", "yearly", jan2027)).toBeNull();
        expect(counterInPeriod("INVYYYYNNNN", "INV20270003", "yearly", jan2027)).toBe(3);
        expect(counterInPeriod("INVYYYYNNNN", "INV20260342", "never", jan2027)).toBe(342);
    });
});

describe("rederiveNumberDerivedFields", () => {
    it("replaces a preview-derived VS and title number with the issued number", () => {
        expect(
            rederiveNumberDerivedFields("INVYYYYNNNN", "INV20260006", 6, {
                title: "Invoice INV20260005",
                variableSymbol: "20260005",
            }),
        ).toEqual({ title: "Invoice INV20260006", variableSymbol: "20260006" });
    });

    it("re-derives what a duplicate inherited from its source", () => {
        expect(
            rederiveNumberDerivedFields("INVYYYYNNNN", "INV20260009", 9, {
                title: "Invoice INV20260005 (copy)",
                variableSymbol: "20260005",
            }),
        ).toEqual({ title: "Invoice INV20260009 (copy)", variableSymbol: "20260009" });
    });

    it("keeps a user-chosen VS and an unrelated title", () => {
        expect(
            rederiveNumberDerivedFields("INVYYYYNNNN", "INV20260006", 6, {
                title: "Consulting October",
                variableSymbol: "7781234",
            }),
        ).toEqual({ title: "Consulting October", variableSymbol: "7781234" });
        // Looks like a series number, but far outside the plausible window.
        expect(
            rederiveNumberDerivedFields("INVYYYYNNNN", "INV20260006", 6, {
                title: null,
                variableSymbol: "20269999",
            }).variableSymbol,
        ).toBe("20269999");
    });
});

describe("rederiveNumberDerivedFields across years", () => {
    it("replaces a number of another year even when the counter is the same or far away", () => {
        // Duplicate of last year's #12 issued as this year's #12.
        expect(
            rederiveNumberDerivedFields("INVYYYYNNNN", "INV20260012", 12, {
                title: "Invoice INV20250012",
                variableSymbol: "20250012",
            }),
        ).toEqual({ title: "Invoice INV20260012", variableSymbol: "20260012" });
        // Last year's #120 copied into this year's #3.
        expect(
            rederiveNumberDerivedFields("INVYYYYNNNN", "INV20260003", 3, {
                title: null,
                variableSymbol: "20250120",
            }).variableSymbol,
        ).toBe("20260003");
    });
});

describe("dateForPeriodKey", () => {
    const jan2027 = new Date(2027, 0, 2, 10);

    it("keeps today for the current period and uses the last day of an earlier one", () => {
        expect(dateForPeriodKey("2027", jan2027)).toBe(jan2027);
        expect(dateForPeriodKey("2026", jan2027).getFullYear()).toBe(2026);
        expect(dateForPeriodKey("2026-12", jan2027).getMonth()).toBe(11);
        expect(dateForPeriodKey("all", jan2027)).toBe(jan2027);
        expect(dateForPeriodKey(null, jan2027)).toBe(jan2027);
    });
});
