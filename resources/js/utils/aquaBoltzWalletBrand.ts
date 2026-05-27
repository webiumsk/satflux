import { stripDescriptorChecksum } from './aquaBoltzDescriptor';

export type AquaBoltzWalletBrand = 'aqua' | 'bull';

/** Detect wallet brand from a watch-only descriptor body (null if unknown). */
export function detectWalletBrandFromDescriptor(descriptor: string): AquaBoltzWalletBrand | null {
  const d = stripDescriptorChecksum(descriptor);
  if (!d) {
    return null;
  }
  if (/,\s*elwpkh\s*\(/i.test(d)) {
    return 'bull';
  }
  if (/,\s*elsh\s*\(\s*wpkh\s*\(/i.test(d)) {
    return 'aqua';
  }
  return null;
}
