/**
 * Capability-based routing of a bare Lightning address to a wallet connection target.
 * The primary onboarding path: the merchant types one Lightning address and we
 * pick the backend for them - no connection strings.
 *
 * Every address is probed server-side before choosing LNAddressConnect or
 * offering CashuMelt. A domain identifies a brand, never payment capabilities.
 */

import { isValidCashuLightningAddress } from './detectWalletConnectionInput';
import {
  lnAddressBrandForAddress,
  type LnAddressWalletBrand,
} from './lnAddressWalletBrands';

export type LightningAddressTarget = 'blink' | 'lnaddress' | 'cashu' | 'probe';

export type LightningAddressRoute = {
  target: LightningAddressTarget;
  /** The address as typed, trimmed. */
  address: string;
  /** Connection secret for blink/lnaddress targets; null before probing. */
  connectionSecret: string | null;
  /** Wallet branding only; this never establishes LUD-21 support. */
  brand: LnAddressWalletBrand | null;
};

/**
 * Routes a Lightning address, or null when the input is not a valid Lightning address.
 */
export function routeLightningAddress(input: string): LightningAddressRoute | null {
  const address = input.trim();
  // Connection strings (type=...;) are not bare addresses - the advanced tab handles those.
  if (address.includes(';') || address.includes('=')) {
    return null;
  }
  if (!isValidCashuLightningAddress(address)) {
    return null;
  }

  return { target: 'probe', address, connectionSecret: null, brand: lnAddressBrandForAddress(address) };
}
