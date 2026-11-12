/** Fully synthetic native-text invoices for isolated PDF.js/browser regression checks. */
export function syntheticInvoicePage({
    number = "ID-TEST1", lines = [{ quantity: "1", unitPrice: "100", rate: "23", net: "100", tax: "23", gross: "123" }],
    issueDate = "01.11.2026", deliveryDate = issueDate, dueDate = "15.11.2026",
    supplierRegistration = "00012345", buyerRegistration = "00054321", buyerName = "Synthetic Buyer",
} = {}) {
    const item = (str, x, y, width) => ({ str, dir: "ltr", width, height: 8, transform: [8, 0, 0, 8, x, y] });
    const fmt = value => Number(value).toFixed(2).replace(".", ",");
    const unitText = value => {
        const [integer, fraction = ""] = String(value).split(".");
        return `${integer},${fraction.padEnd(2, "0")}`;
    };
    const buckets = new Map();
    for (const line of lines) {
        const bucket = buckets.get(line.rate) ?? { net: 0, tax: 0, gross: 0 };
        for (const field of ["net", "tax", "gross"]) bucket[field] += Math.round(Number(line[field]) * 100);
        buckets.set(line.rate, bucket);
    }
    const totals = [...buckets.values()].reduce((sum, bucket) => ({
        net: sum.net + bucket.net, tax: sum.tax + bucket.tax, gross: sum.gross + bucket.gross,
    }), { net: 0, tax: 0, gross: 0 });
    const totalY = 254 - (buckets.size - 1) * 18;
    const items = [
        item("Dodávateľ", 45, 812, 60), item("Synthetic Supplier", 45, 801, 100),
        item("Faktúra vystavená", 340, 813, 90), item(number, 510, 813, 70),
        item(`IČO: ${supplierRegistration}`, 45, 751, 60),
        item("Odberateľ", 339, 694, 60), item(buyerName, 339, 682, 100),
        item("Synthetic street", 339, 670, 100), item(`IČO: ${buyerRegistration}`, 339, 632, 60),
        item("Dátum vystavenia", 44, 561, 64), item(issueDate, 112, 561, 50),
        item("Dátum dodania", 185, 561, 55), item(deliveryDate, 244, 561, 50),
        item("Dátum splatnosti", 302, 561, 61), item(dueDate, 367, 561, 50),
        item("Na úhradu", 420, 492, 50), item(`${fmt(totals.gross / 100)} EUR`, 398, 476, 90),
        item("Označenie dodávky", 45, 417, 120), item("Počet", 243, 417, 20),
        item("m. j.", 267, 417, 18), item("Cena za m.j.", 310, 417, 43),
        item("DPH %", 361, 417, 28), item("Bez DPH", 413, 417, 35),
        item("DPH", 483, 417, 18), item("Spolu", 542, 417, 25),
    ];
    for (const [index, line] of lines.entries()) {
        const y = 398 - index * 18;
        items.push(item(line.name ?? `Synthetic service ${index + 1}`, 45, y, 110),
            item(unitText(line.quantity), 247, y, 17), item(unitText(line.unitPrice), 326, y, 24), item(line.rate, 373, y, 12),
            item(fmt(line.net), 418, y, 27), item(fmt(line.tax), 475, y, 28), item(fmt(line.gross), 529, y, 35));
        if (line.unit) items.push(item(line.unit, 269, y, 10));
    }
    items.push(item("Sadzba", 285, 292, 40), item("Základ", 393, 292, 30), item("Výška DPH", 444, 292, 46), item("Spolu", 539, 292, 28));
    for (const [index, [rate, bucket]] of [...buckets].entries()) {
        const y = 272 - index * 18;
        items.push(item(`${rate} %`, 285, y, 30), item(`${fmt(bucket.net / 100)} EUR`, 375, y, 70),
            item(`${fmt(bucket.tax / 100)} EUR`, 445, y, 60), item(`${fmt(bucket.gross / 100)} EUR`, 507, y, 70));
    }
    items.push(item("Spolu", 287, totalY, 35), item(`${fmt(totals.net / 100)} EUR`, 375, totalY, 70),
        item(`${fmt(totals.tax / 100)} EUR`, 445, totalY, 60), item(`${fmt(totals.gross / 100)} EUR`, 507, totalY, 70),
        item("Spolu na úhradu:", 361, totalY - 48, 100), item(`${fmt(totals.gross / 100)} EUR`, 498, totalY - 48, 80));
    return { width: 595, height: 842, rotation: 0, items };
}

/** Embedded ToUnicode maps keep Slovak labels machine-readable without font downloads. */
export function createPdfFromTextPages(pages) {
    const strings = pages.flatMap(page => page.items.map(item => item.str));
    if (strings.some(text => [...text].some(char => char.codePointAt(0) > 0xffff))) throw Error("Synthetic fixture supports BMP text only");
    const points = [...new Set(strings.join("").split("").map(char => char.charCodeAt(0)))].sort((a, b) => a - b);
    const hex = value => value.toString(16).padStart(4, "0").toUpperCase();
    const mappings = [];
    for (let index = 0; index < points.length; index += 100) {
        const slice = points.slice(index, index + 100);
        mappings.push(`${slice.length} beginbfchar\n${slice.map(point => `<${hex(point)}> <${hex(point)}>`).join("\n")}\nendbfchar`);
    }
    const cmap = Buffer.from(`/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n/CIDSystemInfo << /Registry (Adobe) /Ordering (UCS) /Supplement 0 >> def\n/CMapName /Synthetic-UCS def\n/CMapType 2 def\n1 begincodespacerange\n<0000> <FFFF>\nendcodespacerange\n${mappings.join("\n")}\nendcmap\nCMapName currentdict /CMap defineresource pop\nend\nend`);
    const kids = pages.map((_page, index) => `${7 + index * 2} 0 R`).join(" ");
    const objects = [
        Buffer.from("<< /Type /Catalog /Pages 2 0 R >>"),
        Buffer.from(`<< /Type /Pages /Count ${pages.length} /Kids [${kids}] >>`),
        Buffer.from("<< /Type /Font /Subtype /Type0 /BaseFont /Helvetica /Encoding /Identity-H /DescendantFonts [4 0 R] /ToUnicode 6 0 R >>"),
        Buffer.from("<< /Type /Font /Subtype /CIDFontType2 /BaseFont /Helvetica /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 5 0 R /CIDToGIDMap /Identity /DW 500 >>"),
        Buffer.from("<< /Type /FontDescriptor /FontName /Helvetica /Flags 32 /FontBBox [0 -200 1000 900] /ItalicAngle 0 /Ascent 800 /Descent -200 /CapHeight 700 /StemV 80 /MissingWidth 500 >>"),
        Buffer.concat([Buffer.from(`<< /Length ${cmap.length} >>\nstream\n`), cmap, Buffer.from("\nendstream")]),
    ];
    for (const [index, page] of pages.entries()) {
        const contentId = 8 + index * 2;
        const content = Buffer.from(page.items.map(item => {
            const encoded = item.str.split("").map(char => hex(char.charCodeAt(0))).join("");
            const fontSize = 2 * item.width / Math.max(1, item.str.length);
            return `BT /F1 ${fontSize.toFixed(6)} Tf 1 0 0 1 ${item.transform[4]} ${item.transform[5]} Tm <${encoded}> Tj ET`;
        }).join("\n"));
        objects.push(Buffer.from(`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${page.width} ${page.height}] /Resources << /Font << /F1 3 0 R >> >> /Contents ${contentId} 0 R >>`));
        objects.push(Buffer.concat([Buffer.from(`<< /Length ${content.length} >>\nstream\n`), content, Buffer.from("\nendstream")]));
    }
    const output = [Buffer.from("%PDF-1.7\n")], offsets = [];
    let offset = output[0].length;
    for (const [index, object] of objects.entries()) {
        offsets.push(offset);
        const bytes = Buffer.concat([Buffer.from(`${index + 1} 0 obj\n`), object, Buffer.from("\nendobj\n")]);
        output.push(bytes); offset += bytes.length;
    }
    output.push(Buffer.from(`xref\n0 ${objects.length + 1}\n0000000000 65535 f \n${offsets.map(value => `${String(value).padStart(10, "0")} 00000 n \n`).join("")}trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${offset}\n%%EOF\n`));
    return Buffer.concat(output);
}
export function createIdokladPdf(options = {}) {
    const invoices = Array.isArray(options) ? options : [options];
    return createPdfFromTextPages(invoices.map(syntheticInvoicePage));
}
