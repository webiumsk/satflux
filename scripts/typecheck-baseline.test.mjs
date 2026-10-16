import assert from "node:assert/strict";
import { copyFileSync, mkdirSync, mkdtempSync, rmSync, writeFileSync } from "node:fs";
import { tmpdir } from "node:os";
import { join } from "node:path";
import test from "node:test";
import { executionFailure, runCompiler } from "./typecheck-baseline.mjs";

test("captures compiler stdout and stderr with the actual failure status", () => {
    const directory = mkdtempSync(join(tmpdir(), "satflux-typecheck-fixture-"));
    try {
        const fixture = join(directory, "compiler.mjs");
        writeFileSync(fixture, 'console.log("stdout diagnostic"); console.error("stderr diagnostic"); process.exitCode = 2;');
        const result = runCompiler([fixture]);
        assert.equal(result.status, 2);
        assert.match(result.output, /stdout diagnostic/);
        assert.match(result.output, /stderr diagnostic/);
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});

test("accepts a successful clean compiler and normal attributed diagnostics", () => {
    assert.equal(executionFailure({ status: 0 }, 0, 0), null);
    assert.equal(executionFailure({ status: 1 }, 3, 3), null);
    assert.equal(executionFailure({ status: 2 }, 3, 3), null);
});

test("rejects silent nonzero exits instead of claiming zero errors", () => {
    for (const status of [1, 2, 137]) {
        assert.match(executionFailure({ status }, 0, 0), /FAILED to run/);
    }
});

test("rejects crashes even when a partial set of diagnostics was emitted", () => {
    assert.match(executionFailure({ status: 137 }, 3, 3), /FAILED to run/);
    assert.match(executionFailure({ status: null, signal: "SIGTERM" }, 3, 3), /SIGTERM/);
    assert.match(executionFailure({ status: null, error: { code: "ETIMEDOUT" } }, 3, 3), /ETIMEDOUT/);
});

test("rejects unattributed configuration errors", () => {
    assert.match(executionFailure({ status: 1 }, 0, 1), /could not attribute/);
});

test("the CLI preserves exact fingerprints and occurrence caps", () => {
    const directory = mkdtempSync(join(tmpdir(), "satflux-typecheck-gate-"));
    try {
        const scripts = join(directory, "scripts");
        const compilerDirectory = join(directory, "node_modules", "vue-tsc");
        mkdirSync(scripts);
        mkdirSync(join(compilerDirectory, "bin"), { recursive: true });
        const gate = join(scripts, "typecheck-baseline.mjs");
        copyFileSync(new URL("./typecheck-baseline.mjs", import.meta.url), gate);
        writeFileSync(join(compilerDirectory, "package.json"), '{"name":"vue-tsc","version":"0.0.0"}');
        const compiler = join(compilerDirectory, "bin", "vue-tsc.js");
        writeFileSync(join(directory, "typecheck-baseline.json"), JSON.stringify({
            "resources/js/example.ts|TS2322|Existing mismatch": 1,
        }));
        const run = (output, status) => {
            writeFileSync(compiler, `process.stdout.write(${JSON.stringify(output)}); process.exitCode = ${status};`);
            return runCompiler([gate]);
        };
        const existing = "resources/js/example.ts(12,4): error TS2322: Existing mismatch\n";
        assert.equal(run(existing, 2).status, 0);
        const repeated = run(existing + existing, 2);
        assert.equal(repeated.status, 1);
        assert.match(repeated.output, /occurrences: 2 \(baseline allows 1\)/);
        assert.equal(run("resources/js/example.ts(12,4): error TS2322: New mismatch\n", 2).status, 1);
        const silentFailure = run("", 2);
        assert.equal(silentFailure.status, 2);
        assert.match(silentFailure.output, /without attributable diagnostics/);
    } finally {
        rmSync(directory, { recursive: true, force: true });
    }
});
