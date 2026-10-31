import { createHash } from 'node:crypto';
import { Resolver } from 'node:dns/promises';
import { dkimVerify } from 'mailauth/lib/dkim/verify';
import { simpleParser } from 'mailparser';

export const MAX_RAW_BYTES = 262144;
const MAX_BODY_BYTES = 65535;
const MAX_SIGNATURES = 5;
const MAX_DNS_LOOKUPS = 5;
const DNS_TIMEOUT_MS = 2000;
const TOTAL_DNS_MS = 10000;
const BANK_DOMAIN = 'tatrabanka.sk';
const SINGLETONS = new Set([
  'from', 'to', 'subject', 'date', 'message-id', 'mime-version',
  'content-type', 'content-transfer-encoding', 'content-disposition',
]);

const invalid = (reason) => ({ status: 'invalid', reason });
const retryable = (reason) => ({ status: 'retryable', reason });
const bankDomain = (domain) => typeof domain === 'string'
  && /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*tatrabanka\.sk$/.test(domain);
const codeError = (code) => Object.assign(new Error(code), { code });

function headersOf(raw) {
  // A deliberately narrow wire profile avoids divergent MIME interpretations.
  if (/\r(?!\n)|(?<!\r)\n|\0/.test(raw)) return null;
  const split = raw.indexOf('\r\n\r\n');
  if (split < 0 || split > 32768) return null;
  const fields = new Map();
  let current;
  for (const line of raw.slice(0, split).split('\r\n')) {
    if (Buffer.byteLength(line) > 998) return null;
    if (/^[ \t]/.test(line)) {
      if (!current) return null;
      const values = fields.get(current);
      values[values.length - 1] += ` ${line.trim()}`;
      continue;
    }
    const match = /^([!-9;-~]+):[ \t]*(.*)$/.exec(line);
    if (!match || /[\x00-\x08\x0b-\x1f\x7f]/.test(match[2])) return null;
    current = match[1].toLowerCase();
    const values = fields.get(current) ?? [];
    values.push(match[2]);
    if (SINGLETONS.has(current) && values.length > 1) return null;
    fields.set(current, values);
  }
  return fields;
}

function oneMailbox(field) {
  if (!field || field.value?.length !== 1 || field.value[0].group) return null;
  const address = field.value[0].address;
  // The supported bank/route identities are ordinary ASCII mailboxes.
  return typeof address === 'string' && /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9.-]+$/.test(address)
    ? address : null;
}

function permittedDnsName(name, rr) {
  if (rr !== 'TXT' || typeof name !== 'string' || name.length > 253) return false;
  const parts = name.toLowerCase().split('._domainkey.');
  if (parts.length !== 2 || !bankDomain(parts[1])) return false;
  return parts[0].split('.').every((label) => /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/.test(label));
}

function dnsBudget(injectedResolver) {
  const live = injectedResolver ? null : new Resolver({ timeout: DNS_TIMEOUT_MS, tries: 1 });
  const resolve = injectedResolver ?? ((name) => live.resolveTxt(name));
  const deadline = Date.now() + TOTAL_DNS_MS;
  let count = 0;
  let temporaryFailure = false;
  return {
    async resolver(name, rr) {
      if (!permittedDnsName(name, rr)) throw codeError('ENOTFOUND');
      if (++count > MAX_DNS_LOOKUPS) throw codeError('ENOTFOUND');
      const remaining = Math.min(DNS_TIMEOUT_MS, deadline - Date.now());
      if (remaining <= 0) {
        temporaryFailure = true;
        throw codeError('ETIMEOUT');
      }
      let timer;
      try {
        return await Promise.race([
          Promise.resolve().then(() => resolve(name, rr)),
          new Promise((_, reject) => { timer = setTimeout(() => reject(codeError('ETIMEOUT')), remaining); }),
        ]);
      } catch (error) {
        if (!['ENOTFOUND', 'ENODATA', 'ENONAME', 'EBADNAME'].includes(error?.code)) temporaryFailure = true;
        throw error;
      } finally {
        clearTimeout(timer);
      }
    },
    get temporaryFailure() { return temporaryFailure; },
    close() { live?.cancel(); },
  };
}

/**
 * Verify original MIME, never a provider-transformed body or supplied auth header.
 * resolver is a library-only test dependency; CLI input cannot select DNS settings.
 */
export async function verify(input, { resolver } = {}) {
  if (!input || typeof input !== 'object' || Array.isArray(input)
    || Object.keys(input).some((key) => !['rawMime', 'recipient'].includes(key))
    || typeof input.rawMime !== 'string' || typeof input.recipient !== 'string') return invalid('invalid_input');
  const { rawMime } = input;
  const recipient = input.recipient.toLowerCase();
  if (Buffer.byteLength(rawMime) > MAX_RAW_BYTES) return invalid('message_too_large');
  if (Buffer.from(rawMime).toString('utf8') !== rawMime
    || recipient.length > 255 || !/^[a-z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-z0-9.-]+$/.test(recipient)) return invalid('invalid_input');
  const headers = headersOf(rawMime);
  if (!headers) return invalid('invalid_headers');
  if (!headers.has('from') || !headers.has('to')) return invalid('missing_identity');
  const signatures = headers.get('dkim-signature') ?? [];
  if (!signatures.length) return invalid('unsigned_message');
  if (signatures.length > MAX_SIGNATURES) return invalid('too_many_signatures');
  // Even a passing signature with l= equal to today's body length is outside this profile.
  if (signatures.some((signature) => /(?:^|;)\s*l\s*=/i.test(signature))) return invalid('partial_body_signature');
  const contentType = headers.get('content-type')?.[0];
  if (!contentType || !/^text\/plain(?:\s*;\s*charset\s*=\s*(?:"(?:utf-8|us-ascii)"|utf-8|us-ascii))?\s*$/i.test(contentType)) return invalid('unsupported_mime');
  if (headers.has('content-disposition') || (headers.has('mime-version') && headers.get('mime-version')[0] !== '1.0')) return invalid('unsupported_mime');
  const encoding = headers.get('content-transfer-encoding')?.[0]?.toLowerCase();
  if (encoding && !['7bit', '8bit', 'quoted-printable', 'base64'].includes(encoding)) return invalid('unsupported_mime');

  let parsed;
  try {
    parsed = await simpleParser(Buffer.from(rawMime, 'utf8'), {
      skipHtmlToText: true, skipTextToHtml: true, skipImageLinks: true,
      maxHtmlLengthToParse: 0,
    });
  } catch {
    return invalid('invalid_mime');
  }
  const from = oneMailbox(parsed.from);
  const to = oneMailbox(parsed.to);
  if (!from || !to || from.length > 255 || to.toLowerCase() !== recipient) return invalid('identity_mismatch');
  const fromDomain = from.slice(from.lastIndexOf('@') + 1).toLowerCase();
  if (!bankDomain(fromDomain)) return invalid('unsupported_sender');
  if (parsed.attachments?.length || parsed.html || typeof parsed.text !== 'string'
    || Buffer.byteLength(parsed.text) > MAX_BODY_BYTES || parsed.text.includes('\0')
    || typeof parsed.subject !== 'string' || parsed.subject.length > 500) return invalid('unsupported_content');

  const budget = dnsBudget(resolver);
  let authentication;
  try {
    authentication = await dkimVerify(Buffer.from(rawMime, 'utf8'), {
      strict: true, minBitLength: 1024, rejectRsaSha1: true,
      resolver: budget.resolver,
    });
  } catch {
    return retryable('verification_unavailable');
  } finally {
    budget.close();
  }
  const requiredHeaders = ['from', 'to', 'subject'];
  for (const name of ['mime-version', 'content-type', 'content-transfer-encoding']) {
    if (headers.has(name)) requiredHeaders.push(name);
  }
  const proof = authentication.results.find((result) => {
    const domain = result.signingDomain?.toLowerCase();
    // Pinned 8.0.4 emits a colon-delimited string despite its docs' array label.
    const signed = typeof result.signingHeaders?.keys === 'string'
      ? result.signingHeaders.keys.split(':').map((name) => name.trim().toLowerCase()) : [];
    return result.status?.result === 'pass' && bankDomain(domain)
      && result.status.aligned && bankDomain(String(result.status.aligned).toLowerCase())
      && result.signatureTimeValid === true && !result.canonBodyLengthLimited
      && result.canonBodyLength === result.canonBodyLengthTotal
      && ['rsa-sha256', 'ed25519-sha256'].includes(result.algo)
      && requiredHeaders.every((name) => signed.includes(name));
  });
  if (!proof) {
    return budget.temporaryFailure || authentication.results.some((result) => result.status?.result === 'temperror')
      ? retryable('dns_unavailable') : invalid('invalid_signature');
  }
  return {
    status: 'verified', reason: 'verified', bankDomain: proof.signingDomain.toLowerCase(),
    from, subject: parsed.subject, body: parsed.text,
    contentSha256: createHash('sha256').update(Buffer.from(rawMime, 'utf8')).digest('hex'),
  };
}
