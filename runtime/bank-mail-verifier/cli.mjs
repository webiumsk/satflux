// Transport only: neither CLI arguments nor JSON input can configure verification/DNS.
const MAX_INPUT_BYTES = 262144 * 6 + 4096;
let finished = false;
const finish = (result) => {
  if (finished) return;
  finished = true;
  process.stdout.write(`${JSON.stringify(result)}\n`, () => process.exit(0));
};
const timer = setTimeout(() => finish({ status: 'retryable', reason: 'verification_timeout' }), 13000);
try {
  const chunks = [];
  let bytes = 0;
  for await (const chunk of process.stdin) {
    bytes += chunk.length;
    if (bytes > MAX_INPUT_BYTES) {
      finish({ status: 'invalid', reason: 'input_too_large' });
      break;
    }
    chunks.push(chunk);
  }
  if (!finished) {
    let input;
    try { input = JSON.parse(Buffer.concat(chunks).toString('utf8')); } catch {
      finish({ status: 'invalid', reason: 'invalid_json' });
    }
    if (!finished) {
      const { verify } = await import('./verifier.mjs');
      finish(await verify(input));
    }
  }
} catch {
  finish({ status: 'retryable', reason: 'verification_unavailable' });
} finally {
  clearTimeout(timer);
}
