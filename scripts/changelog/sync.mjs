import { createHash } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { pathToFileURL } from 'node:url';
import { GitLedger, REPOSITORY, readJson } from './git-ledger.mjs';

const idPattern = /^[a-z0-9][a-z0-9-]{0,79}$/;
const sourcePattern = /^https:\/\/github\.com\/webiumsk\/satflux\/pull\/[1-9][0-9]*$/;
const hash = value => createHash('sha256').update(JSON.stringify(value)).digest('hex');
const stringList = value => Array.isArray(value) && value.length <= 100
    && value.every(item => typeof item === 'string' && item.length > 0 && item.length <= 200);

export function apiBase(value) {
    let url;
    try { url = new URL(value); } catch { throw new Error('Invalid Chorala API base'); }
    if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash
        || !url.hostname.includes('.') || /^[\d.]+$/.test(url.hostname)
        || /(^|\.)(localhost|local|internal|lan)$/.test(url.hostname)
        || !/^(?:\/[A-Za-z0-9_-]+)*\/api\/v1\/?$/.test(url.pathname)) throw new Error('Invalid Chorala API base');
    return url.toString().replace(/\/$/, '');
}

export function fragment(value, filename) {
    if (!value || typeof value.id !== 'string' || !idPattern.test(value.id) || filename !== `${value.id}.json`
        || typeof value.title !== 'string' || !value.title.trim() || value.title.length > 200
        || typeof value.body !== 'string' || !value.body.trim() || value.body.length > 20_000
        || typeof value.source !== 'string' || !sourcePattern.test(value.source) || !stringList(value.labels ?? [])) {
        throw new Error('Invalid changelog fragment');
    }
    const payload = { title: value.title.trim(), body: `${value.body.trim()}\n\n[Details](${value.source})`,
        labels: [...new Set(value.labels ?? [])], linkedPostIds: [], segmentId: null };
    return { id: value.id, source: value.source, payload, hash: hash(payload) };
}

function git(args, cwd) {
    try { return execFileSync('git', args, { cwd, encoding: 'utf8', maxBuffer: 4_000_000, stdio: ['ignore', 'pipe', 'pipe'] }); }
    catch { throw new Error('Source Git validation failed'); }
}

/** Read reviewed JSON as Git data; never execute code from the event-selected SHA. */
export function readFragments(sha, cwd = process.cwd(), { requireMaster = true } = {}) {
    if (!/^[a-f0-9]{40}$/.test(sha ?? '')) throw new Error('An exact source SHA is required');
    if (requireMaster) git(['merge-base', '--is-ancestor', sha, 'refs/remotes/origin/master'], cwd);
    const listing = git(['ls-tree', '-rz', sha, '--', '.changelog/entries/'], cwd).split('\0').filter(Boolean);
    if (listing.length > 2000) throw new Error('Too many changelog fragments');
    const entries = [];
    const sources = new Set();
    for (const line of listing) {
        const match = /^(100644) blob [a-f0-9]{40}\t\.changelog\/entries\/([a-z0-9][a-z0-9-]{0,79}\.json)$/.exec(line);
        if (!match) throw new Error('Changelog fragments must be regular JSON files');
        let parsed;
        try { parsed = JSON.parse(git(['show', `${sha}:.changelog/entries/${match[2]}`], cwd)); }
        catch { throw new Error('Invalid changelog fragment JSON'); }
        const item = fragment(parsed, match[2]);
        if (sources.has(item.source)) throw new Error('Duplicate changelog source');
        sources.add(item.source);
        entries.push(item);
    }
    return entries;
}

export class ChoralaClient {
    constructor({ base, projectId, key, fetchImpl = fetch }) {
        this.base = apiBase(base);
        if (!/^[A-Za-z0-9_-]{1,100}$/.test(projectId ?? '')) throw new Error('Invalid Chorala project ID');
        if (!/^hk_[A-Za-z0-9_-]{12,500}$/.test(key ?? '')) throw new Error('Chorala project key is required');
        this.url = `${this.base}/projects/${projectId}/changelog`;
        this.key = key;
        this.fetch = fetchImpl;
    }
    async request(method, suffix = '', body) {
        let response;
        try {
            response = await this.fetch(`${this.url}${suffix}`, {
                method, redirect: 'error', signal: AbortSignal.timeout(15_000),
                headers: { Authorization: `Bearer ${this.key}`, 'Content-Type': 'application/json' },
                ...(body === undefined ? {} : { body: JSON.stringify(body) }),
            });
        } catch { throw new Error('Chorala transport failed; any mutation result is uncertain'); }
        return readJson(response);
    }
    async list() {
        const rows = await this.request('GET');
        return validateCollection(rows);
    }
    create(payload) { return this.request('POST', '', { ...payload, status: 'draft' }); }
    publish(entry) {
        if (!/^[A-Za-z0-9_-]{1,100}$/.test(entry.id ?? '')) throw new Error('Invalid entry ID');
        return this.request('PATCH', `/${entry.id}`, { status: 'published',
            labels: entry.labels, linkedPostIds: entry.linkedPostIds, segmentId: entry.segmentId });
    }
}

function validateCollection(rows) {
    if (!Array.isArray(rows) || rows.length > 20_000) throw new Error('Invalid Chorala collection');
    const ids = new Set();
    for (const row of rows) {
        if (!row || typeof row !== 'object' || !/^[A-Za-z0-9_-]{1,100}$/.test(row.id ?? '')
            || typeof row.title !== 'string' || typeof row.body !== 'string'
            || !['draft', 'published'].includes(row.status) || !stringList(row.labels)
            || !stringList(row.linkedPostIds) || !(row.segmentId === null || typeof row.segmentId === 'string')
            || ids.has(row.id)) throw new Error('Invalid Chorala collection entry');
        ids.add(row.id);
    }
    return rows;
}

/** Keep deployment intent when deployment finishes before CI, then reconcile on CI success. */
export async function processRequest({ mode, sha, ledger, client, readEntries = readFragments }) {
    if (!['draft', 'publish'].includes(mode)) throw new Error('Invalid synchronization mode');
    const entries = readEntries(sha);
    const state = structuredClone(await ledger.load());
    if (mode === 'publish') {
        state.deploymentSha = sha;
        await ledger.save(state);
    }
    if (!await ledger.hasSuccessfulCI(sha)) return { waitingForCI: true };
    const result = await synchronize({ mode, sha, entries, ledger, client });
    if (mode === 'draft') {
        const pending = (await ledger.load()).deploymentSha;
        if (pending && await ledger.hasSuccessfulCI(pending)) {
            result.deployment = await synchronize({ mode: 'publish', sha: pending,
                entries: readEntries(pending), ledger, client });
        }
    }
    return result;
}

function assertEntry(row, item) {
    if (!row || !/^[A-Za-z0-9_-]{1,100}$/.test(row.id ?? '') || !['draft', 'published'].includes(row.status)
        || typeof row.title !== 'string' || typeof row.body !== 'string'
        || !stringList(row.labels) || !stringList(row.linkedPostIds)
        || !(row.segmentId === null || (typeof row.segmentId === 'string' && /^[A-Za-z0-9_-]{1,100}$/.test(row.segmentId)))
        || hash({ title: row.title, body: row.body, labels: row.labels,
            linkedPostIds: row.linkedPostIds, segmentId: row.segmentId }) !== item.hash) {
        throw new Error('Changelog content or audience changed; manual reconciliation required');
    }
    return row;
}

/** Every external mutation follows a durable intent; ambiguous outcomes never retry blindly. */
export async function synchronize({ mode, sha, entries, ledger, client }) {
    if (!['draft', 'publish'].includes(mode) || !/^[a-f0-9]{40}$/.test(sha ?? '')) throw new Error('Invalid synchronization mode or SHA');
    const state = structuredClone(await ledger.load());
    const summary = { created: 0, published: 0, unchanged: 0 };
    for (const item of entries) {
        let record = Object.hasOwn(state.entries, item.id) ? state.entries[item.id] : undefined;
        if (record && (record.hash !== item.hash || record.source !== item.source)) {
            throw new Error('An existing fragment is immutable; manual reconciliation required');
        }
        const rows = validateCollection(await client.list());
        const matching = rows.filter(row => typeof row?.body === 'string'
            && row.body.endsWith(`\n\n[Details](${item.source})`));
        if (matching.length > 1) throw new Error('Duplicate remote sources require manual reconciliation');
        let row;
        if (!record) {
            if (matching.length) throw new Error('Unmanaged existing source requires manual reconciliation');
            record = { hash: item.hash, source: item.source, sourceSha: sha, phase: 'create_pending' };
            state.entries[item.id] = record;
            await ledger.save(state);
            row = assertEntry(await client.create(item.payload), item);
            if (row.status !== 'draft') throw new Error('Created entry is not a draft');
            record.entryId = row.id;
            record.phase = 'created';
            await ledger.save(state);
            summary.created++;
        } else if (record.phase === 'create_pending') {
            if (matching.length !== 1) throw new Error('Uncertain create is unresolved; do not retry creation');
            row = assertEntry(matching[0], item);
            record.entryId = row.id;
            record.phase = row.status === 'published' ? 'published' : 'created';
            await ledger.save(state);
        } else {
            row = rows.find(entry => entry?.id === record.entryId);
            if (!row || matching.length !== 1 || matching[0].id !== record.entryId) {
                throw new Error('Managed entry is missing or unlinked; do not recreate');
            }
            assertEntry(row, item);
        }
        if (row.status === 'published') {
            if (record.phase !== 'published') { record.phase = 'published'; await ledger.save(state); }
            summary.unchanged++;
            continue;
        }
        if (record.phase === 'published') throw new Error('Published entry was reverted manually; do not republish');
        if (record.phase === 'publish_pending') throw new Error('Uncertain publication is unresolved; manual reconciliation required');
        if (mode === 'draft') { summary.unchanged++; continue; }
        record.phase = 'publish_pending';
        await ledger.save(state);
        row = assertEntry(await client.publish(row), item);
        if (row.status !== 'published') throw new Error('Publication is not confirmed');
        record.phase = 'published';
        await ledger.save(state);
        summary.published++;
    }
    return summary;
}

export async function main(env = process.env) {
    if (env.GITHUB_REPOSITORY !== REPOSITORY) throw new Error('Only the owned Satflux repository is supported');
    const base = apiBase(env.CHORALA_API_URL);
    const projectId = env.CHORALA_PROJECT_ID;
    if (!/^[A-Za-z0-9_-]{1,100}$/.test(projectId ?? '')) throw new Error('Invalid Chorala project ID');
    const ledger = new GitLedger({ token: env.GITHUB_TOKEN,
        context: { repository: REPOSITORY, apiBase: base, projectId } });
    if (env.MODE === 'initialize') { await ledger.initialize(); return { initialized: true }; }
    const client = new ChoralaClient({ base, projectId, key: env.CHORALA_API_KEY });
    return processRequest({ mode: env.MODE, sha: env.SOURCE_SHA, ledger, client });
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    main().then(result => console.log(JSON.stringify(result))).catch(() => {
        // Neither transport errors nor API bodies may echo credentials or private responses.
        console.error('Changelog synchronization stopped. Check configuration and journal state; no blind retry of uncertain mutations.');
        process.exitCode = 1;
    });
}
