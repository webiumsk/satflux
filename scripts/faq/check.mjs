import { createHash } from 'node:crypto';
import { existsSync, readFileSync, realpathSync, statSync, writeFileSync } from 'node:fs';
import { dirname, resolve, sep } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

export const locales = ['en', 'sk', 'es', 'de', 'cs'];
const maxOrder = 2147483647;
const slugPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const sourcePattern = /^docs\/user\/(en|sk|es|de|cs)\/[a-z0-9]+(?:-[a-z0-9]+)*\.md$/;

/** Reject invalid reviewed content without changing any source files. */
function fail(message) { throw new Error(message); }
/** Require nonempty text in exactly the five supported application locales. */
function localized(value, label, max = Infinity) {
    if (!value || typeof value !== 'object' || Array.isArray(value)
        || Object.keys(value).sort().join() !== [...locales].sort().join()) fail(`${label}: require exactly five locales`);
    for (const locale of locales) {
        if (typeof value[locale] !== 'string' || !value[locale].trim() || Array.from(value[locale]).length > max) fail(`${label}.${locale}: invalid text`);
    }
}
/** Resolve a documentation file and reject symlinks outside the documentation root. */
function insideFile(root, relative) {
    const path = realpathSync(resolve(root, relative));
    if (!path.startsWith(realpathSync(resolve(root, 'docs/user')) + sep)) fail('Source escapes documentation');
    return path;
}
/** Read a versioned JSON artifact without loading the application or database. */
function readJson(path) { return JSON.parse(readFileSync(path, 'utf8')); }

/** Validate tracked content without connecting to the application or a database. */
export function validateCatalog(root, { refresh = false } = {}) {
    const catalog = readJson(resolve(root, 'docs/faq/catalog.json'));
    if (catalog.version !== 1 || !Array.isArray(catalog.categories) || !catalog.categories.length
        || !Array.isArray(catalog.items) || !catalog.items.length) fail('Invalid FAQ catalog');
    const categories = new Set();
    for (const category of catalog.categories) {
        if (typeof category.slug !== 'string' || category.slug.length > 255 || !slugPattern.test(category.slug) || categories.has(category.slug)) fail('Invalid/duplicate category slug');
        categories.add(category.slug);
        if (!Number.isSafeInteger(category.order) || category.order < 0 || category.order > maxOrder) fail('Invalid category order');
        localized(category.name, `category ${category.slug} name`);
        localized(category.description, `category ${category.slug} description`);
    }
    const slugs = new Set();
    const expected = {};
    const sourceItems = {};
    for (const item of catalog.items) {
        if (typeof item.slug !== 'string' || item.slug.length > 255 || !slugPattern.test(item.slug) || slugs.has(item.slug)) fail('Invalid/duplicate item slug');
        slugs.add(item.slug);
        if (!categories.has(item.category) || !Number.isSafeInteger(item.order) || item.order < 0 || item.order > maxOrder) fail(`Invalid category/order: ${item.slug}`);
        localized(item.question, `${item.slug} question`, 500);
        localized(item.answer, `${item.slug} answer`);
        if (!Array.isArray(item.sources) || !item.sources.length || new Set(item.sources).size !== item.sources.length
            || !item.sources.some(source => typeof source === 'string' && source.startsWith('docs/user/en/'))) fail(`Missing/duplicate sources: ${item.slug}`);
        for (const source of item.sources) {
            if (typeof source !== 'string' || !sourcePattern.test(source)) fail(`Invalid source: ${item.slug}`);
            const path = insideFile(root, source);
            expected[source] = createHash('sha256').update(readFileSync(path)).digest('hex');
            (sourceItems[source] ??= []).push(item.slug);
            if (source.startsWith('docs/user/en/')) {
                for (const locale of locales) {
                    const translated = source.replace('docs/user/en/', `docs/user/${locale}/`);
                    const path = resolve(root, translated);
                    if (existsSync(path) && statSync(path).isFile() && !item.sources.includes(translated)) {
                        fail(`Missing translated source: ${item.slug}: ${translated}`);
                    }
                }
            }
        }
        for (const locale of locales) {
            // Answers deliberately support a small local-documentation Markdown subset.
            if (/[<>]/.test(item.answer[locale])) fail(`Raw HTML is unsupported: ${item.slug}.${locale}`);
            const plain = item.answer[locale].replace(/\[([^\]\r\n]+)\]\(([^)\s]+)\)/gu, '$1');
            if (/\]\(|(?:https?:|javascript:|data:|\]\[)/i.test(plain)) fail(`Unsupported FAQ markup: ${item.slug}.${locale}`);
            const links = [...item.answer[locale].matchAll(/\[([^\]\n]+)\]\(([^)\n]*)\)/g)];
            if (!links.length) fail(`Missing documentation link: ${item.slug}.${locale}`);
            for (const [, , href] of links) {
                if (!/^\/documentation\/[a-z0-9]+(?:-[a-z0-9]+)*$/.test(href)) fail(`Unsafe FAQ link: ${item.slug}.${locale}`);
                const docSlug = href.slice('/documentation/'.length);
                const fallback = `docs/user/en/${docSlug}.md`;
                insideFile(root, fallback);
                if (!item.sources.includes(fallback)) fail(`Untracked link source: ${item.slug}.${locale}`);
            }
        }
    }
    const sorted = Object.fromEntries(Object.entries(expected).sort(([a], [b]) => a.localeCompare(b)));
    const lockPath = resolve(root, 'docs/faq/sources.lock.json');
    if (refresh) {
        writeFileSync(lockPath, JSON.stringify(sorted, null, 2) + '\n');
    } else {
        const locked = readJson(lockPath);
        if (!locked || typeof locked !== 'object' || Array.isArray(locked)) fail('Invalid FAQ source lock');
        const stale = Object.keys(expected).filter(source => locked[source] !== expected[source]);
        if (Object.keys(locked).some(source => !(source in expected))) fail('Unexpected FAQ source lock entry');
        if (stale.length) fail(`FAQ source review required: ${[...new Set(stale.flatMap(source => sourceItems[source]))].join(', ')}`);
    }
    return { items: catalog.items.length, locales: locales.length, sources: Object.keys(expected).length };
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
    try {
        const args = process.argv.slice(2);
        if (args.some(arg => arg !== '--refresh-sources') || args.length > 1) fail('Usage: node scripts/faq/check.mjs [--refresh-sources]');
        const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
        const result = validateCatalog(root, { refresh: args.includes('--refresh-sources') });
        console.log(`${args.length ? 'Refreshed reviewed source fingerprints' : 'Validated FAQ'}: ${result.items} questions, ${result.locales} locales, ${result.sources} sources.`);
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
