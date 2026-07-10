import type { AquaBoltzWalletBrand } from './aquaBoltzWalletBrand';

export function resolveStoreWalletBrand(store: {
  wallet_type?: string | null;
  wallet_brand?: AquaBoltzWalletBrand | null;
  wallet_connection?: { brand?: AquaBoltzWalletBrand | null } | null;
}): AquaBoltzWalletBrand | undefined {
  if (store.wallet_type !== 'aqua_boltz') {
    return undefined;
  }

  return store.wallet_brand ?? store.wallet_connection?.brand ?? undefined;
}
