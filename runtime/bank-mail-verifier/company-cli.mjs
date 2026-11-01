// Separate extraction transport: no input/argv can select Store proof policy.
const MAX_INPUT_BYTES = Math.ceil(262144 / 3) * 4 + 4096;
const MAX_OUTPUT_BYTES = 300000;
let finished = false;
const finish = (result) => {
  if (finished) return;
  finished = true;
  let output = JSON.stringify(result);
  if (Buffer.byteLength(output) > MAX_OUTPUT_BYTES) output = JSON.stringify({ status: 'invalid', reason: 'output_too_large' });
  process.stdout.write(`${output}\n`, () => process.exit(0));
};
const timer = setTimeout(() => finish({ status: 'retryable', reason: 'extraction_timeout' }), 13000);
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
      const { extractCompany } = await import('./company-extractor.mjs');
      finish(await extractCompany(input));
    }
  }
} catch {
  finish({ status: 'retryable', reason: 'extraction_unavailable' });
} finally {
  clearTimeout(timer);
}
