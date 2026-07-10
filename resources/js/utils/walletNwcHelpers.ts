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
