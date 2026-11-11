import { deflateSync } from "node:zlib";

/** Build a one-page synthetic PDF with a document-level attachment name tree. */
export function createPdf(files) {
    const fileIds = files.map((_file, index) => 4 + index * 2);
    const nameTree = files.map(({ name }, index) => `(${name}) ${fileIds[index]} 0 R`).join(" ");
    const names = files.length ? `/Names << /EmbeddedFiles << /Names [${nameTree}] >> >> /AF [${fileIds.map(id => `${id} 0 R`).join(" ")}]` : "";
    const objects = [
        Buffer.from(`<< /Type /Catalog /Pages 2 0 R ${names} >>`),
        Buffer.from("<< /Type /Pages /Kids [3 0 R] /Count 1 >>"),
        Buffer.from("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>"),
    ];
    for (const { name, bytes } of files) {
        const fileId = objects.length + 1;
        const stream = deflateSync(bytes);
        objects.push(Buffer.from(`<< /Type /Filespec /F (${name}) /UF (${name}) /AFRelationship /Alternative /EF << /F ${fileId + 1} 0 R /UF ${fileId + 1} 0 R >> >>`));
        objects.push(Buffer.concat([
            Buffer.from(`<< /Type /EmbeddedFile /Subtype /text#2Fxml /Filter /FlateDecode /Params << /Size ${bytes.length} >> /Length ${stream.length} >>\nstream\n`),
            stream,
            Buffer.from("\nendstream"),
        ]));
    }
    const header = Buffer.from("%PDF-1.7\n");
    const result = [header];
    let offset = header.length;
    const xref = [];
    for (const [index, body] of objects.entries()) {
        const object = Buffer.concat([Buffer.from(`${index + 1} 0 obj\n`), body, Buffer.from("\nendobj\n")]);
        xref.push(`${String(offset).padStart(10, "0")} 00000 n \n`);
        offset += object.length;
        result.push(object);
    }
    result.push(Buffer.from(`xref\n0 ${objects.length + 1}\n0000000000 65535 f \n${xref.join("")}trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${offset}\n%%EOF\n`));
    return Buffer.concat(result);
}
