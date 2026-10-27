import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chmodSync, mkdtempSync, readFileSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { deploymentConfig, dispatchDeployment, readPrivateToken } from './dispatch.mjs';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const SHA = 'a'.repeat(40);
const OTHER = 'b'.repeat(40);
const TOKEN = 'github_pat_SYNTHETIC_DEPLOYMENT_TOKEN';
function configuration(overrides = {}) {
  return {
    CHORALA_CHANGELOG_ENABLED: 'true', CHORALA_DEPLOY_ENVIRONMENT: 'production',
    CHORALA_DEPLOY_SHA: SHA, CHORALA_DEPLOY_HOST_SHA: SHA,
    CHORALA_DEPLOY_HEALTH_URL: 'https://satflux.example/api/health', CHORALA_DEPLOY_TOKEN: TOKEN,
    ...overrides,
  };
}
function transport({ health = { status: 200, json: async () => ({ status: 'ok' }) }, dispatch = { status: 204 } } = {}) {
  const calls = [];
  return {
    calls,
    fetchImpl: async (url, options) => {
      calls.push({ url, options });
      const result = options.method === 'GET' ? health : dispatch;
      if (result instanceof Error) throw result;
      return result;
    },
  };
}
async function notify(env = configuration(), overrides = {}) {
  const io = transport();
  const result = await dispatchDeployment(env, { readRuntimeSha: () => SHA, fetchImpl: io.fetchImpl, ...overrides });
  return { result, calls: io.calls };
}

test('disabled dispatch reads neither credentials nor runtime identity and performs no HTTP', async () => {
  const env = new Proxy({}, { get: (_target, key) => {
    assert.equal(key, 'CHORALA_CHANGELOG_ENABLED');
    return undefined;
  } });
  const forbidden = () => { throw new Error('Unexpected side effect'); };
  assert.deepEqual(await dispatchDeployment(env, {
    readRuntimeSha: forbidden, fetchImpl: forbidden, readTokenFile: forbidden,
  }), { enabled: false });
});

test('only explicit production opt-in accepts an exact matching deployment SHA', () => {
  assert.equal(deploymentConfig({ CHORALA_CHANGELOG_ENABLED: '1' }), null);
  for (const override of [
    { CHORALA_DEPLOY_ENVIRONMENT: 'staging' }, { CHORALA_DEPLOY_ENVIRONMENT: '' },
    { CHORALA_DEPLOY_SHA: 'master' }, { CHORALA_DEPLOY_SHA: `${SHA}\nINJECT=yes` },
    { CHORALA_DEPLOY_HOST_SHA: OTHER },
  ]) assert.throws(() => deploymentConfig(configuration(override)));
});

test('health configuration refuses insecure, credential-bearing and non-health destinations', () => {
  for (const url of [
    'http://satflux.example/api/health', 'https://name:secret@satflux.example/api/health',
    'https://satflux.example/api/health?token=secret', 'https://satflux.example/api/health#fragment',
    'https://satflux.example/login', '/api/health', 'not a URL',
    'https://127.0.0.1/api/health', 'https://[::1]/api/health', 'https://localhost/api/health',
    'https://satflux.internal/api/health', 'https://satflux.local/api/health',
  ]) assert.throws(() => deploymentConfig(configuration({ CHORALA_DEPLOY_HEALTH_URL: url })));
});

test('runtime identity mismatch stops before health or credential access', async () => {
  await assert.rejects(notify(configuration(), {
    readRuntimeSha: () => OTHER,
    fetchImpl: () => { throw new Error('Unexpected network'); },
    readTokenFile: () => { throw new Error('Unexpected secret read'); },
  }), /Runtime source differs/);
});

test('successful health verification queues fixed-master publication of exactly the deployed SHA', async () => {
  const { result, calls } = await notify();
  assert.deepEqual(result, { enabled: true, queued: true, sha: SHA });
  assert.equal(calls.length, 2);
  assert.equal(calls[0].url, 'https://satflux.example/api/health');
  assert.equal(calls[0].options.headers.Authorization, undefined);
  assert.equal(calls[1].url, 'https://api.github.com/repos/webiumsk/satflux/actions/workflows/chorala-changelog.yml/dispatches');
  assert.deepEqual(JSON.parse(calls[1].options.body), {
    ref: 'master', inputs: { mode: 'publish', source_sha: SHA, environment: 'production' },
  });
  assert.equal(calls[1].options.headers.Authorization, `Bearer ${TOKEN}`);
  assert.ok(!calls[1].options.body.includes(TOKEN));
  for (const call of calls) {
    assert.equal(call.options.redirect, 'error');
    assert.equal(call.options.credentials, 'omit');
    assert.ok(call.options.signal instanceof AbortSignal);
  }
});

test('a runtime revision change during health verification blocks token access and publication', async () => {
  const io = transport();
  let reads = 0;
  await assert.rejects(notify(configuration({ CHORALA_DEPLOY_TOKEN: '', CHORALA_DEPLOY_TOKEN_FILE: 'private' }), {
    fetchImpl: io.fetchImpl, readRuntimeSha: () => ++reads === 1 ? SHA : OTHER,
    readTokenFile: () => { throw new Error('Unexpected token read'); },
  }), /Runtime source changed/);
  assert.equal(io.calls.length, 1);
});

for (const [name, response] of [
  ['unreachable', new Error(`Provider error containing ${TOKEN}`)],
  ['redirect', { status: 302 }], ['unhealthy', { status: 503 }],
  ['invalid JSON', { status: 200, json: async () => { throw new Error(TOKEN); } }],
  ['wrong status', { status: 200, json: async () => ({ status: 'pending' }) }],
]) test(`a ${name} health response never reads the token or dispatches`, async () => {
  const io = transport({ health: response });
  let secretRead = false;
  await assert.rejects(dispatchDeployment(configuration({ CHORALA_DEPLOY_TOKEN: '', CHORALA_DEPLOY_TOKEN_FILE: 'private' }), {
    readRuntimeSha: () => SHA, fetchImpl: io.fetchImpl,
    readTokenFile: () => { secretRead = true; return TOKEN; },
  }), error => !error.message.includes(TOKEN));
  assert.equal(secretRead, false);
  assert.equal(io.calls.length, 1);
});

test('invalid and ambiguous token sources cannot dispatch after successful health', async () => {
  for (const overrides of [
    { CHORALA_DEPLOY_TOKEN: '' }, { CHORALA_DEPLOY_TOKEN: 'short' },
    { CHORALA_DEPLOY_TOKEN: `wrong ${TOKEN}` }, { CHORALA_DEPLOY_TOKEN_FILE: 'private' },
  ]) {
    const io = transport();
    await assert.rejects(notify(configuration(overrides), { fetchImpl: io.fetchImpl }));
    assert.equal(io.calls.length, 1);
  }
});

test('a private token file is read after health and its value is not returned', async () => {
  const io = transport();
  const { result } = await notify(configuration({ CHORALA_DEPLOY_TOKEN: '', CHORALA_DEPLOY_TOKEN_FILE: 'private' }), {
    fetchImpl: io.fetchImpl, readTokenFile: path => {
      assert.equal(io.calls.length, 1); assert.equal(path, 'private'); return TOKEN;
    },
  });
  assert.ok(!JSON.stringify(result).includes(TOKEN));
});

test('private token files refuse public permissions and symlinks', () => {
  const directory = mkdtempSync(join(tmpdir(), 'satflux-dispatch-token-'));
  try {
    const path = join(directory, 'token');
    writeFileSync(path, `${TOKEN}\n`, { mode: 0o600 });
    assert.equal(readPrivateToken(path), TOKEN);
    chmodSync(path, 0o640);
    assert.throws(() => readPrivateToken(path), /private and regular/);
    chmodSync(path, 0o600);
    const alias = join(directory, 'alias');
    symlinkSync(path, alias);
    assert.throws(() => readPrivateToken(alias), /private and regular/);
    writeFileSync(path, 'x'.repeat(4097), { mode: 0o600 });
    assert.throws(() => readPrivateToken(path), /private and regular/);
  } finally { rmSync(directory, { recursive: true, force: true }); }
});

test('dispatch failures redact response bodies and retain only the changelog retry identity', async () => {
  for (const dispatch of [
    new Error(`Network error contains ${TOKEN}`),
    { status: 403, get body() { throw new Error('Response body must not be inspected'); } },
    { status: 503, get body() { throw new Error('Response body must not be inspected'); } },
  ]) {
    const io = transport({ dispatch });
    await assert.rejects(notify(configuration(), { fetchImpl: io.fetchImpl }), error => {
      assert.ok(error.message.includes(SHA));
      assert.match(error.message, /retry changelog dispatch only/i);
      assert.ok(!error.message.includes(TOKEN));
      return true;
    });
    assert.equal(io.calls.length, 2);
  }
});

test('the actual post-deploy hook is inert when disabled and keeps credentials out of Docker arguments', () => {
  const source = readFileSync(join(ROOT, 'deploy.sh'), 'utf8');
  const hook = source.match(/# BEGIN CHORALA CHANGELOG HOOK\n([\s\S]*?)# END CHORALA CHANGELOG HOOK/)[1];
  const directory = mkdtempSync(join(tmpdir(), 'satflux-dispatch-hook-'));
  const calls = join(directory, 'calls');
  try {
    writeFileSync(join(directory, 'git'), '#!/bin/sh\nprintf "git\\n" >> "$TEST_CALLS"\nprintf "%s\\n" "$TEST_HOST_SHA"\n', { mode: 0o700 });
    writeFileSync(join(directory, 'docker'), '#!/bin/sh\nprintf "%s\\n" "$@" >> "$TEST_CALLS"\nexit "$TEST_DISPATCH_EXIT"\n', { mode: 0o700 });
    const env = { ...process.env, PATH: `${directory}:${process.env.PATH}`, TEST_CALLS: calls,
      TEST_HOST_SHA: SHA, TEST_DISPATCH_EXIT: '0', PHP_CONTAINER: 'synthetic-php', DEPLOYED_SHA: SHA,
      YELLOW: '', NC: '', CHORALA_CHANGELOG_ENABLED: 'false', CHORALA_DEPLOY_TOKEN: TOKEN };
    assert.equal(execFileSync('bash', ['-c', `set -e\n${hook}`], { env, encoding: 'utf8' }), '');
    assert.throws(() => readFileSync(calls), { code: 'ENOENT' });
    env.CHORALA_CHANGELOG_ENABLED = 'true';
    execFileSync('bash', ['-c', `set -e\n${hook}`], { env });
    const argumentsUsed = readFileSync(calls, 'utf8');
    assert.ok(argumentsUsed.includes('CHORALA_DEPLOY_TOKEN\n'));
    assert.ok(!argumentsUsed.includes(TOKEN));
    assert.ok(argumentsUsed.includes('/var/www/scripts/changelog/dispatch.mjs'));
    env.TEST_DISPATCH_EXIT = '1';
    const output = execFileSync('bash', ['-c', `set -e\n${hook}`], { env, encoding: 'utf8' });
    assert.ok(output.includes(SHA));
    assert.match(output, /do not redeploy solely/);
    assert.ok(!output.includes(TOKEN));
  } finally { rmSync(directory, { recursive: true, force: true }); }
});

function workflowEvent(overrides = {}) {
  return { workflow_run: {
    conclusion: 'success', event: 'push', path: '.github/workflows/ci.yml', head_branch: 'master',
    head_repository: { full_name: 'webiumsk/satflux' }, head_sha: SHA, ...overrides,
  } };
}
function runWorkflowGuard(event, { name = 'workflow_run', ref = 'refs/heads/master', ancestor = true } = {}) {
  const source = readFileSync(join(ROOT, '.github/workflows/chorala-changelog.yml'), 'utf8');
  const guard = source.match(/node --input-type=module <<'NODE'\n([\s\S]*?)\n\s+NODE/)[1]
    .split('\n').map(line => line.replace(/^          /, '')).join('\n');
  const directory = mkdtempSync(join(tmpdir(), 'satflux-workflow-guard-'));
  try {
    const eventPath = join(directory, 'event.json');
    const outputPath = join(directory, 'environment');
    writeFileSync(eventPath, JSON.stringify(event)); writeFileSync(outputPath, '');
    writeFileSync(join(directory, 'git'), `#!/bin/sh\nexit ${ancestor ? 0 : 1}\n`, { mode: 0o700 });
    let failed = false;
    try {
      execFileSync(process.execPath, ['--input-type=module', '-e', guard], {
        env: { ...process.env, PATH: `${directory}:${process.env.PATH}`, GITHUB_EVENT_PATH: eventPath,
          GITHUB_ENV: outputPath, GITHUB_EVENT_NAME: name, GITHUB_REPOSITORY: 'webiumsk/satflux',
          EXPECTED_REPOSITORY: 'webiumsk/satflux', GITHUB_REF: ref, GITHUB_SHA: SHA },
        stdio: 'ignore',
      });
    } catch { failed = true; }
    return { failed, output: readFileSync(outputPath, 'utf8') };
  } finally { rmSync(directory, { recursive: true, force: true }); }
}

test('the actual workflow guard accepts only successful owned master push CI', () => {
  assert.deepEqual(runWorkflowGuard(workflowEvent()), { failed: false, output: `MODE=draft\nSOURCE_SHA=${SHA}\n` });
  for (const override of [
    { conclusion: 'failure' }, { event: 'pull_request' }, { head_branch: 'feature' },
    { path: '.github/workflows/other.yml' }, { head_repository: { full_name: 'outsider/satflux' } },
    { head_sha: `${SHA}\nMODE=publish` },
  ]) assert.deepEqual(runWorkflowGuard(workflowEvent(override)), { failed: true, output: '' });
  assert.deepEqual(runWorkflowGuard(workflowEvent(), { ancestor: false }), { failed: true, output: '' });
});

test('manual publication requires trusted master, production and an exact ancestor SHA', () => {
  const event = { inputs: { mode: 'publish', source_sha: SHA, environment: 'production' } };
  assert.deepEqual(runWorkflowGuard(event, { name: 'workflow_dispatch' }), { failed: false, output: `MODE=publish\nSOURCE_SHA=${SHA}\n` });
  assert.deepEqual(runWorkflowGuard(event, { name: 'workflow_dispatch', ref: 'refs/heads/feature' }), { failed: true, output: '' });
  assert.deepEqual(runWorkflowGuard({ inputs: { ...event.inputs, environment: 'staging' } }, { name: 'workflow_dispatch' }), { failed: true, output: '' });
  assert.deepEqual(runWorkflowGuard({ inputs: { ...event.inputs, source_sha: 'master' } }, { name: 'workflow_dispatch' }), { failed: true, output: '' });
  assert.deepEqual(runWorkflowGuard({ inputs: { mode: 'initialize' } }, { name: 'workflow_dispatch' }), { failed: false, output: `MODE=initialize\nSOURCE_SHA=${SHA}\n` });
});
