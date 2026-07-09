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
import { execSync } from "node:child_process";
import { readFileSync, writeFileSync, existsSync } from "node:fs";

const BASELINE_PATH = new URL("../typecheck-baseline.json", import.meta.url);
const update = process.argv.includes("--update");

let output = "";
try {
    output = execSync("npx vue-tsc --noEmit -p tsconfig.fulltypecheck.json", {
        encoding: "utf8",
        stdio: ["ignore", "pipe", "pipe"],
        maxBuffer: 64 * 1024 * 1024,
    });
} catch (e) {
    output = `${e.stdout ?? ""}${e.stderr ?? ""}`;
}

// file(line,col): error TSxxxx: message   (line/col dropped - they shift)
const errorRe = /^(.+?)\(\d+,\d+\): error (TS\d+): (.*)$/;
const current = new Map();
for (const line of output.split("\n")) {
    const m = line.match(errorRe);
    if (!m) continue;
    const [, file, code, msg] = m;
    const key = `${file}|${code}|${msg.replace(/\s+/g, " ").trim().slice(0, 200)}`;
    current.set(key, (current.get(key) ?? 0) + 1);
}

const totalErrors = [...current.values()].reduce((a, b) => a + b, 0);

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
        const [file, code, msg] = key.split("|");
        console.error(`  ${file}\n    ${code}: ${msg}\n    occurrences: ${count} (baseline allows ${allowed})\n`);
    }
    console.error("Fix the new errors, or if they are intentional run: npm run typecheck:update-baseline");
    process.exit(1);
}

console.log(`Typecheck OK - ${totalErrors} baselined error(s) tolerated, nothing new.`);
if (stale > 0) {
    console.log(`${stale} baseline entrie(s) no longer occur - consider npm run typecheck:update-baseline to shrink the baseline.`);
}
