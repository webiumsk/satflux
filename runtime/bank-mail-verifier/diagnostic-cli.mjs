// Private stdin/stdout transport for the operator service, never a raw-export command.
import { createHash } from 'node:crypto';

const MAX_INPUT_BYTES = 262144 * 6 + 4096;
let finished = false;
let contentSha256 = null;
const finish = (envelope) => {
  if (finished) return;
  finished = true;
  process.stdout.write(`${JSON.stringify(envelope)}\n`, () => process.exit(0));
};
const failure = (status, reason) => ({
  verification: { status, reason },
  diagnostics: { version: 1, status, reason, signatures: [] },
  contentSha256,
});
const timer = setTimeout(() => finish(failure('retryable', 'verification_timeout')), 13000);
try {
  const chunks = [];
  let bytes = 0;
  for await (const chunk of process.stdin) {
    bytes += chunk.length;
    if (bytes > MAX_INPUT_BYTES) {
      finish(failure('invalid', 'input_too_large'));
      break;
    }
    chunks.push(chunk);
  }
  if (!finished) {
    let input;
    try { input = JSON.parse(Buffer.concat(chunks).toString('utf8')); } catch {
      finish(failure('invalid', 'invalid_json'));
    }
    if (!finished) {
      if (typeof input?.rawMime === 'string' && Buffer.from(input.rawMime).toString('utf8') === input.rawMime) {
        contentSha256 = createHash('sha256').update(Buffer.from(input.rawMime, 'utf8')).digest('hex');
      }
      const { diagnose } = await import('./verifier.mjs');
      finish(await diagnose(input));
    }
  }
} catch {
  finish(failure('retryable', 'verification_unavailable'));
} finally {
  clearTimeout(timer);
}
