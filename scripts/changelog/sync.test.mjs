import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, symlinkSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { apiBase, fragment, readFragments, synchronize, processRequest, ChoralaClient } from './sync.mjs';

const sha = 'a'.repeat(40);
const note = { id: 'raffle-fix', title: 'Raffle creation', body: 'Creation now checks limits.',
    source: 'https://github.com/webiumsk/satflux/pull/404', labels: ['fix'] };
const item = () => fragment(note, 'raffle-fix.json');

function fixture() {
    const ledger = { state: { entries: {} }, saves: 0, failAt: 0, ci: true,
        async load() { return structuredClone(this.state); },
        async save(state) { this.saves++; if (this.saves === this.failAt) throw Error('journal write failed'); this.state = structuredClone(state); },
        async hasSuccessfulCI() { return this.ci; } };
    const client = { rows: [], creates: 0, publishes: 0, loseCreate: false, losePublish: false,
        async list() { return structuredClone(this.rows); },
        async create(payload) {
            this.creates++;
            const row = { ...structuredClone(payload), id: 'clg_test', status: 'draft' };
            this.rows.push(row);
            if (this.loseCreate) throw Error('response lost');
            return structuredClone(row);
        },
        async publish(row) {
            this.publishes++;
            this.rows.find(entry => entry.id === row.id).status = 'published';
            if (this.losePublish) throw Error('response lost');
            return { ...structuredClone(row), status: 'published' };
        } };
    const run = (mode = 'draft') => synchronize({ mode, sha, entries: [item()], ledger, client });
    return { ledger, client, run };
}

test('first draft records intent before create, then reruns do not duplicate it', async () => {
    const { ledger, client, run } = fixture();
    const create = client.create.bind(client);
    client.create = async payload => {
        assert.equal(ledger.state.entries['raffle-fix'].phase, 'create_pending');
        return create(payload);
    };
    await run(); await run();
    assert.equal(client.creates, 1);
    assert.equal(client.publishes, 0);
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'created');
});

test('failed intent persistence prevents any Chorala mutation', async () => {
    const { ledger, client, run } = fixture(); ledger.failAt = 1;
    await assert.rejects(run(), /journal/);
    assert.equal(client.creates, 0); assert.equal(client.publishes, 0);
});

test('a lost create response reconciles one matching result without another POST', async () => {
    const { ledger, client, run } = fixture(); client.loseCreate = true;
    await assert.rejects(run(), /lost/);
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'create_pending');
    await run();
    assert.equal(client.creates, 1);
    assert.equal(ledger.state.entries['raffle-fix'].entryId, 'clg_test');
});

test('late create commit: missing result stays blocked until exact evidence appears', async () => {
    const { ledger, client, run } = fixture();
    client.create = async () => { client.creates++; throw Error('response lost'); };
    await assert.rejects(run());
    await assert.rejects(run(), /Uncertain create/);
    assert.equal(client.creates, 1);
    client.rows.push({ ...item().payload, id: 'clg_late', status: 'draft' });
    await run();
    assert.equal(client.creates, 1); assert.equal(ledger.state.entries['raffle-fix'].entryId, 'clg_late');
});

test('failed create acknowledgement leaves durable intent that can reconcile', async () => {
    const { ledger, client, run } = fixture(); ledger.failAt = 2;
    await assert.rejects(run());
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'create_pending');
    await run(); assert.equal(client.creates, 1);
});

test('duplicate, unmanaged, missing and modified remote entries cannot be adopted or overwritten', async t => {
    await t.test('unmanaged source', async () => {
        const { client, run } = fixture();
        client.rows.push({ ...item().payload, id: 'manual', status: 'draft' });
        await assert.rejects(run(), /Unmanaged/); assert.equal(client.creates, 0);
    });
    await t.test('duplicate source', async () => {
        const { client, run } = fixture();
        client.rows.push(...['one', 'two'].map(id => ({ ...item().payload, id, status: 'draft' })));
        await assert.rejects(run(), /Duplicate/); assert.equal(client.creates, 0);
    });
    for (const mutation of ['delete', 'title', 'labels', 'audience', 'links']) {
        await t.test(mutation, async () => {
            const { client, run } = fixture(); await run();
            if (mutation === 'delete') client.rows = [];
            if (mutation === 'title') client.rows[0].title = 'Edited by staff';
            if (mutation === 'labels') client.rows[0].labels = ['manual'];
            if (mutation === 'audience') client.rows[0].segmentId = 'segment_private';
            if (mutation === 'links') client.rows[0].linkedPostIds = ['post_private'];
            await assert.rejects(run('publish'));
            assert.equal(client.creates, 1); assert.equal(client.publishes, 0);
        });
    }
});

test('publication intent precedes PATCH and successful repeats do not re-publish', async () => {
    const { ledger, client, run } = fixture();
    const publish = client.publish.bind(client);
    client.publish = async row => {
        assert.equal(ledger.state.entries['raffle-fix'].phase, 'publish_pending');
        return publish(row);
    };
    await run('publish'); await run('publish'); await run('draft');
    assert.equal(client.creates, 1); assert.equal(client.publishes, 1);
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'published');
});

test('lost publish response reconciles published state without resetting it', async () => {
    const { ledger, client, run } = fixture(); client.losePublish = true;
    await assert.rejects(run('publish'));
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'publish_pending');
    await run('publish');
    assert.equal(client.publishes, 1); assert.equal(ledger.state.entries['raffle-fix'].phase, 'published');
});

test('unconfirmed publication and manually reverted published records stay blocked', async () => {
    const { ledger, client, run } = fixture();
    client.publish = async () => { client.publishes++; throw Error('timeout'); };
    await assert.rejects(run('publish'));
    await assert.rejects(run('publish'), /Uncertain publication/);
    assert.equal(client.publishes, 1);
    ledger.state.entries['raffle-fix'].phase = 'published';
    await assert.rejects(run('publish'), /reverted/);
});

test('a manual publication is acknowledged without another publication transition', async () => {
    const { client, run } = fixture(); await run(); client.rows[0].status = 'published';
    await run('publish'); assert.equal(client.publishes, 0);
});

test('CRLF editor bodies acknowledge existing publications and allow later drafts without rewriting either', async () => {
    const { ledger, client } = fixture();
    const first = item();
    const next = fragment({ ...note, id: 'next-fix', source: 'https://github.com/webiumsk/satflux/pull/405' }, 'next-fix.json');
    const body = first.payload.body.replace(/\n/g, '\r\n');
    client.rows.push({ ...first.payload, body, id: 'clg_existing', status: 'published' });
    ledger.state.entries[first.id] = { hash: first.hash, source: first.source, sourceSha: sha,
        phase: 'created', entryId: 'clg_existing' };
    const result = await synchronize({ mode: 'draft', sha, entries: [first, next], ledger, client });
    assert.deepEqual(result, { created: 1, published: 0, unchanged: 2 });
    assert.equal(client.creates, 1); assert.equal(client.publishes, 0);
    assert.equal(client.rows[0].body, body);
    assert.equal(ledger.state.entries[first.id].phase, 'published');
    assert.equal(ledger.state.entries[first.id].hash, 'f34eb9ad70ef8576f079f24dc20a57244560f2aea67bd08dd2eff4921e1ebd97');
    assert.equal(ledger.state.entries[next.id].phase, 'created');
});

test('lost create responses with CRLF bodies reconcile without another POST', async () => {
    const { ledger, client, run } = fixture(); client.loseCreate = true;
    await assert.rejects(run(), /lost/);
    client.rows[0].body = client.rows[0].body.replace(/\n/g, '\r\n');
    await run(); await run();
    assert.equal(client.creates, 1); assert.equal(client.publishes, 0);
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'created');
    assert.equal(ledger.state.entries['raffle-fix'].hash, item().hash);
});

test('CRLF uncertain publications reconcile only an already-published result', async () => {
    const accepted = fixture(); accepted.client.losePublish = true;
    await assert.rejects(accepted.run('publish'), /lost/);
    accepted.client.rows[0].body = accepted.client.rows[0].body.replace(/\n/g, '\r\n');
    await accepted.run('publish');
    assert.equal(accepted.client.publishes, 1);
    assert.equal(accepted.ledger.state.entries['raffle-fix'].phase, 'published');

    const unresolved = fixture();
    unresolved.client.publish = async () => { unresolved.client.publishes++; throw Error('timeout'); };
    await assert.rejects(unresolved.run('publish'), /timeout/);
    unresolved.client.rows[0].body = unresolved.client.rows[0].body.replace(/\n/g, '\r\n');
    await assert.rejects(unresolved.run('publish'), /Uncertain publication/);
    assert.equal(unresolved.client.publishes, 1);
    assert.equal(unresolved.ledger.state.entries['raffle-fix'].phase, 'publish_pending');
});

test('CRLF source fragments round-trip through LF provider bodies without changing raw source hashes', async () => {
    const { ledger, client } = fixture();
    const source = { ...note, body: 'First paragraph.\r\n\r\nSecond paragraph.' };
    const entry = fragment(source, 'raffle-fix.json');
    const create = client.create.bind(client);
    client.create = payload => create({ ...payload, body: payload.body.replace(/\r\n/g, '\n') });
    await synchronize({ mode: 'draft', sha, entries: [entry], ledger, client });
    assert.equal(ledger.state.entries[entry.id].hash, entry.hash);
    const editedSource = fragment({ ...source, body: source.body.replace(/\r\n/g, '\n') }, 'raffle-fix.json');
    assert.notEqual(entry.hash, editedSource.hash);
    await assert.rejects(synchronize({ mode: 'draft', sha, entries: [editedSource], ledger, client }), /immutable/);
    assert.equal(client.creates, 1); assert.equal(client.publishes, 0);
});

test('CRLF create and publication acknowledgements preserve the provider body and publish only once', async () => {
    const { ledger, client, run } = fixture();
    const create = client.create.bind(client);
    client.create = payload => create({ ...payload, body: payload.body.replace(/\n/g, '\r\n') });
    await run();
    const body = client.rows[0].body;
    await run('publish'); await run('publish');
    assert.equal(client.creates, 1); assert.equal(client.publishes, 1);
    assert.equal(client.rows[0].body, body);
    assert.equal(ledger.state.entries['raffle-fix'].hash, item().hash);
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'published');
});

test('CRLF equivalence does not accept changed text, whitespace, source or metadata', async t => {
    const mutations = {
        text: row => { row.body = row.body.replace('checks', 'ignores'); },
        title: row => { row.title += ' '; },
        'bare carriage return': row => { row.body = row.body.replace('\r\n', '\r'); },
        'extra body space': row => { row.body = row.body.replace('limits.', 'limits. '); },
        'trailing space': row => { row.body += ' '; },
        'trailing newline': row => { row.body += '\r\n'; },
        'different source': row => { row.body = row.body.replace('/404)', '/405)'); },
        labels: row => { row.labels = ['fix', 'manual']; },
        'linked posts': row => { row.linkedPostIds = ['post_private']; },
        audience: row => { row.segmentId = 'segment_private'; },
    };
    for (const [name, mutate] of Object.entries(mutations)) {
        await t.test(name, async () => {
            const { ledger, client, run } = fixture(); await run();
            client.rows[0].body = client.rows[0].body.replace(/\n/g, '\r\n');
            client.rows[0].status = 'published';
            mutate(client.rows[0]);
            const saves = ledger.saves;
            await assert.rejects(run('publish'));
            assert.equal(client.creates, 1); assert.equal(client.publishes, 0);
            assert.equal(ledger.saves, saves);
            assert.equal(ledger.state.entries['raffle-fix'].phase, 'created');
        });
    }
});

test('LF and CRLF copies remain duplicate sources, including uncertain create recovery', async () => {
    const { ledger, client, run } = fixture(); client.loseCreate = true;
    await assert.rejects(run(), /lost/);
    client.rows.push({ ...client.rows[0], id: 'clg_duplicate', body: client.rows[0].body.replace(/\n/g, '\r\n') });
    await assert.rejects(run(), /Duplicate/);
    assert.equal(client.creates, 1); assert.equal(client.publishes, 0);
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'create_pending');
});

test('label order remains fingerprinted when a remote body uses CRLF', async () => {
    const { ledger, client } = fixture();
    const entry = fragment({ ...note, labels: ['fix', 'improvement'] }, 'raffle-fix.json');
    const run = mode => synchronize({ mode, sha, entries: [entry], ledger, client });
    await run('draft');
    client.rows[0].body = client.rows[0].body.replace(/\n/g, '\r\n');
    client.rows[0].labels.reverse();
    await assert.rejects(run('publish'), /content or audience changed/);
    assert.equal(client.creates, 1); assert.equal(client.publishes, 0);
    assert.equal(ledger.state.entries['raffle-fix'].phase, 'created');
});

test('immutable fragment mismatch is rejected before provider mutation', async () => {
    const { ledger, client, run } = fixture(); await run();
    ledger.state.entries['raffle-fix'].hash = 'b'.repeat(64);
    await assert.rejects(run(), /immutable/); assert.equal(client.creates, 1);
});

test('malformed collection members cannot be treated as absence', async () => {
    for (const bad of [null, 'invalid', {}, { id: 'partial' }]) {
        const { client, ledger, run } = fixture(); client.rows = [bad];
        await assert.rejects(run(), /collection entry/);
        assert.equal(client.creates, 0); assert.equal(ledger.saves, 0);
    }
});

test('valid constructor ID is an own journal property, not Object.prototype metadata', async () => {
    const { client, ledger } = fixture();
    const entry = fragment({ ...note, id: 'constructor' }, 'constructor.json');
    await synchronize({ mode: 'draft', sha, entries: [entry], client, ledger });
    assert.equal(client.creates, 1); assert.equal(ledger.state.entries.constructor.phase, 'created');
});

test('deployment before CI is saved, then successful CI publishes its entry', async () => {
    const { ledger, client } = fixture(); ledger.ci = false;
    const options = { sha, ledger, client, readEntries: () => [item()] };
    assert.deepEqual(await processRequest({ ...options, mode: 'publish' }), { waitingForCI: true });
    assert.equal(ledger.state.deploymentSha, sha); assert.equal(client.creates, 0);
    ledger.ci = true;
    await processRequest({ ...options, mode: 'draft' });
    assert.equal(client.creates, 1); assert.equal(client.publishes, 1);
});

test('missing journal fails closed; invalid source is checked before saving deployment intent', async () => {
    const { ledger, client, run } = fixture(); ledger.load = async () => { throw Error('journal missing'); };
    await assert.rejects(run()); assert.equal(client.creates, 0);
    const other = fixture();
    await assert.rejects(processRequest({ mode: 'publish', sha, ledger: other.ledger, client: other.client,
        readEntries: () => { throw Error('invalid source'); } }));
    assert.equal(other.ledger.saves, 0);
});

test('API transport rejects redirects, bounds time and preserves old-provider publication fields', async () => {
    const calls = [];
    const client = new ChoralaClient({ base: 'https://feedback.example.test/api/v1', projectId: 'proj_test',
        key: 'hk_synthetic_test_key_12345', fetchImpl: async (url, options) => {
            calls.push({ url, options }); return new Response('{}');
        } });
    await client.publish({ id: 'clg_test', labels: ['fix'], linkedPostIds: ['post_one'], segmentId: 'segment_one' });
    assert.equal(calls[0].options.redirect, 'error'); assert.ok(calls[0].options.signal);
    assert.deepEqual(JSON.parse(calls[0].options.body), {
        status: 'published', labels: ['fix'], linkedPostIds: ['post_one'], segmentId: 'segment_one',
    });
    assert.equal(calls[0].url, 'https://feedback.example.test/api/v1/projects/proj_test/changelog/clg_test');
});

test('transport failure never returns a token-bearing diagnostic', async () => {
    const key = 'hk_synthetic_test_key_12345';
    const client = new ChoralaClient({ base: 'https://feedback.example.test/api/v1', projectId: 'proj_test', key,
        fetchImpl: async () => { throw Error(`Authorization: ${key}`); } });
    await assert.rejects(client.list(), error => !error.message.includes(key));
});

test('fragment and API-base validation reject unsafe inputs', () => {
    assert.equal(apiBase('https://feedback.example.test/prefix/api/v1/'), 'https://feedback.example.test/prefix/api/v1');
    for (const url of ['http://feedback.example.test/api/v1', 'https://user:pass@feedback.example.test/api/v1',
        'https://127.0.0.1/api/v1', 'https://localhost/api/v1', 'https://host.internal/api/v1',
        'https://feedback.example.test/api/v1?token=anything', 'https://feedback.example.test/api/v1#hash']) {
        assert.throws(() => apiBase(url));
    }
    for (const change of [{ id: '../escape' }, { id: 123 }, { title: '' }, { labels: [null] },
        { source: 'https://github.com/foreign/repo/pull/404' }]) {
        assert.throws(() => fragment({ ...note, ...change }, 'raffle-fix.json'));
    }
    assert.throws(() => fragment(note, 'another.json'));
});

function gitFixture(t) {
    const cwd = mkdtempSync(join(tmpdir(), 'satflux-changelog-git-'));
    t.after(() => rmSync(cwd, { recursive: true, force: true }));
    const git = (...args) => execFileSync('git', args, { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
    git('init', '-b', 'master'); git('config', 'user.email', 'synthetic@example.test'); git('config', 'user.name', 'Synthetic QA');
    git('config', 'commit.gpgsign', 'false');
    mkdirSync(join(cwd, '.changelog/entries'), { recursive: true });
    writeFileSync(join(cwd, '.changelog/entries/raffle-fix.json'), JSON.stringify(note));
    git('add', '.'); git('commit', '-qm', 'test fixture');
    const head = git('rev-parse', 'HEAD'); git('update-ref', 'refs/remotes/origin/master', head);
    return { cwd, git, head };
}

test('real Git data loader requires an exact trusted ancestor and rejects symlinks', t => {
    const { cwd, git, head } = gitFixture(t);
    assert.equal(readFragments(head, cwd)[0].id, note.id);
    assert.throws(() => readFragments('master; anything', cwd));
    git('checkout', '-b', 'unmerged');
    writeFileSync(join(cwd, 'other.txt'), 'synthetic'); git('add', '.'); git('commit', '-qm', 'unmerged');
    const unmerged = git('rev-parse', 'HEAD');
    assert.throws(() => readFragments(unmerged, cwd));
    assert.equal(readFragments(unmerged, cwd, { requireMaster: false }).length, 1);
    symlinkSync('raffle-fix.json', join(cwd, '.changelog/entries/link.json'));
    git('add', '.'); git('commit', '-qm', 'symlink');
    assert.throws(() => readFragments(git('rev-parse', 'HEAD'), cwd, { requireMaster: false }), /regular JSON/);
});

test('real Git data loader rejects duplicate source identities', t => {
    const { cwd, git } = gitFixture(t);
    writeFileSync(join(cwd, '.changelog/entries/another.json'), JSON.stringify({ ...note, id: 'another' }));
    git('add', '.'); git('commit', '-qm', 'duplicate');
    assert.throws(() => readFragments(git('rev-parse', 'HEAD'), cwd, { requireMaster: false }), /Duplicate/);
});
