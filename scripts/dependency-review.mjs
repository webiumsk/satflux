#!/usr/bin/env node
/**
 * Dependabot PR triage policy - the single source of truth for how a
 * dependency update is classified, shared by the dependabot-auto workflow
 * (which passes the update-type from dependabot/fetch-metadata) and its unit
 * test. Pure function; the CLI shim below prints GITHUB_OUTPUT-style lines.
 *
 * Policy: patch updates are safe to auto-merge once the normal CI on the PR
 * passes; minor and major (and anything unrecognised) require manual review.
 */

export const APPROVED_LABEL = 'dependencies-approved';
export const NEEDS_REVIEW_LABEL = 'dependencies-needs-review';

/**
 * Normalise a dependabot update-type to a semver level.
 * Accepts both the raw fetch-metadata form ("version-update:semver-patch")
 * and a bare level ("patch").
 * @param {string | null | undefined} updateType
 * @returns {'patch' | 'minor' | 'major' | 'unknown'}
 */
export function normalizeSemverLevel(updateType) {
    const value = String(updateType ?? '').toLowerCase();
    if (value.includes('patch')) return 'patch';
    if (value.includes('minor')) return 'minor';
    if (value.includes('major')) return 'major';
    return 'unknown';
}

/**
 * Classify a Dependabot update into a triage decision.
 * @param {string | null | undefined} updateType
 * @returns {{ semver: 'patch'|'minor'|'major'|'unknown', label: string, autoMerge: boolean }}
 */
export function classifyDependencyUpdate(updateType) {
    const semver = normalizeSemverLevel(updateType);
    const autoMerge = semver === 'patch';
    return {
        semver,
        label: autoMerge ? APPROVED_LABEL : NEEDS_REVIEW_LABEL,
        autoMerge,
    };
}

// CLI: `node scripts/dependency-review.mjs "<update-type>"`
// Prints `semver=`, `label=`, `automerge=` lines (GITHUB_OUTPUT friendly).
// import.meta.url check keeps this inert when imported by the test.
const invokedDirectly =
    process.argv[1] && import.meta.url === `file://${process.argv[1]}`;
if (invokedDirectly) {
    const decision = classifyDependencyUpdate(process.argv[2]);
    process.stdout.write(
        `semver=${decision.semver}\nlabel=${decision.label}\nautomerge=${decision.autoMerge}\n`,
    );
}
