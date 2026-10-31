import { generateKeyPairSync } from 'node:crypto';
import { dkimSign } from 'mailauth/lib/dkim/sign';

// Generated synthetic keys stay in process memory; no committed/private key fixture.
const { privateKey, publicKey } = generateKeyPairSync('rsa', { modulusLength: 2048 });
const DNS_RECORD = `v=DKIM1; k=rsa; p=${publicKey.export({ type: 'spki', format: 'der' }).toString('base64')}`;
export const DEFAULT_RECIPIENT = 'pssynthetic@mail.satflux.test';
export const DEFAULT_BODY = 'Vazeny klient,\r\n\r\n01.06.2026 12:31 bol zostatok Vasho uctu SK3112000000198742637541 zvyseny o 12,50 EUR.\r\n';
const DEFAULT_HEADERS = ['from', 'to', 'subject', 'date', 'message-id', 'mime-version', 'content-type', 'content-transfer-encoding'];

export async function signedFixture({
  recipient = DEFAULT_RECIPIENT, body = DEFAULT_BODY, from = 'b-mail@tatrabanka.sk',
  domain = 'tatrabanka.sk', selector = 'synthetic', contentType = 'text/plain; charset=utf-8',
  encoding = '8bit', extraHeaders = [], signedHeaders = DEFAULT_HEADERS,
  signOptions = {}, signatureOptions = {},
} = {}) {
  const unsigned = [
    `From: ${from}`, `To: ${recipient}`, 'Subject: Kredit na ucte',
    `Date: ${new Date().toUTCString()}`, 'Message-ID: <synthetic-only@bank-mail.test>',
    'MIME-Version: 1.0', `Content-Type: ${contentType}`, `Content-Transfer-Encoding: ${encoding}`,
    ...extraHeaders, '', body,
  ].join('\r\n');
  const signed = await dkimSign(Buffer.from(unsigned), {
    strict: true, canonicalization: 'relaxed/relaxed', headerList: signedHeaders,
    signatureData: [{ signingDomain: domain, selector, privateKey: privateKey.export({ type: 'pkcs8', format: 'pem' }), ...signatureOptions }],
    ...signOptions,
  });
  if (signed.errors.length) throw new Error('Synthetic signing failed');
  const rawMime = signed.signatures + unsigned;
  const dnsName = `${selector}._domainkey.${domain}`;
  const resolver = async (name, rr) => {
    if (name !== dnsName || rr !== 'TXT') throw Object.assign(new Error('No synthetic record'), { code: 'ENOTFOUND' });
    return [[DNS_RECORD]];
  };
  return { rawMime, dnsName, dnsRecord: DNS_RECORD, selector, recipient, body, resolver };
}
