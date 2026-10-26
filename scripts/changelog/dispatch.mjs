import { execFileSync } from 'node:child_process';
import { constants, closeSync, fstatSync, openSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const SHA = /^[0-9a-f]{40}$/;
const DISPATCH_URL = 'https://api.github.com/repos/webiumsk/satflux/actions/workflows/chorala-changelog.yml/dispatches';
const TIMEOUT_MS = 10_000;

/** Publication is an explicit production-only extension of a successful deploy. */
export function deploymentConfig(env) {
  if (env.CHORALA_CHANGELOG_ENABLED !== 'true') return null;
  if (env.CHORALA_DEPLOY_ENVIRONMENT !== 'production') throw new Error('Changelog dispatch requires production configuration.');
  const sha = env.CHORALA_DEPLOY_SHA;
  if (!SHA.test(sha ?? '') || env.CHORALA_DEPLOY_HOST_SHA !== sha) {
    throw new Error('Deployment source changed; changelog dispatch was skipped.');
  }
  let healthUrl;
  try { healthUrl = new URL(env.CHORALA_DEPLOY_HEALTH_URL); } catch { throw new Error('Configure a public HTTPS application health URL.'); }
  if (healthUrl.protocol !== 'https:' || !healthUrl.hostname || healthUrl.username || healthUrl.password
    || healthUrl.search || healthUrl.hash || !healthUrl.hostname.includes('.') || /^[\d.]+$/.test(healthUrl.hostname)
    || /(^|\.)(localhost|local|internal|lan)$/.test(healthUrl.hostname)
    || !healthUrl.pathname.endsWith('/api/health')) {
    throw new Error('Configure a public HTTPS application /api/health URL without credentials or query.');
  }
  return { sha, healthUrl: healthUrl.href };
}

/** Read only the Git identity, without changing trust or printing child errors. */
export function runtimeSourceSha() {
  try {
    return execFileSync('git', ['-c', `safe.directory=${process.cwd()}`, 'rev-parse', 'HEAD'], {
      encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], timeout: TIMEOUT_MS,
    }).trim();
  } catch { throw new Error('Could not verify the deployed runtime source.'); }
}

/** A token file must be a private regular file; do not follow a symlink. */
export function readPrivateToken(path) {
  let descriptor;
  try {
    descriptor = openSync(path, constants.O_RDONLY | constants.O_NOFOLLOW);
    const metadata = fstatSync(descriptor);
    if (!metadata.isFile() || (metadata.mode & 0o077) !== 0 || metadata.size > 4096) {
      throw new Error('Invalid token file');
    }
    return readFileSync(descriptor, 'utf8').trim();
  } catch { throw new Error('The deployment token file must be readable, private and regular.'); }
  finally { if (descriptor !== undefined) closeSync(descriptor); }
}

function deploymentToken(env, readTokenFile) {
  const direct = env.CHORALA_DEPLOY_TOKEN;
  const path = env.CHORALA_DEPLOY_TOKEN_FILE;
  if (Boolean(direct) === Boolean(path)) throw new Error('Configure exactly one deployment workflow token source.');
  const token = direct || readTokenFile(path);
  if (typeof token !== 'string' || token.length < 20 || token.length > 4096 || /\s/.test(token)) {
    throw new Error('The deployment workflow token is invalid.');
  }
  return token;
}

async function healthyApplication(fetchImpl, url) {
  let response;
  try {
    response = await fetchImpl(url, {
      method: 'GET', redirect: 'error', credentials: 'omit', cache: 'no-store',
      signal: AbortSignal.timeout(TIMEOUT_MS), headers: { Accept: 'application/json' },
    });
  } catch { throw new Error('Application health could not be verified; changelog dispatch was skipped.'); }
  if (response.status !== 200) throw new Error('Application health failed; changelog dispatch was skipped.');
  let body;
  try { body = await response.json(); } catch { throw new Error('Application health returned an invalid response.'); }
  if (!body || body.status !== 'ok') throw new Error('Application health did not confirm readiness.');
}

/** Injectable transports keep all regression tests offline and synthetic. */
export async function dispatchDeployment(env, {
  fetchImpl = globalThis.fetch, readRuntimeSha = runtimeSourceSha, readTokenFile = readPrivateToken,
} = {}) {
  const config = deploymentConfig(env);
  if (!config) return { enabled: false };
  if (readRuntimeSha() !== config.sha) throw new Error('Runtime source differs from the deployed commit; changelog dispatch was skipped.');
  await healthyApplication(fetchImpl, config.healthUrl);
  if (readRuntimeSha() !== config.sha) throw new Error('Runtime source changed during health verification; changelog dispatch was skipped.');
  // Read the credential only after both source identity and public health pass.
  const token = deploymentToken(env, readTokenFile);
  let response;
  try {
    response = await fetchImpl(DISPATCH_URL, {
      method: 'POST', redirect: 'error', credentials: 'omit',
      signal: AbortSignal.timeout(TIMEOUT_MS),
      headers: {
        Accept: 'application/vnd.github+json', Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json', 'X-GitHub-Api-Version': '2022-11-28',
      },
      body: JSON.stringify({ ref: 'master', inputs: {
        mode: 'publish', source_sha: config.sha, environment: 'production',
      } }),
    });
  } catch { throw new Error(`Changelog dispatch outcome is unknown for ${config.sha}; retry changelog dispatch only, not deployment.`); }
  if (response.status !== 204) throw new Error(`Changelog dispatch failed (HTTP ${response.status}) for ${config.sha}; retry changelog dispatch only.`);
  return { enabled: true, queued: true, sha: config.sha };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const result = await dispatchDeployment(process.env);
    console.log(result.enabled ? `Changelog publication queued for ${result.sha}.` : 'Automatic changelog dispatch is disabled.');
  } catch (failure) {
    console.error(failure.message);
    process.exitCode = 1;
  }
}
