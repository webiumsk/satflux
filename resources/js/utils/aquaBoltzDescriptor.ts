/**
 * Watch-only output descriptors for Aqua/Boltz and Bull Bitcoin (Boltz) wallets.
 */

/** Strip optional descriptor checksum suffix (#...) after the closing paren. */
export function stripDescriptorChecksum(descriptor: string): string {
  const trimmed = descriptor.trim();
  const hashIdx = trimmed.lastIndexOf('#');
  if (hashIdx < 0) {
    return trimmed;
  }
  const lastClose = trimmed.lastIndexOf(')');
  if (lastClose >= 0 && hashIdx > lastClose) {
    return trimmed.slice(0, hashIdx).trimEnd();
  }
  return trimmed;
}

export function isValidAquaBoltzDescriptor(descriptor: string): boolean {
  const d = stripDescriptorChecksum(descriptor);
  if (!d) {
    return false;
  }

  if (/(xprv|yprv|zprv)/i.test(d)) {
    return false;
  }

  if (!/^ct\s*\(\s*slip77\s*\(/i.test(d)) {
    return false;
  }

  const hasSecondBranch =
    /,\s*elsh\s*\(\s*wpkh\s*\(/i.test(d) || /,\s*elwpkh\s*\(/i.test(d);
  if (!hasSecondBranch) {
    return false;
  }

  const openParens = (d.match(/\(/g) ?? []).length;
  const closeParens = (d.match(/\)/g) ?? []).length;
  if (openParens !== closeParens || openParens === 0) {
    return false;
  }

  const lastClose = d.lastIndexOf(')');
  if (lastClose < 0 || d.slice(lastClose + 1).trim() !== '') {
    return false;
  }

  if (!/(xpub|ypub|zpub|tpub|upub|vpub)/i.test(d)) {
    return false;
  }

  return true;
}
