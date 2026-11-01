import { createHash } from 'node:crypto';
import { Transform } from 'node:stream';
import { simpleParser } from 'mailparser';

export const MAX_RAW_BYTES = 262144;
const MAX_BODY_BYTES = 65535;
const invalid = (reason) => ({ status: 'invalid', reason });
const invalidText = (text) => /[\x00-\x08\x0b\x0c\x0e-\x1f\x7f\ufffd]/u.test(text);
const CHARSETS = new Set(['windows1250', 'cp1250', 'iso88592', 'latin2', 'windows1252', 'cp1252', 'iso88591', 'latin1']);

// This is company extraction, never bank authentication. No DNS or network
// dependency, recipient selection or caller-selected trust policy exists here.
export async function extractCompany(input) {
  if (!input || typeof input !== 'object' || Array.isArray(input)
    || Object.keys(input).length !== 1 || typeof input.rawMimeBase64 !== 'string') return invalid('invalid_input');
  const encoded = input.rawMimeBase64;
  if (encoded.length > Math.ceil(MAX_RAW_BYTES / 3) * 4) return invalid('message_too_large');
  if (!encoded || encoded.length % 4 || !/^[A-Za-z0-9+/]*={0,2}$/.test(encoded)) return invalid('invalid_input');
  const raw = Buffer.from(encoded, 'base64');
  if (raw.toString('base64') !== encoded) return invalid('invalid_input');
  if (raw.length > MAX_RAW_BYTES) return invalid('message_too_large');
  const wire = raw.toString('latin1');
  const separator = /\r?\n\r?\n/.exec(wire);
  if (!separator || separator.index > 32768 || wire.includes('\0')) return invalid('invalid_headers');
  const rawHeaders = wire.slice(0, separator.index);
  const fields = new Map();
  let current;
  for (const line of rawHeaders.split(/\r?\n/)) {
    if (/^[ \t]/.test(line)) {
      if (!current) return invalid('invalid_headers');
      fields.set(current, `${fields.get(current)} ${line.trim()}`);
      continue;
    }
    const match = /^([!-9;-~]+):[ \t]*(.*)$/.exec(line);
    if (!match || /[\x00-\x08\x0b-\x1f\x7f]/.test(match[2])) return invalid('invalid_headers');
    current = match[1].toLowerCase();
    if (fields.has(current) && ['from', 'subject', 'content-type', 'content-transfer-encoding', 'mime-version'].includes(current)) return invalid('invalid_headers');
    fields.set(current, match[2]);
  }
  const encoding = (fields.get('content-transfer-encoding') ?? '7bit').toLowerCase();
  const rootCharset = /(?:^|;)\s*charset\s*=\s*(?:"([^"]*)"|([^;\s]+))/i.exec(fields.get('content-type') ?? '');
  if (rootCharset) {
    const charset = (rootCharset[1] ?? rootCharset[2]).toLowerCase().replace(/[^a-z0-9]/g, '');
    if (!['utf8', 'ascii', 'usascii'].includes(charset) && !CHARSETS.has(charset)) return invalid('unsupported_charset');
  }
  if (!['7bit', '8bit', 'binary', 'base64', 'quoted-printable'].includes(encoding)) return invalid('unsupported_encoding');
  const rootBody = wire.slice(separator.index + separator[0].length);
  if (encoding === 'base64' && (!/^[A-Za-z0-9+/=\r\n\t ]*$/.test(rootBody)
    || Buffer.from(rootBody, 'base64').toString('base64') !== rootBody.replace(/\s/g, ''))) return invalid('invalid_encoding');
  if (encoding === 'quoted-printable' && /=(?![A-Fa-f0-9]{2}|\r?\n)/.test(rootBody)) return invalid('invalid_encoding');

  let badCharset = false;
  // mailparser's supported Iconv adapter hook also runs for nested MIME parts.
  // Use Node's existing ICU decoder and reject unknown charsets instead of
  // mailparser's permissive UTF-8 replacement fallback for legacy imports.
  class CompanyCharsetDecoder extends Transform {
    constructor(charset) {
      super();
      if (!CHARSETS.has(charset.toLowerCase().replace(/[^a-z0-9]/g, ''))) {
        badCharset = true;
        throw new Error('Unsupported company charset');
      }
      try { this.decoder = new TextDecoder(charset, { fatal: true }); } catch {
        badCharset = true;
        throw new Error('Unsupported company charset');
      }
    }
    _transform(chunk, _encoding, callback) {
      try { callback(null, this.decoder.decode(chunk, { stream: true })); } catch { callback(new Error('Invalid company charset')); }
    }
    _flush(callback) {
      try { callback(null, this.decoder.decode()); } catch { callback(new Error('Invalid company charset')); }
    }
  }
  let parsed;
  try {
    parsed = await simpleParser(raw, {
      Iconv: CompanyCharsetDecoder, skipTextToHtml: true, skipImageLinks: true,
      keepCidLinks: true, maxHtmlLengthToParse: MAX_BODY_BYTES,
    });
  } catch {
    return invalid('invalid_mime');
  }
  if (badCharset || parsed.textConversionError) return invalid('unsupported_charset_or_html');
  const fromValues = parsed.from?.value ?? [];
  if (fromValues.length > 1 || fromValues.some((value) => value.group)) return invalid('invalid_sender');
  const from = fromValues[0]?.address ?? '';
  const subject = parsed.subject ?? '';
  const body = typeof parsed.text === 'string' ? parsed.text.trim() : '';
  if (!body || Buffer.byteLength(body) > MAX_BODY_BYTES || Buffer.byteLength(subject) > 500
    || Buffer.byteLength(from) > 255 || [from, subject, body].some(invalidText)) return invalid('unsupported_content');
  // Only forwarding facts used by the existing importer leave this process.
  // Preserve its case-insensitive substring/line checks without returning PII.
  const lowerHeaders = rawHeaders.toLowerCase();
  const headers = [
    lowerHeaders.includes('x-forwarded-for') ? 'X-Forwarded-For: present' : '',
    lowerHeaders.includes('resent-from') ? 'Resent-From: present' : '',
    /^fwd:/im.test(rawHeaders) ? 'Fwd: present' : '',
  ].filter(Boolean).join('\r\n');
  return {
    status: 'extracted', from, subject, body, headers,
    contentSha256: createHash('sha256').update(raw).digest('hex'),
  };
}
