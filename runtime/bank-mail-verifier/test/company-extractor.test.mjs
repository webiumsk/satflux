import test from 'node:test';
import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { extractCompany, MAX_RAW_BYTES } from '../company-extractor.mjs';

const mime = ({ body = 'Suma: 12,50 EUR. VS: 123.', type = 'text/plain; charset=utf-8', encoding = '8bit', headers = [] } = {}) => Buffer.concat([
  Buffer.from(['From: bank@slsp.sk', 'To: different@example.test', 'Subject: Obrat', 'MIME-Version: 1.0',
    `Content-Type: ${type}`, `Content-Transfer-Encoding: ${encoding}`, ...headers, '', ''].join('\r\n')),
  Buffer.isBuffer(body) ? body : Buffer.from(body),
]);
const input = (raw) => ({ rawMimeBase64: raw.toString('base64') });
const run = (options) => extractCompany(input(mime(options)));
const assertInvalid = (value) => {
  assert.equal(value.status, 'invalid');
  assert.deepEqual(Object.keys(value).sort(), ['reason', 'status']);
};

test('company extraction is unsigned, recipient-independent and never bank proof', async () => {
  const raw = mime();
  const result = await extractCompany(input(raw));
  assert.equal(result.status, 'extracted');
  assert.equal(result.from, 'bank@slsp.sk');
  assert.equal(result.body, 'Suma: 12,50 EUR. VS: 123.');
  assert.equal(result.contentSha256, createHash('sha256').update(raw).digest('hex'));
  assert.equal('bankDomain' in result, false);
  assert.equal('recipient' in result, false);
  assert.equal('_verified_bank_domain' in result, false);
});

test('quoted-printable and base64 text decode without changing original digest', async () => {
  for (const [encoding, body] of [['quoted-printable', 'Pr=C3=ADjem'], ['base64', Buffer.from('Príjem').toString('base64')]]) {
    const raw = mime({ encoding, body });
    const result = await extractCompany(input(raw));
    assert.equal(result.status, 'extracted');
    assert.equal(result.body, 'Príjem');
    assert.equal(result.contentSha256, createHash('sha256').update(raw).digest('hex'));
  }
});

test('binary-safe Windows1250 and ISO8859-2 text retain Slovak characters', async () => {
  for (const charset of ['windows-1250', 'iso-8859-2']) {
    const raw = mime({ body: Buffer.from([0x50, 0x72, 0xed, 0x6a, 0x65, 0x6d]), type: `text/plain; charset=${charset}` });
    const result = await extractCompany(input(raw));
    assert.equal(result.status, 'extracted');
    assert.equal(result.body, 'Príjem');
    assert.equal(result.contentSha256, createHash('sha256').update(raw).digest('hex'));
  }
});

test('ASCII default and explicit ASCII are accepted', async () => {
  for (const type of ['text/plain', 'text/plain; charset=us-ascii']) assert.equal((await run({ type })).status, 'extracted');
});

test('encoded subject is decoded', async () => {
  const raw = mime().toString().replace('Subject: Obrat', `Subject: =?UTF-8?B?${Buffer.from('Príjem').toString('base64')}?=`);
  const result = await extractCompany(input(Buffer.from(raw)));
  assert.equal(result.status, 'extracted');
  assert.equal(result.subject, 'Príjem');
});

test('multipart alternatives prefer plain text and HTML-only messages convert locally', async () => {
  const result = await run({ type: 'multipart/alternative; boundary=synthetic', body: [
    '--synthetic', 'Content-Type: text/plain; charset=utf-8', '', 'PLAIN',
    '--synthetic', 'Content-Type: text/html; charset=utf-8', '', '<p>HTML</p>', '--synthetic--', '',
  ].join('\r\n') });
  assert.equal(result.status, 'extracted');
  assert.equal(result.body, 'PLAIN');
  const html = await run({ type: 'text/html; charset=utf-8', body: '<p>Príjem</p><p>Suma: 12,50 EUR.</p><img src="https://never-fetch.invalid/private">' });
  assert.equal(html.status, 'extracted');
  assert.match(html.body, /Príjem/);
  assert.match(html.body, /Suma: 12,50 EUR/);
});

test('legacy charset applies within multipart text parts', async () => {
  const result = await run({ type: 'multipart/alternative; boundary=synthetic', body: Buffer.concat([
    Buffer.from('--synthetic\r\nContent-Type: text/plain; charset=windows-1250\r\nContent-Transfer-Encoding: 8bit\r\n\r\n'),
    Buffer.from([0x50, 0x72, 0xed, 0x6a, 0x65, 0x6d]), Buffer.from('\r\n--synthetic--\r\n'),
  ]) });
  assert.equal(result.status, 'extracted');
  assert.equal(result.body, 'Príjem');
});

test('attachments are ignored and do not become transaction text', async () => {
  const result = await run({ type: 'multipart/mixed; boundary=synthetic', body: [
    '--synthetic', 'Content-Type: text/plain', '', 'BODY',
    '--synthetic', 'Content-Type: text/plain', 'Content-Disposition: attachment; filename=private.txt', '', 'ATTACHMENT', '--synthetic--', '',
  ].join('\r\n') });
  assert.equal(result.status, 'extracted');
  assert.equal(result.body, 'BODY');
  assert.equal(JSON.stringify(result).includes('ATTACHMENT'), false);
});

test('only existing forwarding facts leave the process, never header values', async () => {
  for (const header of ['X-Forwarded-For: synthetic-private-marker', 'Resent-From: synthetic-private-marker', 'Fwd: synthetic-private-marker']) {
    const result = await run({ headers: [header] });
    assert.equal(result.status, 'extracted');
    assert.match(result.headers, /present/);
    assert.equal(JSON.stringify(result).includes('synthetic-private-marker'), false);
  }
});

test('unknown charset and invalid UTF8 never silently replace transaction text', async () => {
  assertInvalid(await run({ type: 'text/plain; charset=made-up', body: Buffer.from([0xe9]) }));
  assertInvalid(await run({ type: 'text/plain; charset=utf-8', body: Buffer.from([0xe9]) }));
  assertInvalid(await run({ type: 'text/plain; charset=utf-7', body: '+ADw-script+AD4-' }));
});

test('malformed headers, duplicate controls and invalid encodings are rejected without data', async () => {
  assertInvalid(await extractCompany(input(Buffer.from('not MIME'))));
  assertInvalid(await run({ headers: ['Subject: duplicate'] }));
  assertInvalid(await run({ encoding: 'base64', body: '!!!private!!!' }));
  assertInvalid(await run({ encoding: 'quoted-printable', body: '=ZZ' }));
  assertInvalid(await run({ encoding: 'made-up' }));
  assertInvalid(await run({ body: 'private\0marker' }));
});

test('raw, decoded body and subject limits are enforced', async () => {
  assertInvalid(await extractCompany(input(Buffer.alloc(MAX_RAW_BYTES + 1, 65))));
  assertInvalid(await run({ body: 'x'.repeat(65536) }));
  assertInvalid(await run({ headers: ['X-Note: ' + 'x'.repeat(32769)] }));
  assertInvalid(await extractCompany(input(Buffer.from(mime().toString().replace('Subject: Obrat', 'Subject: ' + 'x'.repeat(501))))));
});

test('strict canonical base64 and fixed input schema prevent policy flags', async () => {
  for (const value of [null, [], {}, { rawMimeBase64: '!!!' }, { rawMimeBase64: 'Zh==' }, { ...input(mime()), mode: 'store' }, { ...input(mime()), bankDomain: 'tatrabanka.sk' }]) {
    assertInvalid(await extractCompany(value));
  }
});

test('inherited multipart and HTML nesting limits reject resource amplification', async () => {
  const body = `${('--synthetic\r\nContent-Type: text/plain\r\n\r\nx\r\n').repeat(1002)}--synthetic--\r\n`;
  assertInvalid(await run({ type: 'multipart/mixed; boundary=synthetic', body }));
  assertInvalid(await run({ type: 'text/html; charset=utf-8', body: '<div>'.repeat(1100) + 'private' + '</div>'.repeat(1100) }));
});

test('maximum decoded text and JSON expansion remain within CLI capture bounds', () => {
  const cli = fileURLToPath(new URL('../company-cli.mjs', import.meta.url));
  const body = '"'.repeat(65535);
  const result = spawnSync(process.execPath, [cli], { input: JSON.stringify(input(mime({ body }))), encoding: 'utf8', timeout: 5000 });
  assert.equal(result.status, 0);
  assert.equal(JSON.parse(result.stdout).status, 'extracted');
  assert.equal(JSON.parse(result.stdout).body, body);
  assert.ok(Buffer.byteLength(result.stdout) < 300000);
});

test('actual CLI supports binary transport and returns only sanitized invalid errors', () => {
  const cli = fileURLToPath(new URL('../company-cli.mjs', import.meta.url));
  const success = spawnSync(process.execPath, [cli], { input: JSON.stringify(input(mime())), encoding: 'utf8', timeout: 5000 });
  assert.equal(success.status, 0);
  assert.equal(JSON.parse(success.stdout).status, 'extracted');
  assert.equal(success.stderr, '');
  for (const value of ['synthetic-private-invalid-json', JSON.stringify({ rawMimeBase64: '!!!' }), 'x'.repeat(355000)]) {
    const result = spawnSync(process.execPath, [cli], { input: value, encoding: 'utf8', timeout: 5000 });
    assert.equal(result.status, 0);
    assertInvalid(JSON.parse(result.stdout));
    assert.equal(result.stderr, '');
    assert.equal(result.stdout.includes('synthetic-private'), false);
  }
});
