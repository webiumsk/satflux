import { Buffer } from 'node:buffer';
import { randomUUID } from 'node:crypto';

export const REPOSITORY = 'webiumsk/satflux';
export const STATE_REF = 'heads/automation/chorala-changelog-state';
const API = `https://api.github.com/repos/${REPOSITORY}`;
const STATE_PATH = 'state.json';
const phases = new Set(['create_pending', 'created', 'publish_pending', 'published']);

/** Limit responses and keep untrusted response bodies out of diagnostics. */
export async function readJson(response) {
    if (!response.ok) throw new Error(`Remote request rejected (HTTP ${response.status})`);
    let text;
    if (response.body?.getReader) {
        const reader = response.body.getReader();
        const chunks = [];
        let size = 0;
        try {
            while (true) {
                const chunk = await reader.read();
                if (chunk.done) break;
                size += chunk.value.byteLength;
                if (size > 4_000_000) throw new Error('Remote response exceeds limit');
                chunks.push(Buffer.from(chunk.value));
            }
            text = Buffer.concat(chunks).toString('utf8');
        } finally { await reader.cancel().catch(() => {}); }
    } else {
        text = await response.text();
        if (Buffer.byteLength(text) > 4_000_000) throw new Error('Remote response exceeds limit');
    }
    try { return JSON.parse(text); }
    catch { throw new Error('Remote response is not valid JSON'); }
}

export function validateState(state, context) {
    if (!state || state.schema !== 1 || JSON.stringify(state.context) !== JSON.stringify(context)
        || !state.entries || Array.isArray(state.entries) || typeof state.entries !== 'object'
        || Object.keys(state.entries).length > 2000
        || (state.deploymentSha !== undefined && !/^[a-f0-9]{40}$/.test(state.deploymentSha))) {
        throw new Error('Journal identity or format mismatch');
    }
    for (const [id, entry] of Object.entries(state.entries)) {
        if (!/^[a-z0-9][a-z0-9-]{0,79}$/.test(id) || !entry || !phases.has(entry.phase)
            || !/^[a-f0-9]{64}$/.test(entry.hash) || !/^[a-f0-9]{40}$/.test(entry.sourceSha)
            || !/^https:\/\/github\.com\/webiumsk\/satflux\/pull\/[1-9][0-9]*$/.test(entry.source)
            || (entry.phase !== 'create_pending' && !/^[A-Za-z0-9_-]{1,100}$/.test(entry.entryId ?? ''))) {
            throw new Error('Invalid journal entry');
        }
    }
    return state;
}

/** Fixed owned Git ref: persists write-ahead intents without cache/artifact expiry. */
export class GitLedger {
    constructor({ token, context, fetchImpl = fetch }) {
        if (typeof token !== 'string' || !token || /\s/.test(token)) throw new Error('GitHub token is required');
        this.token = token;
        this.context = context;
        this.fetch = fetchImpl;
        this.head = null;
        this.tree = null;
        this.state = null;
    }

    async request(path, method = 'GET', body) {
        let response;
        try {
            response = await this.fetch(`${API}${path}`, {
                method, redirect: 'error', signal: AbortSignal.timeout(15_000),
                headers: { Authorization: `Bearer ${this.token}`, Accept: 'application/vnd.github+json',
                    'X-GitHub-Api-Version': '2022-11-28', 'Content-Type': 'application/json' },
                ...(body === undefined ? {} : { body: JSON.stringify(body) }),
            });
        } catch { throw new Error('GitHub journal transport failed'); }
        return response;
    }

    async load() {
        const refResponse = await this.request(`/git/ref/${STATE_REF}`);
        if (refResponse.status === 404) throw new Error('Journal is missing; explicit initialization/recovery is required');
        const ref = await readJson(refResponse);
        if (!/^[a-f0-9]{40}$/.test(ref.object?.sha ?? '')) throw new Error('Invalid journal ref');
        const commit = await readJson(await this.request(`/git/commits/${ref.object.sha}`));
        if (!/^[a-f0-9]{40}$/.test(commit.tree?.sha ?? '')) throw new Error('Invalid journal tree');
        const tree = await readJson(await this.request(`/git/trees/${commit.tree.sha}`));
        const file = tree.tree?.find(item => item.path === STATE_PATH && item.type === 'blob' && item.mode === '100644');
        if (!file || !/^[a-f0-9]{40}$/.test(file.sha ?? '')) throw new Error('Journal state file is missing');
        const blob = await readJson(await this.request(`/git/blobs/${file.sha}`));
        if (blob.encoding !== 'base64' || typeof blob.content !== 'string' || blob.content.length > 3_000_000) {
            throw new Error('Invalid journal blob');
        }
        let state;
        try { state = JSON.parse(Buffer.from(blob.content, 'base64').toString('utf8')); }
        catch { throw new Error('Invalid journal JSON'); }
        this.state = validateState(state, this.context);
        this.head = ref.object.sha;
        this.tree = commit.tree.sha;
        return this.state;
    }

    async hasSuccessfulCI(sha) {
        if (!/^[a-f0-9]{40}$/.test(sha ?? '')) throw new Error('Invalid CI source SHA');
        const response = await readJson(await this.request(
            `/actions/workflows/ci.yml/runs?event=push&branch=master&head_sha=${sha}&per_page=100`,
        ));
        if (!Array.isArray(response.workflow_runs)) throw new Error('Invalid CI response');
        const runs = response.workflow_runs.filter(run => run.head_sha === sha && run.event === 'push'
            && run.head_branch === 'master' && run.head_repository?.full_name === REPOSITORY
            && /^\.github\/workflows\/ci\.yml(?:@refs\/heads\/master)?$/.test(run.path ?? ''));
        runs.sort((a, b) => Number(b.id) - Number(a.id));
        return runs[0]?.status === 'completed' && runs[0]?.conclusion === 'success';
    }

    async initialize() {
        const existing = await this.request(`/git/ref/${STATE_REF}`);
        if (existing.ok) return this.load();
        if (existing.status !== 404) await readJson(existing);
        const state = { schema: 1, context: this.context, entries: {} };
        const commit = await this.createCommit(state);
        await readJson(await this.request('/git/refs', 'POST', { ref: `refs/${STATE_REF}`, sha: commit.sha }));
        return this.load();
    }

    async createCommit(state) {
        validateState(state, this.context);
        const blob = await readJson(await this.request('/git/blobs', 'POST', {
            content: `${JSON.stringify(state, null, 2)}\n`, encoding: 'utf-8',
        }));
        if (!/^[a-f0-9]{40}$/.test(blob.sha ?? '')) throw new Error('Invalid created journal blob');
        const tree = await readJson(await this.request('/git/trees', 'POST', {
            ...(this.tree ? { base_tree: this.tree } : {}),
            tree: [{ path: STATE_PATH, mode: '100644', type: 'blob', sha: blob.sha }],
        }));
        if (!/^[a-f0-9]{40}$/.test(tree.sha ?? '')) throw new Error('Invalid created journal tree');
        const commit = await readJson(await this.request('/git/commits', 'POST', {
            // Distinct competing writers must not create the same Git object:
            // a repeated identical ref update would otherwise succeed as a no-op.
            message: `chore(changelog): record synchronization state ${randomUUID()}`, tree: tree.sha,
            parents: this.head ? [this.head] : [],
        }));
        if (!/^[a-f0-9]{40}$/.test(commit.sha ?? '')) throw new Error('Invalid created journal commit');
        return { sha: commit.sha, tree: tree.sha };
    }

    async save(state) {
        if (!this.head) throw new Error('Journal must be loaded before mutation');
        const commit = await this.createCommit(state);
        // Only a child of the observed head can fast-forward this fixed ref.
        // A concurrent writer makes this fail before any subsequent Chorala mutation.
        await readJson(await this.request(`/git/refs/${STATE_REF}`, 'PATCH', { sha: commit.sha, force: false }));
        this.head = commit.sha;
        this.tree = commit.tree;
        this.state = structuredClone(state);
    }
}
