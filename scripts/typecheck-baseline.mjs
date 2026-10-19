#!/usr/bin/env node
/**
 * Full-project vue-tsc gate with a phpstan-style baseline.
 *
 * tsconfig.typecheck.json used to allowlist a handful of files, leaving pages
 * and components unchecked. This runs vue-tsc over ALL of resources/js
 * (tsconfig.fulltypecheck.json) and compares the errors against
 * typecheck-baseline.json: known pre-existing errors are tolerated, anything
 * new fails. Burn the baseline down over time.
 *
 *   node scripts/typecheck-baseline.mjs            # gate (CI)
 *   node scripts/typecheck-baseline.mjs --update   # refresh baseline
 */
import { spawnSync } from "node:child_process";
import { readFileSync, writeFileSync, existsSync, mkdtempSync, openSync, closeSync, rmSync } from "node:fs";
import { createRequire } from "node:module";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { pathToFileURL } from "node:url";

/** Capture compiler output through a private file, including on restricted hosts. */
export function runCompiler(args) {
    const directory = mkdtempSync(join(tmpdir(), "satflux-typecheck-"));
    const outputPath = join(directory, "output.log");
    let descriptor;
    try {
        descriptor = openSync(outputPath, "w", 0o600);
        const result = spawnSync(process.execPath, args, {
            stdio: ["ignore", descriptor, descriptor],
            timeout: 10 * 60 * 1000,
        });
        closeSync(descriptor);
        descriptor = undefined;
        return { output: readFileSync(outputPath, "utf8"), status: result.status, signal: result.signal, error: result.error };
    } finally {
        if (descriptor !== undefined) closeSync(descriptor);
        rmSync(directory, { recursive: true, force: true });
    }
}

/** A nonzero compiler exit without diagnostics must never pass the ratchet. */
export function executionFailure(result, totalErrors, rawErrorCount) {
    if (result.error || result.signal || ![0, 1, 2].includes(result.status)) {
        return `Typecheck FAILED to run (${result.error?.code ?? result.signal ?? `exit ${result.status}`}).`;
    }
    if (rawErrorCount !== totalErrors) {
        return `Typecheck produced ${rawErrorCount - totalErrors} error(s) this script could not attribute to a file - refusing to continue.`;
    }
    if (result.status !== 0 && totalErrors === 0) {
        return `Typecheck FAILED to run (exit ${result.status} without attributable diagnostics).`;
    }
    return null;
}

function main() {
    const BASELINE_PATH = new URL("../typecheck-baseline.json", import.meta.url);
    const update = process.argv.includes("--update");

    const require = createRequire(import.meta.url);
    const compilerPath = join(dirname(require.resolve("vue-tsc/package.json")), "bin", "vue-tsc.js");
    const result = runCompiler([compilerPath, "--noEmit", "-p", "tsconfig.fulltypecheck.json"]);
    const { output } = result;

    // file(line,col): error TSxxxx: message   (line/col dropped - they shift)
    const errorRe = /^(.+?)\(\d+,\d+\): error (TS\d+): (.*)$/;
    const current = new Map();
    const meta = new Map(); // key -> { file, code, msg } (messages may contain '|')
    for (const line of output.split("\n")) {
        const m = line.match(errorRe);
        if (!m) continue;
        const [, file, code, rawMsg] = m;
        const msg = rawMsg.replace(/\s+/g, " ").trim().slice(0, 200);
        const key = `${file}|${code}|${msg}`;
        current.set(key, (current.get(key) ?? 0) + 1);
        if (!meta.has(key)) meta.set(key, { file, code, msg });
    }

    const totalErrors = [...current.values()].reduce((a, b) => a + b, 0);

    // Config-level failures (TS5083, TS18003, ...) print without the
    // file(line,col) prefix and would otherwise pass as "0 errors".
    const rawErrorCount = (output.match(/\berror TS\d+:/g) ?? []).length;

    const failure = executionFailure(result, totalErrors, rawErrorCount);
    if (failure) {
        console.error(failure);
        console.error("--- vue-tsc output (tail) ---");
        console.error(output.split("\n").slice(-30).join("\n"));
        process.exit(2);
    }

    if (update) {
        const sorted = Object.fromEntries([...current.entries()].sort(([a], [b]) => a.localeCompare(b)));
        writeFileSync(BASELINE_PATH, `${JSON.stringify(sorted, null, 2)}\n`);
        console.log(`Baseline updated: ${current.size} unique errors (${totalErrors} total).`);
        process.exit(0);
    }

    const baseline = existsSync(BASELINE_PATH)
        ? JSON.parse(readFileSync(BASELINE_PATH, "utf8"))
        : {};

    const offenders = [];
    for (const [key, count] of current) {
        const allowed = baseline[key] ?? 0;
        if (count > allowed) {
            offenders.push({ key, count, allowed });
        }
    }

    const stale = Object.keys(baseline).filter((key) => !current.has(key)).length;

    if (offenders.length > 0) {
        console.error(`Typecheck: ${offenders.length} error group(s) not covered by the baseline:\n`);
        for (const { key, count, allowed } of offenders.sort((a, b) => a.key.localeCompare(b.key))) {
            const { file, code, msg } = meta.get(key);
            console.error(`  ${file}\n    ${code}: ${msg}\n    occurrences: ${count} (baseline allows ${allowed})\n`);
        }
        console.error("Fix the new errors, or if they are intentional run: npm run typecheck:update-baseline");
        process.exit(1);
    }

    console.log(`Typecheck OK - ${totalErrors} baselined error(s) tolerated, nothing new.`);
    if (stale > 0) {
        console.log(`${stale} baseline entrie(s) no longer occur - consider npm run typecheck:update-baseline to shrink the baseline.`);
    }
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) main();
