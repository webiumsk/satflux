import { test } from 'node:test';
import assert from 'node:assert/strict';
import { GitLedger, REPOSITORY, STATE_REF, validateState, readJson } from './git-ledger.mjs';

const context = { repository: REPOSITORY, apiBase: 'https://feedback.example.test/api/v1', projectId: 'proj_test' };
function server() {
    let sequence = 1;
    const next = () => (sequence++).toString(16).padStart(40, '0');
    const data = { head: null, blobs: new Map(), trees: new Map(), commits: new Map(), calls: [], runs: [] };
    const response = (value, status = 200) => new Response(JSON.stringify(value), { status });
    data.fetch = async (url, options) => {
        assert.ok(url.startsWith(`https://api.github.com/repos/${REPOSITORY}/`));
        assert.equal(options.redirect, 'error'); assert.ok(options.signal);
        const path = url.slice(`https://api.github.com/repos/${REPOSITORY}`.length);
        const method = options.method;
        const body = options.body ? JSON.parse(options.body) : undefined;
        data.calls.push({ path, method, body });
        if (path.startsWith('/actions/workflows/ci.yml/runs?')) return response({ workflow_runs: data.runs });
        if (path === `/git/ref/${STATE_REF}`) return data.head ? response({ object: { sha: data.head } }) : response({}, 404);
        if (method === 'GET' && path.startsWith('/git/commits/')) return response(data.commits.get(path.split('/').at(-1)));
        if (method === 'GET' && path.startsWith('/git/trees/')) return response(data.trees.get(path.split('/').at(-1)));
        if (method === 'GET' && path.startsWith('/git/blobs/')) {
            return response({ content: Buffer.from(data.blobs.get(path.split('/').at(-1))).toString('base64'), encoding: 'base64' });
        }
        if (method === 'POST' && path === '/git/blobs') { const sha = next(); data.blobs.set(sha, body.content); return response({ sha }, 201); }
        if (method === 'POST' && path === '/git/trees') { const sha = next(); data.trees.set(sha, { tree: body.tree }); return response({ sha }, 201); }
        if (method === 'POST' && path === '/git/commits') {
            const sha = next(); data.commits.set(sha, { tree: { sha: body.tree }, parents: body.parents }); return response({ sha }, 201);
        }
        if (method === 'POST' && path === '/git/refs') {
            assert.equal(body.ref, `refs/${STATE_REF}`);
            if (data.head) return response({}, 422);
            data.head = body.sha; return response({ object: { sha: body.sha } }, 201);
        }
        if (method === 'PATCH' && path === `/git/refs/${STATE_REF}`) {
            assert.equal(body.force, false);
            if (body.sha === data.head) return response({ object: { sha: body.sha } });
            if (data.commits.get(body.sha).parents[0] !== data.head) return response({}, 422);
            data.head = body.sha; return response({ object: { sha: body.sha } });
        }
        throw Error('Unexpected fake API route');
    };
    data.ledger = () => new GitLedger({ token: 'synthetic_token', context, fetchImpl: data.fetch });
    return data;
}

test('missing journal is never automatically initialized by a normal load', async () => {
    const remote = server();
    await assert.rejects(remote.ledger().load(), /missing/);
    assert.equal(remote.calls.filter(call => call.method !== 'GET').length, 0);
});

test('explicit initialization creates one fixed state ref and validates it on repeat', async () => {
    const remote = server();
    const initial = await remote.ledger().initialize();
    assert.deepEqual(initial, { schema: 1, context, entries: {} });
    await remote.ledger().initialize();
    assert.equal(remote.calls.filter(call => call.path === '/git/refs').length, 1);
    assert.ok(!remote.blobs.values().next().value.includes('synthetic_token'));
});

test('competing writers cannot advance the same observed head', async () => {
    const remote = server(); await remote.ledger().initialize();
    const first = remote.ledger(); const second = remote.ledger();
    const one = await first.load(); const two = await second.load();
    one.deploymentSha = 'a'.repeat(40); two.deploymentSha = 'b'.repeat(40);
    await first.save(one);
    await assert.rejects(second.save(two), /HTTP 422/);
    assert.equal((await remote.ledger().load()).deploymentSha, one.deploymentSha);
});

test('wrong target and broken state fail closed', async () => {
    const remote = server(); await remote.ledger().initialize();
    const wrong = new GitLedger({ token: 'synthetic_token', context: { ...context, projectId: 'foreign' }, fetchImpl: remote.fetch });
    await assert.rejects(wrong.load(), /identity/);
    const commit = remote.commits.get(remote.head);
    remote.trees.get(commit.tree.sha).tree = [];
    await assert.rejects(remote.ledger().load(), /missing/);
    assert.throws(() => validateState({ schema: 1, context, entries: [], deploymentSha: 'no' }, context));
});

test('identical competing state writes have distinct commits, so the loser cannot also advance', async () => {
    const remote = server(); await remote.ledger().initialize();
    const first = remote.ledger(); const second = remote.ledger();
    const one = await first.load(); const two = await second.load();
    one.deploymentSha = 'a'.repeat(40); two.deploymentSha = 'a'.repeat(40);
    await first.save(one);
    await assert.rejects(second.save(two), /HTTP 422/);
    const messages = remote.calls.filter(call => call.path === '/git/commits' && call.method === 'POST').map(call => call.body.message);
    assert.equal(new Set(messages).size, messages.length);
});

test('journal read does not expose transport credentials', async () => {
    const ledger = new GitLedger({ token: 'synthetic_token', context, fetchImpl: async () => { throw Error('synthetic_token'); } });
    await assert.rejects(ledger.load(), error => !error.message.includes('synthetic_token'));
});

test('only successful exact owned master-push CI qualifies; newer failures override older successes', async () => {
    const remote = server(); const ledger = remote.ledger(); const sha = 'a'.repeat(40);
    const good = { id: 10, head_sha: sha, event: 'push', head_branch: 'master',
        head_repository: { full_name: REPOSITORY }, path: '.github/workflows/ci.yml', status: 'completed', conclusion: 'success' };
    remote.runs = [good]; assert.equal(await ledger.hasSuccessfulCI(sha), true);
    for (const change of [{ head_sha: 'b'.repeat(40) }, { event: 'pull_request' }, { head_branch: 'feature' },
        { head_repository: { full_name: 'foreign/repo' } }, { path: '.github/workflows/other.yml' },
        { status: 'in_progress', conclusion: null }, { conclusion: 'failure' }]) {
        remote.runs = [{ ...good, ...change }]; assert.equal(await ledger.hasSuccessfulCI(sha), false);
    }
    remote.runs = [good, { ...good, id: 11, conclusion: 'failure' }];
    assert.equal(await ledger.hasSuccessfulCI(sha), false);
});

test('response and status errors never echo remote bodies', async () => {
    await assert.rejects(readJson(new Response('sensitive response', { status: 403 })), error => !error.message.includes('sensitive'));
    await assert.rejects(readJson(new Response('invalid json')), /valid JSON/);
    await assert.rejects(readJson(new Response('x'.repeat(4_000_001))), /exceeds/);
});
