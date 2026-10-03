import { describe, expect, it } from "vitest";
import {
    counterInPeriod,
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
