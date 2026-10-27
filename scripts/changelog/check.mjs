import { execFileSync } from 'node:child_process';
import { readFragments } from './sync.mjs';

try {
    const sha = execFileSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).trim();
    // PR CI validates reviewed data without granting the PR any publication credentials.
    const entries = readFragments(sha, process.cwd(), { requireMaster: false });
    console.log(`Validated ${entries.length} changelog fragment(s).`);
} catch {
    console.error('Invalid changelog fragments. Use regular JSON files matching the documented schema.');
    process.exitCode = 1;
}
