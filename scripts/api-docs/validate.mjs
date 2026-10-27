import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { resolve } from 'node:path';
import { validate } from '@scalar/openapi-parser';

const documentNames = ['satflux', 'woocommerce', 'webhooks'];

/** Resolve only in-document JSON pointers. API reference generation is offline. */
function checkReferences(value, document) {
    if (value === null || typeof value !== 'object') return;

    if (Object.hasOwn(value, '$ref')) {
        const reference = value.$ref;
        if (typeof reference !== 'string' || !reference.startsWith('#/')) {
            throw new Error('Only local JSON pointer references are permitted.');
        }
        let target = document;
        for (const segment of decodeURIComponent(reference.slice(2)).split('/')) {
            const key = segment.replace(/~1/g, '/').replace(/~0/g, '~');
            if (target === null || typeof target !== 'object' || !Object.hasOwn(target, key)) {
                throw new Error(`Unresolved local reference: ${reference}`);
            }
            target = target[key];
        }
    }

    for (const child of Object.values(value)) checkReferences(child, document);
}

export async function validateDocument(document) {
    if (document?.openapi !== '3.1.0') {
        throw new Error('API documents must declare OpenAPI 3.1.0.');
    }
    checkReferences(document, document);
    const result = await validate(JSON.stringify(document));
    if (!result.valid) {
        throw new Error(`OpenAPI schema validation failed: ${JSON.stringify(result.errors)}`);
    }
}

export async function validateArtifacts(directory) {
    for (const name of documentNames) {
        const document = JSON.parse(await readFile(resolve(directory, `${name}.json`), 'utf8'));
        await validateDocument(document);
        console.log(`OpenAPI valid: ${name}`);
    }
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    // The validator accepts local JSON only. Any unexpected transport fails closed.
    globalThis.fetch = async () => { throw new Error('Network access is disabled during API validation.'); };
    try {
        await validateArtifacts(fileURLToPath(new URL('../../docs/api/', import.meta.url)));
    } catch (error) {
        console.error(error instanceof Error ? error.message : String(error));
        process.exitCode = 1;
    }
}
