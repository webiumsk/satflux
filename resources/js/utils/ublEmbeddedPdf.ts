export type EmbeddedUblPdf = {
  id: string;
  filename: string;
  bytes: Uint8Array;
};

type EmbeddedUblPdfResult = {
  pdfs: EmbeddedUblPdf[];
  rejectedCount: number;
  error?: 'invalid_xml' | 'too_large' | 'unsupported_document';
};

const MAX_XML_BYTES = 2 * 1024 * 1024;
const MAX_PDF_BYTES = 1024 * 1024;
const MAX_TOTAL_PDF_BYTES = 2 * 1024 * 1024;
const MAX_PDFS = 10;
const CAC_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
const CBC_NAMESPACE = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';
const BASE64_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

function childrenNamed(parent: Element, namespace: string, name: string): Element[] {
  return Array.from(parent.children).filter((child) => child.namespaceURI === namespace && child.localName === name);
}

function pdfFilename(raw: string | null, index: number): string {
  const fallback = `attachment-${index}.pdf`;
  const sanitized = Array.from(raw ?? '').map((character) => {
    const code = character.charCodeAt(0);
    if (
      code === 0x061c || code === 0x200e || code === 0x200f
      || (code >= 0x202a && code <= 0x202e) || (code >= 0x2066 && code <= 0x2069)
    ) return '';
    if (
      code <= 0x1f || (code >= 0x7f && code <= 0x9f)
      || '/\\:*?"<>|'.includes(character)
    ) return '_';
    return character;
  }).join('').trim();
  let stem = sanitized.replace(/\.pdf$/i, '');
  let start = 0;
  let end = stem.length;
  while (start < end && (stem[start] === '.' || stem[start] === ' ')) start += 1;
  while (end > start && (stem[end - 1] === '.' || stem[end - 1] === ' ')) end -= 1;
  stem = stem.slice(start, end);
  if (!stem) return fallback;

  // Keep the suffix and avoid cutting a Unicode surrogate pair at the limit.
  let shortened = '';
  for (const character of stem) {
    if (shortened.length + character.length > 251) break;
    shortened += character;
  }
  return `${shortened.replace(/[. ]+$/g, '')}.pdf`;
}

function decodePdf(element: Element): Uint8Array | null {
  if (element.children.length !== 0) return null;
  const base64 = (element.textContent ?? '').replace(/[ \t\r\n]/g, '');
  if (!base64 || base64.length % 4 !== 0 || !/^[A-Za-z0-9+/]+={0,2}$/.test(base64)) return null;
  const padding = base64.endsWith('==') ? 2 : base64.endsWith('=') ? 1 : 0;
  const decodedSize = (base64.length / 4) * 3 - padding;
  if (decodedSize < 5 || decodedSize > MAX_PDF_BYTES) return null;

  // Reject noncanonical padding bits as well as malformed Base64 syntax.
  const lastValue = BASE64_ALPHABET.indexOf(base64[base64.length - padding - 1]);
  if ((padding === 2 && (lastValue & 15) !== 0) || (padding === 1 && (lastValue & 3) !== 0)) return null;

  try {
    const decoded = atob(base64);
    if (!decoded.startsWith('%PDF-')) return null;
    return Uint8Array.from(decoded, (character) => character.charCodeAt(0));
  } catch {
    return null;
  }
}

/** Extract bounded, embedded PDF bytes only; external document references are ignored. */
export function extractUblEmbeddedPdfs(xml: string): EmbeddedUblPdfResult {
  // Bound allocation before UTF-8 encoding, then enforce the actual byte limit.
  if (xml.length > MAX_XML_BYTES || new TextEncoder().encode(xml).length > MAX_XML_BYTES) {
    return { pdfs: [], rejectedCount: 0, error: 'too_large' };
  }
  if (/<!\s*(?:DOCTYPE|ENTITY)\b/i.test(xml)) {
    return { pdfs: [], rejectedCount: 0, error: 'invalid_xml' };
  }

  let document: XMLDocument;
  try {
    document = new DOMParser().parseFromString(xml, 'application/xml');
  } catch {
    return { pdfs: [], rejectedCount: 0, error: 'invalid_xml' };
  }
  const root = document.documentElement;
  if (!root || document.getElementsByTagNameNS('http://www.mozilla.org/newlayout/xml/parsererror.xml', 'parsererror').length > 0) {
    return { pdfs: [], rejectedCount: 0, error: 'invalid_xml' };
  }
  if (
    !['Invoice', 'CreditNote'].includes(root.localName)
    || root.namespaceURI !== `urn:oasis:names:specification:ubl:schema:xsd:${root.localName}-2`
  ) return { pdfs: [], rejectedCount: 0, error: 'unsupported_document' };

  const result: EmbeddedUblPdfResult = { pdfs: [], rejectedCount: 0 };
  let index = 0;
  let totalBytes = 0;
  for (const reference of childrenNamed(root, CAC_NAMESPACE, 'AdditionalDocumentReference')) {
    for (const attachment of childrenNamed(reference, CAC_NAMESPACE, 'Attachment')) {
      for (const embedded of childrenNamed(attachment, CBC_NAMESPACE, 'EmbeddedDocumentBinaryObject')) {
        index += 1;
        if (embedded.getAttribute('mimeCode')?.trim().toLowerCase() !== 'application/pdf') continue;
        if (result.pdfs.length >= MAX_PDFS) {
          result.rejectedCount += 1;
          continue;
        }
        const bytes = decodePdf(embedded);
        if (!bytes || totalBytes + bytes.length > MAX_TOTAL_PDF_BYTES) {
          result.rejectedCount += 1;
          continue;
        }
        totalBytes += bytes.length;
        result.pdfs.push({ id: `ubl-pdf-${index}`, filename: pdfFilename(embedded.getAttribute('filename'), index), bytes });
      }
    }
  }
  return result;
}
