import { describe, expect, it } from 'vitest';
import {
    APPROVED_LABEL,
    NEEDS_REVIEW_LABEL,
    classifyDependencyUpdate,
    normalizeSemverLevel,
    // @ts-expect-error - plain .mjs policy script shared with the CI workflow
} from '../../../scripts/dependency-review.mjs';

describe('normalizeSemverLevel', () => {
    it('reads dependabot fetch-metadata update-type strings', () => {
        expect(normalizeSemverLevel('version-update:semver-patch')).toBe('patch');
        expect(normalizeSemverLevel('version-update:semver-minor')).toBe('minor');
        expect(normalizeSemverLevel('version-update:semver-major')).toBe('major');
    });

    it('reads bare levels and is case-insensitive', () => {
        expect(normalizeSemverLevel('patch')).toBe('patch');
        expect(normalizeSemverLevel('MINOR')).toBe('minor');
    });

    it('falls back to unknown for empty/unrecognised input', () => {
        expect(normalizeSemverLevel('')).toBe('unknown');
        expect(normalizeSemverLevel(null)).toBe('unknown');
        expect(normalizeSemverLevel('something-else')).toBe('unknown');
    });
});

describe('classifyDependencyUpdate', () => {
    it('auto-merges and approves patch updates', () => {
        expect(classifyDependencyUpdate('version-update:semver-patch')).toEqual({
            semver: 'patch',
            label: APPROVED_LABEL,
            autoMerge: true,
        });
    });

    it('sends minor updates to manual review', () => {
        expect(classifyDependencyUpdate('version-update:semver-minor')).toEqual({
            semver: 'minor',
            label: NEEDS_REVIEW_LABEL,
            autoMerge: false,
        });
    });

    it('sends major updates to manual review', () => {
        const result = classifyDependencyUpdate('version-update:semver-major');
        expect(result.autoMerge).toBe(false);
        expect(result.label).toBe(NEEDS_REVIEW_LABEL);
    });

    it('treats unknown update types as needs-review (never auto-merge)', () => {
        const result = classifyDependencyUpdate(undefined);
        expect(result.semver).toBe('unknown');
        expect(result.autoMerge).toBe(false);
        expect(result.label).toBe(NEEDS_REVIEW_LABEL);
    });
});
