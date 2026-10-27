import assert from 'node:assert/strict';
import test from 'node:test';
import { validateDocument } from './validate.mjs';

const fixture = () => ({
    openapi: '3.1.0',
    info: { title: 'Synthetic API', version: '1.0.0' },
    paths: {
        '/example': {
            get: {
                responses: {
                    200: {
                        description: 'Synthetic response',
                        content: { 'application/json': { schema: { $ref: '#/components/schemas/Example' } } },
                    },
                },
            },
        },
    },
    components: { schemas: { Example: { type: ['object', 'null'] } } },
});

test('accepts OpenAPI 3.1 with nullable JSON Schema and local references', async () => {
    await validateDocument(fixture());
});

test('rejects missing required OpenAPI metadata using the schema validator', async () => {
    const document = fixture();
    delete document.info.title;
    await assert.rejects(validateDocument(document), /OpenAPI schema validation failed/);
});

test('rejects an invalid nested response shape using the schema validator', async () => {
    const document = fixture();
    document.paths['/example'].get.responses[200] = { description: 42 };
    await assert.rejects(validateDocument(document), /OpenAPI schema validation failed/);
});

test('requires an object for anonymous security alternatives', async () => {
    const document = fixture();
    document.paths['/example'].get.security = [{}];
    await validateDocument(document);
    document.paths['/example'].get.security = [[]];
    await assert.rejects(validateDocument(document), /OpenAPI schema validation failed/);
});

test('rejects external references before any request', async () => {
    const document = fixture();
    document.components.schemas.Example = { $ref: 'https://invalid.example/schema.json' };
    let requests = 0;
    const previousFetch = globalThis.fetch;
    globalThis.fetch = async () => { requests += 1; throw new Error('Unexpected request'); };
    try {
        await assert.rejects(validateDocument(document), /Only local JSON pointer references/);
        assert.equal(requests, 0);
    } finally {
        globalThis.fetch = previousFetch;
    }
});

test('rejects unresolved local references rather than silently accepting them', async () => {
    const document = fixture();
    delete document.components.schemas.Example;
    await assert.rejects(validateDocument(document), /Unresolved local reference/);
});

test('rejects inherited properties as local reference targets', async () => {
    const document = fixture();
    document.components.schemas.Example = { $ref: '#/components/schemas/constructor' };
    await assert.rejects(validateDocument(document), /Unresolved local reference/);
});

test('rejects a different declared OpenAPI version', async () => {
    const document = fixture();
    document.openapi = '3.0.3';
    await assert.rejects(validateDocument(document), /OpenAPI 3.1.0/);
});
