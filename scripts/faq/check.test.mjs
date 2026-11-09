import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { resolve } from 'node:path';
import { locales, validateCatalog } from './check.mjs';

/** Create an isolated catalog and source lock for validator regression scenarios. */
function fixture(t) {
    const root = mkdtempSync(resolve(tmpdir(), 'satflux-faq-check-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    mkdirSync(resolve(root, 'docs/faq'), { recursive: true });
    mkdirSync(resolve(root, 'docs/user/en'), { recursive: true });
    writeFileSync(resolve(root, 'docs/user/en/start.md'), '# Start\n');
    const text = value => Object.fromEntries(locales.map(locale => [locale, value]));
    const catalog = { version: 1, categories: [{ slug: 'start', order: 1, name: text('Start'), description: text('Begin') }], items: [{ slug: 'first-payment', category: 'start', order: 1, question: text('How?'), answer: text('Read [the guide](/documentation/start).'), sources: ['docs/user/en/start.md'] }] };
    const save = () => writeFileSync(resolve(root, 'docs/faq/catalog.json'), JSON.stringify(catalog));
    save();
    validateCatalog(root, { refresh: true });
    return { root, catalog, save };
}

test('offline validation and explicit lock refresh are repeatable', t => {
    const { root } = fixture(t);
    assert.deepEqual(validateCatalog(root), { items: 1, locales: 5, sources: 1 });
    const before = readFileSync(resolve(root, 'docs/faq/sources.lock.json'), 'utf8');
    validateCatalog(root);
    assert.equal(readFileSync(resolve(root, 'docs/faq/sources.lock.json'), 'utf8'), before);
});
test('changed documentation reports affected FAQ without rewriting lock', t => {
    const { root } = fixture(t);
    writeFileSync(resolve(root, 'docs/user/en/start.md'), '# Changed\n');
    assert.throws(() => validateCatalog(root), /review required: first-payment/);
    validateCatalog(root, { refresh: true });
    assert.doesNotThrow(() => validateCatalog(root));
});
test('missing translation fails', t => {
    const { root, catalog, save } = fixture(t);
    delete catalog.items[0].answer.cs; save();
    assert.throws(() => validateCatalog(root), /five locales/);
});
test('unsafe link and raw HTML fail', t => {
    const { root, catalog, save } = fixture(t);
    catalog.items[0].answer.en = '[x](javascript:alert)'; save();
    assert.throws(() => validateCatalog(root), /Unsafe FAQ link|Unsupported FAQ markup/);
    catalog.items[0].answer.en = '<img src=x> [x](/documentation/start)'; save();
    assert.throws(() => validateCatalog(root), /Raw HTML/);
});
test('undeclared link source fails', t => {
    const { root, catalog, save } = fixture(t);
    writeFileSync(resolve(root, 'docs/user/en/other.md'), '# Other');
    catalog.items[0].answer.en = '[Other](/documentation/other)'; save();
    assert.throws(() => validateCatalog(root), /Untracked link source/);
});
test('duplicate identity and traversal sources fail', t => {
    const { root, catalog, save } = fixture(t);
    catalog.items.push({ ...catalog.items[0] }); save();
    assert.throws(() => validateCatalog(root), /duplicate item/);
    catalog.items.pop(); catalog.items[0].sources.push('../private.md'); save();
    assert.throws(() => validateCatalog(root), /Invalid source/);
});
test('extra lock entries fail instead of being silently ignored', t => {
    const { root } = fixture(t);
    const path = resolve(root, 'docs/faq/sources.lock.json');
    const lock = JSON.parse(readFileSync(path)); lock['docs/user/en/unused.md'] = '0'.repeat(64);
    writeFileSync(path, JSON.stringify(lock));
    assert.throws(() => validateCatalog(root), /Unexpected FAQ source/);
});

test('missing and overlong slugs fail without coercion', t => {
    const { root, catalog, save } = fixture(t);
    delete catalog.items[0].slug; save();
    assert.throws(() => validateCatalog(root), /Invalid\/duplicate item slug/);
    catalog.items[0].slug = 'a'.repeat(256); save();
    assert.throws(() => validateCatalog(root), /Invalid\/duplicate item slug/);
    catalog.items[0].slug = 'first-payment'; delete catalog.categories[0].slug; save();
    assert.throws(() => validateCatalog(root), /Invalid\/duplicate category slug/);
});

for (const kind of ['categories', 'items']) {
    test(`${kind} accept the signed PostgreSQL integer maximum`, t => {
        const { root, catalog, save } = fixture(t);
        catalog[kind][0].order = 2147483647; save();
        assert.doesNotThrow(() => validateCatalog(root));
    });
    for (const invalid of [-1, 2147483648, 1.5, '1']) {
        test(`${kind} reject invalid order ${JSON.stringify(invalid)}`, t => {
            const { root, catalog, save } = fixture(t);
            catalog[kind][0].order = invalid; save();
            assert.throws(() => validateCatalog(root), /Invalid category order|Invalid category\/order/);
        });
    }
}

test('each available translated counterpart must be declared by its own FAQ item', t => {
    const { root, catalog, save } = fixture(t);
    mkdirSync(resolve(root, 'docs/user/cs'), { recursive: true });
    writeFileSync(resolve(root, 'docs/user/cs/start.md'), '# Začátek\n');
    catalog.items.push({ ...catalog.items[0], slug: 'another-payment', sources: [...catalog.items[0].sources, 'docs/user/cs/start.md'] });
    save();
    assert.throws(() => validateCatalog(root, { refresh: true }), /Missing translated source: first-payment: docs\/user\/cs\/start.md/);
    catalog.items[0].sources.push('docs/user/cs/start.md'); save();
    validateCatalog(root, { refresh: true });
    assert.deepEqual(validateCatalog(root), { items: 2, locales: 5, sources: 2 });
});

test('English fallback remains valid when translated source files are unavailable', t => {
    const { root } = fixture(t);
    assert.doesNotThrow(() => validateCatalog(root));
});

/** Write an order as literal JSON so decimal/exponent spellings survive fixture serialization. */
function literalOrder(t, kind, literal) {
    const { root, catalog, save } = fixture(t);
    catalog[kind][0].order = 1234567890;
    save();
    const path = resolve(root, 'docs/faq/catalog.json');
    const encoded = readFileSync(path, 'utf8');
    assert.equal(encoded.split('"order":1234567890').length, 2);
    writeFileSync(path, encoded.replace('"order":1234567890', `"order":${literal}`));
    assert.ok(readFileSync(path, 'utf8').includes(`"order":${literal}`));
    return root;
}

for (const kind of ['categories', 'items']) {
    for (const literal of ['0', '-0.0', '1', '1.0', '1e0', '1E+0', '2147483647.0', '2.147483647e9']) {
        test(`${kind} accept whole raw JSON order ${literal}`, t => {
            const root = literalOrder(t, kind, literal);
            assert.doesNotThrow(() => validateCatalog(root));
        });
    }
    for (const literal of ['-1', '-1.0', '1.5', '1e-1', '2147483648', '2147483648.0', '1e309', '9007199254740993', '"1"', '"1.0"', 'true', 'null']) {
        test(`${kind} reject unsafe or nonnumeric raw JSON order ${literal}`, t => {
            const root = literalOrder(t, kind, literal);
            assert.throws(() => validateCatalog(root), /Invalid category order|Invalid category\/order/);
        });
    }
}
