import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { Script } from 'node:vm';

const directory = new URL('../resources/vendor/chorala/', import.meta.url);
const upstream = await readFile(new URL('upstream-widget.js', directory), 'utf8');
const expectedHash = '3af734560f49ca71c70271d09595394865f897e20c87b230585ae97f2317cf73';
if (createHash('sha256').update(upstream).digest('hex') !== expectedHash) {
    throw new Error('The pinned Chorala widget source changed. Review the source and patches before updating its hash.');
}

let patched = upstream;
function replaceOnce(before, after) {
    if (patched.split(before).length !== 2) throw new Error(`Expected exactly one Chorala patch anchor: ${before}`);
    // Function replacement keeps literal "$&" in minified JavaScript intact.
    patched = patched.replace(before, () => after);
}

// Existing UI instances retain this API object. Updating its closed-over JWT
// changes subsequent requests without replacing the UI or its in-memory draft.
replaceOnce('return{listBoards:', 'return{setJwt:n=>{e.jwt=n},listBoards:');
replaceOnce('case"open":L==null||L.open(o);break;', 'case"refreshIdentity":{H&&(H.user=o),$&&$.api.setJwt(o==null?void 0:o.jwt);break}case"open":L==null||L.open(o);break;');
replaceOnce('we.Chorala=Ft;for(let e of zt)xe(e);', 'we.Chorala=Ft;Ft.supportsIdentityRefresh=!0;for(let e of zt)xe(e);');
// The upstream map describes the unpatched bundle and is not distributed here.
replaceOnce('//# sourceMappingURL=widget.js.map', '');
const license = await readFile(new URL('LICENSE', directory), 'utf8');
const preactLicense = await readFile(new URL('PREACT-LICENSE', directory), 'utf8');
const notice = await readFile(new URL('NOTICE', directory), 'utf8');
const output = `/*!\n${notice}\n${license}\nBundled Preact:\n${preactLicense}*/\n${patched}`;
// Compile only: validate the browser bundle without executing it or using a network.
new Script(output, { filename: 'chorala-widget.js' });
const destination = new URL('widget.js', directory);
if (process.argv.includes('--check')) {
    if (await readFile(destination, 'utf8') !== output) throw new Error('The generated Chorala widget is stale. Run npm run build:chorala-widget.');
    console.log('Chorala widget source hash, patch anchors and generated output verified.');
} else {
    await writeFile(destination, output);
    console.log(`Generated ${fileURLToPath(destination)} from the verified upstream widget.`);
}
