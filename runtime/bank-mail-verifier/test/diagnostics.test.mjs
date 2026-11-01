import assert from 'node:assert/strict';
import { createHash, generateKeyPairSync } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { dkimSign } from 'mailauth/lib/dkim/sign';
import { diagnose, verify, MAX_RAW_BYTES } from '../verifier.mjs';
import { signedFixture, DEFAULT_RECIPIENT } from './fixtures.mjs';

const HEADERS = ['from', 'to', 'subject', 'mime-version', 'content-type', 'content-transfer-encoding'];
const VERDICTS = ['pass', 'fail', 'neutral', 'policy', 'none', 'permerror', 'temperror', 'unknown', null];
const input = (fixture) => ({ rawMime: fixture.rawMime, recipient: fixture.recipient });
const assertPrivate = (diagnostics) => {
  assert.deepEqual(Object.keys(diagnostics).sort(), ['reason', 'signatures', 'status', 'version']);
  assert.equal(diagnostics.version, 1);
  assert.ok(diagnostics.signatures.length <= 5);
  for (const signature of diagnostics.signatures) {
    assert.deepEqual(Object.keys(signature).sort(), ['algorithm_allowed', 'bank_aligned', 'bank_signer', 'full_body', 'headers', 'time_valid', 'verdict']);
    assert.ok(VERDICTS.includes(signature.verdict));
    for (const key of ['algorithm_allowed', 'bank_aligned', 'bank_signer', 'full_body', 'time_valid']) {
      assert.ok([true, false, null].includes(signature[key]), key);
    }
    assert.deepEqual(Object.keys(signature.headers), HEADERS);
    for (const facts of Object.values(signature.headers)) {
      assert.deepEqual(Object.keys(facts), ['required', 'signed']);
      assert.ok([true, false, null].includes(facts.required));
      assert.ok([true, false, null].includes(facts.signed));
    }
  }
  assert.doesNotMatch(JSON.stringify(diagnostics), /DIAG[A-Z]+|tatrabanka\.sk|mail\.satflux\.test|Kredit na ucte|canonical|selector|publicKey|comment|bodyHash/);
};
const check = async (fixture, resolver = fixture.resolver) => {
  const envelope = await diagnose(input(fixture), { resolver });
  assert.deepEqual(envelope.verification, await verify(input(fixture), { resolver }));
  assert.equal(envelope.contentSha256, createHash('sha256').update(fixture.rawMime).digest('hex'));
  assert.equal(envelope.diagnostics.status, envelope.verification.status);
  assert.equal(envelope.diagnostics.reason, envelope.verification.reason);
  assertPrivate(envelope.diagnostics);
  return envelope;
};
const signaturePrefix = (fixture) => fixture.rawMime.slice(0, fixture.rawMime.indexOf('\r\nFrom:') + 2);

test('diagnostics share an eligible real proof while returning only fixed facts', async () => {
  const fixture = await signedFixture({
    from: 'DIAGFROM@DIAGDOMAIN.tatrabanka.sk', domain: 'DIAGDOMAIN.tatrabanka.sk',
    selector: 'DIAGSELECTOR', recipient: 'DIAGTO@mail.satflux.test', body: 'DIAGBODY\r\n',
    extraHeaders: ['X-DIAGHEADER: DIAGVALUE', 'Authentication-Results: DIAGCOMMENT'],
  });
  // DNS is case-insensitive, as is the production allowlist.
  const resolver = async () => [[fixture.dnsRecord]];
  const { diagnostics, verification } = await check(fixture, resolver);
  assert.equal(verification.status, 'verified');
  assert.equal(diagnostics.signatures.length, 1);
  assert.deepEqual(diagnostics.signatures[0], {
    verdict: 'pass', bank_signer: true, bank_aligned: true, algorithm_allowed: true,
    time_valid: true, full_body: true,
    headers: Object.fromEntries(HEADERS.map((name) => [name, { required: true, signed: true }])),
  });
});

test('cryptographic PASS remains distinct from missing required signed headers', async () => {
  for (const missing of HEADERS.filter((name) => name !== 'from')) {
    const fixture = await signedFixture({ signedHeaders: HEADERS.filter((name) => name !== missing) });
    const { diagnostics, verification } = await check(fixture);
    assert.equal(verification.reason, 'invalid_signature');
    assert.equal(diagnostics.signatures[0].verdict, 'pass');
    assert.deepEqual(diagnostics.signatures[0].headers[missing], { required: true, signed: false });
  }
});

test('future and expired real signatures preserve time failure separately from crypto', async () => {
  for (const signOptions of [
    { signTime: new Date(Date.now() + 60000) },
    { signTime: new Date(Date.now() - 120000), expires: new Date(Date.now() - 60000) },
  ]) {
    const { diagnostics, verification } = await check(await signedFixture({ signOptions }));
    assert.equal(verification.reason, 'invalid_signature');
    assert.equal(diagnostics.signatures[0].time_valid, false);
  }
});

test('real body and header crypto failures cannot inherit a passing verdict', async () => {
  for (const [before, after] of [['12,50 EUR', 'DIAGBODY'], ['Subject: Kredit', 'Subject: DIAGSUBJECT']]) {
    const fixture = await signedFixture();
    fixture.rawMime = fixture.rawMime.replace(before, after);
    const { diagnostics, verification } = await check(fixture);
    assert.equal(verification.reason, 'invalid_signature');
    assert.notEqual(diagnostics.signatures[0].verdict, 'pass');
  }
});

test('weak synthetic key is rejected without disclosing the key or library explanation', async () => {
  const { publicKey } = generateKeyPairSync('rsa', { modulusLength: 512 });
  const fixture = await signedFixture();
  const record = `v=DKIM1; k=rsa; p=${publicKey.export({ type: 'spki', format: 'der' }).toString('base64')}`;
  const { diagnostics, verification } = await check(fixture, async () => [[record]]);
  assert.equal(verification.reason, 'invalid_signature');
  assert.equal(diagnostics.signatures[0].verdict, 'policy');
  assert.doesNotMatch(JSON.stringify(diagnostics), /weak|512|BEGIN|p=/);
});

test('missing and temporary key failures preserve the existing invalid/retry split', async () => {
  const fixture = await signedFixture();
  for (const [code, status, reason] of [['ENOTFOUND', 'invalid', 'invalid_signature'], ['ETIMEOUT', 'retryable', 'dns_unavailable']]) {
    const resolver = async () => { throw Object.assign(new Error('DIAGCOMMENT'), { code }); };
    const envelope = await check(fixture, resolver);
    assert.equal(envelope.verification.status, status);
    assert.equal(envelope.verification.reason, reason);
    assert.notEqual(envelope.diagnostics.signatures[0].verdict, 'pass');
  }
});

test('an unsupported signer remains false and does not query attacker DNS', async () => {
  const fixture = await signedFixture({ domain: 'DIAGDOMAIN.test' });
  let queries = 0;
  const { diagnostics, verification } = await check(fixture, async () => { queries++; return [[fixture.dnsRecord]]; });
  assert.equal(verification.reason, 'invalid_signature');
  assert.equal(diagnostics.signatures[0].bank_signer, false);
  assert.equal(queries, 0);
});

test('malformed signatures retain unavailable facts as null instead of false or true', async () => {
  const fixture = await signedFixture();
  fixture.rawMime = 'DKIM-Signature: DIAGSIGNATURE\r\n' + fixture.rawMime.slice(signaturePrefix(fixture).length);
  const { diagnostics, verification } = await check(fixture);
  assert.equal(verification.reason, 'invalid_signature');
  assert.equal(diagnostics.signatures.length, 1);
  assert.equal(diagnostics.signatures[0].full_body, null);
  assert.equal(diagnostics.signatures[0].headers.from.signed, null);
});

test('precrypto rejections produce no signature claims and keep exact original decisions', async () => {
  const fixture = await signedFixture();
  const cases = [
    null, [], {}, { ...input(fixture), resolver: 'DIAGRESOLVER' },
    { rawMime: '\ud800', recipient: fixture.recipient },
    { rawMime: 'DIAGBODY'.repeat(MAX_RAW_BYTES), recipient: fixture.recipient },
    { ...input(fixture), rawMime: fixture.rawMime.replaceAll('\r\n', '\n') },
    { ...input(fixture), rawMime: fixture.rawMime.slice(signaturePrefix(fixture).length) },
    input(await signedFixture({ signatureOptions: { maxBodyLength: 10 } })),
    input(await signedFixture({ contentType: 'text/html' })),
    { ...input(fixture), recipient: 'DIAGTO@other.test' },
  ];
  for (const value of cases) {
    const envelope = await diagnose(value, { resolver: fixture.resolver });
    assert.deepEqual(envelope.verification, await verify(value, { resolver: fixture.resolver }));
    assert.deepEqual(envelope.diagnostics.signatures, []);
    assertPrivate(envelope.diagnostics);
  }
});

test('five signatures are bounded and six are rejected before evaluation', async () => {
  const fixture = await signedFixture();
  const prefix = signaturePrefix(fixture);
  const unsigned = fixture.rawMime.slice(prefix.length);
  fixture.rawMime = prefix.repeat(5) + unsigned;
  const envelope = await check(fixture);
  assert.equal(envelope.verification.status, 'verified');
  assert.equal(envelope.diagnostics.signatures.length, 5);
  fixture.rawMime = prefix.repeat(6) + unsigned;
  const rejected = await check(fixture);
  assert.equal(rejected.verification.reason, 'too_many_signatures');
  assert.deepEqual(rejected.diagnostics.signatures, []);
});

test('facts from separate signatures cannot combine into an eligible proof', async () => {
  const first = await signedFixture({ signedHeaders: HEADERS.filter((name) => name !== 'to') });
  const { privateKey, publicKey } = generateKeyPairSync('rsa', { modulusLength: 2048 });
  const unsigned = first.rawMime.slice(signaturePrefix(first).length);
  const second = await dkimSign(Buffer.from(unsigned), {
    strict: true, canonicalization: 'relaxed/relaxed', headerList: HEADERS.filter((name) => name !== 'subject'),
    signatureData: [{ signingDomain: 'tatrabanka.sk', selector: 'DIAGSECOND', privateKey: privateKey.export({ type: 'pkcs8', format: 'pem' }) }],
  });
  assert.deepEqual(second.errors, []);
  first.rawMime = second.signatures + first.rawMime;
  const record = `v=DKIM1; k=rsa; p=${publicKey.export({ type: 'spki', format: 'der' }).toString('base64')}`;
  const resolver = async (name, rr) => name.toLowerCase() === 'diagsecond._domainkey.tatrabanka.sk' ? [[record]] : first.resolver(name, rr);
  const { diagnostics, verification } = await check(first, resolver);
  assert.equal(verification.reason, 'invalid_signature');
  assert.equal(diagnostics.signatures.length, 2);
  assert.ok(diagnostics.signatures.every((facts) => facts.verdict === 'pass'));
  assert.ok(diagnostics.signatures.some((facts) => !facts.headers.to.signed));
  assert.ok(diagnostics.signatures.some((facts) => !facts.headers.subject.signed));
});

const diagnosticCli = fileURLToPath(new URL('../diagnostic-cli.mjs', import.meta.url));
const strictCli = fileURLToPath(new URL('../cli.mjs', import.meta.url));
const runCli = (path, value, args = [], env = {}, scriptArgs = []) => spawnSync(process.execPath, [...args, path, ...scriptArgs], {
  input: value, encoding: 'utf8', timeout: 5000, maxBuffer: 2 * 1024 * 1024, env: { ...process.env, ...env },
});
const decodeCli = (result) => {
  assert.equal(result.status, 0);
  assert.equal(result.stderr, '');
  assert.equal(result.stdout.trim().split('\n').length, 1);
  return JSON.parse(result.stdout);
};

test('real CLI envelope binds input and emits only allowlisted diagnostic facts', async () => {
  const fixture = await signedFixture({
    from: 'DIAGFROM@tatrabanka.sk', recipient: 'DIAGTO@mail.satflux.test',
    selector: 'DIAGSELECTOR', body: 'DIAGBODY\r\n', extraHeaders: ['X-DIAGHEADER: DIAGVALUE'],
  });
  const directory = mkdtempSync(join(tmpdir(), 'satflux-bank-diagnostic-'));
  try {
    const publicDns = join(directory, 'public-dns.json');
    writeFileSync(publicDns, JSON.stringify({ dnsName: fixture.dnsName, dnsRecord: fixture.dnsRecord }), { mode: 0o600 });
    const args = ['--import', fileURLToPath(new URL('./preload-dns.mjs', import.meta.url))];
    const env = { SATFLUX_TEST_DNS_FIXTURE: publicDns };
    const wire = JSON.stringify(input(fixture));
    const envelope = decodeCli(runCli(diagnosticCli, wire, args, env));
    const strict = decodeCli(runCli(strictCli, wire, args, env));
    assert.deepEqual(envelope.verification, strict);
    assert.equal(strict.status, 'verified');
    assert.equal(envelope.contentSha256, createHash('sha256').update(fixture.rawMime).digest('hex'));
    assertPrivate(envelope.diagnostics);
    fixture.rawMime = fixture.rawMime.replace('DIAGBODY', 'DIAGTAMPER');
    const rejected = decodeCli(runCli(diagnosticCli, JSON.stringify(input(fixture)), args, env));
    assert.equal(rejected.verification.reason, 'invalid_signature');
    assertPrivate(rejected.diagnostics);
  } finally { rmSync(directory, { recursive: true, force: true }); }
});

test('diagnostic CLI bounds input, rejects JSON options and ignores argv flags', () => {
  for (const [wire, reason] of [
    ['DIAGJSON', 'invalid_json'],
    [JSON.stringify({ rawMime: 'DIAGBODY', recipient: DEFAULT_RECIPIENT, resolver: 'DIAGRESOLVER' }), 'invalid_input'],
    ['x'.repeat(MAX_RAW_BYTES * 6 + 4097), 'input_too_large'],
  ]) {
    const envelope = decodeCli(runCli(diagnosticCli, wire, [], {}, ['--resolver=DIAGRESOLVER']));
    assert.equal(envelope.verification.reason, reason);
    assert.deepEqual(envelope.diagnostics.signatures, []);
    assertPrivate(envelope.diagnostics);
    if (reason !== 'invalid_input') assert.equal(envelope.contentSha256, null);
    else assert.equal(envelope.contentSha256, createHash('sha256').update('DIAGBODY').digest('hex'));
  }
});

test('diagnostic CLI retains the 13-second hard transport timeout', async () => {
  const started = Date.now();
  const child = spawn(process.execPath, [diagnosticCli], { stdio: ['pipe', 'pipe', 'pipe'] });
  child.stdin.on('error', () => {});
  let stdout = '';
  let stderr = '';
  child.stdout.on('data', (chunk) => { stdout += chunk; });
  child.stderr.on('data', (chunk) => { stderr += chunk; });
  const watchdog = setTimeout(() => child.kill(), 16000);
  try {
    const status = await new Promise((resolve, reject) => { child.on('error', reject); child.on('close', resolve); });
    assert.equal(status, 0);
    assert.equal(stderr, '');
    const envelope = JSON.parse(stdout);
    assert.equal(envelope.verification.reason, 'verification_timeout');
    assertPrivate(envelope.diagnostics);
    assert.equal(envelope.contentSha256, null);
    assert.ok(Date.now() - started >= 12500);
    assert.ok(Date.now() - started < 16000);
  } finally { clearTimeout(watchdog); child.stdin.destroy(); }
});
