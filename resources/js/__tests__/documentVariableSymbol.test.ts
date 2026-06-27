import { describe, expect, it } from "vitest";
import { documentVariableSymbol, variableSymbolFromNumber } from "../evolu/documentNumber";

describe("documentVariableSymbol", () => {
    it("strips invoice prefix from explicit VS", () => {
        expect(documentVariableSymbol("INV20260042")).toBe("20260042");
    });

    it("derives digits-only VS from invoice number when explicit is empty", () => {
        expect(documentVariableSymbol("", "INV20260042")).toBe("20260042");
        expect(variableSymbolFromNumber("INV20260042")).toBe("20260042");
    });

    it("keeps numeric VS unchanged", () => {
        expect(documentVariableSymbol("20260042")).toBe("20260042");
    });
});
