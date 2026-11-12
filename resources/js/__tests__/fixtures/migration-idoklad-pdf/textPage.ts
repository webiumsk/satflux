import type { MigrationPdfTextItem, MigrationPdfTextPage } from "../../../evolu/migrationIsdocPdf";
export function textItem(str: string, x: number, y: number, width = str.length * 4.5): MigrationPdfTextItem {
    return { str, dir: "ltr", width, height: 8, transform: [8, 0, 0, 8, x, y] };
}
export function syntheticIdokladPage(number = "FIX-TEST-1"): MigrationPdfTextPage {
    const item = textItem;
    return { width: 595, height: 842, rotation: 0, items: [
        item("Dodávateľ", 45, 812, 60), item("Synthetic Supplier", 45, 801, 100),
        item("Faktúra vystavená", 340, 813, 90), item(number, 510, 813, 70),
        item("IČO: 00012345", 45, 751, 60),
        item("Odberateľ", 339, 694, 60), item("Synthetic Buyer", 339, 682, 100),
        item("Synthetic street", 339, 670, 100), item("IČO: 00054321", 339, 632, 60),
        item("Dátum vystavenia", 44, 561, 64), item("01.11.2026", 112, 561, 50),
        item("Dátum dodania", 185, 561, 55), item("01.11.2026", 244, 561, 50),
        item("Dátum splatnosti", 302, 561, 61), item("15.11.2026", 367, 561, 50),
        item("Na úhradu", 420, 492, 50), item("123,00 EUR", 398, 476, 90),
        item("Označenie dodávky", 45, 417, 120), item("Počet", 243, 417, 20),
        item("m. j.", 267, 417, 18), item("Cena za m.j.", 310, 417, 43),
        item("DPH %", 361, 417, 28), item("Bez DPH", 413, 417, 35),
        item("DPH", 483, 417, 18), item("Spolu", 542, 417, 25),
        item("Synthetic service", 45, 398, 110), item("1", 247, 398, 17),
        item("100,00", 326, 398, 24), item("23", 373, 398, 12),
        item("100,00", 418, 398, 27), item("23,00", 475, 398, 28), item("123,00", 529, 398, 35),
        item("Sadzba", 285, 292, 40), item("Základ", 393, 292, 30),
        item("Výška DPH", 444, 292, 46), item("Spolu", 539, 292, 28),
        item("23 %", 285, 272, 30), item("100,00 EUR", 375, 272, 70),
        item("23,00 EUR", 445, 272, 60), item("123,00 EUR", 507, 272, 70),
        item("Spolu", 287, 254, 35), item("100,00 EUR", 375, 254, 70),
        item("23,00 EUR", 445, 254, 60), item("123,00 EUR", 507, 254, 70),
        item("Spolu na úhradu:", 361, 206, 100), item("123,00 EUR", 498, 206, 80),
    ] };
}
