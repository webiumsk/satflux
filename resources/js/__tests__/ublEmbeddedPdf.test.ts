import { afterEach, describe, expect, it, vi } from 'vitest';
import { extractUblEmbeddedPdfs } from '../utils/ublEmbeddedPdf';

const CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
const CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
const XML_LIMIT = 2 * 1024 * 1024;
const PDF_LIMIT = 1024 * 1024;
const PDF = new TextEncoder().encode('%PDF-1.4\n% synthetic attachment\n%%EOF\n');

function base64(bytes = PDF): string {
  let binary = '';
  for (const byte of bytes) binary += String.fromCharCode(byte);
  return btoa(binary);
}

function ubl(body: string, type: 'Invoice' | 'CreditNote' = 'Invoice'): string {
  return `<${type} xmlns="urn:oasis:names:specification:ubl:schema:xsd:${type}-2" xmlns:cac="${CAC}" xmlns:cbc="${CBC}">${body}</${type}>`;
}

function attachment(content = base64(), attributes = 'mimeCode="application/pdf" filename="synthetic.pdf"'): string {
  return `<cac:AdditionalDocumentReference><cac:Attachment><cbc:EmbeddedDocumentBinaryObject ${attributes}>${content}</cbc:EmbeddedDocumentBinaryObject></cac:Attachment></cac:AdditionalDocumentReference>`;
}

afterEach(() => vi.unstubAllGlobals());

describe('extractUblEmbeddedPdfs', () => {
  it.each(['Invoice', 'CreditNote'] as const)('extracts exact PDF bytes from a UBL %s', (type) => {
    const result = extractUblEmbeddedPdfs(ubl(attachment(), type));
    expect(result.error).toBeUndefined();
    expect(result.rejectedCount).toBe(0);
    expect(result.pdfs).toHaveLength(1);
    expect(result.pdfs[0]).toMatchObject({ id: 'ubl-pdf-1', filename: 'synthetic.pdf' });
    expect(Array.from(result.pdfs[0].bytes)).toEqual(Array.from(PDF));
  });

  it('uses namespace URIs independently of prefix names', () => {
    const xml = `<u:CreditNote xmlns:u="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2" xmlns:a="${CAC}" xmlns:b="${CBC}"><a:AdditionalDocumentReference><a:Attachment><b:EmbeddedDocumentBinaryObject mimeCode=" Application/PDF " filename="Príloha.PDF">${base64()}</b:EmbeddedDocumentBinaryObject></a:Attachment></a:AdditionalDocumentReference></u:CreditNote>`;
    const result = extractUblEmbeddedPdfs(xml);
    expect(result.pdfs).toHaveLength(1);
    expect(result.pdfs[0]).toMatchObject({ id: 'ubl-pdf-1', filename: 'Príloha.pdf' });
    expect(Array.from(result.pdfs[0].bytes)).toEqual(Array.from(PDF));
  });

  it.each(['Invoice', 'CreditNote'] as const)('accepts XML character references in the %s and attachment namespace URIs', (type) => {
    const xml = ubl(attachment(), type)
      .replace(`${type}-2`, `${type}-&#x32;`)
      .replace('CommonAggregateComponents-2', 'CommonAggregateComponents-&#50;')
      .replace('CommonBasicComponents-2', 'CommonBasicComponents-&#x32;');
    const result = extractUblEmbeddedPdfs(xml);
    expect(result.error).toBeUndefined();
    expect(result.pdfs).toHaveLength(1);
    expect(Array.from(result.pdfs[0].bytes)).toEqual(Array.from(PDF));
  });

  it('accepts XML whitespace in Base64 and preserves binary bytes without UTF-8 decoding', () => {
    const bytes = new Uint8Array([...PDF, 0, 128, 195, 169, 255]);
    const encoded = base64(bytes).match(/.{1,8}/g)!.join(' \t\r\n');
    expect(extractUblEmbeddedPdfs(ubl(attachment(encoded))).pdfs[0].bytes).toEqual(bytes);
  });

  it('ignores non-PDF and external references without making HTTP requests', () => {
    const fetch = vi.fn(() => { throw new Error('Unexpected HTTP'); });
    const XMLHttpRequest = vi.fn(() => { throw new Error('Unexpected HTTP'); });
    vi.stubGlobal('fetch', fetch);
    vi.stubGlobal('XMLHttpRequest', XMLHttpRequest);
    const external = '<cac:AdditionalDocumentReference><cac:Attachment><cac:ExternalReference><cbc:URI>https://example.invalid/file.pdf</cbc:URI></cac:ExternalReference></cac:Attachment></cac:AdditionalDocumentReference>';
    const result = extractUblEmbeddedPdfs(ubl(external + attachment('not-base64', 'mimeCode="image/png"') + attachment()));
    expect(result.pdfs).toHaveLength(1);
    expect(result.pdfs[0].id).toBe('ubl-pdf-2');
    expect(result.rejectedCount).toBe(0);
    expect(fetch).not.toHaveBeenCalled();
    expect(XMLHttpRequest).not.toHaveBeenCalled();
  });

  it('ignores spoofed elements outside the exact direct UBL attachment path', () => {
    const nested = `<cac:InvoiceLine>${attachment()}</cac:InvoiceLine>`;
    const wrongReference = attachment().split('cac:AdditionalDocumentReference').join('spoof:AdditionalDocumentReference');
    const wrongAttachment = attachment().split('cac:Attachment').join('spoof:Attachment');
    const wrongBinary = attachment().split('cbc:EmbeddedDocumentBinaryObject').join('spoof:EmbeddedDocumentBinaryObject');
    const wrapped = attachment().replace('<cbc:', '<cac:Wrapper><cbc:').replace('</cac:Attachment>', '</cac:Wrapper></cac:Attachment>');
    const xml = ubl(nested + wrongReference + wrongAttachment + wrongBinary + wrapped).replace('xmlns:cac=', 'xmlns:spoof="urn:spoof" xmlns:cac=');
    expect(extractUblEmbeddedPdfs(xml)).toEqual({ pdfs: [], rejectedCount: 0 });
  });

  it.each([
    '', '<Invoice>', ubl('<cac:Broken>'), `${ubl('')}${ubl('')}`, '<Other><Unclosed></Other>',
  ])('reports malformed XML as invalid, including unsupported document shapes %s', (xml) => {
    expect(extractUblEmbeddedPdfs(xml)).toEqual({ pdfs: [], rejectedCount: 0, error: 'invalid_xml' });
  });

  it.each([
    '<Invoice/>', '<CreditNote xmlns="urn:spoof"/>',
    '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:CreditNote-2"/>',
    '<html xmlns="http://www.w3.org/1999/xhtml"><body/></html>',
    '<Invoice xmlns="http://isdoc.cz/namespace/2013"><DocumentType>1</DocumentType></Invoice>',
    ubl(attachment()).replace('xsd:Invoice-2', 'xsd:Spoof-2'),
  ])('reports successfully parsed non-UBL XML as unsupported without extracting PDFs %s', (xml) => {
    expect(extractUblEmbeddedPdfs(xml)).toEqual({ pdfs: [], rejectedCount: 0, error: 'unsupported_document' });
  });

  it.each([
    '<!DOCTYPE Invoice SYSTEM "https://example.invalid/external.dtd">',
    '<!DOCTYPE Invoice [<!ENTITY document "JVBERi0xLjQ=">]>',
    '<!ENTITY document SYSTEM "file:///synthetic.pdf">',
  ])('rejects DTD/entity declarations before parsing', (declaration) => {
    const parse = vi.fn();
    vi.stubGlobal('DOMParser', class { parseFromString = parse; });
    expect(extractUblEmbeddedPdfs(declaration + ubl(attachment()))).toEqual({ pdfs: [], rejectedCount: 0, error: 'invalid_xml' });
    expect(parse).not.toHaveBeenCalled();
  });

  it.each([
    '', '!!!!', 'JVBERi0', 'JVBERi0===', 'JVBERi1=', 'JVBERi0xLh==',
    'JVBERi0=AA==', `${base64()}\u00a0`, base64(new TextEncoder().encode('<html>synthetic</html>')),
    '<cbc:Value>JVBERi0=</cbc:Value>',
  ])('rejects an invalid PDF candidate and continues with later attachments', (content) => {
    const result = extractUblEmbeddedPdfs(ubl(attachment(content) + attachment()));
    expect(result.pdfs).toHaveLength(1);
    expect(result.pdfs[0].id).toBe('ubl-pdf-2');
    expect(result.rejectedCount).toBe(1);
    expect(result.error).toBeUndefined();
  });

  it.each(['JVBERi0=', 'JVBERi0xLg==', 'JVBERi0x'])('accepts canonical Base64 with the required padding %s', (content) => {
    const result = extractUblEmbeddedPdfs(ubl(attachment(content)));
    expect(result.pdfs).toHaveLength(1);
    expect(result.rejectedCount).toBe(0);
  });

  it('returns an empty result without warnings when no PDF is embedded', () => {
    expect(extractUblEmbeddedPdfs(ubl(''))).toEqual({ pdfs: [], rejectedCount: 0 });
    expect(extractUblEmbeddedPdfs(ubl(attachment('anything', 'mimeCode="text/plain"')))).toEqual({ pdfs: [], rejectedCount: 0 });
  });

  it('limits XML by UTF-8 bytes, including multibyte text, before DOM parsing', () => {
    const parse = vi.fn();
    vi.stubGlobal('DOMParser', class { parseFromString = parse; });
    for (const xml of ['x'.repeat(XML_LIMIT + 1), 'é'.repeat(XML_LIMIT / 2 + 1)]) {
      expect(extractUblEmbeddedPdfs(xml)).toEqual({ pdfs: [], rejectedCount: 0, error: 'too_large' });
    }
    expect(parse).not.toHaveBeenCalled();
  });

  it('accepts a valid UBL document exactly at the XML byte limit', () => {
    const empty = ubl('<cbc:Note></cbc:Note>');
    const xml = empty.replace('</cbc:Note>', `${'x'.repeat(XML_LIMIT - empty.length)}</cbc:Note>`);
    expect(extractUblEmbeddedPdfs(xml)).toEqual({ pdfs: [], rejectedCount: 0 });
  });

  it('accepts one PDF at the individual decoded byte cap and rejects the next byte', () => {
    const bytes = new Uint8Array(PDF_LIMIT);
    bytes.set(PDF);
    const accepted = extractUblEmbeddedPdfs(ubl(attachment(base64(bytes))));
    expect(accepted.pdfs[0].bytes.length).toBe(bytes.length);
    expect(accepted.pdfs[0].bytes.every((byte, index) => byte === bytes[index])).toBe(true);
    expect(accepted.rejectedCount).toBe(0);
    const oversized = new Uint8Array(PDF_LIMIT + 1);
    oversized.set(PDF);
    expect(extractUblEmbeddedPdfs(ubl(attachment(base64(oversized))))).toEqual({ pdfs: [], rejectedCount: 1 });
  });

  it('caps the PDF count, counts further rejected PDFs and keeps stable duplicate-name IDs', () => {
    const xml = ubl(attachment().repeat(12));
    const result = extractUblEmbeddedPdfs(xml);
    expect(result.pdfs).toHaveLength(10);
    expect(result.rejectedCount).toBe(2);
    expect(new Set(result.pdfs.map((pdf) => pdf.id)).size).toBe(10);
    expect(new Set(result.pdfs.map((pdf) => pdf.filename))).toEqual(new Set(['synthetic.pdf']));
    expect(extractUblEmbeddedPdfs(xml)).toEqual(result);
    expect(result.pdfs.reduce((total, pdf) => total + pdf.bytes.length, 0)).toBeLessThanOrEqual(2 * 1024 * 1024);
  });

  it.each([
    [undefined, 'attachment-1.pdf'], ['', 'attachment-1.pdf'], [' .. ', 'attachment-1.pdf'], ['.pdf', 'attachment-1.pdf'],
    ['../../invoice\\preview.html', '_.._invoice_preview.html.pdf'],
    [' príloha číslo 1.PDF ', 'príloha číslo 1.pdf'],
    ['bad&#x9;name&#x7f;.pdf', 'bad_name_.pdf'],
    ['safe&#x202e;fdp.exe', 'safefdp.exe.pdf'],
    ['safe&#x061c;&#x200e;&#x200f;&#x2066;name&#x2069;', 'safename.pdf'],
  ])('sanitizes the download filename %s', (name, expected) => {
    const attributes = `mimeCode="application/pdf"${name === undefined ? '' : ` filename="${name}"`}`;
    expect(extractUblEmbeddedPdfs(ubl(attachment(base64(), attributes))).pdfs[0].filename).toBe(expected);
  });

  it('bounds Unicode filenames including the PDF suffix without broken surrogate pairs', () => {
    const filename = `${'😀'.repeat(140)}.pdf`;
    const result = extractUblEmbeddedPdfs(ubl(attachment(base64(), `mimeCode="application/pdf" filename="${filename}"`)));
    expect(result.pdfs[0].filename).toBe(`${'😀'.repeat(125)}.pdf`);
    expect(result.pdfs[0].filename.length).toBeLessThanOrEqual(255);
  });
});
