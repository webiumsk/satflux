import { createHash } from 'node:crypto';

export const ASCII_BODY_PROFILE = 'tatra-ascii-relaxed-v1';
const MAX_BODY_BYTES = 65535;
const MAX_LINE_BYTES = 998;

/** Fixed RFC relaxed body projection. MIME labels never select a decoder. */
export function canonicalAsciiRelaxedBody(wireBody) {
  if (typeof wireBody !== 'string' || wireBody.length > MAX_BODY_BYTES) return null;
  const output = Buffer.alloc(wireBody.length + 2);
  let written = 0;
  let lastContentEnd = 0;
  let lineBytes = 0;
  let pendingWhitespace = false;
  // Each input code unit is visited once. Pending WSP is emitted only before
  // another printable byte, preserving token boundaries and dropping line tails.
  for (let index = 0; index < wireBody.length; index++) {
    const byte = wireBody.charCodeAt(index);
    if (byte === 13) {
      if (wireBody.charCodeAt(++index) !== 10) return null;
      output[written++] = 13;
      output[written++] = 10;
      pendingWhitespace = false;
      lineBytes = 0;
      continue;
    }
    if (++lineBytes > MAX_LINE_BYTES) return null;
    if (byte === 9 || byte === 32) {
      pendingWhitespace = true;
    } else if (byte >= 33 && byte <= 126) {
      if (pendingWhitespace) output[written++] = 32;
      output[written++] = byte;
      pendingWhitespace = false;
      lastContentEnd = written;
    } else {
      return null;
    }
  }
  if (!lastContentEnd) return '';
  if (lastContentEnd + 2 > MAX_BODY_BYTES) return null;
  return output.subarray(0, lastContentEnd).toString('ascii') + '\r\n';
}

/** Representation binding only; the caller must establish the same bank proof. */
export function asciiRelaxedBodyForProof(wireBody, proof) {
  if (typeof proof?.format !== 'string' || proof.format.toLowerCase() !== 'relaxed/relaxed' || proof.canonBodyLengthLimited !== false
    || !Number.isSafeInteger(proof.canonBodyLength) || !Number.isSafeInteger(proof.canonBodyLengthTotal)
    || typeof proof.bodyHash !== 'string' || typeof proof.bodyHashExpecting !== 'string') return null;
  const body = canonicalAsciiRelaxedBody(wireBody);
  if (body === null || body.length !== proof.canonBodyLength || body.length !== proof.canonBodyLengthTotal) return null;
  const hash = createHash('sha256').update(body, 'ascii').digest('base64');
  return hash === proof.bodyHash && hash === proof.bodyHashExpecting ? body : null;
}
