import assert from 'node:assert/strict';
import { test } from 'node:test';
import { guard, releaseSource, verifyPendingReleases, verifyReleaseHead } from './guard.mjs';
import { checkVersions } from './check.mjs';

const sha = 'a'.repeat(40);
const next = 'b'.repeat(40);
const env = {
    GITHUB_REPOSITORY: 'webiumsk/satflux',
    GITHUB_EVENT_NAME: 'workflow_run',
    GITHUB_REF: 'refs/heads/master',
    GITHUB_TOKEN: 'synthetic-offline-token',
};
const event = {
    repository: { full_name: 'webiumsk/satflux' },
    workflow_run: {
        id: 100, run_attempt: 1,
        name: 'CI', path: '.github/workflows/ci.yml', status: 'completed', conclusion: 'success',
        event: 'push', head_branch: 'master', head_repository: { full_name: 'webiumsk/satflux' }, head_sha: sha,
    },
};

test('only the successful owned master-push CI source is accepted', () => {
    assert.equal(releaseSource(event, env), sha);
    for (const change of [
        { name: 'Other CI' }, { path: '.github/workflows/other.yml' }, { status: 'in_progress' },
        { conclusion: 'failure' }, { conclusion: 'cancelled' }, { event: 'pull_request' },
        { head_branch: 'feature' }, { head_repository: { full_name: 'attacker/satflux' } },
        { head_repository: null }, { head_sha: 'master' }, { head_sha: `${sha}\nINJECT=true` },
        { head_sha: `${sha}\n` }, { head_sha: [sha] },
    ]) {
        assert.throws(() => releaseSource({ ...event, workflow_run: { ...event.workflow_run, ...change } }, env));
    }
    for (const change of [
        { GITHUB_REPOSITORY: 'attacker/satflux' }, { GITHUB_EVENT_NAME: 'workflow_dispatch' },
        { GITHUB_REF: 'refs/heads/feature' },
    ]) assert.throws(() => releaseSource(event, { ...env, ...change }));
    assert.throws(() => releaseSource({ ...event, repository: { full_name: 'attacker/satflux' } }, env));
    assert.throws(() => releaseSource({}, env));
});

test('stale or malformed checkout and remote master fail closed', () => {
    verifyReleaseHead(sha, sha, sha);
    for (const [checkout, current] of [[next, sha], [sha, next], [next, next], ['', sha], [sha, undefined]]) {
        assert.throws(() => verifyReleaseHead(sha, checkout, current));
    }
});

test('API verification is read-only and binds source, checkout and current master', async () => {
    let calls = 0;
    const fetchImpl = async (url, options) => {
        calls += 1;
        assert.ok(url.startsWith('https://api.github.com/repos/webiumsk/satflux/'));
        assert.equal(options.method, 'GET');
        assert.equal(options.redirect, 'error');
        assert.equal(options.headers.Authorization, 'Bearer synthetic-offline-token');
        return { ok: true, json: async () => url.includes('/issues?') ? [] : ({ object: { sha } }) };
    };
    assert.equal(await guard({ event, env, checkout: sha, fetchImpl }), sha);
    assert.equal(calls, 3);
    await assert.rejects(guard({ event: {}, env, checkout: sha, fetchImpl }));
    await assert.rejects(guard({ event, env: { ...env, GITHUB_TOKEN: '' }, checkout: sha, fetchImpl }));
    assert.equal(calls, 3);
});

function pendingRequest(runs) {
    return async (path) => {
        if (path.startsWith('/issues?')) return [{ number: 10, pull_request: {} }];
        if (path === '/pulls/10') return {
            merged: true, base: { ref: 'master', repo: { full_name: 'webiumsk/satflux' } },
            labels: [{ name: 'autorelease: pending' }], merge_commit_sha: next,
        };
        assert.ok(path.includes(`head_sha=${next}`));
        return { workflow_runs: runs, total_count: runs.length };
    };
}

test('green master cannot authorize a failed or cancelled earlier pending release SHA', async () => {
    for (const conclusion of ['failure', 'cancelled', null]) {
        await assert.rejects(verifyPendingReleases(pendingRequest([
            { ...event.workflow_run, conclusion, head_sha: next }, event.workflow_run,
        ])));
    }
    await verifyPendingReleases(pendingRequest([{ ...event.workflow_run, head_sha: next }]));
});

test('pending-release CI rejects PR, fork, unrelated or incomplete evidence', async () => {
    for (const change of [
        { event: 'pull_request' }, { head_branch: 'feature' }, { status: 'queued' },
        { head_repository: { full_name: 'attacker/satflux' } }, { path: 'other.yml' }, { name: 'Other CI' },
    ]) await assert.rejects(verifyPendingReleases(pendingRequest([{ ...event.workflow_run, head_sha: next, ...change }])));
    await assert.rejects(verifyPendingReleases(pendingRequest([])));
});

test('newer failed CI and failed reruns override earlier successful evidence', async () => {
    const success = { ...event.workflow_run, head_sha: next };
    await assert.rejects(verifyPendingReleases(pendingRequest([
        success, { ...success, id: 101, conclusion: 'failure' },
    ])));
    await assert.rejects(verifyPendingReleases(pendingRequest([
        { ...success, run_attempt: 2, conclusion: 'failure' }, success,
    ])));
    await verifyPendingReleases(pendingRequest([
        { ...success, conclusion: 'failure' }, { ...success, id: 101 },
    ]));
    for (const change of [{ id: undefined }, { id: '100' }, { id: -1 }, { run_attempt: 0 }]) {
        await assert.rejects(verifyPendingReleases(pendingRequest([{ ...success, ...change }])));
    }
});

test('closed non-PR issues and unmerged or other-branch PRs are not release candidates', async () => {
    for (const pr of [
        { merged: false },
        { merged: true, base: { ref: 'other', repo: { full_name: 'webiumsk/satflux' } } },
        { merged: true, base: { ref: 'master', repo: { full_name: 'webiumsk/satflux' } }, labels: [] },
    ]) {
        await verifyPendingReleases(async (path) => path.startsWith('/issues?')
            ? [{ number: 1 }, { number: 10, pull_request: {} }] : pr);
    }
});

test('candidate and CI pages are fully read including second-page release evidence', async () => {
    let issuePages = 0;
    let runPages = 0;
    await verifyPendingReleases(async (path) => {
        if (path.startsWith('/issues?')) {
            issuePages += 1;
            return path.endsWith('page=1') ? Array.from({ length: 100 }, () => ({ number: 1 }))
                : [{ number: 10, pull_request: {} }];
        }
        if (path === '/pulls/10') return pendingRequest([])(path);
        runPages += 1;
        return { total_count: 101, workflow_runs: path.endsWith('page=1')
            ? Array.from({ length: 100 }, (_, index) => ({ ...event.workflow_run, id: index + 1, conclusion: 'failure', head_sha: next }))
            : [{ ...event.workflow_run, id: 101, head_sha: next }] };
    });
    assert.equal(issuePages, 2);
    assert.equal(runPages, 2);
});

test('unknown, malformed and bounded-incomplete candidate evidence blocks release', async () => {
    for (const response of [null, {}, [null], [{ number: '../other', pull_request: {} }]]) {
        await assert.rejects(verifyPendingReleases(async () => response));
    }
    await assert.rejects(verifyPendingReleases(async () => Array.from({ length: 100 }, () => ({ number: 1 }))));
    const read = pendingRequest([]);
    await assert.rejects(verifyPendingReleases(async (path) => path.includes('/runs?') ? {} : read(path)));
    for (const result of [
        { total_count: 1001, workflow_runs: [{ ...event.workflow_run, head_sha: next }] },
        { total_count: 2, workflow_runs: [{ ...event.workflow_run, head_sha: next }] },
    ]) await assert.rejects(verifyPendingReleases(async (path) => path.includes('/runs?') ? result : read(path)));
    for (const pr of [
        {}, null, { merged: 'true' },
        { merged: true, base: { repo: { full_name: 'webiumsk/satflux' } } },
    ]) await assert.rejects(verifyPendingReleases(async (path) => path === '/pulls/10' ? pr : read(path)));
});

test('master advancing while pending releases are checked blocks the action', async () => {
    let heads = 0;
    await assert.rejects(guard({ event, env, checkout: sha, fetchImpl: async (url) => ({
        ok: true, json: async () => url.includes('/issues?') ? [] : ({ object: { sha: ++heads === 1 ? sha : next } }),
    }) }));
});

test('API failure, moved remote and malformed data cannot authorize the action', async () => {
    for (const response of [
        { ok: false }, { ok: true, json: async () => ({}) },
        { ok: true, json: async () => ({ object: { sha: next } }) },
    ]) await assert.rejects(guard({ event, env, checkout: sha, fetchImpl: async () => response }));
    await assert.rejects(guard({ event, env, checkout: sha, fetchImpl: async () => { throw new Error('offline'); } }));
});

test('version agreement includes both lockfile entries and release manifest', () => {
    const pkg = { version: '2.0.0' };
    const lock = { version: '2.0.0', packages: { '': { version: '2.0.0' } } };
    const manifest = { '.': '2.0.0' };
    assert.equal(checkVersions(pkg, lock, manifest), '2.0.0');
    assert.throws(() => checkVersions({ version: '2.1.0' }, lock, manifest));
    assert.throws(() => checkVersions(pkg, { ...lock, version: '1.9.0' }, manifest));
    assert.throws(() => checkVersions(pkg, { ...lock, packages: {} }, manifest));
    assert.throws(() => checkVersions(pkg, lock, { '.': '2.1.0' }));
    assert.throws(() => checkVersions({ version: 'garbage' }, lock, manifest));
});
