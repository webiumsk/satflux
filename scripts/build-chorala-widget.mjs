import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { Script } from 'node:vm';

const directory = new URL('../resources/vendor/chorala/', import.meta.url);
const source = await readFile(new URL('source-widget.js', directory), 'utf8');
const expectedHash = '3d1d76ecf4f022988a2ce69e321f0c000f7ecc4b648b310a4f41ff50c69d83fa';
if (createHash('sha256').update(source).digest('hex') !== expectedHash) {
    throw new Error('The pinned Chorala widget source changed. Review the source, licenses and provenance before updating its hash.');
}

// The exact reviewed MIT package already provides identity refresh and privacy
// handling. Only remove the footer pointing at a source map we do not distribute.
const sourceMapFooter = '\n//# sourceMappingURL=widget.js.map';
if (!source.endsWith(sourceMapFooter)) {
    throw new Error('The pinned Chorala source-map footer changed. Review the new distribution before packaging it.');
}
const bundled = source.slice(0, -sourceMapFooter.length) + '\n';
const license = await readFile(new URL('LICENSE', directory), 'utf8');
const preactLicense = await readFile(new URL('PREACT-LICENSE', directory), 'utf8');
if (!bundled.includes(license.trim()) || !bundled.includes(preactLicense.trim())) {
    throw new Error('The pinned Chorala widget must embed its complete widget and Preact MIT notices.');
}
const notice = await readFile(new URL('NOTICE', directory), 'utf8');
const output = `/*!\n${notice}*/\n${bundled}`;
// Compile only: validate the browser bundle without executing it or using a network.
new Script(output, { filename: 'chorala-widget.js' });
const destination = new URL('widget.js', directory);
if (process.argv.includes('--check')) {
    if (await readFile(destination, 'utf8') !== output) throw new Error('The generated Chorala widget is stale. Run npm run build:chorala-widget.');
    console.log('Chorala widget source hash, embedded licenses and generated output verified.');
} else {
    await writeFile(destination, output);
    console.log(`Generated ${fileURLToPath(destination)} from the verified owned Chorala widget.`);
}
