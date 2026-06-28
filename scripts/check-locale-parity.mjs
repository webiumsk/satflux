#!/usr/bin/env node
/**
 * Fail CI when locale JSON files drift (missing keys vs English baseline).
 */
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const localesDir = join(root, 'resources/js/locales');

function flat(obj, prefix = '') {
  const out = {};
  for (const [key, value] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (value && typeof value === 'object' && !Array.isArray(value)) {
      Object.assign(out, flat(value, path));
    } else {
      out[path] = value;
    }
  }
  return out;
}

function loadLocale(code) {
  return flat(JSON.parse(readFileSync(join(localesDir, `${code}.json`), 'utf8')));
}

const baseline = loadLocale('en');
const targets = ['sk', 'es'];
let failed = false;

for (const code of targets) {
  const locale = loadLocale(code);
  const missing = Object.keys(baseline).filter((key) => !(key in locale));
  const extra = Object.keys(locale).filter((key) => !(key in baseline));

  if (missing.length > 0) {
    failed = true;
    console.error(`\n[${code}.json] missing ${missing.length} key(s) vs en.json:`);
    missing.forEach((key) => console.error(`  - ${key}`));
  }

  if (extra.length > 0) {
    console.warn(`\n[${code}.json] has ${extra.length} extra key(s) not in en.json (informational):`);
    extra.slice(0, 10).forEach((key) => console.warn(`  - ${key}`));
    if (extra.length > 10) {
      console.warn(`  ... and ${extra.length - 10} more`);
    }
  }
}

if (failed) {
  process.exit(1);
}

console.log('Locale parity OK (en vs sk, es).');
