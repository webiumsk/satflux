import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const REPOSITORY = 'webiumsk/satflux';
const SHA = /^[a-f0-9]{40}$/;
const PENDING = 'autorelease: pending';
const isSha = (value) => typeof value === 'string' && value.length === 40 && SHA.test(value);

function masterCiIdentity(run, sha) {
    return run?.name === 'CI' && run.path === '.github/workflows/ci.yml'
        && run.event === 'push'
        && run.head_branch === 'master' && run.head_repository?.full_name === REPOSITORY
        && isSha(run.head_sha) && run.head_sha === sha;
}

function successfulMasterCi(run, sha) {
    return masterCiIdentity(run, sha) && run.status === 'completed' && run.conclusion === 'success';
}

/** Reject PR, fork, failed, unrelated and malformed events before API access. */
export function releaseSource(event, env) {
    const run = event.workflow_run;
    if (env.GITHUB_REPOSITORY !== REPOSITORY || event.repository?.full_name !== REPOSITORY
        || env.GITHUB_EVENT_NAME !== 'workflow_run' || env.GITHUB_REF !== 'refs/heads/master'
        || !successfulMasterCi(run, run?.head_sha)) {
        throw new Error('Only successful owned master-push CI may prepare a release.');
    }
    return run.head_sha;
}

/** Reject a queued CI result if either trusted checkout or master has advanced. */
export function verifyReleaseHead(source, checkout, currentMaster) {
    if (!isSha(checkout) || !isSha(currentMaster)
        || source !== checkout || source !== currentMaster) {
        throw new Error('Master changed after CI; wait for CI on the current master revision.');
    }
}

/** Read every page or stop safely; never assume a truncated result is complete. */
async function listPages(request, path, key) {
    const all = [];
    for (let page = 1; page <= 10; page += 1) {
        const result = await request(`${path}&per_page=100&page=${page}`);
        const items = key ? result[key] : result;
        if (!Array.isArray(items) || items.length > 100) throw new Error('Malformed GitHub list response.');
        if (key && (!Number.isSafeInteger(result.total_count) || result.total_count < 0 || result.total_count > 1000)) {
            throw new Error('GitHub run count exceeds complete verification bounds.');
        }
        all.push(...items);
        if (items.length < 100) {
            if (key && all.length !== result.total_count) throw new Error('Incomplete GitHub run list.');
            return all;
        }
    }
    throw new Error('GitHub pagination limit reached; release verification is incomplete.');
}

/** Older pending releases need their own exact merge-SHA CI, even when master is green. */
export async function verifyPendingReleases(request) {
    const issues = await listPages(request, `/issues?state=closed&labels=${encodeURIComponent(PENDING)}`);
    for (const issue of issues) {
        if (!issue || typeof issue !== 'object') throw new Error('Malformed pending release record.');
        if (!issue.pull_request) continue;
        if (!Number.isSafeInteger(issue.number) || issue.number < 1) throw new Error('Invalid release PR number.');
        const pr = await request(`/pulls/${issue.number}`);
        if (typeof pr?.merged !== 'boolean') throw new Error('Unknown release PR merge state.');
        if (!pr.merged) continue;
        if (pr.base?.repo?.full_name !== REPOSITORY) throw new Error('Unexpected release PR repository.');
        if (typeof pr.base.ref !== 'string' || !pr.base.ref) throw new Error('Missing release PR base branch.');
        if (pr.base.ref !== 'master') continue;
        if (!Array.isArray(pr.labels)) throw new Error('Missing release PR labels.');
        if (!pr.labels.some((label) => label?.name === PENDING)) continue;
        if (!isSha(pr.merge_commit_sha)) throw new Error('Missing release merge SHA.');
        const runs = await listPages(request,
            `/actions/workflows/ci.yml/runs?event=push&branch=master&head_sha=${pr.merge_commit_sha}`, 'workflow_runs');
        const matching = runs.filter((run) => masterCiIdentity(run, pr.merge_commit_sha));
        for (const run of matching) {
            if (!Number.isSafeInteger(run.id) || run.id < 1
                || !Number.isSafeInteger(run.run_attempt) || run.run_attempt < 1) {
                throw new Error('Missing CI run ordering evidence.');
            }
        }
        // Match the existing changelog policy: a newer failed run overrides an
        // older success. The API reports the latest attempt for each run ID.
        matching.sort((a, b) => b.id - a.id || b.run_attempt - a.run_attempt);
        if (!successfulMasterCi(matching[0], pr.merge_commit_sha)) {
            throw new Error('A pending release lacks successful CI on its exact master merge SHA.');
        }
    }
}

export async function guard({ event, env, checkout, fetchImpl = fetch }) {
    const source = releaseSource(event, env);
    if (!env.GITHUB_TOKEN) throw new Error('The repository GitHub token is required.');
    const request = async (path) => {
        const response = await fetchImpl(`https://api.github.com/repos/${REPOSITORY}${path}`, {
            method: 'GET',
            headers: {
                Accept: 'application/vnd.github+json',
                Authorization: `Bearer ${env.GITHUB_TOKEN}`,
                'X-GitHub-Api-Version': '2022-11-28',
            },
            redirect: 'error',
            signal: AbortSignal.timeout(10_000),
        });
        if (!response.ok) throw new Error('Release evidence could not be verified through GitHub.');
        return response.json();
    };
    const current = await request('/git/ref/heads/master');
    verifyReleaseHead(source, checkout, current.object?.sha);
    await verifyPendingReleases(request);
    const final = await request('/git/ref/heads/master');
    verifyReleaseHead(source, checkout, final.object?.sha);
    return source;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
    try {
        const event = JSON.parse(readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8'));
        const checkout = execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim();
        await guard({ event, env: process.env, checkout });
        console.log('Successful CI matches the trusted checkout and current master.');
    } catch {
        console.error('Release source validation failed. Require successful CI on the current owned master.');
        process.exitCode = 1;
    }
}
