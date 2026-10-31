import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import test from 'node:test';
import { verify, MAX_RAW_BYTES } from '../verifier.mjs';
import { signedFixture, DEFAULT_RECIPIENT } from './fixtures.mjs';

const input = (fixture, overrides = {}) => ({ rawMime: fixture.rawMime, recipient: fixture.recipient, ...overrides });
const run = async (fixture, overrides = {}) => verify(input(fixture, overrides), { resolver: fixture.resolver });
const assertInvalid = (result, reason) => {
  assert.equal(result.status, 'invalid');
  if (reason) assert.equal(result.reason, reason);
  assert.deepEqual(Object.keys(result).sort(), ['reason', 'status']);
};

test('actual passing synthetic bank signature yields the body and exact raw-byte digest', async () => {
  const fixture = await signedFixture();
  const result = await run(fixture);
  assert.equal(result.status, 'verified');
  assert.equal(result.bankDomain, 'tatrabanka.sk');
  assert.equal(result.from, 'b-mail@tatrabanka.sk');
  assert.equal(result.subject, 'Kredit na ucte');
  assert.equal(result.body.replaceAll('\n', '\r\n'), fixture.body);
  assert.equal(result.contentSha256, createHash('sha256').update(fixture.rawMime).digest('hex'));
});

test('ordinary display names do not weaken unique bank and route mailbox binding', async () => {
  const fixture = await signedFixture({ from: 'Tatra banka <b-mail@tatrabanka.sk>' });
  assert.equal((await run(fixture)).status, 'verified');
});

test('signed quoted-printable/plain UTF-8 content is decoded from verified bytes', async () => {
  const fixture = await signedFixture({ encoding: 'quoted-printable', body: 'V=C3=A1=C5=BEen=C3=BD klient,\r\n' });
  const result = await run(fixture);
  assert.equal(result.status, 'verified');
  assert.equal(result.body, 'Vážený klient,\n');
});

test('signed base64/plain content is decoded from verified bytes', async () => {
  const fixture = await signedFixture({ encoding: 'base64', body: Buffer.from('Bank credit\r\n').toString('base64') + '\r\n' });
  assert.equal((await run(fixture)).body, 'Bank credit\n');
});

test('body tampering cannot inherit signature trust', async () => {
  const fixture = await signedFixture();
  fixture.rawMime = fixture.rawMime.replace('12,50 EUR', '99,50 EUR');
  assertInvalid(await run(fixture), 'invalid_signature');
});

test('From, To, Subject and MIME interpretation remain covered by signature', async () => {
  for (const [before, after] of [
    ['From: b-mail@', 'From: changed@'], ['To: pssynthetic@', 'To: other@'],
    ['Subject: Kredit', 'Subject: Changed'], ['charset=utf-8', 'charset=us-ascii'],
  ]) {
    const fixture = await signedFixture();
    fixture.rawMime = fixture.rawMime.replace(before, after);
    const result = await run(fixture);
    assert.equal(result.status, 'invalid', before);
  }
});

test('recipient cannot be redirected to another store even with a valid bank signature', async () => {
  assertInvalid(await run(await signedFixture(), { recipient: 'other@mail.satflux.test' }), 'identity_mismatch');
});

test('fake Authentication-Results/Pass headers cannot authenticate unsigned or altered content', async () => {
  const fixture = await signedFixture({ extraHeaders: [
    'Authentication-Results: attacker.test; dkim=pass header.d=tatrabanka.sk',
    'X-Mailgun-Dkim-Check-Result: Pass',
  ] });
  fixture.rawMime = fixture.rawMime.replace('12,50 EUR', '99,50 EUR');
  assertInvalid(await run(fixture), 'invalid_signature');
  fixture.rawMime = fixture.rawMime.replace(/^DKIM-Signature:[\s\S]+?\r\nFrom:/, 'From:');
  assertInvalid(await run(fixture), 'unsigned_message');
});

test('an attacker-controlled valid DKIM signer and forged bank From cannot pass', async () => {
  const fixture = await signedFixture({ domain: 'attacker.test' });
  assertInvalid(await run(fixture), 'invalid_signature');
});

test('a foreign From cannot gain bank trust from a bank-domain signature', async () => {
  assertInvalid(await run(await signedFixture({ from: 'sender@attacker.test' })), 'unsupported_sender');
});

test('partial-body l signatures are unsupported even when the covered portion verifies', async () => {
  const fixture = await signedFixture({ signatureOptions: { maxBodyLength: 10 } });
  assert.match(fixture.rawMime, /l=10/);
  assertInvalid(await run(fixture), 'partial_body_signature');
});

test('missing signed To or MIME controls cannot be substituted by unsigned headers', async () => {
  for (const missing of ['to', 'content-type', 'content-transfer-encoding', 'mime-version', 'subject']) {
    const signedHeaders = ['from', 'to', 'subject', 'date', 'message-id', 'mime-version', 'content-type', 'content-transfer-encoding'].filter((name) => name !== missing);
    assertInvalid(await run(await signedFixture({ signedHeaders })), 'invalid_signature');
  }
});

test('duplicate singleton identity and MIME headers are rejected before DNS', async () => {
  for (const header of ['From: second@tatrabanka.sk', `To: ${DEFAULT_RECIPIENT}`, 'Subject: second', 'Content-Type: text/plain', 'Content-Transfer-Encoding: 8bit', 'MIME-Version: 1.0']) {
    assertInvalid(await run(await signedFixture({ extraHeaders: [header] })), 'invalid_headers');
  }
});

test('multiple recipients/groups are outside the supported profile', async () => {
  assertInvalid(await run(await signedFixture({ recipient: `${DEFAULT_RECIPIENT}, other@mail.satflux.test` }), { recipient: DEFAULT_RECIPIENT }), 'identity_mismatch');
});

test('HTML, multipart, attachments and unqualified charsets remain unsupported', async () => {
  for (const contentType of ['text/html; charset=utf-8', 'multipart/alternative; boundary=xx', 'text/plain; charset=windows-1250']) {
    assertInvalid(await run(await signedFixture({ contentType })), 'unsupported_mime');
  }
  assertInvalid(await run(await signedFixture({ extraHeaders: ['Content-Disposition: attachment'] })), 'unsupported_mime');
});

test('unknown transfer encoding and malformed CRLF/header syntax fail safely', async () => {
  assertInvalid(await run(await signedFixture({ encoding: 'made-up' })), 'unsupported_mime');
  const fixture = await signedFixture();
  fixture.rawMime = fixture.rawMime.replaceAll('\r\n', '\n');
  assertInvalid(await run(fixture), 'invalid_headers');
});

test('permanent missing DNS key is invalid and temporary DNS failure is retryable', async () => {
  const fixture = await signedFixture();
  const missing = await verify(input(fixture), { resolver: async () => { throw Object.assign(new Error('Missing synthetic record'), { code: 'ENOTFOUND' }); } });
  assertInvalid(missing, 'invalid_signature');
  const temporary = await verify(input(fixture), { resolver: async () => { throw Object.assign(new Error('Private error must not escape'), { code: 'ETIMEOUT' }); } });
  assert.deepEqual(temporary, { status: 'retryable', reason: 'dns_unavailable' });
});

test('DNS timeout budget is bounded and returns retryable without leaking input', async () => {
  const fixture = await signedFixture();
  const start = Date.now();
  const result = await verify(input(fixture), { resolver: () => new Promise(() => {}) });
  assert.deepEqual(result, { status: 'retryable', reason: 'dns_unavailable' });
  assert.ok(Date.now() - start < 3500);
});

test('lookups are limited to bank DKIM TXT names, never attacker domains', async () => {
  const fixture = await signedFixture({ domain: 'attacker.test' });
  let queries = 0;
  const result = await verify(input(fixture), { resolver: async () => { queries++; return [[fixture.dnsRecord]]; } });
  assertInvalid(result);
  assert.equal(queries, 0);
});

test('signature count, raw bytes and decoded content are bounded', async () => {
  const fixture = await signedFixture();
  const signature = fixture.rawMime.slice(0, fixture.rawMime.indexOf('\r\nFrom:') + 2);
  fixture.rawMime = signature.repeat(6) + fixture.rawMime.slice(signature.length);
  assertInvalid(await run(fixture), 'too_many_signatures');
  assertInvalid(await verify({ rawMime: 'x'.repeat(MAX_RAW_BYTES + 1), recipient: DEFAULT_RECIPIENT }), 'message_too_large');
  assertInvalid(await run(await signedFixture({ body: 'x'.repeat(65536) + '\r\n' })), 'unsupported_content');
});

test('runtime input cannot supply resolver options or malformed Unicode', async () => {
  const fixture = await signedFixture();
  assertInvalid(await verify({ ...input(fixture), resolver: 'attacker' }), 'invalid_input');
  assertInvalid(await verify(input(fixture, { rawMime: '\ud800' })), 'invalid_input');
});

test('production CLI executes real crypto through an explicitly test-only DNS preload', async () => {
  const fixture = await signedFixture();
  const directory = mkdtempSync(join(tmpdir(), 'satflux-bank-verifier-'));
  const fixtureFile = join(directory, 'public-dns.json');
  try {
    writeFileSync(fixtureFile, JSON.stringify({ dnsName: fixture.dnsName, dnsRecord: fixture.dnsRecord }), { mode: 0o600 });
    const result = spawnSync(process.execPath, ['--import', fileURLToPath(new URL('./preload-dns.mjs', import.meta.url)), fileURLToPath(new URL('../cli.mjs', import.meta.url))], {
      input: JSON.stringify(input(fixture)), encoding: 'utf8', timeout: 5000,
      env: { ...process.env, SATFLUX_TEST_DNS_FIXTURE: fixtureFile },
    });
    assert.equal(result.status, 0);
    assert.equal(result.stderr, '');
    const output = JSON.parse(result.stdout);
    assert.equal(output.status, 'verified');
    assert.equal(output.contentSha256, createHash('sha256').update(fixture.rawMime).digest('hex'));
  } finally { rmSync(directory, { recursive: true, force: true }); }
});

test('CLI rejects malformed JSON and attempted test-option injection without stderr', () => {
  for (const value of ['not JSON', JSON.stringify({ rawMime: '', recipient: DEFAULT_RECIPIENT, resolver: 'attacker' })]) {
    const result = spawnSync(process.execPath, [fileURLToPath(new URL('../cli.mjs', import.meta.url))], { input: value, encoding: 'utf8', timeout: 5000 });
    assert.equal(result.status, 0);
    assert.equal(result.stderr, '');
    assert.equal(JSON.parse(result.stdout).status, 'invalid');
  }
});

test('bounded multiple signatures may contain an eligible passing bank proof', async () => {
  const fixture = await signedFixture();
  const signature = fixture.rawMime.slice(0, fixture.rawMime.indexOf('\r\nFrom:') + 2);
  fixture.rawMime = signature.replace('d=tatrabanka.sk', 'd=attacker.test') + fixture.rawMime;
  assert.equal((await run(fixture)).status, 'verified');
});

test('future or expired signature time constraints cannot pass', async () => {
  const future = await signedFixture({ signOptions: { signTime: new Date(Date.now() + 60000) } });
  assertInvalid(await run(future), 'invalid_signature');
  const expired = await signedFixture({ signOptions: { signTime: new Date(Date.now() - 120000), expires: new Date(Date.now() - 60000) } });
  assertInvalid(await run(expired), 'invalid_signature');
});

test('an aligned bank subdomain is eligible under the explicit bank allowlist', async () => {
  const fixture = await signedFixture({ from: 'b-mail@notify.tatrabanka.sk', domain: 'notify.tatrabanka.sk' });
  assert.equal((await run(fixture)).status, 'verified');
});
