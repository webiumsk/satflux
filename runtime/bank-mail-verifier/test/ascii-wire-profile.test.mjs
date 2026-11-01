import assert from 'node:assert/strict';
import { createHash, generateKeyPairSync, sign } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { dkimSign } from 'mailauth/lib/dkim/sign';
import { dkimVerify } from 'mailauth/lib/dkim/verify';
import { generateCanonicalizedHeader } from 'mailauth/lib/dkim/header';
import { parseHeaders, getSigningHeaderLines } from 'mailauth/lib/tools';
import { simpleParser } from 'mailparser';
import { verify, diagnose } from '../verifier.mjs';
import { ASCII_BODY_PROFILE, canonicalAsciiRelaxedBody, asciiRelaxedBodyForProof } from '../ascii-wire-profile.mjs';
import { signedFixture, DEFAULT_RECIPIENT } from './fixtures.mjs';

const CORE = ['from', 'to', 'subject'];
const MIME = ['mime-version', 'content-type', 'content-transfer-encoding'];
const ALL = [...CORE, ...MIME];
const BODY = 'WIREPROFILE 12,50 EUR\r\n';
const fixture = (options = {}) => signedFixture({ body: BODY, encoding: '7bit', signedHeaders: CORE, ...options });
const input = (value) => ({ rawMime: value.rawMime, recipient: value.recipient });
const run = (value, resolver = value.resolver) => verify(input(value), { resolver });
const rawBody = (value) => value.rawMime.slice(value.rawMime.indexOf('\r\n\r\n') + 4);
const assertInvalid = (value, reason = 'invalid_signature') => assert.deepEqual(value, { status: 'invalid', reason });
const authentic = (value) => dkimVerify(Buffer.from(value.rawMime), {
  strict: true, minBitLength: 1024, rejectRsaSha1: true, resolver: value.resolver,
});

test('real unsigned-MIME bank signature verifies through the exact fixed ASCII body', async () => {
  const value = await fixture({ body: 'WIREPROFILE\t 12,50 EUR \t\r\n\r\n' });
  const result = await run(value);
  assert.equal(result.status, 'verified');
  assert.equal(result.bodyProfile, ASCII_BODY_PROFILE);
  assert.equal(result.body, BODY);
  assert.equal(result.contentSha256, createHash('sha256').update(value.rawMime).digest('hex'));
  assert.deepEqual(Object.keys(result).sort(), ['bankDomain', 'body', 'bodyProfile', 'contentSha256', 'from', 'reason', 'status', 'subject']);
  const { results } = await authentic(value);
  assert.equal(results[0].status.result, 'pass');
  assert.equal(asciiRelaxedBodyForProof(rawBody(value), results[0]), result.body);
});

test('legacy fully signed MIME keeps exact decoded body, result shape and precedence', async () => {
  for (const options of [
    { body: 'WIREPROFILE\t 12,50 EUR \t\r\n\r\n', encoding: '7bit' },
    { body: 'WIREPROFILE=2012,50=20EUR\r\n', encoding: 'quoted-printable' },
    { body: Buffer.from(BODY).toString('base64') + '\r\n', encoding: 'base64' },
  ]) {
    const value = await fixture({ ...options, signedHeaders: ALL });
    const result = await run(value);
    const parsed = await simpleParser(Buffer.from(value.rawMime));
    assert.equal(result.status, 'verified');
    assert.equal(result.body, parsed.text);
    assert.deepEqual(Object.keys(result).sort(), ['bankDomain', 'body', 'contentSha256', 'from', 'reason', 'status', 'subject']);
  }
});

test('unsigned admitted header spelling changes never select a different body', async () => {
  const value = await fixture();
  for (const type of ['text/plain; charset=utf-8', 'TEXT/PLAIN; CHARSET="UTF-8"', 'text/plain;\tcharset = utf-8']) {
    const changed = { ...value, rawMime: value.rawMime.replace('text/plain; charset=utf-8', type) };
    const result = await run(changed);
    assert.equal(result.status, 'verified');
    assert.equal(result.bodyProfile, ASCII_BODY_PROFILE);
    assert.equal(result.body, BODY);
  }
});

test('unsigned encoding/charset/type/version mutations reject instead of choosing a decoder', async () => {
  const value = await fixture({ body: 'WIREPROFILE=2012,50=20EUR\r\n' });
  const changes = [
    ['Content-Transfer-Encoding: 7bit', 'Content-Transfer-Encoding: 8bit'],
    ['Content-Transfer-Encoding: 7bit', 'Content-Transfer-Encoding: quoted-printable'],
    ['Content-Transfer-Encoding: 7bit', 'Content-Transfer-Encoding: base64'],
    ['Content-Transfer-Encoding: 7bit', 'Content-Transfer-Encoding: binary'],
    ['Content-Transfer-Encoding: 7bit\r\n', ''],
    ['charset=utf-8', 'charset=us-ascii'], ['charset=utf-8', 'charset=windows-1250'],
    ['; charset=utf-8', ''], ['text/plain', 'text/html'],
    ['text/plain; charset=utf-8', 'multipart/mixed; boundary=PROFILE'],
    ['text/plain; charset=utf-8', 'text/plain; charset=utf-8; format=flowed'],
    ['MIME-Version: 1.0', 'MIME-Version: 1.1'], ['MIME-Version: 1.0\r\n', ''],
    ['Content-Type: text/plain; charset=utf-8\r\n', ''],
  ];
  for (const [before, after] of changes) {
    const changed = { ...value, rawMime: value.rawMime.replace(before, after) };
    assert.equal((await run(changed)).status, 'invalid', after);
  }
  for (const extra of ['Content-Type: text/plain; charset=utf-8', 'MIME-Version: 1.0', 'Content-Transfer-Encoding: 7bit', 'Content-Disposition: inline']) {
    const changed = { ...value, rawMime: value.rawMime.replace('\r\n\r\n', `\r\n${extra}\r\n\r\n`) };
    assert.equal((await run(changed)).status, 'invalid');
  }
});

test('encoded-looking ASCII stays literal and is never decoded, unwrapped or promoted', async () => {
  for (const body of ['WIREPROFILE=2012,50=20EUR\r\n', 'V0lSRVBST0ZJTEUgMTIsNTAgRVVSDQo=\r\n', '<p>WIREPROFILE 12,50 EUR</p>\r\n', 'WIREPROFILE 12,50&#32;EUR\r\n', 'WIREPROFILE=\r\n12,50 EUR\r\n']) {
    const result = await run(await fixture({ body }));
    assert.equal(result.status, 'verified');
    assert.equal(result.body, body);
  }
});

test('non-ASCII and every forbidden ASCII control fail the fallback even with real signatures', async () => {
  for (const character of ['é', '\u00a0', '\u200b', '\u202e', '\ufeff', ...Array.from({ length: 33 }, (_, n) => n === 32 ? '\x7f' : String.fromCharCode(n)).filter((char) => !['\t', '\r', '\n'].includes(char))]) {
    assert.equal((await run(await fixture({ body: `WIRE${character}PROFILE 12,50 EUR\r\n` }))).status, 'invalid');
  }
  const value = await fixture();
  for (const newline of ['\n', '\r']) {
    assertInvalid(await run({ ...value, rawMime: value.rawMime.replaceAll('\r\n', newline) }), 'invalid_headers');
  }
});

test('wire body, canonical body and physical line limits are independent and bounded', async () => {
  assert.equal(canonicalAsciiRelaxedBody('X'.repeat(998)), 'X'.repeat(998) + '\r\n');
  assert.equal(canonicalAsciiRelaxedBody('X'.repeat(999)), null);
  const atLimit = ('X'.repeat(998) + '\r\n').repeat(65) + 'X'.repeat(533);
  assert.equal(atLimit.length, 65533);
  assert.equal(canonicalAsciiRelaxedBody(atLimit).length, 65535);
  assert.equal(canonicalAsciiRelaxedBody(atLimit + 'X'), null);
  assert.equal(canonicalAsciiRelaxedBody(atLimit + '\r\n\r\n'), null);
  assert.equal(canonicalAsciiRelaxedBody(' '.repeat(262144)), null);
  assertInvalid(await run(await fixture({ body: 'X'.repeat(999) + '\r\n' })));
  assert.equal((await run(await fixture({ body: atLimit }))).status, 'verified');
  assert.equal((await run(await fixture({ body: atLimit + 'X' }))).status, 'invalid');
});

test('RFC relaxed edge vectors agree with the actual signer/verifier hashes and lengths', async () => {
  const vectors = [
    ['', ''], ['\r\n', ''], [' \t\r\n\t ', ''], ['A', 'A\r\n'],
    ['A\r\n\r\n', 'A\r\n'], [' \tA\t \tB \t\r\n\r\n', ' A B\r\n'],
    ['\r\nA\r\n\r\nB\r\n \t\r\n', '\r\nA\r\n\r\nB\r\n'],
    ['1\t 000\r\n12,50\r\n', '1 000\r\n12,50\r\n'],
  ];
  for (const [wire, canonical] of vectors) {
    assert.equal(canonicalAsciiRelaxedBody(wire), canonical);
    const value = await fixture({ body: wire });
    const { results } = await authentic(value);
    assert.equal(results[0].status.result, 'pass');
    const digest = createHash('sha256').update(canonical).digest('base64');
    assert.equal(results[0].bodyHash, digest);
    assert.equal(results[0].bodyHashExpecting, digest);
    assert.equal(results[0].canonBodyLength, canonical.length);
    assert.equal(results[0].canonBodyLengthTotal, canonical.length);
    assert.equal(asciiRelaxedBodyForProof(wire, results[0]), canonical);
  }
  assert.notEqual(canonicalAsciiRelaxedBody('1 000'), canonicalAsciiRelaxedBody('1000'));
});

test('deterministic varied whitespace projections differentially bind to real DKIM', async () => {
  let seed = 6376;
  const next = () => { seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0; return seed; };
  const tokens = ['A', '9', ' ', '\t', '\r\n'];
  for (let sample = 0; sample < 32; sample++) {
    const wire = Array.from({ length: 96 }, () => tokens[next() % tokens.length]).join('');
    const value = await fixture({ body: wire });
    const { results: [proof] } = await authentic(value);
    assert.equal(proof.status.result, 'pass');
    assert.equal(asciiRelaxedBodyForProof(wire, proof), canonicalAsciiRelaxedBody(wire));
  }
});

test('same-proof projection binding rejects missing, malformed or mismatched hashes and lengths', async () => {
  const value = await fixture();
  const { results: [proof] } = await authentic(value);
  assert.equal(asciiRelaxedBodyForProof(rawBody(value), proof), BODY);
  assert.equal(asciiRelaxedBodyForProof(rawBody(value), { ...proof, format: 'RELAXED/RELAXED' }), BODY);
  for (const overrides of [
    { bodyHash: undefined }, { bodyHashExpecting: undefined }, { bodyHash: null },
    { bodyHash: createHash('sha256').update('OTHER').digest('base64') },
    { bodyHashExpecting: createHash('sha256').update('OTHER').digest('base64') },
    { bodyHash: 'not a SHA256 hash' }, { bodyHashExpecting: proof.bodyHash.trimEnd() + ' ' },
    { canonBodyLength: undefined, canonBodyLengthTotal: undefined },
    { canonBodyLength: String(BODY.length) }, { canonBodyLength: BODY.length - 1 },
    { canonBodyLengthTotal: BODY.length + 1 }, { canonBodyLength: NaN }, { canonBodyLengthTotal: Infinity },
    { canonBodyLengthLimited: true }, { canonBodyLengthLimited: undefined },
    { format: undefined }, { format: 'simple/simple' }, { format: 'relaxed' }, { format: 'relaxed/simple' },
    { format: ' relaxed/relaxed' }, { format: 'relaxed/relaxed ' },
  ]) assert.equal(asciiRelaxedBodyForProof(rawBody(value), { ...proof, ...overrides }), null);
  assert.equal(asciiRelaxedBodyForProof('OTHER\r\n', proof), null);
  assert.equal(asciiRelaxedBodyForProof(rawBody(value), null), null);
});

test('only explicit relaxed/relaxed proof format enters the fallback', async () => {
  for (const canonicalization of ['simple/simple', 'relaxed/simple', 'simple/relaxed', 'relaxed']) {
    const value = await fixture({ signOptions: { canonicalization } });
    assert.equal((await authentic(value)).results[0].status.result, 'pass');
    assertInvalid(await run(value));
  }
});

test('all original signed identity, key, algorithm, time, recipient and no-l checks remain required', async () => {
  for (const missing of ['to', 'subject']) {
    const value = await fixture({ signedHeaders: CORE.filter((name) => name !== missing) });
    assert.equal((await authentic(value)).results[0].status.result, 'pass');
    assertInvalid(await run(value));
  }
  const unsignedFrom = await fixture();
  unsignedFrom.rawMime = unsignedFrom.rawMime.replace(/\bh=[^;]+;/, 'h=Subject: To;');
  assertInvalid(await run(unsignedFrom));
  assertInvalid(await run(await fixture({ signatureOptions: { algorithm: 'rsa-sha1' }, signOptions: { strict: false } })));
  for (const signOptions of [
    { signTime: new Date(Date.now() + 60000) },
    { signTime: new Date(Date.now() - 120000), expires: new Date(Date.now() - 60000) },
  ]) assertInvalid(await run(await fixture({ signOptions })));
  assertInvalid(await run(await fixture({ domain: 'attacker.test' })));
  assertInvalid(await run(await fixture({ from: 'PROFILE@attacker.test' })), 'unsupported_sender');
  const value = await fixture();
  assertInvalid(await verify({ ...input(value), recipient: 'PROFILE@other.test' }, { resolver: value.resolver }), 'identity_mismatch');
  assertInvalid(await run(await fixture({ signatureOptions: { maxBodyLength: 10 } })), 'partial_body_signature');
  const { publicKey } = generateKeyPairSync('rsa', { modulusLength: 512 });
  assertInvalid(await run(value, async () => [[`v=DKIM1; k=rsa; p=${publicKey.export({ type: 'spki', format: 'der' }).toString('base64')}`]]));
  for (const [code, expected] of [
    ['ENOTFOUND', { status: 'invalid', reason: 'invalid_signature' }],
    ['ETIMEOUT', { status: 'retryable', reason: 'dns_unavailable' }],
  ]) assert.deepEqual(await run(value, async () => { throw Object.assign(new Error('Synthetic DNS failure'), { code }); }), expected);
});

test('body amount, signed subject and signature tampering cannot enter the fallback', async () => {
  const value = await fixture();
  for (const rawMime of [
    value.rawMime.replace('12,50 EUR', '99,50 EUR'),
    value.rawMime.replace('Subject: Kredit', 'Subject: PROFILE'),
    value.rawMime.replace(/\bb=([A-Za-z0-9+\/])/, (_, first) => `b=${first === 'A' ? 'B' : 'A'}`),
  ]) assertInvalid(await run({ ...value, rawMime }));
});

test('relaxed SP/HTAB/trailing-line variants yield identical authenticated output', async () => {
  const value = await fixture();
  for (const body of ['WIREPROFILE\t12,50 EUR\r\n', 'WIREPROFILE \t 12,50\t \tEUR \t\r\n\r\n', 'WIREPROFILE 12,50 EUR']) {
    const rawMime = value.rawMime.slice(0, value.rawMime.indexOf('\r\n\r\n') + 4) + body;
    const result = await run({ ...value, rawMime });
    assert.equal(result.status, 'verified');
    assert.equal(result.body, BODY);
  }
});

const { privateKey, publicKey } = generateKeyPairSync('rsa', { modulusLength: 2048 });
const multiDns = `v=DKIM1; k=rsa; p=${publicKey.export({ type: 'spki', format: 'der' }).toString('base64')}`;
async function multiFixture(specs) {
  const value = await fixture({ body: 'WIREPROFILE\t 12,50 EUR \t\r\n\r\n' });
  const unsigned = value.rawMime.slice(value.rawMime.indexOf('\r\nFrom:') + 2);
  const signatures = [];
  for (const [index, spec] of specs.entries()) {
    const signed = await dkimSign(Buffer.from(unsigned), {
      strict: true, canonicalization: spec.mode ?? 'relaxed/relaxed', headerList: spec.headers ?? CORE,
      signatureData: [{ signingDomain: 'tatrabanka.sk', selector: `profile${index}`, privateKey: privateKey.export({ type: 'pkcs8', format: 'pem' }) }],
      ...spec.signOptions,
    });
    assert.equal(signed.errors.length, 0);
    signatures.push(signed.signatures);
  }
  return { ...value, rawMime: signatures.join('') + unsigned, unsigned, signatures, resolver: async () => [[multiDns]] };
}

test('real uppercase mode tokens pass while omitted or one-component tokens stay outside fallback', async () => {
  for (const [mode, wireFormat, accepted] of [
    ['relaxed/relaxed', 'RELAXED/RELAXED', true],
    ['simple/simple', null, false], ['relaxed/simple', 'relaxed', false],
  ]) {
    const value = await multiFixture([{ mode }]);
    let signatureHeader = value.signatures[0].trimEnd();
    signatureHeader = wireFormat === null
      ? signatureHeader.replace(/\bc=[^;]+;[ \t]*/, '')
      : signatureHeader.replace(/\bc=[^;]+;/, `c=${wireFormat};`);
    const parsed = parseHeaders(Buffer.from(value.unsigned.slice(0, value.unsigned.indexOf('\r\n\r\n'))));
    const { canonicalizedHeader } = generateCanonicalizedHeader('DKIM', getSigningHeaderLines(parsed.parsed, CORE.join(':')), {
      canonicalization: mode, signatureHeaderLine: Buffer.from(signatureHeader), strict: true,
    });
    const signature = sign('RSA-SHA256', canonicalizedHeader, privateKey).toString('base64');
    value.rawMime = signatureHeader.replace(/\bb=[\s\S]*$/, `b=${signature}`) + '\r\n' + value.unsigned;
    assert.equal((await authentic(value)).results[0].status.result, 'pass');
    const result = await run(value);
    if (accepted) {
      assert.equal(result.bodyProfile, ASCII_BODY_PROFILE);
      assert.equal(result.body, BODY);
    } else assertInvalid(result);
  }
});

test('multiple eligible relaxed proofs have the same output across ordering and removal', async () => {
  const value = await multiFixture([{}, {}]);
  for (const signatures of [value.signatures, [...value.signatures].reverse(), [value.signatures[0]], [value.signatures[1]]]) {
    const result = await run({ ...value, rawMime: signatures.join('') + value.unsigned });
    assert.equal(result.bodyProfile, ASCII_BODY_PROFILE);
    assert.equal(result.body, BODY);
  }
});

test('mixed simple and relaxed proofs cannot select simple body text by ordering', async () => {
  const value = await multiFixture([{ mode: 'relaxed/simple' }, {}]);
  for (const signatures of [value.signatures, [...value.signatures].reverse()]) {
    const result = await run({ ...value, rawMime: signatures.join('') + value.unsigned });
    assert.equal(result.bodyProfile, ASCII_BODY_PROFILE);
    assert.equal(result.body, BODY);
  }
  assertInvalid(await run({ ...value, rawMime: value.signatures[0] + value.unsigned }));
});

test('legacy full-MIME proof always wins over fallback regardless of signature order', async () => {
  const value = await multiFixture([{}, { headers: ALL, mode: 'simple/simple' }]);
  for (const signatures of [value.signatures, [...value.signatures].reverse()]) {
    const rawMime = signatures.join('') + value.unsigned;
    const result = await run({ ...value, rawMime });
    assert.equal(result.status, 'verified');
    assert.equal(Object.hasOwn(result, 'bodyProfile'), false);
    assert.equal(result.body, (await simpleParser(Buffer.from(rawMime))).text);
    assert.notEqual(result.body, BODY);
  }
});

test('different signatures cannot synthesize required core coverage or time validity', async () => {
  const value = await multiFixture([
    { headers: ['from', 'subject'] }, { headers: ['from', 'to'] },
    { headers: ALL, signOptions: { signTime: new Date(Date.now() + 60000) } },
  ]);
  for (const signatures of [value.signatures, [...value.signatures].reverse()]) {
    assertInvalid(await run({ ...value, rawMime: signatures.join('') + value.unsigned }));
  }
});

test('diagnostics retain v1 fixed keys and adapt required headers only for a selected fallback', async () => {
  for (const [value, selected] of [
    [await fixture(), true], [await fixture({ encoding: '8bit' }), false],
    [await fixture({ signedHeaders: ALL }), false],
  ]) {
    const envelope = await diagnose(input(value), { resolver: value.resolver });
    assert.deepEqual(envelope.verification, await run(value));
    assert.equal(envelope.diagnostics.version, 1);
    assert.deepEqual(Object.keys(envelope.diagnostics), ['version', 'status', 'reason', 'signatures']);
    const facts = envelope.diagnostics.signatures[0];
    for (const name of CORE) assert.equal(facts.headers[name].required, true);
    for (const name of MIME) assert.equal(facts.headers[name].required, !selected);
    assert.doesNotMatch(JSON.stringify(envelope.diagnostics), /tatrabanka\.sk|PROFILE|bodyProfile|contentSha256|Kredit/);
  }
});

test('test-only ASCII fixture producer returns the existing public fixture shape', () => {
  const path = fileURLToPath(new URL('./build-ascii-wire-message.mjs', import.meta.url));
  const result = spawnSync(process.execPath, [path], {
    input: JSON.stringify({ recipient: DEFAULT_RECIPIENT, body: BODY }), encoding: 'utf8', timeout: 5000,
  });
  assert.equal(result.status, 0);
  assert.equal(result.stderr, '');
  const value = JSON.parse(result.stdout);
  assert.deepEqual(Object.keys(value).sort(), ['dnsName', 'dnsRecord', 'rawMime', 'selector']);
  assert.match(value.rawMime, /Content-Transfer-Encoding: 7bit/);
  assert.match(value.rawMime, /h=Subject:\s*To:\s*From/i);
  assert.doesNotMatch(result.stdout, /PRIVATE KEY|privateKey/);
});
