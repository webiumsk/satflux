// Test fixture producer only. Never accepts a signing key or writes one to disk.
import { signedFixture } from './fixtures.mjs';
try {
  const chunks = [];
  let size = 0;
  for await (const chunk of process.stdin) {
    size += chunk.length;
    if (size > 262144) throw new Error('Input size');
    chunks.push(chunk);
  }
  const input = JSON.parse(Buffer.concat(chunks).toString('utf8'));
  if (typeof input.recipient !== 'string' || typeof input.body !== 'string') throw new Error('Fixture input');
  const fixture = await signedFixture({ recipient: input.recipient, body: input.body });
  process.stdout.write(`${JSON.stringify({ rawMime: fixture.rawMime, dnsName: fixture.dnsName, dnsRecord: fixture.dnsRecord, selector: fixture.selector })}\n`);
} catch {
  process.stdout.write('{"error":"fixture_generation_failed"}\n');
  process.exitCode = 1;
}
