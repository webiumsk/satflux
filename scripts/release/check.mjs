import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

/** A release must update the package, both lockfile versions and manifest together. */
export function checkVersions(pkg, lock, manifest) {
    const version = pkg.version;
    if (!/^\d+\.\d+\.\d+$/.test(version ?? '') || lock.version !== version
        || lock.packages?.['']?.version !== version || manifest['.'] !== version) {
        throw new Error('package.json, package-lock.json and the release manifest must share one stable version.');
    }
    return version;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
    const read = (name) => JSON.parse(readFileSync(name, 'utf8'));
    try {
        const version = checkVersions(read('package.json'), read('package-lock.json'), read('.release-please-manifest.json'));
        console.log(`Release versions agree: ${version}`);
    } catch {
        console.error('Release version validation failed. Update the package, lockfile and release manifest together.');
        process.exitCode = 1;
    }
}
