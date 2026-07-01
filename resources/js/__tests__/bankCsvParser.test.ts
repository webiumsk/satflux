import { describe, expect, it } from "vitest";
import { parseCsvBankStatement } from "../evolu/bankCsvParser";

describe("parseCsvBankStatement wise profile", () => {
    it("parses Wise CSV and keeps reference separate from VS", () => {
        const csv = [
            "ID,Status,Direction,Created on,Finished on,Source name,Source amount (after fees),Source currency,Target name,Target amount (after fees),Target currency,Reference",
            "tx-1,COMPLETED,IN,01-06-2026,02-06-2026,Acme Client,250.00,USD,Webium LLC,250.00,USD,Invoice INV-0042",
        ].join("\n");

        const rows = parseCsvBankStatement(csv);

        expect(rows).toHaveLength(1);
        expect(rows[0].direction).toBe("credit");
        expect(rows[0].amount).toBe(250);
        expect(rows[0].currency).toBe("USD");
        expect(rows[0].variableSymbol).toBeNull();
        expect(rows[0].counterpartyName).toBe("Acme Client");
        expect(rows[0].reference).toBe("Invoice INV-0042");
    });

    it("marks OUT transactions as debit", () => {
        const csv = [
            "ID,Status,Direction,Created on,Finished on,Source name,Source amount (after fees),Source currency,Target name,Target amount (after fees),Target currency,Reference",
            "tx-2,COMPLETED,OUT,01-06-2026,02-06-2026,Webium LLC,50.00,USD,Vendor Inc,50.00,USD,Payment",
        ].join("\n");

        const rows = parseCsvBankStatement(csv);

        expect(rows[0].direction).toBe("debit");
        expect(rows[0].amount).toBe(50);
    });
});
