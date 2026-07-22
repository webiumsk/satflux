/** Domains/hosts that indicate NWC from a Cashu ecash wallet, not BTCPay store Lightning. */
const CASHU_WALLET_NWC_MARKERS = [
  'minibits',
  'coinos.io',
  'coinos.',
  'cashu.space',
  'mint.coinos',
] as const;

const CASHU_WALLET_LN_DOMAINS = [
  'minibits.cash',
  'coinos.io',
] as const;

export function normalizeNwcUri(value: string): string {
  let uri = value.trim();
  if (uri.toLowerCase().startsWith('type=nwc;')) {
    uri = uri.replace(/^type=nwc;key=/i, '');
  }
  return uri.replace('nostr+walletconnect://', 'nostr+walletconnect:');
}

export function looksLikeNwcUri(value: string): boolean {
  const lower = value.trim().toLowerCase();
  return (
    lower.startsWith('nostr+walletconnect:') ||
    lower.startsWith('nostr+walletconnect://') ||
    lower.startsWith('type=nwc;')
  );
}

export function extractNwcLud16(value: string): string | null {
  const uri = normalizeNwcUri(value);
  const match = uri.match(/[?&]lud16=([^&]+)/i);
  if (!match?.[1]) {
    return null;
  }
  try {
    return decodeURIComponent(match[1]).trim() || null;
  } catch {
    return match[1].trim() || null;
  }
}

export function isCashuWalletNwcUri(value: string): boolean {
  if (!looksLikeNwcUri(value)) {
    return false;
  }

  const lower = normalizeNwcUri(value).toLowerCase();

  if (CASHU_WALLET_NWC_MARKERS.some((marker) => lower.includes(marker))) {
    return true;
  }

  const lud16 = extractNwcLud16(value);
  if (lud16) {
    const domain = lud16.split('@')[1]?.toLowerCase();
    if (domain && CASHU_WALLET_LN_DOMAINS.some((d) => domain === d || domain.endsWith(`.${d}`))) {
      return true;
    }
  }

  return false;
}

/** Lightning address shape: local@domain.tld with at least 2 chars after the last dot. */
const LIGHTNING_ADDRESS_PATTERN = /^[^@\s]+@[^@\s]*\.[^@\s]{2,}$/;

/** Bare 'name@blink.sv' paste - the non-custodial Blink shorthand (blink.sv domain only). */
export function isBareBlinkLightningAddress(value: string): boolean {
  return /^[^@\s;=]+@blink\.sv$/i.test(value.trim());
}

/** Canonical BTCPay Blink connection string (bare ln-address shorthand → full form). */
export function normalizeBlinkConnectionString(value: string): string {
  const trimmed = value.trim();
  if (isBareBlinkLightningAddress(trimmed)) {
    return `type=blink;ln-address=${trimmed};`;
  }
  return trimmed;
}

/**
 * Blink connection string - either custodial (type=blink;server=...;api-key=...;wallet-id=...),
 * non-custodial (type=blink;ln-address=you@blink.sv;), or the bare blink.sv address shorthand.
 */
export function validateBlinkConnectionString(connectionString: string): boolean {
  const trimmed = connectionString.trim();
  if (!trimmed) return false;
  if (isBareBlinkLightningAddress(trimmed)) return true;
  if (!trimmed.includes(';')) return false;
  const parts = trimmed
    .split(';')
    .map((p) => p.trim())
    .filter(Boolean);
  let typeVal = '';
  let serverVal = '';
  let apiKeyVal = '';
  let walletIdVal = '';
  let lnAddressVal = '';
  for (const part of parts) {
    const eq = part.indexOf('=');
    if (eq === -1) continue;
    const key = part.slice(0, eq).trim().toLowerCase();
    const value = part.slice(eq + 1).trim();
    if (key === 'type') typeVal = value;
    if (key === 'server') serverVal = value;
    if (key === 'api-key' || key === 'apikey') apiKeyVal = value;
    if (key === 'wallet-id' || key === 'walletid') walletIdVal = value;
    if (key === 'ln-address' || key === 'lnaddress' || key === 'username') lnAddressVal = value;
  }
  if (typeVal !== 'blink') return false;
  if (lnAddressVal) {
    // Plugin defaults a bare username to the blink.sv domain
    const address = lnAddressVal.includes('@') ? lnAddressVal : `${lnAddressVal}@blink.sv`;
    return LIGHTNING_ADDRESS_PATTERN.test(address);
  }
  return !!serverVal && !!apiKeyVal && !!walletIdVal;
}

export function validateNwcUri(value: string): boolean {
  if (isCashuWalletNwcUri(value)) {
    return false;
  }
  const uri = normalizeNwcUri(value);
  const lower = uri.toLowerCase();
  return (
    lower.startsWith('nostr+walletconnect:') &&
    uri.length >= 80 &&
    lower.includes('relay=') &&
    lower.includes('secret=') &&
    !/\s/.test(uri)
  );
}
